#!/usr/bin/env python3
"""Assert snapshot event-local placement + games rollup (slice 8 — legacy participation retired)."""

from __future__ import annotations

import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.player_tournament_participation import _PLAYER_GAMES_ROLLUP_SQL

_TOLERANCE = 1e-5
_SAMPLE_LIMIT = 5


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


def verify_player_participation(conn: pymysql.connections.Connection) -> list[str]:
    errors: list[str] = []
    rollup_cols = ("games", "wins", "draws", "losses", "goals_for", "goals_against")

    with conn.cursor() as cur:
        mismatch_parts = [
            f"(s.`{col}` IS NULL) <> (pg.`{col}` IS NULL) "
            f"OR (s.`{col}` IS NOT NULL AND pg.`{col}` IS NOT NULL AND s.`{col}` <> pg.`{col}`)"
            for col in rollup_cols
        ]
        cur.execute(
            f"""
            SELECT COUNT(*) AS n
            FROM amiga_player_event_snapshots s
            INNER JOIN {_PLAYER_GAMES_ROLLUP_SQL}
              ON pg.player_id = s.player_id AND pg.tournament_id = s.tournament_id
            WHERE {" OR ".join(mismatch_parts)}
            """
        )
        rollup_mismatch = int(cur.fetchone()["n"])
        if rollup_mismatch:
            cur.execute(
                f"""
                SELECT s.player_id, s.tournament_id
                FROM amiga_player_event_snapshots s
                INNER JOIN {_PLAYER_GAMES_ROLLUP_SQL}
                  ON pg.player_id = s.player_id AND pg.tournament_id = s.tournament_id
                WHERE {" OR ".join(mismatch_parts)}
                LIMIT %s
                """,
                (_SAMPLE_LIMIT,),
            )
            sample = cur.fetchall()
            errors.append(
                f"snapshot games rollup mismatch: {rollup_mismatch} "
                f"(first player_id={sample[0]['player_id']}, "
                f"tournament_id={sample[0]['tournament_id']})"
            )

        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_player_event_snapshots s
            WHERE ABS(s.rating_after - (s.rating_before + s.rating_delta)) > %s
            """,
            (_TOLERANCE,),
        )
        bad_rating = int(cur.fetchone()["n"])
        if bad_rating:
            errors.append(
                f"snapshots with invalid rating identity: {bad_rating}"
            )

    return errors


def main() -> int:
    cfg = load_amiga_db_config()
    conn = _connect(cfg)
    try:
        errors = verify_player_participation(conn)
    finally:
        conn.close()

    if errors:
        print(f"FAIL: {len(errors)} verify-player-participation issue(s):", file=sys.stderr)
        for err in errors[:20]:
            print(f"  - {err}", file=sys.stderr)
        if len(errors) > 20:
            print(f"  ... and {len(errors) - 20} more", file=sys.stderr)
        return 1

    with _connect(cfg) as conn:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT COUNT(*) AS n FROM amiga_player_event_snapshots WHERE games > 0"
            )
            rows = int(cur.fetchone()["n"])

    print(f"OK: snapshot event-local verified ({rows} active snapshot rows)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
