#!/usr/bin/env python3
"""SC-11 match extensions oracle — structured cols + standings stability."""

from __future__ import annotations

import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.match_extensions import extract_structured_from_extra, resolve_game_extension_winner
from scripts.amiga.scoring_contract import load_scoring_context_for_tournament
from scripts.amiga.tournament_standings import GAME_SELECT_FOR_TOURNAMENT, compute_tournament_standings

_EXTENSION_COLS = ("goals_et_a", "goals_et_b", "pens_a", "pens_b")


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


def _column_exists(conn: pymysql.connections.Connection, table: str, column: str) -> bool:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s
            LIMIT 1
            """,
            (table, column),
        )
        return cur.fetchone() is not None


def verify_match_extensions(conn: pymysql.connections.Connection) -> list[str]:
    errors: list[str] = []
    for table in ("amiga_games", "tournament_fixtures"):
        for col in _EXTENSION_COLS:
            if not _column_exists(conn, table, col):
                errors.append(f"{table}.{col} missing — run apply_schema_structure")

    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT id, extra, goals_a, goals_b, goals_et_a, goals_et_b, pens_a, pens_b
            FROM amiga_games
            WHERE extra IS NOT NULL AND TRIM(extra) <> ''
            """
        )
        rows = list(cur.fetchall())

    for row in rows:
        structured = extract_structured_from_extra(
            row["extra"],
            goals_a=row["goals_a"],
            goals_b=row["goals_b"],
        )
        if structured is None:
            continue
        for field in ("goals_et_a", "goals_et_b", "pens_a", "pens_b"):
            expected = getattr(structured, field)
            got = row[field]
            if expected is None and got is None:
                continue
            if expected is None or got is None or int(expected) != int(got):
                errors.append(
                    f"game_id={row['id']}: {field} db={got} expected={expected} from extra={row['extra']!r}"
                )

    # ET structured rows should resolve extra_time step when regulation draw.
    for row in rows:
        if row["goals_et_a"] is None or row["goals_et_b"] is None:
            continue
        if int(row["goals_et_a"]) == int(row["goals_et_b"]):
            continue
        with conn.cursor() as cur:
            cur.execute(
                "SELECT goals_a, goals_b, player_a_id, player_b_id FROM amiga_games WHERE id = %s",
                (int(row["id"]),),
            )
            game = cur.fetchone()
        if game is None:
            continue
        if int(game["goals_a"]) != int(game["goals_b"]):
            continue
        wid = resolve_game_extension_winner(
            row,
            "extra_time",
            int(game["player_a_id"]),
            int(game["player_b_id"]),
        )
        if wid is None:
            errors.append(f"game_id={row['id']}: structured ET should resolve extra_time winner")

    # Standings signatures unchanged vs pre-structured (recompute all tournaments with extra games).
    tournament_ids: set[int] = set()
    with conn.cursor() as cur:
        cur.execute(
            "SELECT DISTINCT tournament_id FROM amiga_games WHERE extra IS NOT NULL AND TRIM(extra) <> ''"
        )
        for r in cur.fetchall():
            if r["tournament_id"] is not None:
                tournament_ids.add(int(r["tournament_id"]))

    for tid in sorted(tournament_ids):
        with conn.cursor() as cur:
            cur.execute(GAME_SELECT_FOR_TOURNAMENT, (tid,))
            games = cur.fetchall()
        if not games:
            continue
        ctx = load_scoring_context_for_tournament(conn, tid)
        rows_out = compute_tournament_standings(games, scoring_context=ctx)
        if not rows_out:
            errors.append(f"tournament_id={tid}: standings compute returned no rows with extra games")

    return errors


def main(argv: list[str] | None = None) -> int:
    # argv reserved for CLI dispatcher; no options yet (SC-11).
    _ = argv

    cfg = load_amiga_db_config()
    conn = _connect(cfg)
    try:
        errors = verify_match_extensions(conn)
    finally:
        conn.close()

    if errors:
        for err in errors[:25]:
            print(f"FAIL: {err}", file=sys.stderr)
        if len(errors) > 25:
            print(f"FAIL: ... and {len(errors) - 25} more", file=sys.stderr)
        print(f"verify-match-extensions: FAIL ({len(errors)} errors)", file=sys.stderr)
        return 1

    print("verify-match-extensions: OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())