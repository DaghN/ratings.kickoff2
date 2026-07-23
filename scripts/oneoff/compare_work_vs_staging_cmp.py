"""Compare ko2amiga_work vs ko2amiga_staging_cmp (semantic P-1 style). Does not mutate either DB."""
from __future__ import annotations

import json
import sys
from datetime import datetime, timezone
from pathlib import Path

import pymysql

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.modern.constants import PARITY_TABLES
from scripts.amiga.modern.parity import (
    _community_stats_row,
    _compare_row_dicts,
    _generalstats_row,
    _semantic_signature,
    _table_count,
    _VOLATILE_COLUMN_NAMES,
)

CMP_DB = "ko2amiga_staging_cmp"
WORK_DB = "ko2amiga_work"
OUT = Path("data/amiga/modern/work-vs-staging-cmp-last.json")


def _connect(database: str) -> pymysql.connections.Connection:
    cfg = load_amiga_db_config()
    return pymysql.connect(
        host=cfg.host,
        user=cfg.user,
        password=cfg.password,
        database=database,
        port=cfg.port,
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=True,
    )


def _tip(conn: pymysql.connections.Connection) -> dict:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT id, name, event_date, chrono, "
            "(SELECT COUNT(*) FROM amiga_games g WHERE g.tournament_id = t.id) AS games "
            "FROM tournaments t WHERE COALESCE(rating_finalized, 0) = 1 "
            "ORDER BY event_date DESC, chrono DESC, id DESC LIMIT 1"
        )
        row = cur.fetchone()
    return dict(row) if row else {}


def main() -> int:
    work = _connect(WORK_DB)
    cmp = _connect(CMP_DB)
    errors: list[str] = []
    table_report: dict = {}
    try:
        tip_w = _tip(work)
        tip_c = _tip(cmp)
        print(f"work tip: #{tip_w.get('id')} {tip_w.get('name')} games={tip_w.get('games')}")
        print(f"cmp  tip: #{tip_c.get('id')} {tip_c.get('name')} games={tip_c.get('games')}")
        if tip_w.get("id") != tip_c.get("id"):
            errors.append(f"tip id work={tip_w.get('id')} cmp={tip_c.get('id')}")

        for table in PARITY_TABLES:
            w_count = _table_count(work, table)
            c_count = _table_count(cmp, table)
            entry = {"work_count": w_count, "cmp_count": c_count}
            if w_count != c_count:
                errors.append(f"{table}: count work={w_count} cmp={c_count}")
            w_sig = _semantic_signature(work, table)
            c_sig = _semantic_signature(cmp, table)
            entry["work_signature"] = w_sig
            entry["cmp_signature"] = c_sig
            if w_sig != c_sig:
                errors.append(f"{table}: signature mismatch work={w_sig} cmp={c_sig}")
            table_report[table] = entry
            status = "OK" if w_count == c_count and w_sig == c_sig else "DIFF"
            print(f"  [{status}] {table}: n={w_count}/{c_count} sig={w_sig}/{c_sig}")

        errors.extend(
            _compare_row_dicts(
                label="amiga_generalstats",
                oracle=_generalstats_row(cmp),
                work=_generalstats_row(work),
                skip_cols=_VOLATILE_COLUMN_NAMES,
            )
        )
        errors.extend(
            _compare_row_dicts(
                label="amiga_community_stats",
                oracle=_community_stats_row(cmp),
                work=_community_stats_row(work),
                skip_cols=_VOLATILE_COLUMN_NAMES,
            )
        )
    finally:
        work.close()
        cmp.close()

    report = {
        "started_utc": datetime.now(timezone.utc).isoformat().replace("+00:00", "Z"),
        "work_database": WORK_DB,
        "cmp_database": CMP_DB,
        "tip_work": tip_w,
        "tip_cmp": tip_c,
        "ok": not errors,
        "errors": errors,
        "tables": table_report,
    }
    OUT.parent.mkdir(parents=True, exist_ok=True)
    OUT.write_text(json.dumps(report, indent=2, default=str) + "\n", encoding="utf-8")
    print(f"\nReport: {OUT}")
    if errors:
        print(f"FAIL ({len(errors)} errors)")
        for e in errors[:40]:
            print(f"  - {e}")
        if len(errors) > 40:
            print(f"  ... +{len(errors) - 40} more")
        return 1
    print("PASS — work matches staging_cmp (semantic P-1 scope)")
    return 0


if __name__ == "__main__":
    sys.exit(main())