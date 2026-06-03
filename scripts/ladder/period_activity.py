"""Rebuild player_period_games / player_peak_period_games from processed ratedresults."""

from __future__ import annotations

import logging
from pathlib import Path

import pymysql

log = logging.getLogger(__name__)


def _table_exists(conn: pymysql.connections.Connection, table: str) -> bool:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT COUNT(*) AS n FROM information_schema.tables "
            "WHERE table_schema = DATABASE() AND table_name = %s",
            (table,),
        )
        return int(cur.fetchone()["n"]) > 0

_SQL_DIR = Path(__file__).resolve().parent / "sql"

_PROCESSED_FILTER = " AND `NewRatingA` IS NOT NULL"


def _run_sql_file(conn: pymysql.connections.Connection, path: Path) -> None:
    sql = path.read_text(encoding="utf-8")
    statements = [s.strip() for s in sql.split(";") if s.strip() and not s.strip().startswith("--")]
    with conn.cursor() as cur:
        for stmt in statements:
            if stmt.upper().startswith("SET "):
                cur.execute(stmt)
                continue
            cur.execute(stmt)


def rebuild_player_period_games_processed(conn: pymysql.connections.Connection) -> None:
    """Rebuild period games from ratedresults rows that have been replayed."""
    if not _table_exists(conn, "player_period_games"):
        log.info("player_period_games missing — skip period rebuild")
        return

    filt = _PROCESSED_FILTER
    with conn.cursor() as cur:
        cur.execute("TRUNCATE TABLE `player_period_games`")

        for period_type, period_expr_a, period_expr_b in (
            ("day", "DATE(`Date`)", "DATE(`Date`)"),
            (
                "week",
                "DATE_SUB(DATE(`Date`), INTERVAL WEEKDAY(`Date`) DAY)",
                "DATE_SUB(DATE(`Date`), INTERVAL WEEKDAY(`Date`) DAY)",
            ),
            (
                "month",
                "CAST(DATE_FORMAT(`Date`, '%%Y-%%m-01') AS DATE)",
                "CAST(DATE_FORMAT(`Date`, '%%Y-%%m-01') AS DATE)",
            ),
            (
                "year",
                "CAST(CONCAT(YEAR(`Date`), '-01-01') AS DATE)",
                "CAST(CONCAT(YEAR(`Date`), '-01-01') AS DATE)",
            ),
        ):
            sql = f"""
                INSERT INTO `player_period_games` (`period_type`, `period_start`, `player_id`, `games`)
                SELECT %s, `period_start`, `player_id`, COUNT(*) AS `games`
                FROM (
                  SELECT {period_expr_a} AS `period_start`, `idA` AS `player_id`
                  FROM `ratedresults` WHERE `idA` IS NOT NULL{filt}
                  UNION ALL
                  SELECT {period_expr_b} AS `period_start`, `idB` AS `player_id`
                  FROM `ratedresults` WHERE `idB` IS NOT NULL{filt}
                ) AS appearances
                GROUP BY `period_start`, `player_id`
            """
            cur.execute(sql, (period_type,))

    log.info("player_period_games rebuilt from processed ratedresults")


def rebuild_player_peak_period_games(conn: pymysql.connections.Connection) -> None:
    if not _table_exists(conn, "player_peak_period_games"):
        log.info("player_peak_period_games missing — skip peak rebuild")
        return

    path = _SQL_DIR / "player_peak_period_games_rebuild.sql"
    if not path.is_file():
        raise SystemExit(f"Missing {path}")
    _run_sql_file(conn, path)
    log.info("player_peak_period_games rebuilt")


def rebuild_period_activity_if_present(conn: pymysql.connections.Connection) -> None:
    """Batch oracle for P4 parity (after replay_all, before commit)."""
    rebuild_player_period_games_processed(conn)
    rebuild_player_peak_period_games(conn)
