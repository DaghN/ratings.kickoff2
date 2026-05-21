"""Ensure optional ladder tables exist (generalstatstable)."""

from __future__ import annotations

import logging
from pathlib import Path

import pymysql

log = logging.getLogger(__name__)

_SQL_PATH = Path(__file__).resolve().parent / "sql" / "generalstatstable.sql"


def _table_exists(conn: pymysql.connections.Connection) -> bool:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT COUNT(*) AS n FROM information_schema.tables "
            "WHERE table_schema = DATABASE() AND table_name = 'generalstatstable'"
        )
        return int(cur.fetchone()["n"]) > 0


def ensure_generalstatstable(conn: pymysql.connections.Connection) -> bool:
    """Create generalstatstable + seed id=1 if missing. Returns True if table exists after call."""
    if not _SQL_PATH.is_file():
        raise FileNotFoundError(f"Missing migration SQL: {_SQL_PATH}")

    sql = _SQL_PATH.read_text(encoding="utf-8")
    created = not _table_exists(conn)
    with conn.cursor() as cur:
        for stmt in _split_sql_statements(sql):
            cur.execute(stmt)
    if created:
        log.info("generalstatstable created and seeded id=1")
    return True


def reset_generalstatstable_row(conn: pymysql.connections.Connection) -> None:
    """NULL all data columns on row id=1 (keep the row). No-op if table missing."""
    if not _table_exists(conn):
        return
    with conn.cursor() as cur:
        cur.execute(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS "
            "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'generalstatstable' "
            "AND COLUMN_NAME != 'id' ORDER BY ORDINAL_POSITION"
        )
        cols = [row["COLUMN_NAME"] for row in cur.fetchall()]
    if not cols:
        return
    sets = ", ".join(f"`{c}` = NULL" for c in cols)
    with conn.cursor() as cur:
        cur.execute(f"UPDATE generalstatstable SET {sets} WHERE id = 1")
    log.info("generalstatstable id=1 cleared (%s columns)", len(cols))


def _split_sql_statements(sql: str) -> list[str]:
    parts: list[str] = []
    for chunk in sql.split(";"):
        lines = [
            line
            for line in chunk.splitlines()
            if line.strip() and not line.strip().startswith("--")
        ]
        if lines:
            parts.append("\n".join(lines))
    return parts
