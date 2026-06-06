#!/usr/bin/env python3
"""Smoke checks after Track B import + replay."""

from __future__ import annotations

import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config


def main() -> int:
    cfg = load_amiga_db_config()
    conn = pymysql.connect(
        host=cfg.host, port=cfg.port, user=cfg.user, password=cfg.password,
        database=cfg.database, charset="utf8mb4", cursorclass=DictCursor,
    )
    errors: list[str] = []
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_games WHERE extra IS NOT NULL AND extra <> ''")
        extra_n = int(cur.fetchone()["n"])
        if extra_n < 50:
            errors.append(f"expected ~108 games with extra, got {extra_n}")

        cur.execute(
            """
            SELECT g.id, g.goals_a, g.goals_b, g.extra, gr.actual_score, gr.draw
            FROM amiga_games g
            INNER JOIN amiga_game_ratings gr ON gr.game_id = g.id
            WHERE g.extra IS NOT NULL AND g.extra <> ''
              AND g.goals_a = g.goals_b
            LIMIT 20
            """
        )
        reg_draws = cur.fetchall()
        for row in reg_draws:
            if abs(float(row["actual_score"]) - 0.5) > 0.001 or int(row["draw"]) != 1:
                errors.append(
                    f"game {row['id']}: regulation draw with extra must be Elo draw "
                    f"(actual_score={row['actual_score']}, extra={row['extra']!r})"
                )

        cur.execute("SELECT COUNT(*) AS n FROM amiga_tournament_standings")
        standings_n = int(cur.fetchone()["n"])
        if standings_n < 1000:
            errors.append(f"standings row count suspiciously low: {standings_n}")

    conn.close()
    if errors:
        for e in errors:
            print("FAIL:", e)
        return 1
    print(f"OK: extra={extra_n}, standings={standings_n}, Elo draw rule on {len(reg_draws)} ET samples")
    return 0


if __name__ == "__main__":
    sys.exit(main())
