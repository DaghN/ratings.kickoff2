"""P-1 parity — ko2amiga_work vs frozen ko2amiga_db oracle."""

from __future__ import annotations

import json
import logging
import subprocess
import time
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

import pymysql

from scripts.amiga.modern.constants import ORACLE_DB, PARITY_TABLES, WORK_DB
from scripts.amiga.modern.work_db import connect_oracle, connect_work

log = logging.getLogger(__name__)

_REPO = Path(__file__).resolve().parents[3]
_PARITY_LAST = _REPO / "data" / "amiga" / "modern" / "parity-last.json"

# Replay/L4 metadata — expected to differ between oracle prove run and work simul.
_VOLATILE_COLUMN_NAMES: frozenset[str] = frozenset({
    "rating_finalized_at",
    "last_finalized_at",
    "finalized_at",
    "created_at",
    "updated_at",
})

# Auto-increment allocators — business rows match on natural keys, not surrogate id.
_SURROGATE_ID_TABLES: frozenset[str] = frozenset({
    "amiga_tournament_standings",
})


def _git_head() -> str | None:
    try:
        proc = subprocess.run(
            ["git", "rev-parse", "HEAD"],
            cwd=_REPO,
            capture_output=True,
            text=True,
            check=False,
        )
        if proc.returncode == 0:
            return proc.stdout.strip() or None
    except OSError:
        pass
    return None


def _table_count(conn: pymysql.connections.Connection, table: str) -> int:
    with conn.cursor() as cur:
        cur.execute(f"SELECT COUNT(*) AS n FROM `{table}`")
        return int(cur.fetchone()["n"])


