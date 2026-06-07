#!/usr/bin/env python3
"""Smoke checks: import manifest present and catalog overrides landed in MySQL."""

from __future__ import annotations

import json
import sys
from datetime import date
from pathlib import Path

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.import_corrections import TOURNAMENT_EVENT_DATE_OVERRIDES
from scripts.amiga.import_manifest import default_manifest_path

_REPO = Path(__file__).resolve().parents[2]


def main() -> int:
    errors: list[str] = []
    manifest_path = default_manifest_path(_REPO)

    if not manifest_path.is_file():
        errors.append(f"missing manifest: {manifest_path} (run import first)")
    else:
        manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
        if manifest.get("manifest_version") != 1:
            errors.append(f"unexpected manifest_version: {manifest.get('manifest_version')}")
        overrides = manifest.get("transforms", {}).get("catalog_overrides", [])
        for name, want in TOURNAMENT_EVENT_DATE_OVERRIDES.items():
            match = [o for o in overrides if o.get("tournament") == name]
            if not match:
                errors.append(f"manifest missing catalog override for {name!r}")

    cfg = load_amiga_db_config()
    conn = pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=DictCursor,
    )
    with conn.cursor() as cur:
        for name, want in TOURNAMENT_EVENT_DATE_OVERRIDES.items():
            cur.execute(
                "SELECT event_date FROM tournaments WHERE name = %s LIMIT 1",
                (name,),
            )
            row = cur.fetchone()
            if row is None:
                errors.append(f"tournaments row missing: {name!r}")
                continue
            got = row["event_date"]
            if isinstance(got, date) and got != want:
                errors.append(f"{name}: event_date={got}, want {want}")

    conn.close()

    if errors:
        for err in errors:
            print(f"FAIL: {err}", file=sys.stderr)
        return 1

    print(
        f"OK: import manifest + {len(TOURNAMENT_EVENT_DATE_OVERRIDES)} catalog override(s) in DB"
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
