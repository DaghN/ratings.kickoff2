#!/usr/bin/env python3
"""Verify live-created amiga_players rows."""

from __future__ import annotations

import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.country_registry import official_name_to_row
from scripts.amiga.player_orphans import PLAYER_SOURCE_LIVE_OPS


def _column_exists(conn: pymysql.connections.Connection, column: str) -> bool:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'amiga_players'
              AND COLUMN_NAME = %s
            """,
            (column,),
        )
        return int(cur.fetchone()["n"]) > 0


def main() -> int:
    errors: list[str] = []
    cfg = load_amiga_db_config()
    conn = pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=DictCursor,
    )
    try:
        if not _column_exists(conn, "player_source"):
            errors.append("amiga_players.player_source column missing — run python -m scripts.amiga prove")
            for err in errors:
                print(f"FAIL: {err}", file=sys.stderr)
            return 1

        choosable = {
            name
            for name, row in official_name_to_row().items()
            if bool(row.get("choosable", True))
        }
        with conn.cursor() as cur:
            cur.execute(
                "SELECT id, name, country, player_source FROM amiga_players WHERE player_source = %s ORDER BY id",
                (PLAYER_SOURCE_LIVE_OPS,),
            )
            live_rows = cur.fetchall()

        for row in live_rows:
            pid = int(row["id"])
            country = str(row.get("country") or "").strip()
            if country == "":
                errors.append(f"live_ops player_id={pid} has empty country")
            elif country not in choosable:
                errors.append(f"live_ops player_id={pid} country not choosable: {country!r}")

        if errors:
            for err in errors:
                print(f"FAIL: {err}", file=sys.stderr)
            return 1
        print(f"OK: verify-player-create ({len(live_rows)} live_ops row(s))")
        return 0
    finally:
        conn.close()


if __name__ == "__main__":
    sys.exit(main())