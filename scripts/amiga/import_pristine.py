#!/usr/bin/env python3
"""L1 pristine mirror — mechanical export of all koatd.mdb tables to SQL."""

from __future__ import annotations

import json
import logging
from datetime import date, datetime, timezone
from decimal import Decimal
from pathlib import Path

import pyodbc

from scripts.amiga.discover_access_schema import (
    connect_mdb,
    list_user_tables,
    table_columns,
    table_row_count,
)

log = logging.getLogger(__name__)

_REPO = Path(__file__).resolve().parents[2]
_DEFAULT_MDB = _REPO / "data" / "amiga" / "source" / "koatd.mdb"
_DEFAULT_OUT = _REPO / "data" / "amiga" / "exports" / "pristine"


def _quote_ident(name: str) -> str:
    return "`" + name.replace("`", "``") + "`"


def _access_type_to_mysql(type_name: str | None, size: int | None) -> str:
    t = (type_name or "").upper()
    if "COUNTER" in t or t in ("INTEGER", "LONG", "INT"):
        return "int"
    if "DOUBLE" in t or "REAL" in t or "SINGLE" in t or "FLOAT" in t:
        return "double"
    if "DATETIME" in t:
        return "datetime"
    if t == "DATE":
        return "date"
    if "BIT" in t or "YESNO" in t:
        return "tinyint(1)"
    if "MEMO" in t or "LONGCHAR" in t or "OLE" in t or "BINARY" in t:
        return "longtext"
    if "TEXT" in t and (size or 0) > 255:
        return "text"
    n = int(size or 255)
    if n <= 0:
        n = 255
    if n > 16383:
        return "text"
    return f"varchar({n})"


def _sql_literal(value: object) -> str:
    if value is None:
        return "NULL"
    if isinstance(value, bool):
        return "1" if value else "0"
    if isinstance(value, int):
        return str(value)
    if isinstance(value, float):
        return repr(value)
    if isinstance(value, Decimal):
        return format(value, "f")
    if isinstance(value, datetime):
        return "'" + value.strftime("%Y-%m-%d %H:%M:%S") + "'"
    if isinstance(value, date):
        return "'" + value.isoformat() + "'"
    if isinstance(value, bytes):
        return "X'" + value.hex() + "'"
    text = str(value).replace("\\", "\\\\").replace("'", "''")
    return f"'{text}'"


def _create_table_sql(table: str, columns: list[dict[str, object]]) -> str:
    col_defs: list[str] = []
    for col in columns:
        name = str(col["name"])
        mysql_type = _access_type_to_mysql(
            str(col["type"]) if col.get("type") is not None else None,
            int(col["size"]) if col.get("size") is not None else None,
        )
        null_sql = "NULL" if col.get("nullable") else "NOT NULL"
        col_defs.append(f"  {_quote_ident(name)} {mysql_type} {null_sql}")
    body = ",\n".join(col_defs)
    return f"CREATE TABLE IF NOT EXISTS {_quote_ident(table)} (\n{body}\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;"


def _fetch_all_rows(cur: pyodbc.Cursor, table: str) -> tuple[list[str], list[tuple[object, ...]]]:
    cur.execute(f"SELECT * FROM [{table}]")
    col_names = [d[0] for d in cur.description]
    return col_names, list(cur.fetchall())


def _insert_batches(
    table: str,
    col_names: list[str],
    rows: list[tuple[object, ...]],
    *,
    batch_size: int = 200,
) -> list[str]:
    if not rows:
        return []
    cols = ", ".join(_quote_ident(c) for c in col_names)
    table_q = _quote_ident(table)
    stmts: list[str] = []
    for i in range(0, len(rows), batch_size):
        chunk = rows[i : i + batch_size]
        values_sql: list[str] = []
        for row in chunk:
            vals = ", ".join(_sql_literal(row[j]) for j in range(len(col_names)))
            values_sql.append(f"({vals})")
        stmts.append(
            f"INSERT INTO {table_q} ({cols}) VALUES\n  " + ",\n  ".join(values_sql) + ";"
        )
    return stmts


