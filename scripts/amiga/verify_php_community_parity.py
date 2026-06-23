#!/usr/bin/env python3
"""PHP vs Python community stats build parity (headline + v1 facts)."""

from __future__ import annotations

import json
import os
import subprocess
import sys
from pathlib import Path

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.community_stat_facts import build_community_facts_at_cutoff
from scripts.amiga.community_stats import build_community_headline_row
from scripts.amiga.community_stats_columns import COMMUNITY_HEADLINE_COLUMNS
from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.verify_community_stats import (
    _DECIMAL_COLUMNS,
    _sample_finalized_tournament_ids,
    _values_match,
)

_REPO = Path(__file__).resolve().parents[2]
_PHP_PROBE = _REPO / "scripts" / "oneoff" / "amiga_community_build_parity.php"
_PHP_CANDIDATES = (
    Path(r"C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe"),
    Path(r"C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe"),
    Path(r"C:\laragon\bin\php\php-8.2.12-Win32-vs16-x64\php.exe"),
)


def _find_php() -> Path | None:
    for candidate in _PHP_CANDIDATES:
        if candidate.is_file():
            return candidate
    laragon = Path(r"C:\laragon\bin\php")
    if laragon.is_dir():
        for exe in sorted(laragon.glob("*/php.exe"), reverse=True):
            return exe
    return None


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


def _fact_key(row: dict) -> tuple:
    return (
        row["period_type"],
        row["period_key"],
        row["slice_type"],
        row["slice_key"],
        row["metric_key"],
        row["count_basis"],
    )


def _facts_map(facts: list[dict]) -> dict[tuple, float]:
    return {_fact_key(f): float(f["value"]) for f in facts}


def _php_build(php: Path, tournament_id: int) -> dict:
    proc = subprocess.run(
        [str(php), str(_PHP_PROBE), str(tournament_id)],
        capture_output=True,
        text=True,
        check=False,
    )
    if proc.returncode != 0:
        raise RuntimeError(
            f"PHP probe failed for tournament_id={tournament_id}: {proc.stderr.strip() or proc.stdout}"
        )
    return json.loads(proc.stdout)


def verify_php_community_parity(conn: pymysql.connections.Connection, php: Path) -> list[str]:
    errors: list[str] = []
    sample_ids = _sample_finalized_tournament_ids(conn, n=3)
    extra = [24]
    tournament_ids: list[int] = []
    for tid in sample_ids + extra:
        if tid not in tournament_ids:
            tournament_ids.append(tid)

    if not tournament_ids:
        return ["no finalized tournaments for PHP community parity"]

    for tid in tournament_ids:
        try:
            php_payload = _php_build(php, tid)
        except (RuntimeError, json.JSONDecodeError) as exc:
            errors.append(str(exc))
            continue

        py_headline = build_community_headline_row(
            conn, as_of_tournament_id=tid
        )
        php_headline = php_payload.get("headline") or {}
        for col in COMMUNITY_HEADLINE_COLUMNS:
            if not _values_match(py_headline.get(col), php_headline.get(col), col):
                errors.append(
                    f"PHP headline mismatch tournament_id={tid} col={col} "
                    f"py={py_headline.get(col)!r} php={php_headline.get(col)!r}"
                )
                break

        py_facts = _facts_map(build_community_facts_at_cutoff(conn, tid))
        php_facts = _facts_map(php_payload.get("facts") or [])
        if py_facts != php_facts:
            missing = set(py_facts) - set(php_facts)
            extra_facts = set(php_facts) - set(py_facts)
            mismatched = [
                k for k in py_facts if k in php_facts and py_facts[k] != php_facts[k]
            ]
            errors.append(
                f"PHP facts mismatch tournament_id={tid} "
                f"missing={len(missing)} extra={len(extra_facts)} mismatched={len(mismatched)}"
            )

    return errors


def _php_required() -> bool:
    return os.environ.get("AMIGA_REQUIRE_PHP", "").strip().lower() in ("1", "true", "yes")


def main(argv: list[str] | None = None) -> int:
    _ = argv
    php = _find_php()
    if php is None:
        if _php_required():
            print(
                "FAIL: PHP CLI not found (AMIGA_REQUIRE_PHP=1)",
                file=sys.stderr,
            )
            return 1
        print("SKIP: PHP CLI not found (Laragon path)", file=sys.stderr)
        return 0

    cfg = load_amiga_db_config()
    conn = _connect(cfg)
    try:
        errors = verify_php_community_parity(conn, php)
    finally:
        conn.close()

    if errors:
        for err in errors:
            print(f"FAIL: {err}", file=sys.stderr)
        return 1

    print(f"OK: PHP community build parity ({php.name})")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
