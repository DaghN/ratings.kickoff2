#!/usr/bin/env python3
"""Assert amiga_world_cup_stats invariants."""

from __future__ import annotations

import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.tournament_honours import is_world_cup_tournament
from scripts.amiga.world_cup_stats import build_world_cup_stats_row
from scripts.amiga.world_cup_stats_columns import WORLD_CUP_STATS_COLUMNS

_TOLERANCE = 1e-5

_DECIMAL_COLUMNS = frozenset(
    {
        "goals_per_game",
        "draw_rate",
        "decided_rate",
        "double_digit_rate",
        "clean_sheet_rate",
        "high_scoring_rate",
        "low_scoring_rate",
        "avg_games_per_player",
        "avg_opponents_per_player",
        "guest_player_share",
        "share_of_year_games",
    }
)


def _connect(cfg) -> pymysql.connections.Connection:
    return pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=DictCursor,
    )


def _values_match(left: object, right: object, column: str) -> bool:
    if left is None and right is None:
        return True
    if column in _DECIMAL_COLUMNS:
        if left is None or right is None:
            return False
        return abs(float(left) - float(right)) <= _TOLERANCE
    return left == right


def verify_world_cup_stats(conn: pymysql.connections.Connection) -> list[str]:
    errors: list[str] = []

    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT t.id, t.name
            FROM tournaments t
            WHERE t.rating_finalized = 1
              AND EXISTS (SELECT 1 FROM amiga_games g WHERE g.tournament_id = t.id)
            """
        )
        wc_ids = [
            int(row["id"])
            for row in cur.fetchall()
            if is_world_cup_tournament(str(row.get("name") or ""))
        ]

        cur.execute("SELECT tournament_id FROM amiga_world_cup_stats")
        stored_ids = {int(row["tournament_id"]) for row in cur.fetchall()}

    if stored_ids != set(wc_ids):
        errors.append(
            f"world cup stats rows {len(stored_ids)} != catalog WCs {len(wc_ids)}"
        )

    sample = wc_ids[:3]
    if wc_ids and wc_ids[-1] not in sample:
        sample.append(wc_ids[-1])

    for tid in sample:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT * FROM amiga_world_cup_stats WHERE tournament_id = %s LIMIT 1",
                (tid,),
            )
            stored = cur.fetchone()
        if not stored:
            errors.append(f"missing world cup stats row tournament_id={tid}")
            continue
        oracle = build_world_cup_stats_row(conn, tid)
        if oracle is None:
            errors.append(f"oracle could not build WC row tournament_id={tid}")
            continue
        for col in WORLD_CUP_STATS_COLUMNS:
            if not _values_match(stored.get(col), oracle.get(col), col):
                errors.append(
                    f"world cup stats {col} mismatch tournament_id={tid} "
                    f"stored={stored.get(col)!r} oracle={oracle.get(col)!r}"
                )
                break

    return errors


def main(argv: list[str] | None = None) -> int:
    _ = argv
    cfg = load_amiga_db_config()
    conn = _connect(cfg)
    try:
        errors = verify_world_cup_stats(conn)
    finally:
        conn.close()
    if errors:
        for err in errors:
            print(f"FAIL: {err}", file=sys.stderr)
        return 1
    with _connect(cfg) as conn2:
        with conn2.cursor() as cur:
            cur.execute("SELECT COUNT(*) AS n FROM amiga_world_cup_stats")
            n = int(cur.fetchone()["n"])
    print(f"OK: world cup stats verified ({n} rows)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
