#!/usr/bin/env python3
"""RTB fixture broadcast vs amiga_games/L5 parity (SC-8)."""

from __future__ import annotations

import argparse
import json
import subprocess
import sys
from pathlib import Path

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.promote_running_tournament import running_tournament_games
from scripts.amiga.scoring_contract import load_scoring_context_for_tournament
from scripts.amiga.tournament_standings import GAME_SELECT_FOR_TOURNAMENT, compute_tournament_standings
from scripts.amiga.verify_php_community_parity import _find_php

_REPO = Path(__file__).resolve().parents[2]
_PHP_PROBE = _REPO / "scripts" / "oneoff" / "amiga_rtb_standings_build_parity.php"

_ROW_FIELDS = (
    "scope_type",
    "scope_key",
    "player_id",
    "position",
    "games",
    "wins",
    "draws",
    "losses",
    "goals_for",
    "goals_against",
    "points",
)

_L5_SELECT = """
SELECT scope_type, scope_key, player_id, position, games, wins, draws, losses,
       goals_for, goals_against, points
FROM amiga_tournament_standings
WHERE tournament_id = %s
"""


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


def _row_tuple(row: dict) -> tuple:
    return tuple(
        int(row[col]) if col not in ("scope_type", "scope_key") else row[col]
        for col in _ROW_FIELDS
    )


def _rows_signature(rows: list[dict]) -> list[tuple]:
    return sorted(_row_tuple(r) for r in rows)


def _php_rtb_build(php: Path, tournament_id: int) -> list[dict]:
    proc = subprocess.run(
        [str(php), str(_PHP_PROBE), str(tournament_id)],
        capture_output=True,
        text=True,
        check=False,
    )
    if proc.returncode != 0:
        raise RuntimeError(
            f"PHP RTB probe failed for tournament_id={tournament_id}: "
            f"{proc.stderr.strip() or proc.stdout}"
        )
    payload = json.loads(proc.stdout)
    return list(payload.get("rows") or [])


def _is_live_ops_row(row: dict) -> bool:
    if row.get("source_id") is not None:
        return False
    overrides = str(row.get("format_overrides") or "")
    return "tournament_builder" in overrides or "fixtures" in overrides


def _sample_tournament_ids(
    conn: pymysql.connections.Connection,
    *,
    sample: int,
    tournament_id: int | None,
    sweep: bool,
) -> list[int]:
    if tournament_id is not None:
        return [tournament_id]

    pattern_builder = "%tournament_builder%"
    pattern_fixtures = "%fixtures%"
    with conn.cursor() as cur:
        if sweep:
            cur.execute(
                """
                SELECT DISTINCT t.id
                FROM tournaments t
                INNER JOIN tournament_stages st ON st.tournament_id = t.id
                INNER JOIN tournament_fixtures f ON f.stage_id = st.id
                WHERE t.source_id IS NULL
                  AND (t.format_overrides LIKE %s OR t.format_overrides LIKE %s)
                  AND f.status = 'played'
                ORDER BY t.id
                """,
                (pattern_builder, pattern_fixtures),
            )
            return [int(row["id"]) for row in cur.fetchall()]

        cur.execute(
            """
            SELECT t.id
            FROM tournaments t
            WHERE t.source_id IS NULL
              AND (t.format_overrides LIKE %s OR t.format_overrides LIKE %s)
              AND t.rating_finalized = 1
              AND EXISTS (
                  SELECT 1 FROM amiga_games g WHERE g.tournament_id = t.id LIMIT 1
              )
            ORDER BY t.id DESC
            LIMIT %s
            """,
            (pattern_builder, pattern_fixtures, max(sample, 1)),
        )
        ids = [int(row["id"]) for row in cur.fetchall()]

        if len(ids) < sample:
            cur.execute(
                """
                SELECT DISTINCT t.id
                FROM tournaments t
                INNER JOIN tournament_stages st ON st.tournament_id = t.id
                INNER JOIN tournament_fixtures f ON f.stage_id = st.id
                WHERE t.source_id IS NULL
                  AND (t.format_overrides LIKE %s OR t.format_overrides LIKE %s)
                  AND f.status = 'played'
                  AND t.rating_finalized = 0
                ORDER BY t.id DESC
                LIMIT %s
                """,
                (pattern_builder, pattern_fixtures, sample),
            )
            for row in cur.fetchall():
                tid = int(row["id"])
                if tid not in ids:
                    ids.append(tid)

        return ids[:sample] if sample > 0 else ids


