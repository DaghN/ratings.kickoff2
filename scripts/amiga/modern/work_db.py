"""Connect to ko2amiga_work (living local ground)."""

from __future__ import annotations

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.modern.db_config import load_work_db_config
from scripts.amiga.modern.constants import WORK_DB


def connect_work(*, database: str = WORK_DB) -> pymysql.connections.Connection:
    if database != WORK_DB:
        raise SystemExit(f"Refusing work DB connect: expected {WORK_DB!r}, got {database!r}")
    cfg = load_work_db_config()
    return pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=database,
        charset="utf8mb4",
        cursorclass=DictCursor,
    )


def connect_server() -> pymysql.connections.Connection:
    """Server connection (no default database)."""
    cfg = load_amiga_db_config()
    return pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        charset="utf8mb4",
        cursorclass=DictCursor,
    )


def ensure_work_database() -> None:
    conn = connect_server()
    try:
        with conn.cursor() as cur:
            cur.execute(
                f"CREATE DATABASE IF NOT EXISTS `{WORK_DB}` "
                "CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
            )
        conn.commit()
    finally:
        conn.close()