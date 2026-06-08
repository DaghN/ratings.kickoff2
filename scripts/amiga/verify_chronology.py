#!/usr/bin/env python3
"""Assert monotonic game_date in canonical order (game_date ASC, id ASC)."""

from __future__ import annotations

import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config


def _count_backward_transitions(rows: list[dict]) -> int:
    backward = 0
    prev_date = None
    prev_id = None
    for row in rows:
        gd = row["game_date"]
        gid = int(row["id"])
        if prev_date is not None:
            if gd < prev_date or (gd == prev_date and gid <= prev_id):
                backward += 1
        prev_date = gd
        prev_id = gid
    return backward


def main() -> int:
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
    errors: list[str] = []

    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_games")
        game_count = int(cur.fetchone()["n"])
        if game_count < 27000:
            errors.append(f"expected ~27418 games, got {game_count}")

        cur.execute(
            """
            SELECT id, game_date
            FROM amiga_games
            ORDER BY game_date ASC, id ASC
            """
        )
        canonical = cur.fetchall()
        backward = _count_backward_transitions(canonical)
        if backward != 0:
            errors.append(
                f"backward game_date transitions in canonical order: {backward} (expected 0)"
            )

        cur.execute(
            """
            SELECT id, game_date
            FROM amiga_games
            ORDER BY id ASC
            """
        )
        by_id = cur.fetchall()
        id_order_backward = _count_backward_transitions(by_id)
        if id_order_backward != 0:
            errors.append(
                f"backward game_date when ordered by id: {id_order_backward} (expected 0)"
            )

    conn.close()

    if errors:
        for err in errors:
            print(f"FAIL: {err}", file=sys.stderr)
        return 1

    print(
        f"OK: {game_count} games, 0 backward game_date transitions "
        "(canonical and insert order)"
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
