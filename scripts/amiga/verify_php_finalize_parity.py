#!/usr/bin/env python3
"""PHP vs Python reopen+finalize parity (Phase D stored-field semantics)."""

from __future__ import annotations

import logging
import os
import subprocess
import sys
from datetime import date, datetime
from decimal import Decimal
from pathlib import Path
from typing import Any

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.finalize_tournament import finalize_tournament
from scripts.amiga.generalstats_columns import (
    GENERALSTATS_PAYLOAD_COLUMNS,
    GEO_YEAR_PLAYER_COLUMNS,
    REALM_SNAPSHOT_PAYLOAD_COLUMNS,
    RECORD_RISE_PLAYER_COLUMNS,
)
from scripts.amiga.refinalize import reopen_tournament
from scripts.amiga.snapshot_row import (
    EVENT_LOCAL_COLUMNS,
    HONOURS_CURRENT_COLUMNS,
    HONOURS_SNAPSHOT_COLUMNS,
)

log = logging.getLogger(__name__)

_REPO_ROOT = Path(__file__).resolve().parents[2]
_PHP_RUNNER = _REPO_ROOT / "site" / "public_html" / "amiga" / "ops" / "run_process_game.php"
_ALKIS_PLAYER_ID = 14
_ALKIS_RISE_EVENT_DATE = "2025-09-20"
_TOLERANCE = 1e-5
_SAMPLE_LIMIT = 8

_REALM_KEY_COLUMNS = (
    "tournament_id",
    "event_date",
    "event_chrono",
    "tournament_name",
)

_SNAPSHOT_KEY_COLUMNS = (
    "player_id",
    "tournament_id",
    "event_date",
    "event_chrono",
    "tournament_name",
    "is_cup",
    "country",
    "has_league",
    "has_cup",
)

_CAREER_BEST_COLUMNS = (
    "career_best_performance_rating",
    "career_best_performance_tournament_id",
)

_PARITY_SNAPSHOT_COLUMNS: tuple[str, ...] = (
    _SNAPSHOT_KEY_COLUMNS
    + EVENT_LOCAL_COLUMNS
    + HONOURS_SNAPSHOT_COLUMNS
    + _CAREER_BEST_COLUMNS
    + GEO_YEAR_PLAYER_COLUMNS
    + RECORD_RISE_PLAYER_COLUMNS
)

_PARITY_CURRENT_COLUMNS: tuple[str, ...] = (
    ("player_id",)
    + ("last_tournament_id", "last_event_date")
    + HONOURS_CURRENT_COLUMNS
    + _CAREER_BEST_COLUMNS
    + GEO_YEAR_PLAYER_COLUMNS
    + RECORD_RISE_PLAYER_COLUMNS
)


def _norm_scalar(value: Any) -> Any:
    if value is None:
        return None
    if isinstance(value, Decimal):
        return float(value)
    if isinstance(value, datetime):
        return value.strftime("%Y-%m-%d %H:%M:%S")
    if isinstance(value, date):
        return value.isoformat()
    if isinstance(value, float):
        return round(value, 6)
    if isinstance(value, (int, str)):
        return value
    return str(value)


def _values_equal(left: Any, right: Any) -> bool:
    left_n = _norm_scalar(left)
    right_n = _norm_scalar(right)
    if left_n is None and right_n is None:
        return True
    if isinstance(left_n, float) or isinstance(right_n, float):
        try:
            return abs(float(left_n) - float(right_n)) <= _TOLERANCE
        except (TypeError, ValueError):
            return False
    return left_n == right_n


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
        autocommit=False,
    )
    with conn.cursor() as cur:
        cur.execute("SET time_zone = '+00:00'")
    return conn


def resolve_php_cli() -> Path:
    env = os.environ.get("KOOL_PHP_CLI")
    if env:
        path = Path(env)
        if path.is_file():
            return path
    laragon_root = Path(r"C:\laragon\bin\php")
    if laragon_root.is_dir():
        candidates = sorted(laragon_root.glob("*/php.exe"), reverse=True)
        if candidates:
            return candidates[0]
    raise FileNotFoundError(
        "PHP CLI not found — set KOOL_PHP_CLI or install Laragon PHP "
        "(see AGENTS.md / docs/OPERATIONS_QUICK_START.md)"
    )