def export_pristine_mirror(
    *,
    mdb: Path,
    out_dir: Path,
    sql_name: str = "L1_mirror.sql",
    batch_size: int = 200,
) -> dict[str, object]:
    """Export all Access user tables to SQL + pristine_manifest.json."""
    if not mdb.is_file():
        raise FileNotFoundError(mdb)

    out_dir.mkdir(parents=True, exist_ok=True)
    sql_path = out_dir / sql_name
    manifest_path = out_dir / "pristine_manifest.json"

    conn = connect_mdb(mdb)
    cur = conn.cursor()
    tables = list_user_tables(cur)

    sql_parts: list[str] = [
        "-- L1 pristine mirror — mechanical koatd.mdb export (no corrections).",
        "-- Policy: docs/amiga-ground-layers-policy.md §4 L1",
        f"-- Source: {mdb.resolve()}",
        "SET NAMES utf8mb4;",
        "SET FOREIGN_KEY_CHECKS = 0;",
    ]

    table_stats: dict[str, dict[str, object]] = {}

    for table in tables:
        columns = table_columns(cur, table)
        expected = table_row_count(cur, table)
        log.info("L1 export %s (%s rows)", table, expected)

        sql_parts.append(f"\n-- table: {table}")
        sql_parts.append(f"DROP TABLE IF EXISTS {_quote_ident(table)};")
        sql_parts.append(_create_table_sql(table, columns))

        col_names, rows = _fetch_all_rows(cur, table)
        if len(rows) != expected:
            raise RuntimeError(
                f"Row count drift on {table!r}: COUNT(*)={expected}, SELECT *={len(rows)}"
            )
        sql_parts.extend(_insert_batches(table, col_names, rows, batch_size=batch_size))

        table_stats[table] = {
            "rows": expected,
            "columns": len(columns),
            "column_names": [str(c["name"]) for c in columns],
        }

    sql_parts.append("SET FOREIGN_KEY_CHECKS = 1;")
    sql_text = "\n".join(sql_parts) + "\n"
    sql_path.write_text(sql_text, encoding="utf-8")

    stat = mdb.stat()
    manifest: dict[str, object] = {
        "layer": "L1",
        "description": "Full mechanical Access mirror — all user tables, zero transforms",
        "source": {
            "mdb": str(mdb.resolve()),
            "bytes": stat.st_size,
            "modified_utc": datetime.fromtimestamp(stat.st_mtime, tz=timezone.utc)
            .isoformat()
            .replace("+00:00", "Z"),
        },
        "exported_at_utc": datetime.now(timezone.utc).isoformat().replace("+00:00", "Z"),
        "sql_file": sql_name,
        "sql_bytes": sql_path.stat().st_size,
        "table_count": len(tables),
        "tables": table_stats,
        "conventions": {
            "corrections": False,
            "name_merges": False,
            "supplements": False,
            "synthetic_game_date": False,
        },
    }
    manifest_path.write_text(
        json.dumps(manifest, indent=2, ensure_ascii=False) + "\n",
        encoding="utf-8",
    )
    conn.close()

    return {
        "tables": len(tables),
        "rows_total": sum(int(t["rows"]) for t in table_stats.values()),
        "sql_path": str(sql_path),
        "manifest_path": str(manifest_path),
        "table_stats": table_stats,
    }


def verify_pristine_manifest(mdb: Path, manifest_path: Path) -> list[str]:
    """Return list of mismatch messages (empty = OK)."""
    if not manifest_path.is_file():
        return [f"manifest missing: {manifest_path}"]
    manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    expected_tables: dict[str, dict[str, object]] = manifest.get("tables", {})

    conn = connect_mdb(mdb)
    cur = conn.cursor()
    live_tables = set(list_user_tables(cur))
    errors: list[str] = []

    if set(expected_tables) != live_tables:
        missing = live_tables - set(expected_tables)
        extra = set(expected_tables) - live_tables
        if missing:
            errors.append(f"manifest missing tables: {sorted(missing)}")
        if extra:
            errors.append(f"manifest stale tables: {sorted(extra)}")

    for table, meta in sorted(expected_tables.items()):
        if table not in live_tables:
            continue
        want = int(meta["rows"])
        got = table_row_count(cur, table)
        if got != want:
            errors.append(f"{table}: manifest rows={want}, Access COUNT(*)={got}")

    conn.close()
    return errors


def run_import_pristine(
    *,
    mdb: Path,
    out_dir: Path,
    verify: bool = True,
) -> dict[str, object]:
    stats = export_pristine_mirror(mdb=mdb, out_dir=out_dir)
    if verify:
        errors = verify_pristine_manifest(mdb, Path(stats["manifest_path"]))
        if errors:
            raise SystemExit("L1 pristine verify failed:\n  " + "\n  ".join(errors))
    return stats
