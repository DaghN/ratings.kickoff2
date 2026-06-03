"""Rebuild P5 aggregate tables after replay (processed ratedresults only)."""

from __future__ import annotations

import logging
import re
from pathlib import Path

import pymysql

log = logging.getLogger(__name__)

_SQL_DIR = Path(__file__).resolve().parent / "sql"
_RR_FROM = re.compile(r"FROM `ratedresults`", re.IGNORECASE)


def _table_exists(conn: pymysql.connections.Connection, table: str) -> bool:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT COUNT(*) AS n FROM information_schema.tables "
            "WHERE table_schema = DATABASE() AND table_name = %s",
            (table,),
        )
        return int(cur.fetchone()["n"]) > 0


def _strip_line_comments(sql: str) -> str:
    lines: list[str] = []
    for line in sql.splitlines():
        if line.strip().startswith("--"):
            continue
        lines.append(line)
    return "\n".join(lines)


def _run_sql_file(conn: pymysql.connections.Connection, path: Path, *, processed_only: bool) -> None:
    sql = _strip_line_comments(path.read_text(encoding="utf-8"))
    if processed_only:
        sql = _RR_FROM.sub("FROM `ratedresults` WHERE `NewRatingA` IS NOT NULL", sql)
    statements = [s.strip() for s in sql.split(";") if s.strip()]
    with conn.cursor() as cur:
        for stmt in statements:
            if stmt.upper().startswith("SET "):
                cur.execute(stmt)
                continue
            cur.execute(stmt)


def rebuild_server_daily_activity(conn: pymysql.connections.Connection) -> None:
    if not _table_exists(conn, "server_daily_activity"):
        log.info("server_daily_activity missing — skip")
        return
    path = _SQL_DIR / "server_daily_activity_rebuild.sql"
    if not path.is_file():
        raise SystemExit(f"Missing {path}")
    _run_sql_file(conn, path, processed_only=False)
    log.info("server_daily_activity rebuilt from player_period_games")


def rebuild_player_period_league_processed(conn: pymysql.connections.Connection) -> None:
    if not _table_exists(conn, "player_period_league"):
        log.info("player_period_league missing — skip")
        return
    path = _SQL_DIR / "player_period_league_rebuild.sql"
    _run_sql_file(conn, path, processed_only=True)
    log.info("player_period_league rebuilt from processed ratedresults")


def rebuild_player_matchup_summary_processed(conn: pymysql.connections.Connection) -> None:
    if not _table_exists(conn, "player_matchup_summary"):
        log.info("player_matchup_summary missing — skip")
        return
    path = _SQL_DIR / "player_matchup_summary_rebuild.sql"
    _run_sql_file(conn, path, processed_only=True)
    log.info("player_matchup_summary rebuilt from processed ratedresults")


def rebuild_server_period_game_totals_processed(conn: pymysql.connections.Connection) -> None:
    if not _table_exists(conn, "server_period_game_totals"):
        log.info("server_period_game_totals missing — skip")
        return
    path = _SQL_DIR / "server_period_game_totals_rebuild.sql"
    _run_sql_file(conn, path, processed_only=True)
    log.info("server_period_game_totals rebuilt from processed ratedresults")


def rebuild_server_period_matchups_processed(conn: pymysql.connections.Connection) -> None:
    if not _table_exists(conn, "server_period_matchups"):
        log.info("server_period_matchups missing — skip")
        return
    path = _SQL_DIR / "server_period_matchups_rebuild.sql"
    _run_sql_file(conn, path, processed_only=True)
    log.info("server_period_matchups rebuilt from processed ratedresults")


def rebuild_period_aggregates_if_present(conn: pymysql.connections.Connection) -> None:
    """Batch oracle for P5 parity (after P4 period rebuild)."""
    rebuild_server_daily_activity(conn)
    rebuild_player_period_league_processed(conn)
    rebuild_player_matchup_summary_processed(conn)
    rebuild_server_period_game_totals_processed(conn)
    rebuild_server_period_matchups_processed(conn)
