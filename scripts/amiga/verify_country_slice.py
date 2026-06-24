#!/usr/bin/env python3
"""Verify world_cup country slice tables against compute oracle."""

from __future__ import annotations

import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.country_slice_columns import COUNTRY_SLICE_STAT_COLUMNS, SLICE_KEY_WORLD_CUP
from scripts.amiga.country_slice_compute import compute_country_slices_through_tournament
from scripts.amiga.player_geo_year import load_player_countries

_FLOAT_COLS = frozenset(
    {
        "wc_participations_per_player",
        "games_per_player",
        "domestic_game_share",
        "international_game_share",
        "games_share",
        "goals_share",
        "points_per_realm_wc",
        "win_rate",
        "average_opponent_rating",
        "performance_rating",
        "goal_ratio",
        "double_digits_ratio",
        "clean_sheets_ratio",
        "double_digits_conceded_ratio",
        "clean_sheets_conceded_ratio",
    }
)
_INT_COLS = tuple(c for c in COUNTRY_SLICE_STAT_COLUMNS if c not in _FLOAT_COLS)


def _connect() -> pymysql.connections.Connection:
    cfg = load_amiga_db_config()
    conn = pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=DictCursor,
        autocommit=True,
    )
    with conn.cursor() as cur:
        cur.execute("SET time_zone = '+00:00'")
    return conn


def _latest_wc_cutoff(conn: pymysql.connections.Connection) -> tuple[int, Any, float] | None:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT id, event_date, chrono
            FROM tournaments
            WHERE name REGEXP %s
              AND rating_finalized = 1
            ORDER BY event_date DESC, chrono DESC, id DESC
            LIMIT 1
            """,
            (r"^World Cup[[:space:]]+[^[:space:]]",),
        )
        row = cur.fetchone()
    if not row:
        return None
    return int(row["id"]), row.get("event_date"), float(row.get("chrono") or 0.0)


def _load_stored_totals(conn: pymysql.connections.Connection) -> dict[str, dict]:
    cols = ", ".join(COUNTRY_SLICE_STAT_COLUMNS)
    with conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT country_token, {cols}
            FROM amiga_country_slice_totals
            WHERE slice_key = %s
            """,
            (SLICE_KEY_WORLD_CUP,),
        )
        rows = cur.fetchall()
    return {str(r["country_token"]): dict(r) for r in rows}


def _compare_value(col: str, expected: Any, actual: Any) -> str | None:
    if col in _FLOAT_COLS:
        if expected is None and actual is None:
            return None
        if expected is None or actual is None:
            return f"expected={expected!r} actual={actual!r}"
        exp = float(expected)
        act = float(actual)
        if abs(exp - act) > 0.0002:
            return f"expected={exp} actual={act}"
        return None
    exp_i = int(expected or 0)
    act_i = int(actual or 0)
    if exp_i != act_i:
        return f"expected={exp_i} actual={act_i}"
    return None


def main() -> int:
    conn = _connect()
    cutoff = _latest_wc_cutoff(conn)
    if cutoff is None:
        print("verify-country-slice OK (no finalized World Cups)")
        return 0

    tournament_id, event_date, event_chrono = cutoff
    player_countries = load_player_countries(conn)
    oracle = compute_country_slices_through_tournament(
        conn,
        tournament_id=tournament_id,
        event_date=event_date,
        event_chrono=event_chrono,
        player_countries=player_countries,
    )
    stored = _load_stored_totals(conn)

    errors: list[str] = []
    oracle_tokens = set(oracle)
    stored_tokens = set(stored)
    if oracle_tokens != stored_tokens:
        missing = oracle_tokens - stored_tokens
        extra = stored_tokens - oracle_tokens
        if missing:
            errors.append(f"missing country rows in DB: {sorted(missing)}")
        if extra:
            errors.append(f"extra country rows in DB: {sorted(extra)}")

    for token in sorted(oracle_tokens & stored_tokens):
        for col in COUNTRY_SLICE_STAT_COLUMNS:
            mismatch = _compare_value(col, oracle[token].get(col), stored[token].get(col))
            if mismatch:
                errors.append(f"{token}.{col}: {mismatch}")

    if errors:
        print("verify-country-slice FAIL:", len(errors), "issue(s)", file=sys.stderr)
        for err in errors[:40]:
            print(" ", err, file=sys.stderr)
        if len(errors) > 40:
            print(f"  ... and {len(errors) - 40} more", file=sys.stderr)
        return 1

    print(f"verify-country-slice OK ({len(oracle)} countries)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
