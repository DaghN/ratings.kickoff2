#!/usr/bin/env python3
"""Assert amiga_community_stats invariants (community-stats policy §11)."""

from __future__ import annotations

import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.community_stat_facts import build_community_facts_at_cutoff
from scripts.amiga.community_stats import compute_community_headline_aggregates
from scripts.amiga.community_stats_columns import COMMUNITY_HEADLINE_COLUMNS
from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.realm_cutoff import latest_finalized_tournament_id

_TOLERANCE = 1e-5

_DECIMAL_COLUMNS = frozenset(
    {
        "DifferentOpponentsAverage",
        "GamesPlayedAverage",
        "DecidedGamesRatio",
        "DrawsRatio",
        "GoalsPerGameAverage",
        "DoubleDigitsRatio",
        "CleanSheetsRatio",
    }
)


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


def _values_match(left: object, right: object, column: str) -> bool:
    if left is None and right is None:
        return True
    if column in _DECIMAL_COLUMNS:
        if left is None or right is None:
            return False
        return abs(float(left) - float(right)) <= _TOLERANCE
    return left == right


def _sample_finalized_tournament_ids(
    conn: pymysql.connections.Connection,
    *,
    n: int = 3,
) -> list[int]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT t.id
            FROM tournaments t
            WHERE t.rating_finalized = 1
              AND EXISTS (SELECT 1 FROM amiga_games g WHERE g.tournament_id = t.id)
            ORDER BY t.event_date ASC, t.chrono ASC, t.id ASC
            """
        )
        all_ids = [int(row["id"]) for row in cur.fetchall()]
    if not all_ids:
        return []
    if len(all_ids) <= n:
        return all_ids
    mid = len(all_ids) // 2
    return [all_ids[0], all_ids[mid], all_ids[-1]]


def _oracle_facts_map(conn: pymysql.connections.Connection, tournament_id: int) -> dict:
    return {
        (
            f["period_type"],
            f["period_key"],
            f["slice_type"],
            f["slice_key"],
            f["metric_key"],
            f["count_basis"],
        ): float(f["value"])
        for f in build_community_facts_at_cutoff(conn, tournament_id)
    }


def _verify_oracle_at_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    *,
    errors: list[str],
) -> None:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT *
            FROM amiga_community_stats_snapshots
            WHERE tournament_id = %s
            LIMIT 1
            """,
            (tournament_id,),
        )
        snapshot = cur.fetchone()
        if not snapshot:
            errors.append(f"missing community snapshot for tournament_id={tournament_id}")
            return

        oracle_headline = compute_community_headline_aggregates(
            conn, as_of_tournament_id=tournament_id
        )
        for col in COMMUNITY_HEADLINE_COLUMNS:
            if not _values_match(snapshot.get(col), oracle_headline.get(col), col):
                errors.append(
                    f"snapshot {col} != headline oracle at tournament_id={tournament_id}"
                )
                return

        cur.execute(
            """
            SELECT period_type, period_key, slice_type, slice_key,
                   metric_key, count_basis, value
            FROM amiga_community_stat_facts
            WHERE tournament_id = %s
            """,
            (tournament_id,),
        )
        stored = {
            (
                row["period_type"],
                row["period_key"],
                row["slice_type"],
                row["slice_key"],
                row["metric_key"],
                row["count_basis"],
            ): float(row["value"])
            for row in cur.fetchall()
        }

    oracle = _oracle_facts_map(conn, tournament_id)
    if stored != oracle:
        missing = set(oracle) - set(stored)
        extra = set(stored) - set(oracle)
        mismatched = [
            k for k in oracle if k in stored and abs(oracle[k] - stored[k]) > _TOLERANCE
        ]
        errors.append(
            f"fact oracle mismatch at tournament_id={tournament_id} "
            f"missing={len(missing)} extra={len(extra)} mismatched={len(mismatched)}"
        )


