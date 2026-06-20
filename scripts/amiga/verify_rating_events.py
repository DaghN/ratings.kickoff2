#!/usr/bin/env python3
"""Assert tournament finalize rating model invariants (contract § 5.9, slice 8 snapshots)."""

from __future__ import annotations

import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.finalize_tournament import verify_tournament_finalize

_TOLERANCE = 1e-5


def _connect(cfg) -> pymysql.connections.Connection:
    return pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=DictCursor,
    )


def verify_rating_events(conn: pymysql.connections.Connection) -> list[str]:
    errors: list[str] = []

    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_games WHERE tournament_id IS NULL")
        null_tournament = int(cur.fetchone()["n"])
        if null_tournament:
            errors.append(f"amiga_games with NULL tournament_id: {null_tournament} (expected 0)")

        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM tournaments t
            WHERE t.rating_finalized = 0
              AND EXISTS (SELECT 1 FROM amiga_games g WHERE g.tournament_id = t.id)
            """
        )
        unfinalized = int(cur.fetchone()["n"])
        if unfinalized:
            errors.append(
                f"tournaments with games but rating_finalized=0: {unfinalized} (expected 0)"
            )

        cur.execute("SELECT COUNT(*) AS n FROM amiga_games")
        games = int(cur.fetchone()["n"])
        cur.execute("SELECT COUNT(*) AS n FROM amiga_game_ratings")
        ratings = int(cur.fetchone()["n"])
        if games != ratings:
            errors.append(
                f"amiga_games ({games}) != amiga_game_ratings ({ratings}) after full finalize"
            )

        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_games g
            INNER JOIN tournaments t ON t.id = g.tournament_id
            LEFT JOIN amiga_game_ratings r ON r.game_id = g.id
            WHERE t.rating_finalized = 1 AND r.game_id IS NULL
            """
        )
        missing_ratings = int(cur.fetchone()["n"])
        if missing_ratings:
            errors.append(
                f"finalized-tournament games without amiga_game_ratings: {missing_ratings}"
            )

        cur.execute(
            """
            SELECT player_id, rating_before, rating_delta, rating_after
            FROM amiga_player_event_snapshots
            WHERE ABS(rating_after - (rating_before + rating_delta)) > %s
            LIMIT 5
            """,
            (_TOLERANCE,),
        )
        bad_identity = cur.fetchall()
        if bad_identity:
            errors.append(
                f"snapshots where rating_after != rating_before + rating_delta: "
                f"{len(bad_identity)}+ (first player_id={bad_identity[0]['player_id']})"
            )

        cur.execute(
            """
            WITH ordered AS (
              SELECT s.player_id, s.rating_before, s.rating_after,
                     ROW_NUMBER() OVER (
                       PARTITION BY s.player_id
                       ORDER BY t.event_date ASC, t.chrono ASC, t.id ASC, s.tournament_id ASC
                     ) AS rn
              FROM amiga_player_event_snapshots s
              INNER JOIN tournaments t ON t.id = s.tournament_id
            )
            SELECT o1.player_id, o1.rating_after, o2.rating_before AS next_before
            FROM ordered o1
            INNER JOIN ordered o2
              ON o1.player_id = o2.player_id AND o2.rn = o1.rn + 1
            WHERE ABS(o1.rating_after - o2.rating_before) > %s
            LIMIT 5
            """,
            (_TOLERANCE,),
        )
        chain_breaks = cur.fetchall()
        if chain_breaks:
            errors.append(
                f"consecutive snapshot rating chain breaks: {len(chain_breaks)}+ "
                f"(first player_id={chain_breaks[0]['player_id']})"
            )

        cur.execute(
            """
            WITH latest AS (
              SELECT s.player_id, s.rating_after,
                     ROW_NUMBER() OVER (
                       PARTITION BY s.player_id
                       ORDER BY t.event_date DESC, t.chrono DESC, t.id DESC, s.tournament_id DESC
                     ) AS rn
              FROM amiga_player_event_snapshots s
              INNER JOIN tournaments t ON t.id = s.tournament_id
            )
            SELECT c.player_id, c.Rating, l.rating_after
            FROM amiga_player_current c
            INNER JOIN latest l ON l.player_id = c.player_id AND l.rn = 1
            WHERE c.NumberGames > 0
              AND ABS(c.Rating - l.rating_after) > %s
            LIMIT 5
            """,
            (_TOLERANCE,),
        )
        rating_mismatch = cur.fetchall()
        if rating_mismatch:
            errors.append(
                f"amiga_player_current.Rating != latest snapshot rating_after: "
                f"{len(rating_mismatch)}+ (first player_id={rating_mismatch[0]['player_id']})"
            )

        cur.execute(
            "SELECT id FROM tournaments WHERE rating_finalized = 1 ORDER BY id"
        )
        finalized_ids = [int(row["id"]) for row in cur.fetchall()]

    for tournament_id in finalized_ids:
        errors.extend(verify_tournament_finalize(conn, tournament_id))

    return errors


def main() -> int:
    cfg = load_amiga_db_config()
    conn = _connect(cfg)
    try:
        errors = verify_rating_events(conn)
    finally:
        conn.close()

    if errors:
        print(f"FAIL: {len(errors)} verify-rating-events issue(s):", file=sys.stderr)
        for err in errors[:20]:
            print(f"  - {err}", file=sys.stderr)
        if len(errors) > 20:
            print(f"  ... and {len(errors) - 20} more", file=sys.stderr)
        return 1

    with _connect(cfg) as conn:
        with conn.cursor() as cur:
            cur.execute("SELECT COUNT(*) AS n FROM amiga_player_event_snapshots")
            events = int(cur.fetchone()["n"])
            cur.execute(
                """
                SELECT COUNT(*) AS n
                FROM tournaments t
                WHERE t.rating_finalized = 1
                  AND EXISTS (SELECT 1 FROM amiga_games g WHERE g.tournament_id = t.id)
                """
            )
            finalized = int(cur.fetchone()["n"])

    print(
        f"OK: event rating snapshots verified ({finalized} finalized tournaments, "
        f"{events} snapshot rows with rating block)"
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
