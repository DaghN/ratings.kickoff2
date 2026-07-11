#!/usr/bin/env python3
"""PHP vs Python tournament standings executor parity (SC-5)."""

from __future__ import annotations

import argparse
import json
import os
import subprocess
import sys
from pathlib import Path

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.scoring_contract import load_scoring_context_for_tournament
from scripts.amiga.tournament_standings import (
    GAME_SELECT_FOR_TOURNAMENT,
    compute_tournament_standings,
)
from scripts.amiga.verify_php_community_parity import _find_php

_REPO = Path(__file__).resolve().parents[2]
_PHP_PROBE = _REPO / "scripts" / "oneoff" / "amiga_standings_build_parity.php"

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
    return tuple(int(row[col]) if col != "scope_type" and col != "scope_key" else row[col] for col in _ROW_FIELDS)


def _rows_signature(rows: list[dict]) -> list[tuple]:
    return sorted(_row_tuple(r) for r in rows)


def _php_build(php: Path, tournament_id: int) -> list[dict]:
    proc = subprocess.run(
        [str(php), str(_PHP_PROBE), str(tournament_id)],
        capture_output=True,
        text=True,
        check=False,
    )
    if proc.returncode != 0:
        raise RuntimeError(
            f"PHP probe failed for tournament_id={tournament_id}: "
            f"{proc.stderr.strip() or proc.stdout}"
        )
    payload = json.loads(proc.stdout)
    return list(payload.get("rows") or [])


def _sample_tournament_ids(
    conn: pymysql.connections.Connection,
    *,
    sample: int,
    tournament_id: int | None,
    sweep: bool,
) -> list[int]:
    if tournament_id is not None:
        return [tournament_id]

    with conn.cursor() as cur:
        if sweep:
            cur.execute(
                """
                SELECT DISTINCT tournament_id
                FROM amiga_games
                WHERE tournament_id IS NOT NULL
                ORDER BY tournament_id
                """
            )
            return [int(row["tournament_id"]) for row in cur.fetchall()]

        ids: list[int] = []
        cur.execute(
            """
            SELECT tournament_id
            FROM amiga_tournament_standings
            GROUP BY tournament_id
            ORDER BY COUNT(*) DESC, tournament_id ASC
            LIMIT %s
            """,
            (max(sample, 1),),
        )
        for row in cur.fetchall():
            tid = int(row["tournament_id"])
            if tid not in ids:
                ids.append(tid)

        cur.execute(
            """
            SELECT DISTINCT st.tournament_id
            FROM tournament_stages st
            WHERE st.scoring_primitive IS NOT NULL
            ORDER BY st.tournament_id ASC
            LIMIT 3
            """
        )
        for row in cur.fetchall():
            tid = int(row["tournament_id"])
            if tid not in ids:
                ids.append(tid)

        if len(ids) < sample:
            cur.execute(
                """
                SELECT DISTINCT tournament_id
                FROM amiga_games
                WHERE tournament_id IS NOT NULL
                ORDER BY tournament_id ASC
                LIMIT %s
                """,
                (sample,),
            )
            for row in cur.fetchall():
                tid = int(row["tournament_id"])
                if tid not in ids:
                    ids.append(tid)

        return ids[:sample] if sample > 0 else ids


def verify_php_standings_parity(
    conn: pymysql.connections.Connection,
    php: Path,
    *,
    tournament_ids: list[int],
) -> list[str]:
    errors: list[str] = []
    if not tournament_ids:
        return ["no tournaments selected for PHP standings parity"]

    for tid in tournament_ids:
        with conn.cursor() as cur:
            cur.execute(GAME_SELECT_FOR_TOURNAMENT, (tid,))
            games = cur.fetchall()

        if not games:
            continue

        scoring_context = load_scoring_context_for_tournament(conn, tid)
        py_rows = compute_tournament_standings(games, scoring_context=scoring_context)

        try:
            php_rows = _php_build(php, tid)
        except (RuntimeError, json.JSONDecodeError) as exc:
            errors.append(str(exc))
            continue

        py_sig = _rows_signature(py_rows)
        php_sig = _rows_signature(php_rows)
        if py_sig != php_sig:
            errors.append(
                f"standings mismatch tournament_id={tid} py_rows={len(py_rows)} php_rows={len(php_rows)}"
            )
            if len(py_sig) == len(php_sig):
                for idx, (py_row, php_row) in enumerate(zip(py_sig, php_sig)):
                    if py_row != php_row:
                        errors.append(
                            f"  first diff at sorted index {idx}: py={py_row} php={php_row}"
                        )
                        break

    return errors


def _php_required() -> bool:
    return os.environ.get("AMIGA_REQUIRE_PHP", "").strip().lower() in ("1", "true", "yes")


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="PHP vs Python standings executor parity")
    parser.add_argument("--tournament-id", type=int, default=None)
    parser.add_argument("--sample", type=int, default=5, help="Default sample size when not sweeping")
    parser.add_argument("--sweep", action="store_true", help="All tournaments with games")
    args = parser.parse_args(argv if argv is not None else [])

    php = _find_php()
    if php is None:
        if _php_required():
            print("FAIL: PHP CLI not found (AMIGA_REQUIRE_PHP=1)", file=sys.stderr)
            return 1
        print("SKIP: PHP CLI not found (Laragon path)", file=sys.stderr)
        return 0

    cfg = load_amiga_db_config()
    conn = _connect(cfg)
    try:
        tournament_ids = _sample_tournament_ids(
            conn,
            sample=args.sample,
            tournament_id=args.tournament_id,
            sweep=args.sweep,
        )
        errors = verify_php_standings_parity(conn, php, tournament_ids=tournament_ids)
    finally:
        conn.close()

    if errors:
        for err in errors:
            print(f"FAIL: {err}", file=sys.stderr)
        return 1

    label = "sweep" if args.sweep else f"n={len(tournament_ids)}"
    print(f"OK: PHP standings parity ({php.name}, {label})")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())