#!/usr/bin/env python3
"""Assert amiga_player_event_snapshots + amiga_player_current invariants (policy §8)."""

from __future__ import annotations

import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.generalstats_columns import (
    GEO_YEAR_PLAYER_COLUMNS,
    RECORD_RISE_PLAYER_COLUMNS,
)
from scripts.amiga.player_tournament_participation import _PLAYER_GAMES_ROLLUP_SQL
from scripts.amiga.snapshot_row import (
    CAREER_COLUMNS,
    HONOURS_CURRENT_COLUMNS,
)

_TOLERANCE = 1e-5
_SAMPLE_LIMIT = 5

_DECIMAL_COLUMNS = frozenset(
    {
        "Rating",
        "WinRatio",
        "NumberPoints",
        "PeakRating",
        "NadirRating",
        "rating_before",
        "rating_delta",
        "rating_after",
        "performance_rating",
        "avg_goals_for",
        "avg_goals_against",
        "career_best_performance_rating",
    }
)

_EVENT_ROLLUP_COLUMNS = ("games", "wins", "draws", "losses", "goals_for", "goals_against")

_LATEST_SNAPSHOT_CTE = """
    WITH latest AS (
        SELECT
            s.*,
            ROW_NUMBER() OVER (
                PARTITION BY s.player_id
                ORDER BY s.event_date DESC, s.event_chrono DESC, s.tournament_id DESC
            ) AS rn
        FROM amiga_player_event_snapshots s
    )
"""


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


def _current_latest_mismatch_sql() -> str:
    meta_map = (
        ("last_tournament_id", "tournament_id"),
        ("last_event_date", "event_date"),
        ("last_finalized_at", "finalized_at"),
    )
    clauses: list[str] = []
    for current_col, snap_col in meta_map:
        clauses.append(_null_safe_neq(f"c.`{current_col}`", f"l.`{snap_col}`", current_col))
    for col in CAREER_COLUMNS:
        clauses.append(_null_safe_neq(f"c.`{col}`", f"l.`{col}`", col))
    for col in HONOURS_CURRENT_COLUMNS:
        clauses.append(_null_safe_neq(f"c.`{col}`", f"l.`{col}`", col))
    clauses.append(
        _null_safe_neq(
            "c.`career_best_performance_rating`",
            "l.`career_best_performance_rating`",
            "career_best_performance_rating",
        )
    )
    clauses.append(
        _null_safe_neq(
            "c.`career_best_performance_tournament_id`",
            "l.`career_best_performance_tournament_id`",
            "career_best_performance_tournament_id",
        )
    )
    for col in GEO_YEAR_PLAYER_COLUMNS:
        clauses.append(_null_safe_neq(f"c.`{col}`", f"l.`{col}`", col))
    for col in RECORD_RISE_PLAYER_COLUMNS:
        clauses.append(_null_safe_neq(f"c.`{col}`", f"l.`{col}`", col))
    where = " OR ".join(clauses)
    return f"""
        {_LATEST_SNAPSHOT_CTE}
        SELECT c.player_id
        FROM amiga_player_current c
        INNER JOIN latest l ON l.player_id = c.player_id AND l.rn = 1
        WHERE {where}
    """


