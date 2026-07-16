"""Seed ko2amiga_work from day 0 L3 archive (W-1)."""

from __future__ import annotations

import json
import logging
import subprocess
from pathlib import Path

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.modern.constants import (
    DAY0_DIR,
    DAY0_L3_TABLES,
    DAY0_SCHEMA_PART,
    WORK_DB,
)
from scripts.amiga.modern.work_db import connect_work, ensure_work_database
from scripts.amiga.modern.clear_derived import clear_derived
from scripts.amiga.schema_bundles import _DERIVED_DROP_ORDER, apply_schema
from scripts.work_prepare.paths import find_mysql_exe

log = logging.getLogger(__name__)

# DDL bundles seed empty id=1 rows on these tables; allowed after W-1 (no replay yet).
_DERIVED_PLACEHOLDER_MAX: dict[str, int] = {
    "amiga_generalstats": 1,
    "amiga_community_stats": 1,
}


def _load_manifest(day0_dir: Path) -> dict[str, object]:
    manifest_path = day0_dir / "manifest.json"
    if not manifest_path.is_file():
        raise SystemExit(f"Missing day 0 manifest: {manifest_path}")
    return json.loads(manifest_path.read_text(encoding="utf-8"))


def _mysql_source_file(database: str, sql_path: Path) -> None:
    mysql_exe = find_mysql_exe()
    proc = subprocess.run(
        [str(mysql_exe), "-u", "root", database],
        stdin=sql_path.open("r", encoding="utf-8"),
        capture_output=True,
        text=True,
        encoding="utf-8",
        errors="replace",
    )
    if proc.returncode != 0:
        raise SystemExit(
            f"mysql import failed for {sql_path.name} ({proc.returncode}): "
            f"{proc.stderr.strip() or proc.stdout[:500]}"
        )


def _table_counts(conn: pymysql.connections.Connection, tables: tuple[str, ...]) -> dict[str, int]:
    counts: dict[str, int] = {}
    with conn.cursor() as cur:
        for table in tables:
            cur.execute(f"SELECT COUNT(*) AS n FROM `{table}`")
            counts[table] = int(cur.fetchone()["n"])
    return counts


def verify_work_seed(
    conn: pymysql.connections.Connection,
    *,
    expected: dict[str, int],
) -> dict[str, object]:
    """Exit gate for W-1: L3 parity + zero derived."""
    actual_l3 = _table_counts(conn, DAY0_L3_TABLES)
    mismatches: dict[str, dict[str, int]] = {}
    for table, want in expected.items():
        got = actual_l3.get(table, -1)
        if got != want:
            mismatches[table] = {"expected": want, "actual": got}

    derived_counts = _table_counts(conn, _DERIVED_DROP_ORDER)
    derived_bad: dict[str, int] = {}
    for table, count in derived_counts.items():
        allowed = _DERIVED_PLACEHOLDER_MAX.get(table, 0)
        if count > allowed:
            derived_bad[table] = count

    ok = not mismatches and not derived_bad
    return {
        "ok": ok,
        "l3_counts": actual_l3,
        "mismatches": mismatches,
        "derived_bad": derived_bad,
        "derived_counts": derived_counts,
    }


def seed_work_from_day0(
    *,
    day0_dir: Path = DAY0_DIR,
    skip_schema_part: bool = True,
    recreate: bool = True,
    destroy_work: bool = False,
    confirm_destroy: str | None = None,
) -> dict[str, object]:
    """Create ko2amiga_work, apply_schema, load day0 sql_parts, verify."""
    if recreate:
        from scripts.amiga.modern.work_safety import assert_safe_to_nuke_work

        assert_safe_to_nuke_work(
            operation="seed-work",
            cli_destroy_flag=destroy_work,
            confirm_phrase=confirm_destroy,
        )
    manifest = _load_manifest(day0_dir)
    version = manifest.get("version", "unknown")
    sql_parts = manifest.get("sql_parts")
    expected_tables = manifest.get("tables")
    if not isinstance(sql_parts, list) or not sql_parts:
        raise SystemExit("day0 manifest.sql_parts missing or empty")
    if not isinstance(expected_tables, dict):
        raise SystemExit("day0 manifest.tables missing")

    ensure_work_database()
    conn = connect_work()
    try:
        if recreate:
            apply_schema(conn, drop_existing=True)
        else:
            apply_schema(conn, drop_existing=False)

        loaded: list[str] = []
        for part in sql_parts:
            if not isinstance(part, str):
                continue
            if skip_schema_part and part == DAY0_SCHEMA_PART:
                log.info("seed-work: skip %s (apply_schema already applied)", part)
                continue
            sql_path = day0_dir / part
            if not sql_path.is_file():
                raise SystemExit(f"Missing day 0 SQL part: {sql_path}")
            log.info("seed-work: load %s", part)
            _mysql_source_file(WORK_DB, sql_path)
            loaded.append(part)

        clear_derived(conn, dry_run=False)

        verify_conn = connect_work()
        try:
            check = verify_work_seed(verify_conn, expected={k: int(v) for k, v in expected_tables.items()})
        finally:
            verify_conn.close()
    finally:
        conn.close()

    if not check["ok"]:
        raise SystemExit(
            "W-1 verify failed: "
            f"mismatches={check['mismatches']} derived_bad={check['derived_bad']}"
        )

    log.info(
        "seed-work OK: %s on %s — tournaments=%s players=%s games=%s",
        version,
        WORK_DB,
        check["l3_counts"]["tournaments"],
        check["l3_counts"]["amiga_players"],
        check["l3_counts"]["amiga_games"],
    )

    return {
        "version": version,
        "database": WORK_DB,
        "loaded_parts": loaded,
        "l3_counts": check["l3_counts"],
        "derived_rows": 0,
    }