def verify_community_stats(conn: pymysql.connections.Connection) -> list[str]:
    errors: list[str] = []

    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT COUNT(DISTINCT t.id) AS n
            FROM tournaments t
            WHERE t.rating_finalized = 1
              AND EXISTS (SELECT 1 FROM amiga_games g WHERE g.tournament_id = t.id)
            """
        )
        finalized_with_games = int(cur.fetchone()["n"])

        cur.execute("SELECT COUNT(*) AS n FROM amiga_community_stats_snapshots")
        snapshot_count = int(cur.fetchone()["n"])

        if snapshot_count != finalized_with_games:
            errors.append(
                f"snapshot count {snapshot_count} != finalized tournaments {finalized_with_games}"
            )

        cur.execute(
            """
            SELECT s.tournament_id
            FROM amiga_community_stats_snapshots s
            LEFT JOIN tournaments t ON t.id = s.tournament_id
            WHERE t.id IS NULL OR t.rating_finalized <> 1
            LIMIT 1
            """
        )
        if cur.fetchone():
            errors.append("community snapshot exists for non-finalized or missing tournament")

        cur.execute(
            """
            SELECT s.tournament_id
            FROM amiga_community_stats_snapshots s
            LEFT JOIN (
                SELECT tournament_id, COUNT(*) AS n
                FROM amiga_community_stat_facts
                GROUP BY tournament_id
            ) f ON f.tournament_id = s.tournament_id
            WHERE COALESCE(f.n, 0) = 0
            LIMIT 1
            """
        )
        row = cur.fetchone()
        if row:
            errors.append(
                f"community snapshot has no facts (tournament_id={row['tournament_id']})"
            )

        cur.execute(
            """
            SELECT f.tournament_id
            FROM amiga_community_stat_facts f
            LEFT JOIN amiga_community_stats_snapshots s ON s.tournament_id = f.tournament_id
            WHERE s.tournament_id IS NULL
            LIMIT 1
            """
        )
        row = cur.fetchone()
        if row:
            errors.append(
                f"orphan community fact row (tournament_id={row['tournament_id']})"
            )

        cur.execute(
            """
            SELECT f.tournament_id
            FROM amiga_community_stat_facts f
            LEFT JOIN tournaments t ON t.id = f.tournament_id
            WHERE t.id IS NULL OR t.rating_finalized <> 1
            LIMIT 1
            """
        )
        row = cur.fetchone()
        if row:
            errors.append(
                f"community fact for missing or non-finalized tournament_id={row['tournament_id']}"
            )

        cur.execute(
            """
            SELECT s.tournament_id
            FROM amiga_community_stats_snapshots s
            INNER JOIN tournaments t ON t.id = s.tournament_id
            WHERE s.event_date <> t.event_date
               OR s.event_chrono <> t.chrono
               OR s.tournament_name <> t.name
               OR NOT (s.finalized_at <=> t.rating_finalized_at)
            LIMIT 1
            """
        )
        row = cur.fetchone()
        if row:
            errors.append(
                f"community snapshot timeline mismatch (tournament_id={row['tournament_id']})"
            )

        cur.execute(
            """
            SELECT s.*
            FROM amiga_community_stats_snapshots s
            INNER JOIN (
                SELECT tournament_id
                FROM amiga_community_stats_snapshots
                ORDER BY event_date DESC, event_chrono DESC, tournament_id DESC
                LIMIT 1
            ) latest ON latest.tournament_id = s.tournament_id
            """
        )
        latest_snapshot = cur.fetchone()
        cur.execute("SELECT * FROM amiga_community_stats WHERE id = 1 LIMIT 1")
        present = cur.fetchone()

        if latest_snapshot and present:
            for col in COMMUNITY_HEADLINE_COLUMNS:
                if not _values_match(present.get(col), latest_snapshot.get(col), col):
                    errors.append(f"present.{col} != latest snapshot")
                    break

        cur.execute(
            """
            SELECT DISTINCT count_basis
            FROM amiga_community_stat_facts
            WHERE count_basis NOT IN ('game', 'participant')
            LIMIT 1
            """
        )
        if cur.fetchone():
            errors.append("invalid count_basis value in facts")

        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_games g
            INNER JOIN amiga_game_ratings r ON r.game_id = g.id
            """
        )
        rated_games = int(cur.fetchone()["n"])

    if latest_snapshot and present:
        games_played = present.get("GamesPlayed")
        if games_played is not None and int(games_played) != rated_games:
            errors.append(
                f"present community GamesPlayed={games_played} != rated game count {rated_games}"
            )

    oracle_ids = _sample_finalized_tournament_ids(conn, n=3)
    latest_tid = latest_finalized_tournament_id(conn)
    if latest_tid is not None and latest_tid not in oracle_ids:
        oracle_ids.append(latest_tid)

    for tid in oracle_ids:
        _verify_oracle_at_tournament(conn, tid, errors=errors)

    return errors


def main(argv: list[str] | None = None) -> int:
    _ = argv
    cfg = load_amiga_db_config()
    conn = _connect(cfg)
    try:
        errors = verify_community_stats(conn)
    finally:
        conn.close()
    if errors:
        for err in errors:
            print(f"FAIL: {err}", file=sys.stderr)
        return 1
    with _connect(cfg) as conn2:
        with conn2.cursor() as cur:
            cur.execute("SELECT COUNT(*) AS n FROM amiga_community_stats_snapshots")
            n = int(cur.fetchone()["n"])
    print(f"OK: community stats verified ({n} headline snapshots)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