def verify_rtb_standings_parity(
    conn: pymysql.connections.Connection,
    php: Path,
    *,
    tournament_ids: list[int],
) -> list[str]:
    errors: list[str] = []
    if not tournament_ids:
        return errors

    for tid in tournament_ids:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT id, source_id, format_overrides, rating_finalized FROM tournaments WHERE id = %s",
                (tid,),
            )
            meta = cur.fetchone()
        if meta is None:
            errors.append(f"tournament_id={tid}: not found")
            continue
        if not _is_live_ops_row(meta):
            errors.append(f"tournament_id={tid}: not a live-ops generated tournament")
            continue

        fixture_games = running_tournament_games(conn, tid)
        if not fixture_games:
            continue

        scoring_context = load_scoring_context_for_tournament(conn, tid)
        py_fixture_rows = compute_tournament_standings(fixture_games, scoring_context=scoring_context)

        try:
            php_fixture_rows = _php_rtb_build(php, tid)
        except (RuntimeError, json.JSONDecodeError) as exc:
            errors.append(str(exc))
            continue

        py_sig = _rows_signature(py_fixture_rows)
        php_sig = _rows_signature(php_fixture_rows)
        if py_sig != php_sig:
            errors.append(
                f"tournament_id={tid}: Python vs PHP RTB fixture compute mismatch "
                f"(py={len(py_sig)} php={len(php_sig)})"
            )
            continue

        if int(meta.get("rating_finalized") or 0) != 1:
            continue

        with conn.cursor() as cur:
            cur.execute(GAME_SELECT_FOR_TOURNAMENT, (tid,))
            ground_games = cur.fetchall()
            cur.execute(_L5_SELECT, (tid,))
            l5_rows = cur.fetchall()

        if not ground_games:
            continue

        py_ground_rows = compute_tournament_standings(ground_games, scoring_context=scoring_context)
        ground_sig = _rows_signature(py_ground_rows)
        l5_sig = _rows_signature(l5_rows)

        if py_sig != ground_sig:
            errors.append(
                f"tournament_id={tid}: RTB fixture compute vs amiga_games compute mismatch "
                f"(fixture={len(py_sig)} ground={len(ground_sig)})"
            )
        if l5_rows and py_sig != l5_sig:
            errors.append(
                f"tournament_id={tid}: RTB fixture compute vs L5 mismatch "
                f"(fixture={len(py_sig)} l5={len(l5_sig)})"
            )

    return errors


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="RTB broadcast standings parity (SC-8)")
    parser.add_argument("--tournament-id", type=int, default=None)
    parser.add_argument("--sample", type=int, default=5)
    parser.add_argument("--sweep", action="store_true")
    args = parser.parse_args(argv if argv is not None else [])

    php = _find_php()
    if php is None:
        print("PHP executable not found", file=sys.stderr)
        return 1

    cfg = load_amiga_db_config()
    conn = _connect(cfg)
    try:
        tournament_ids = _sample_tournament_ids(
            conn,
            sample=args.sample,
            tournament_id=args.tournament_id,
            sweep=args.sweep,
        )
        errors = verify_rtb_standings_parity(conn, php, tournament_ids=tournament_ids)
    finally:
        conn.close()

    if errors:
        for err in errors:
            print(f"FAIL: {err}", file=sys.stderr)
        print(f"RTB standings parity: FAIL ({len(errors)} errors)", file=sys.stderr)
        return 1

    if not tournament_ids:
        print("RTB standings parity: SKIP (no live-ops tournaments with played fixtures)")
        return 0

    print(
        f"RTB standings parity: OK ({len(tournament_ids)} tournament(s) checked)"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())