def _semantic_columns(conn: pymysql.connections.Connection, table: str) -> tuple[str, ...]:
    db = conn.db.decode() if isinstance(conn.db, bytes) else str(conn.db)
    skip = set(_VOLATILE_COLUMN_NAMES)
    if table in _SURROGATE_ID_TABLES:
        skip.add("id")
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT COLUMN_NAME AS name
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s
            ORDER BY ORDINAL_POSITION
            """,
            (db, table),
        )
        return tuple(
            str(row["name"])
            for row in cur.fetchall()
            if str(row["name"]) not in skip
        )


def _semantic_signature(conn: pymysql.connections.Connection, table: str) -> int | None:
    cols = _semantic_columns(conn, table)
    if not cols:
        return 0
    parts = ", ".join(f"COALESCE(CAST(`{c}` AS CHAR), '')" for c in cols)
    sql = (
        f"SELECT BIT_XOR(CAST(CRC32(CONCAT_WS('|', {parts})) AS UNSIGNED)) AS sig "
        f"FROM `{table}`"
    )
    with conn.cursor() as cur:
        cur.execute(sql)
        row = cur.fetchone()
    if not row:
        return None
    val = row.get("sig")
    return int(val) if val is not None else 0


def _generalstats_row(conn: pymysql.connections.Connection) -> dict[str, Any]:
    with conn.cursor() as cur:
        cur.execute("SELECT * FROM amiga_generalstats WHERE id = 1")
        row = cur.fetchone()
    return dict(row) if row else {}


def _community_stats_row(conn: pymysql.connections.Connection) -> dict[str, Any]:
    with conn.cursor() as cur:
        cur.execute("SELECT * FROM amiga_community_stats WHERE id = 1")
        row = cur.fetchone()
    return dict(row) if row else {}


def _compare_row_dicts(
    *,
    label: str,
    oracle: dict[str, Any],
    work: dict[str, Any],
    skip_cols: frozenset[str] = frozenset(),
) -> list[str]:
    errors: list[str] = []
    keys = sorted(set(oracle) | set(work))
    for key in keys:
        if key in skip_cols:
            continue
        o_val = oracle.get(key)
        w_val = work.get(key)
        if o_val != w_val:
            errors.append(f"{label}.{key}: oracle={o_val!r} work={w_val!r}")
    return errors


def run_parity(*, checksum: bool = True) -> dict[str, Any]:
    """Compare work post-simul to frozen oracle. Raises SystemExit on failure."""
    t0 = time.monotonic()
    started_utc = datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")

    oracle_conn = connect_oracle()
    work_conn = connect_work()
    errors: list[str] = []
    table_report: dict[str, dict[str, Any]] = {}

    try:
        for table in PARITY_TABLES:
            o_count = _table_count(oracle_conn, table)
            w_count = _table_count(work_conn, table)
            entry: dict[str, Any] = {
                "oracle_count": o_count,
                "work_count": w_count,
            }
            if o_count != w_count:
                errors.append(f"{table}: count oracle={o_count} work={w_count}")
            if checksum:
                o_sig = _semantic_signature(oracle_conn, table)
                w_sig = _semantic_signature(work_conn, table)
                entry["oracle_signature"] = o_sig
                entry["work_signature"] = w_sig
                if o_sig != w_sig:
                    errors.append(
                        f"{table}: signature oracle={o_sig} work={w_sig} (counts {o_count}/{w_count})"
                    )
            table_report[table] = entry

        gs_errors = _compare_row_dicts(
            label="amiga_generalstats",
            oracle=_generalstats_row(oracle_conn),
            work=_generalstats_row(work_conn),
        )
        errors.extend(gs_errors)

        cs_errors = _compare_row_dicts(
            label="amiga_community_stats",
            oracle=_community_stats_row(oracle_conn),
            work=_community_stats_row(work_conn),
        )
        errors.extend(cs_errors)

        with oracle_conn.cursor() as cur:
            cur.execute(
                "SELECT COUNT(*) AS n FROM tournaments WHERE rating_finalized = 1"
            )
            oracle_finalized = int(cur.fetchone()["n"])
        with work_conn.cursor() as cur:
            cur.execute(
                "SELECT COUNT(*) AS n FROM tournaments WHERE rating_finalized = 1"
            )
            work_finalized = int(cur.fetchone()["n"])
        if oracle_finalized != work_finalized:
            errors.append(
                f"tournaments.rating_finalized: oracle={oracle_finalized} work={work_finalized}"
            )
    finally:
        oracle_conn.close()
        work_conn.close()

    ok = not errors
    summary: dict[str, Any] = {
        "ok": ok,
        "oracle_database": ORACLE_DB,
        "work_database": WORK_DB,
        "started_utc": started_utc,
        "finished_utc": datetime.now(timezone.utc).isoformat().replace("+00:00", "Z"),
        "duration_sec": round(time.monotonic() - t0, 2),
        "git_head": _git_head(),
        "tables_checked": len(PARITY_TABLES),
        "checksum": checksum,
        "checksum_mode": "semantic_bitxor_crc32" if checksum else None,
        "volatile_columns_excluded": sorted(_VOLATILE_COLUMN_NAMES),
        "surrogate_id_tables": sorted(_SURROGATE_ID_TABLES),
        "tables": table_report,
        "tournaments_rating_finalized": {
            "oracle": oracle_finalized,
            "work": work_finalized,
        },
        "errors": errors,
    }

    _PARITY_LAST.parent.mkdir(parents=True, exist_ok=True)
    _PARITY_LAST.write_text(json.dumps(summary, indent=2) + "\n", encoding="utf-8")

    if ok:
        log.info(
            "parity OK: %s tables match %s (counts%s)",
            len(PARITY_TABLES),
            ORACLE_DB,
            " + semantic signature" if checksum else "",
        )
        log.info("parity report: %s", _PARITY_LAST)
    else:
        for err in errors[:20]:
            log.error("parity: %s", err)
        if len(errors) > 20:
            log.error("parity: ... and %s more", len(errors) - 20)
        raise SystemExit(f"P-1 parity failed ({len(errors)} mismatch(es)) — see {_PARITY_LAST}")

    return summary