def verify_event_snapshots(conn: pymysql.connections.Connection) -> list[str]:
    errors: list[str] = []

    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_player_event_snapshots")
        snapshots = int(cur.fetchone()["n"])
        if snapshots == 0:
            errors.append("no amiga_player_event_snapshots rows (expected > 0 after replay)")

        cur.execute("SELECT COUNT(*) AS n FROM amiga_player_current")
        current_rows = int(cur.fetchone()["n"])
        cur.execute(
            "SELECT COUNT(DISTINCT player_id) AS n FROM amiga_player_event_snapshots"
        )
        snapshot_players = int(cur.fetchone()["n"])
        if current_rows != snapshot_players:
            errors.append(
                f"current rows ({current_rows}) != distinct snapshot players "
                f"({snapshot_players})"
            )

        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_player_event_snapshots s
            LEFT JOIN amiga_players p ON p.id = s.player_id
            WHERE p.id IS NULL
            """
        )
        orphan_players = int(cur.fetchone()["n"])
        if orphan_players:
            errors.append(f"snapshots with missing amiga_players FK: {orphan_players}")

        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_player_event_snapshots s
            LEFT JOIN tournaments t ON t.id = s.tournament_id
            WHERE t.id IS NULL
            """
        )
        orphan_tournaments = int(cur.fetchone()["n"])
        if orphan_tournaments:
            errors.append(f"snapshots with missing tournaments FK: {orphan_tournaments}")

        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_player_current c
            LEFT JOIN amiga_players p ON p.id = c.player_id
            WHERE p.id IS NULL
            """
        )
        orphan_current = int(cur.fetchone()["n"])
        if orphan_current:
            errors.append(f"current rows with missing amiga_players FK: {orphan_current}")

        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_player_current c
            LEFT JOIN amiga_player_event_snapshots s ON s.player_id = c.player_id
            WHERE s.player_id IS NULL
            """
        )
        current_without_snapshots = int(cur.fetchone()["n"])
        if current_without_snapshots:
            errors.append(
                f"current rows without any snapshot: {current_without_snapshots}"
            )

        cur.execute(f"SELECT COUNT(*) AS n FROM ({_current_latest_mismatch_sql()}) x")
        current_mismatch = int(cur.fetchone()["n"])
        if current_mismatch:
            cur.execute(
                f"{_current_latest_mismatch_sql()} LIMIT %s",
                (_SAMPLE_LIMIT,),
            )
            sample = [int(row["player_id"]) for row in cur.fetchall()]
            errors.append(
                f"current != latest snapshot projection: {current_mismatch} "
                f"(first player_id={sample[0] if sample else '?'})"
            )

        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_player_event_snapshots s
            INNER JOIN tournaments t ON t.id = s.tournament_id
            WHERE t.rating_finalized = 0
            """
        )
        unfinalized_snapshots = int(cur.fetchone()["n"])
        if unfinalized_snapshots:
            errors.append(
                f"snapshots for unfinalized tournaments: {unfinalized_snapshots}"
            )

        rollup_mismatch_parts = [
            _null_safe_neq(f"s.`{col}`", f"pg.`{col}`", col) for col in _EVENT_ROLLUP_COLUMNS
        ]
        cur.execute(
            f"""
            SELECT COUNT(*) AS n
            FROM amiga_player_event_snapshots s
            INNER JOIN {_PLAYER_GAMES_ROLLUP_SQL}
              ON pg.player_id = s.player_id AND pg.tournament_id = s.tournament_id
            WHERE {" OR ".join(rollup_mismatch_parts)}
            """
        )
        event_games_mismatch = int(cur.fetchone()["n"])
        if event_games_mismatch:
            cur.execute(
                f"""
                SELECT s.player_id, s.tournament_id, s.games AS snap_games, pg.games AS rollup_games
                FROM amiga_player_event_snapshots s
                INNER JOIN {_PLAYER_GAMES_ROLLUP_SQL}
                  ON pg.player_id = s.player_id AND pg.tournament_id = s.tournament_id
                WHERE {" OR ".join(rollup_mismatch_parts)}
                LIMIT %s
                """,
                (_SAMPLE_LIMIT,),
            )
            sample = cur.fetchall()
            first = sample[0]
            errors.append(
                f"snapshot event-local games rollup mismatch: {event_games_mismatch} "
                f"(first player_id={first['player_id']}, tournament_id={first['tournament_id']}, "
                f"snap={first['snap_games']}, rollup={first['rollup_games']})"
            )

        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_player_current c
            INNER JOIN (
                SELECT
                    player_id,
                    COUNT(*) AS rated_games
                FROM (
                    SELECT player_a_id AS player_id FROM amiga_games
                    UNION ALL
                    SELECT player_b_id AS player_id FROM amiga_games
                ) sides
                GROUP BY player_id
            ) g ON g.player_id = c.player_id
            WHERE c.NumberGames <> g.rated_games
            """
        )
        career_games_mismatch = int(cur.fetchone()["n"])
        if career_games_mismatch:
            cur.execute(
                """
                SELECT c.player_id, c.NumberGames AS current_games, g.rated_games
                FROM amiga_player_current c
                INNER JOIN (
                    SELECT
                        player_id,
                        COUNT(*) AS rated_games
                    FROM (
                        SELECT player_a_id AS player_id FROM amiga_games
                        UNION ALL
                        SELECT player_b_id AS player_id FROM amiga_games
                    ) sides
                    GROUP BY player_id
                ) g ON g.player_id = c.player_id
                WHERE c.NumberGames <> g.rated_games
                LIMIT %s
                """,
                (_SAMPLE_LIMIT,),
            )
            sample = cur.fetchall()
            errors.append(
                f"current.NumberGames != amiga_games row count: {career_games_mismatch} "
                f"(first player_id={sample[0]['player_id']}, "
                f"current={sample[0]['current_games']}, games={sample[0]['rated_games']})"
            )

    return errors


def main() -> int:
    cfg = load_amiga_db_config()
    conn = _connect(cfg)
    try:
        errors = verify_event_snapshots(conn)
    finally:
        conn.close()

    if errors:
        print(f"FAIL: {len(errors)} verify-event-snapshots issue(s):", file=sys.stderr)
        for err in errors[:20]:
            print(f"  - {err}", file=sys.stderr)
        if len(errors) > 20:
            print(f"  ... and {len(errors) - 20} more", file=sys.stderr)
        return 1

    with _connect(cfg) as conn:
        with conn.cursor() as cur:
            cur.execute("SELECT COUNT(*) AS n FROM amiga_player_event_snapshots")
            snapshots = int(cur.fetchone()["n"])
            cur.execute("SELECT COUNT(*) AS n FROM amiga_player_current")
            current_rows = int(cur.fetchone()["n"])

    print(
        f"OK: event snapshots verified ({snapshots} snapshot rows, "
        f"{current_rows} current rows)"
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
