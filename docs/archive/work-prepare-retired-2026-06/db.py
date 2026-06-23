"""MySQL connection helpers for prepare platform v2."""

from __future__ import annotations

import pymysql
from pymysql.cursors import DictCursor

from scripts.ladder.config import DbConfig

from .targets import WorkTarget


def target_to_db_config(target: WorkTarget, *, database: str | None = None) -> DbConfig:
    return DbConfig(
        host=target.host,
        port=target.port,
        user=target.user,
        password=target.password,
        database=database or target.work_database,
    )


def connect(target: WorkTarget, *, database: str | None = None) -> pymysql.connections.Connection:
    db = database or target.work_database
    cfg = target_to_db_config(target, database=db)
    conn = pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        autocommit=False,
        cursorclass=DictCursor,
    )
    with conn.cursor() as cur:
        cur.execute("SET time_zone = '+00:00'")
    return conn


def database_exists(target: WorkTarget, name: str) -> bool:
    conn = pymysql.connect(
        host=target.host,
        port=target.port,
        user=target.user,
        password=target.password,
        charset="utf8mb4",
        cursorclass=DictCursor,
    )
    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT COUNT(*) AS n FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = %s",
                (name,),
            )
            return int(cur.fetchone()["n"]) == 1
    finally:
        conn.close()
