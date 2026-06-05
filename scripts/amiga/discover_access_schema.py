#!/usr/bin/env python3
"""
Phase A0 — inventory Microsoft Access Amiga source (koatd.mdb).

Usage (repo root):
  python scripts/amiga/discover_access_schema.py
  python scripts/amiga/discover_access_schema.py --mdb path/to/file.mdb
"""
from __future__ import annotations

import argparse
import json
import sys
from datetime import date, datetime
from decimal import Decimal
from pathlib import Path

import pyodbc

_REPO = Path(__file__).resolve().parents[2]
_DEFAULT_MDB = _REPO / "data" / "amiga" / "source" / "koatd.mdb"
_DEFAULT_OUT = _REPO / "data" / "amiga" / "exports"


def _json_default(obj: object) -> str:
    if isinstance(obj, (datetime, date)):
        return obj.isoformat(sep=" ", timespec="seconds")
    if isinstance(obj, Decimal):
        return str(obj)
    if isinstance(obj, bytes):
        return obj.hex()
    return str(obj)


def connect_mdb(path: Path) -> pyodbc.Connection:
    if not path.is_file():
        raise FileNotFoundError(path)
    conn_str = f"DRIVER={{Microsoft Access Driver (*.mdb, *.accdb)}};DBQ={path.resolve()};"
    return pyodbc.connect(conn_str)


def list_user_tables(cur: pyodbc.Cursor) -> list[str]:
    names: list[str] = []
    for row in cur.tables(tableType="TABLE"):
        name = row.table_name
        if name and not name.startswith("MSys"):
            names.append(name)
    return sorted(names, key=str.lower)


def table_columns(cur: pyodbc.Cursor, table: str) -> list[dict[str, str | int | bool | None]]:
    cols: list[dict[str, str | int | bool | None]] = []
    for row in cur.columns(table=table):
        cols.append(
            {
                "name": row.column_name,
                "type": row.type_name,
                "size": row.column_size,
                "nullable": bool(row.nullable),
            }
        )
    return cols


def table_row_count(cur: pyodbc.Cursor, table: str) -> int:
    cur.execute(f"SELECT COUNT(*) FROM [{table}]")
    row = cur.fetchone()
    return int(row[0]) if row else 0


def sample_rows(cur: pyodbc.Cursor, table: str, limit: int = 3) -> list[dict[str, object]]:
    cur.execute(f"SELECT TOP {int(limit)} * FROM [{table}]")
    col_names = [d[0] for d in cur.description]
    out: list[dict[str, object]] = []
    for row in cur.fetchall():
        out.append({col_names[i]: row[i] for i in range(len(col_names))})
    return out


def discover(mdb: Path, sample_limit: int) -> dict[str, object]:
    conn = connect_mdb(mdb)
    cur = conn.cursor()
    tables = list_user_tables(cur)
    inventory: dict[str, object] = {
        "source_file": str(mdb.resolve()),
        "source_bytes": mdb.stat().st_size,
        "table_count": len(tables),
        "tables": {},
    }
    for table in tables:
        cols = table_columns(cur, table)
        try:
            count = table_row_count(cur, table)
        except pyodbc.Error as exc:
            count = None
            count_error = str(exc)
        else:
            count_error = None
        entry: dict[str, object] = {
            "row_count": count,
            "columns": cols,
        }
        if count_error:
            entry["row_count_error"] = count_error
        if count and count > 0:
            try:
                entry["sample_rows"] = sample_rows(cur, table, sample_limit)
            except pyodbc.Error as exc:
                entry["sample_error"] = str(exc)
        inventory["tables"][table] = entry
    conn.close()
    return inventory


def main() -> int:
    parser = argparse.ArgumentParser(description="Inventory koatd.mdb (Amiga Access source)")
    parser.add_argument("--mdb", type=Path, default=_DEFAULT_MDB, help="Path to .mdb/.accdb")
    parser.add_argument("--out-dir", type=Path, default=_DEFAULT_OUT, help="JSON export directory")
    parser.add_argument("--sample", type=int, default=3, help="Sample rows per non-empty table")
    args = parser.parse_args()

    inventory = discover(args.mdb, args.sample)
    args.out_dir.mkdir(parents=True, exist_ok=True)
    out_path = args.out_dir / "schema_inventory.json"
    out_path.write_text(
        json.dumps(inventory, indent=2, default=_json_default, ensure_ascii=False) + "\n",
        encoding="utf-8",
    )

    tables = inventory["tables"]
    print(f"Wrote {out_path}")
    print(f"Tables: {inventory['table_count']}")
    for name, meta in sorted(tables.items(), key=lambda kv: (-(kv[1].get('row_count') or 0), kv[0].lower())):
        rc = meta.get("row_count")
        ncol = len(meta.get("columns", []))
        print(f"  {rc:>6} rows  {ncol:>2} cols  {name}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