def _alkis_rise_tournament_id(conn: pymysql.connections.Connection) -> int:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT event_gold_last_rise_tournament_id, event_gold_last_rise_event_date
            FROM amiga_player_current
            WHERE player_id = %s
            LIMIT 1
            """,
            (_ALKIS_PLAYER_ID,),
        )
        row = cur.fetchone()
    if not row or row.get("event_gold_last_rise_tournament_id") is None:
        raise RuntimeError(f"player_id={_ALKIS_PLAYER_ID} missing event_gold rise anchor")
    rise_date = _norm_scalar(row.get("event_gold_last_rise_event_date"))
    if str(rise_date)[:10] != _ALKIS_RISE_EVENT_DATE:
        raise RuntimeError(
            f"Alkis rise anchor date {rise_date!r} != {_ALKIS_RISE_EVENT_DATE!r}"
        )
    return int(row["event_gold_last_rise_tournament_id"])


def _capture_parity_state(
    conn: pymysql.connections.Connection,
    tournament_ids: list[int],
) -> dict[str, Any]:
    if not tournament_ids:
        return {
            "snapshots": {},
            "realm": {},
            "generalstats": {},
            "current": {},
        }

    placeholders = ", ".join(["%s"] * len(tournament_ids))
    params = tuple(tournament_ids)

    snapshots: dict[tuple[int, int], dict[str, Any]] = {}
    with conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT *
            FROM amiga_player_event_snapshots
            WHERE tournament_id IN ({placeholders})
            ORDER BY tournament_id ASC, player_id ASC
            """,
            params,
        )
        for row in cur.fetchall():
            key = (int(row["player_id"]), int(row["tournament_id"]))
            snapshots[key] = row

    realm: dict[int, dict[str, Any]] = {}
    with conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT *
            FROM amiga_realm_snapshots
            WHERE tournament_id IN ({placeholders})
            ORDER BY tournament_id ASC
            """,
            params,
        )
        for row in cur.fetchall():
            realm[int(row["tournament_id"])] = row

    with conn.cursor() as cur:
        cur.execute("SELECT * FROM amiga_generalstats WHERE id = 1 LIMIT 1")
        generalstats = cur.fetchone() or {}

    player_ids = sorted({pid for pid, _tid in snapshots})
    current: dict[int, dict[str, Any]] = {}
    if player_ids:
        p_placeholders = ", ".join(["%s"] * len(player_ids))
        with conn.cursor() as cur:
            cur.execute(
                f"""
                SELECT *
                FROM amiga_player_current
                WHERE player_id IN ({p_placeholders})
                ORDER BY player_id ASC
                """,
                tuple(player_ids),
            )
            for row in cur.fetchall():
                current[int(row["player_id"])] = row

    return {
        "snapshots": snapshots,
        "realm": realm,
        "generalstats": generalstats,
        "current": current,
    }


def _compare_parity_state(
    label: str,
    expected: dict[str, Any],
    actual: dict[str, Any],
    errors: list[str],
) -> None:
    exp_snaps = expected["snapshots"]
    act_snaps = actual["snapshots"]
    if set(exp_snaps) != set(act_snaps):
        errors.append(
            f"{label} snapshots: key set mismatch "
            f"expected={len(exp_snaps)} actual={len(act_snaps)}"
        )
    for key in sorted(set(exp_snaps) | set(act_snaps)):
        exp_row = exp_snaps.get(key)
        act_row = act_snaps.get(key)
        if exp_row is None or act_row is None:
            errors.append(f"{label} snapshot missing key={key}")
            continue
        for col in _PARITY_SNAPSHOT_COLUMNS:
            if not _values_equal(exp_row.get(col), act_row.get(col)):
                errors.append(
                    f"{label} snapshot player_id={key[0]} tournament_id={key[1]} "
                    f"{col}: python={exp_row.get(col)!r} php={act_row.get(col)!r}"
                )

    exp_realm = expected["realm"]
    act_realm = actual["realm"]
    if set(exp_realm) != set(act_realm):
        errors.append(
            f"{label} realm: tournament_id set mismatch "
            f"expected={sorted(exp_realm)} actual={sorted(act_realm)}"
        )
    for tid in sorted(set(exp_realm) | set(act_realm)):
        exp_row = exp_realm.get(tid)
        act_row = act_realm.get(tid)
        if exp_row is None or act_row is None:
            errors.append(f"{label} realm missing tournament_id={tid}")
            continue
        for col in _REALM_KEY_COLUMNS + REALM_SNAPSHOT_PAYLOAD_COLUMNS:
            if not _values_equal(exp_row.get(col), act_row.get(col)):
                errors.append(
                    f"{label} realm tournament_id={tid} {col}: "
                    f"python={exp_row.get(col)!r} php={act_row.get(col)!r}"
                )

    for col in GENERALSTATS_PAYLOAD_COLUMNS:
        if not _values_equal(
            expected["generalstats"].get(col),
            actual["generalstats"].get(col),
        ):
            errors.append(
                f"{label} generalstats {col}: "
                f"python={expected['generalstats'].get(col)!r} "
                f"php={actual['generalstats'].get(col)!r}"
            )

    exp_current = expected["current"]
    act_current = actual["current"]
    if set(exp_current) != set(act_current):
        errors.append(
            f"{label} current: player_id set mismatch "
            f"expected={len(exp_current)} actual={len(act_current)}"
        )
    for pid in sorted(set(exp_current) | set(act_current)):
        exp_row = exp_current.get(pid)
        act_row = act_current.get(pid)
        if exp_row is None or act_row is None:
            errors.append(f"{label} current missing player_id={pid}")
            continue
        for col in _PARITY_CURRENT_COLUMNS:
            if not _values_equal(exp_row.get(col), act_row.get(col)):
                errors.append(
                    f"{label} current player_id={pid} {col}: "
                    f"python={exp_row.get(col)!r} php={act_row.get(col)!r}"
                )


def _run_php_verb(verb: str, tournament_id: int) -> None:
    php = resolve_php_cli()
    if not _PHP_RUNNER.is_file():
        raise FileNotFoundError(f"PHP runner missing: {_PHP_RUNNER}")
    cmd = [
        str(php),
        str(_PHP_RUNNER),
        verb,
        f"--tournament-id={tournament_id}",
    ]
    log.info("verify-php-finalize-parity: %s", " ".join(cmd))
    proc = subprocess.run(
        cmd,
        cwd=str(_REPO_ROOT),
        capture_output=True,
        text=True,
        encoding="utf-8",
        errors="replace",
    )
    if proc.returncode != 0:
        raise RuntimeError(
            f"PHP {verb} failed (exit {proc.returncode}): "
            f"{proc.stderr.strip() or proc.stdout.strip()}"
        )


def _run_python_reopen_finalize(
    conn: pymysql.connections.Connection,
    tournament_id: int,
) -> None:
    reopen_tournament(conn, tournament_id)
    finalize_tournament(conn, tournament_id)
    conn.commit()


def verify_php_finalize_parity(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int | None = None,
) -> list[str]:
    errors: list[str] = []
    anchor_tid = tournament_id if tournament_id is not None else _alkis_rise_tournament_id(conn)

    log.info(
        "verify-php-finalize-parity: anchor tournament_id=%s (reopen+finalize)",
        anchor_tid,
    )

    _run_python_reopen_finalize(conn, anchor_tid)
    oracle = _capture_parity_state(conn, [anchor_tid])

    try:
        _run_php_verb("reopen-tournament", anchor_tid)
        _run_php_verb("finalize-tournament", anchor_tid)
        post_php = _capture_parity_state(conn, [anchor_tid])
        _compare_parity_state("php-reopen-finalize", oracle, post_php, errors)
    finally:
        log.info("verify-php-finalize-parity: restoring via Python reopen+finalize")
        _run_python_reopen_finalize(conn, anchor_tid)

    return errors


def main() -> int:
    logging.basicConfig(level=logging.INFO, format="%(levelname)s %(message)s")
    conn = _connect()
    try:
        errors = verify_php_finalize_parity(conn)
    finally:
        conn.close()

    if errors:
        print(f"FAIL: {len(errors)} verify-php-finalize-parity issue(s):", file=sys.stderr)
        for err in errors[:_SAMPLE_LIMIT]:
            print(f"  - {err}", file=sys.stderr)
        if len(errors) > _SAMPLE_LIMIT:
            print(f"  ... and {len(errors) - _SAMPLE_LIMIT} more", file=sys.stderr)
        return 1

    print("OK: verify-php-finalize-parity")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
