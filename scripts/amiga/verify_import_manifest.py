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
from scripts.amiga.import_corrections import (
    SUPPLEMENTAL_SCORES,
    TOURNAMENT_EVENT_DATE_OVERRIDES,
    TOURNAMENT_NAME_OVERRIDES,
    WORLD_CUP_VENUES,
    catalog_name_after_corrections,
    supplemental_scores_manifest,
    world_cup_catalog_name,
)
from scripts.amiga.import_manifest import default_manifest_path

_REPO = Path(__file__).resolve().parents[2]


def main() -> int:
    errors: list[str] = []
    manifest_path = default_manifest_path(_REPO)
    expected_override_count = (
        len(TOURNAMENT_EVENT_DATE_OVERRIDES)
        + len(TOURNAMENT_NAME_OVERRIDES)
        + len(WORLD_CUP_VENUES) * 2
    )

    if not manifest_path.is_file():
        errors.append(f"missing manifest: {manifest_path} (run import first)")
    else:
        manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
        if manifest.get("manifest_version") != 1:
            errors.append(f"unexpected manifest_version: {manifest.get('manifest_version')}")
        overrides = manifest.get("transforms", {}).get("catalog_overrides", [])
        for access_name, canonical_name in TOURNAMENT_NAME_OVERRIDES.items():
            match = [
                o
                for o in overrides
                if o.get("tournament") == access_name and o.get("field") == "name"
            ]
            if not match:
                errors.append(f"manifest missing name override for {access_name!r}")
            elif match[0].get("canonical") != canonical_name:
                errors.append(
                    f"manifest name override {access_name!r}: canonical={match[0].get('canonical')!r}"
                )
        for name, want in TOURNAMENT_EVENT_DATE_OVERRIDES.items():
            match = [
                o for o in overrides if o.get("tournament") == name and o.get("field") == "event_date"
            ]
            if not match:
                errors.append(f"manifest missing event_date override for {name!r}")

        supplements = manifest.get("transforms", {}).get("score_supplements", [])
        for entry in supplemental_scores_manifest():
            name = str(entry["tournament"])
            want_count = int(entry["games_added"])
            match = [s for s in supplements if s.get("tournament") == name]
            if not match:
                errors.append(f"manifest missing score_supplements entry for {name!r}")
            elif int(match[0].get("games_added", 0)) != want_count:
                errors.append(
                    f"manifest score_supplements {name!r}: games_added={match[0].get('games_added')!r}, want {want_count}"
                )

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
        for access_name, canonical_name in TOURNAMENT_NAME_OVERRIDES.items():
            want_name = catalog_name_after_corrections(access_name)
            cur.execute("SELECT id FROM tournaments WHERE name = %s LIMIT 1", (want_name,))
            if cur.fetchone() is None:
                errors.append(f"tournaments row missing canonical name: {want_name!r}")
            cur.execute("SELECT id FROM tournaments WHERE name = %s LIMIT 1", (access_name,))
            if cur.fetchone() is not None:
                errors.append(f"tournaments still has Access name: {access_name!r}")

        for base_name, (_city, want_country) in WORLD_CUP_VENUES.items():
            want_name = world_cup_catalog_name(base_name)
            cur.execute(
                "SELECT country FROM tournaments WHERE name = %s LIMIT 1",
                (want_name,),
            )
            row = cur.fetchone()
            if row is None:
                errors.append(f"tournaments row missing World Cup: {want_name!r}")
            elif str(row["country"]) != want_country:
                errors.append(f"{want_name}: country={row['country']!r}, want {want_country!r}")

        for name, want in TOURNAMENT_EVENT_DATE_OVERRIDES.items():
            lookup_name = catalog_name_after_corrections(name)
            cur.execute(
                "SELECT event_date FROM tournaments WHERE name = %s LIMIT 1",
                (lookup_name,),
            )
            row = cur.fetchone()
            if row is None:
                errors.append(f"tournaments row missing: {name!r}")
                continue
            got = row["event_date"]
            if isinstance(got, date) and got != want:
                errors.append(f"{name}: event_date={got}, want {want}")

        by_tournament: dict[str, int] = {}
        for row in SUPPLEMENTAL_SCORES:
            by_tournament[row.tournament] = by_tournament.get(row.tournament, 0) + 1
        for name, want_count in sorted(by_tournament.items()):
            cur.execute(
                """
                SELECT COUNT(g.id) AS n
                FROM amiga_games g
                INNER JOIN tournaments t ON t.id = g.tournament_id
                WHERE t.name = %s
                """,
                (name,),
            )
            row = cur.fetchone()
            got = int(row["n"]) if row else 0
            if got != want_count:
                errors.append(f"{name}: {got} games in DB, want {want_count} from supplemental import")

    conn.close()

    if errors:
        for err in errors:
            print(f"FAIL: {err}", file=sys.stderr)
        return 1

    print(f"OK: import manifest + {expected_override_count} catalog override(s) in DB")
    return 0


if __name__ == "__main__":
    sys.exit(main())
