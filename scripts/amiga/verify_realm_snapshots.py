#!/usr/bin/env python3
"""Assert amiga_realm_snapshots + amiga_generalstats invariants (realm-snapshot policy §7)."""

from __future__ import annotations

import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.generalstats_columns import GENERALSTATS_PAYLOAD_COLUMNS
from scripts.amiga.server_records import build_generalstats_payload

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
        "BiggestRatingAscent",
        "BiggestPeakRating",
        "BiggestWinRatio",
        "BiggestGoalsForAverage",
        "SmallestGoalsAgainstAverage",
        "BiggestGoalRatio",
        "BiggestDoubleDigitsRatio",
        "BiggestCleanSheetsRatio",
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


def _null_safe_neq(left: str, right: str, column: str) -> str:
    if column in _DECIMAL_COLUMNS:
        return (
            f"(({left} IS NULL) <> ({right} IS NULL) "
            f"OR ({left} IS NOT NULL AND {right} IS NOT NULL "
            f"AND ABS({left} - {right}) > {_TOLERANCE}))"
        )
    return f"NOT ({left} <=> {right})"


def verify_realm_snapshots(conn: pymysql.connections.Connection) -> list[str]:
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

        cur.execute("SELECT COUNT(*) AS n FROM amiga_realm_snapshots")
        snapshot_count = int(cur.fetchone()["n"])

        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_games g
            INNER JOIN amiga_game_ratings r ON r.game_id = g.id
            """
        )
        rated_games = int(cur.fetchone()["n"])

        cur.execute(
            """
            SELECT s.tournament_id
            FROM amiga_realm_snapshots s
            LEFT JOIN tournaments t ON t.id = s.tournament_id
            WHERE t.id IS NULL
            LIMIT 5
            """
        )
        orphan_snapshots = cur.fetchall()

        payload_checks = " OR ".join(
            _null_safe_neq("g.`" + col + "`", "s.`" + col + "`", col)
            for col in GENERALSTATS_PAYLOAD_COLUMNS
        )
        cur.execute(
            f"""
            WITH latest AS (
                SELECT s.*
                FROM amiga_realm_snapshots s
                INNER JOIN tournaments t ON t.id = s.tournament_id
                ORDER BY t.event_date DESC, t.chrono DESC, t.id DESC
                LIMIT 1
            )
            SELECT COUNT(*) AS n
            FROM amiga_generalstats g
            CROSS JOIN latest s
            WHERE g.id = 1
              AND ({payload_checks})
            """
        )
        present_mismatch = int(cur.fetchone()["n"])

        cur.execute(
            """
            SELECT s.GamesPlayed
            FROM amiga_realm_snapshots s
            INNER JOIN tournaments t ON t.id = s.tournament_id
            ORDER BY t.event_date DESC, t.chrono DESC, t.id DESC
            LIMIT 1
            """
        )
        latest_row = cur.fetchone()
        latest_games_played = (
            int(latest_row["GamesPlayed"]) if latest_row and latest_row["GamesPlayed"] is not None else None
        )

        cur.execute(
            """
            SELECT t.id AS tournament_id
            FROM tournaments t
            WHERE t.rating_finalized = 1
              AND EXISTS (SELECT 1 FROM amiga_games g WHERE g.tournament_id = t.id)
              AND NOT EXISTS (
                  SELECT 1 FROM amiga_realm_snapshots s WHERE s.tournament_id = t.id
              )
            ORDER BY t.event_date, t.chrono, t.id
            LIMIT 5
            """
        )
        missing_snapshots = cur.fetchall()

    if snapshot_count != finalized_with_games:
        errors.append(
            f"realm snapshot count {snapshot_count} != finalized tournaments with games "
            f"{finalized_with_games}"
        )

    if orphan_snapshots:
        errors.append(
            "orphan realm snapshots (missing tournament): "
            + ", ".join(str(int(r["tournament_id"])) for r in orphan_snapshots)
        )

    if present_mismatch:
        errors.append(
            f"amiga_generalstats id=1 differs from latest realm snapshot on "
            f"{present_mismatch} column comparison(s)"
        )

    if latest_games_played is not None and latest_games_played != rated_games:
        errors.append(
            f"latest realm GamesPlayed={latest_games_played} != rated game count {rated_games}"
        )

    if missing_snapshots:
        errors.append(
            "finalized tournaments missing realm snapshots: "
            + ", ".join(str(int(r["tournament_id"])) for r in missing_snapshots)
        )

    if snapshot_count > 0:
        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT tournament_id
                FROM amiga_realm_snapshots
                ORDER BY event_date DESC, event_chrono DESC, tournament_id DESC
                LIMIT 1
                """
            )
            latest_tid_row = cur.fetchone()
        if latest_tid_row:
            latest_tid = int(latest_tid_row["tournament_id"])
            oracle = build_generalstats_payload(conn, as_of_tournament_id=latest_tid)
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT * FROM amiga_generalstats WHERE id = 1 LIMIT 1"
                )
                present = cur.fetchone()
            for col in GENERALSTATS_PAYLOAD_COLUMNS:
                left = present.get(col)
                right = oracle.get(col)
                if col in _DECIMAL_COLUMNS:
                    if left is None and right is None:
                        continue
                    if left is None or right is None:
                        errors.append(f"oracle mismatch {col}: {left!r} vs {right!r}")
                        continue
                    if abs(float(left) - float(right)) > _TOLERANCE:
                        errors.append(
                            f"oracle mismatch {col}: {left!r} vs {right!r}"
                        )
                elif left != right:
                    errors.append(f"oracle mismatch {col}: {left!r} vs {right!r}")

    return errors


def main() -> int:
    cfg = load_amiga_db_config()
    conn = _connect(cfg)
    try:
        errors = verify_realm_snapshots(conn)
    finally:
        conn.close()

    if errors:
        for err in errors:
            print(f"ERROR: {err}", file=sys.stderr)
        return 1

    with _connect(cfg) as conn:
        with conn.cursor() as cur:
            cur.execute("SELECT COUNT(*) AS n FROM amiga_realm_snapshots")
            snapshots = int(cur.fetchone()["n"])
            cur.execute("SELECT GamesPlayed FROM amiga_generalstats WHERE id = 1")
            row = cur.fetchone()
            games = row.get("GamesPlayed") if row else None

    print(
        f"OK: realm snapshots verified ({snapshots} rows; "
        f"present GamesPlayed={games})"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
