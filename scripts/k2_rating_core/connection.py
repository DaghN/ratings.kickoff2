"""MySQL connection helpers for rating tooling (oneoffs, legacy shims)."""

from __future__ import annotations

import logging

import pymysql
from pymysql.cursors import DictCursor

from .config import DbConfig
from .constants import ALLOWED_DATABASES

log = logging.getLogger(__name__)

TARGET_DATABASES = {
    "local": "ko2unity_db",
    "sandbox": "ko2unity_work",
    "staging": "kooldb",
    "amiga": "ko2amiga_db",
}

_PROTECTED_BASELINE_DATABASES = frozenset({"ko2unity_baseline", "kooldb2"})


def _resolve_target(cfg: DbConfig, target: str | None) -> str:
    if cfg.database not in ALLOWED_DATABASES:
        raise SystemExit(
            f"Refusing to connect: database {cfg.database!r} not in {sorted(ALLOWED_DATABASES)}"
        )

    if target is None:
        if cfg.database == TARGET_DATABASES["local"]:
            return "local"
        if cfg.database == TARGET_DATABASES["sandbox"]:
            return "sandbox"
        if cfg.database == TARGET_DATABASES["amiga"]:
            return "amiga"
        raise SystemExit(
            f"Refusing to use database {cfg.database!r} without an explicit target. "
            "Use --target sandbox with ladder-work.ini, --target amiga with ko2amiga_config.local.php, "
            "or --target staging for staging."
        )

    if target not in TARGET_DATABASES:
        raise SystemExit(f"Unknown target {target!r}; expected one of {sorted(TARGET_DATABASES)}")

    expected = TARGET_DATABASES[target]
    if cfg.database != expected:
        raise SystemExit(
            f"Refusing target {target!r}: config database is {cfg.database!r}, expected {expected!r}."
        )

    return target


def _log_connection_identity(
    conn: pymysql.connections.Connection,
    *,
    target: str,
    configured_host: str,
    configured_port: int,
) -> None:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT
                DATABASE() AS db,
                CURRENT_USER() AS db_user,
                @@hostname AS server_host,
                @@port AS server_port,
                VERSION() AS server_version,
                @@session.time_zone AS session_tz
            """
        )
        row = cur.fetchone()
        assert row is not None

    log.info(
        "DB target=%s configured_host=%s configured_port=%s db=%s current_user=%s server_host=%s server_port=%s version=%s session_tz=%s",
        target,
        configured_host,
        configured_port,
        row["db"],
        row["db_user"],
        row["server_host"],
        row["server_port"],
        row["server_version"],
        row["session_tz"],
    )


def connect(
    cfg: DbConfig,
    *,
    dry_run: bool,
    target: str | None = None,
) -> pymysql.connections.Connection:
    if cfg.database in _PROTECTED_BASELINE_DATABASES:
        raise SystemExit(
            f"Refusing to connect to protected baseline database {cfg.database!r}."
        )
    resolved_target = _resolve_target(cfg, target)
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
    _log_connection_identity(
        conn,
        target=resolved_target,
        configured_host=cfg.host,
        configured_port=cfg.port,
    )
    with conn.cursor() as cur:
        cur.execute("SELECT DATABASE() AS db")
        row = cur.fetchone()
        assert row is not None
        if row["db"] != cfg.database:
            raise SystemExit(f"DATABASE()={row['db']!r} != configured {cfg.database!r}")
    if dry_run:
        log.info("Dry-run: connected to %s (no commits)", cfg.database)
    return conn
