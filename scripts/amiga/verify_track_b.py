#!/usr/bin/env python3
"""Smoke checks after Track B import + replay."""

from __future__ import annotations

import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.tournament_standings import parse_standings_winner


def _check_parse_standings_winner(errors: list[str]) -> None:
    cases = [
        ((4, 4, "(4-4) 5-3 p.k.", 10, 20), 10),
        ((5, 5, "(5-4 pen.)", 11, 22), 11),
        ((0, 0, "7-6pen", 12, 24), 12),
        ((3, 1, None, 13, 26), 13),
        ((2, 2, "7-7 e.t.", 14, 28), None),
    ]
    for (ga, gb, extra, pa, pb), want in cases:
        got = parse_standings_winner(ga, gb, extra, pa, pb)
        if got != want:
            errors.append(f"parse_standings_winner({ga},{gb},{extra!r}) => {got}, want {want}")


def main() -> int:
    cfg = load_amiga_db_config()
    conn = pymysql.connect(
        host=cfg.host, port=cfg.port, user=cfg.user, password=cfg.password,
        database=cfg.database, charset="utf8mb4", cursorclass=DictCursor,
    )
    errors: list[str] = []
    _check_parse_standings_winner(errors)
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

        for scope_key, winner_id in (
            ("Semi Finals|149-253", 149),
            ("Semi Finals|14-30", 14),
        ):
            cur.execute(
                """
                SELECT player_id, position FROM amiga_tournament_standings
                WHERE tournament_id = 527 AND scope_type = 'knockout' AND scope_key = %s
                ORDER BY position ASC
                """,
                (scope_key,),
            )
            rows = cur.fetchall()
            if len(rows) != 2:
                errors.append(f"WC XI {scope_key}: expected 2 rows, got {len(rows)}")
                continue
            if int(rows[0]["player_id"]) != winner_id or int(rows[0]["position"]) != 1:
                errors.append(
                    f"WC XI {scope_key}: winner should be player {winner_id}, "
                    f"got {rows[0]['player_id']} pos {rows[0]['position']}"
                )

    conn.close()
    if errors:
        for e in errors:
            print("FAIL:", e)
        return 1
    print(f"OK: extra={extra_n}, standings={standings_n}, Elo draw rule on {len(reg_draws)} ET samples")
    return 0


if __name__ == "__main__":
    sys.exit(main())
