#!/usr/bin/env python3
"""Reference report: derived WC medals vs Access added_players (parity tooling only)."""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

import pymysql
import pyodbc
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.import_access import _DEFAULT_MDB, connect_access
from scripts.amiga.player_names import normalize_display_name

_SAMPLE_LIMIT = 20


def _connect_mysql() -> pymysql.connections.Connection:
    cfg = load_amiga_db_config()
    return pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=DictCursor,
    )


def _player_key(name: str) -> str:
    return normalize_display_name(str(name))


def load_derived_top(conn: pymysql.connections.Connection, limit: int) -> list[dict]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT ap.name,
                   t.wc_gold,
                   t.wc_silver,
                   t.wc_bronze,
                   (t.wc_gold + t.wc_silver + t.wc_bronze) AS wc_total
            FROM amiga_player_tournament_totals t
            INNER JOIN amiga_players ap ON ap.id = t.player_id
            WHERE (t.wc_gold + t.wc_silver + t.wc_bronze) > 0
            ORDER BY t.wc_gold DESC, t.wc_silver DESC, t.wc_bronze DESC, ap.name ASC
            LIMIT %s
            """,
            (limit,),
        )
        return list(cur.fetchall())


def load_access_medals(cur: pyodbc.Cursor) -> dict[str, dict]:
    cur.execute(
        """
        SELECT name, goldmedals, silvermedals, bronzemedals
        FROM added_players
        WHERE goldmedals > 0 OR silvermedals > 0 OR bronzemedals > 0
        """
    )
    cols = [d[0] for d in cur.description]
    idx = {name: cols.index(name) for name in cols}
    by_key: dict[str, dict] = {}
    for row in cur.fetchall():
        name = str(row[idx["name"]])
        by_key[_player_key(name)] = {
            "name": name,
            "wc_gold": int(row[idx["goldmedals"]] or 0),
            "wc_silver": int(row[idx["silvermedals"]] or 0),
            "wc_bronze": int(row[idx["bronzemedals"]] or 0),
        }
    return by_key


def run_honours_parity_sample(*, mdb: Path, limit: int = _SAMPLE_LIMIT) -> int:
    mysql = _connect_mysql()
    try:
        derived_rows = load_derived_top(mysql, limit)
    finally:
        mysql.close()

    access = connect_access(mdb)
    try:
        access_by_key = load_access_medals(access.cursor())
    finally:
        access.close()

    print(f"WC medal parity sample (top {limit} derived holders; reference only)")
    print(f"{'Player':<24} {'Drv G/S/B':>12} {'Acc G/S/B':>12} {'Match':>5}")
    mismatches = 0
    for row in derived_rows:
        key = _player_key(str(row["name"]))
        access_row = access_by_key.get(key)
        drv = (int(row["wc_gold"]), int(row["wc_silver"]), int(row["wc_bronze"]))
        if access_row is None:
            acc = ("—", "—", "—")
            match = "MISS"
            mismatches += 1
        else:
            acc = (
                int(access_row["wc_gold"]),
                int(access_row["wc_silver"]),
                int(access_row["wc_bronze"]),
            )
            match = "OK" if drv == acc else "DIFF"
            if match != "OK":
                mismatches += 1
        print(
            f"{str(row['name'])[:24]:<24} "
            f"{drv[0]:>2}/{drv[1]:>2}/{drv[2]:>2} "
            f"{str(acc[0]):>4}/{str(acc[1]):>4}/{str(acc[2]):>4} "
            f"{match:>5}"
        )

    print(f"\nRows compared: {len(derived_rows)}; mismatches or missing Access row: {mismatches}")
    print("Report only — not a ship blocker.")
    return 0


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="Compare derived WC medals to Access added_players (reference report)",
    )
    parser.add_argument("--mdb", type=Path, default=_DEFAULT_MDB)
    parser.add_argument("--limit", type=int, default=_SAMPLE_LIMIT)
    args = parser.parse_args(argv)
    try:
        return run_honours_parity_sample(mdb=args.mdb, limit=args.limit)
    except OSError as exc:
        print(f"honours-parity-sample failed: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
