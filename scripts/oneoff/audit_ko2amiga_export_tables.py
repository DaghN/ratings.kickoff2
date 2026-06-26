#!/usr/bin/env python3
"""Audit ko2amiga_db tables vs staging export script (export_ko2amiga_db.ps1)."""

from __future__ import annotations

import re
import subprocess
import sys
from pathlib import Path

_REPO = Path(__file__).resolve().parents[2]
if str(_REPO) not in sys.path:
    sys.path.insert(0, str(_REPO))
_EXPORT_PS1 = _REPO / "scripts" / "export_ko2amiga_db.ps1"

# Active product tables (docs/amiga-data-contract.md register, Jun 2026).
ACTIVE: frozenset[str] = frozenset(
    {
        "tournament_format_templates",
        "tournaments",
        "tournament_entrants",
        "tournament_stages",
        "tournament_stage_players",
        "tournament_fixtures",
        "amiga_players",
        "amiga_games",
        "amiga_game_ratings",
        "amiga_player_event_snapshots",
        "amiga_player_current",
        "amiga_player_elo_rank_at_event",
        "amiga_player_matchup_at_event",
        "amiga_player_matchup_summary",
        "amiga_tournament_standings",
        "amiga_tournament_catalog_stats",
        "amiga_generalstats",
        "amiga_realm_snapshots",
        "amiga_community_stats",
        "amiga_community_stats_snapshots",
        "amiga_community_stat_facts",
        "amiga_world_cup_stats",
        "amiga_tournament_finish_override",
        "amiga_player_slice_totals",
        "amiga_player_slice_at_event",
        "amiga_country_slice_totals",
        "amiga_country_slice_at_event",
    }
)

RETIRED: frozenset[str] = frozenset(
    {
        "amiga_player_stats",
        "amiga_player_tournament_participation",
        "amiga_player_tournament_totals",
        "amiga_rating_events",
    }
)

# Recent slices (Jun 2026) — spot-check export inclusion explicitly.
RECENT_SLICES: dict[str, str] = {
    "033_player_slice": "amiga_player_slice_totals, amiga_player_slice_at_event",
    "037_world_cup_stats": "amiga_world_cup_stats",
    "039_player_slice_v2": "(ALTER only — columns on player slice tables)",
    "040_country_slice": "amiga_country_slice_totals, amiga_country_slice_at_event",
    "041_peak_elo_rank": "(ALTER only — peak_elo_rank on elo_rank + current)",
    "042_peak_rating_tournament_id": "(ALTER only — on snapshots + current)",
    "043_drop_rating_game_ids": "(DROP columns only)",
    "034–036_community_stats": "amiga_community_stats, amiga_community_stats_snapshots, amiga_community_stat_facts",
}


def _parse_export_tables() -> set[str]:
    text = _EXPORT_PS1.read_text(encoding="utf-8")
    m = re.search(r"\$Tables = @\((.*?)\)", text, re.S)
    if not m:
        raise SystemExit("Could not parse $Tables from export_ko2amiga_db.ps1")
    return set(re.findall(r"'([^']+)'", m.group(1)))


def _db_tables_with_counts() -> dict[str, int]:
    mysql = Path(r"C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe")
    if not mysql.is_file():
        raise SystemExit(f"mysql not found: {mysql}")
    out = subprocess.check_output(
        [
            str(mysql),
            "-u",
            "root",
            "-N",
            "-B",
            "-e",
            "SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES "
            "WHERE TABLE_SCHEMA = 'ko2amiga_db' ORDER BY TABLE_NAME",
        ],
        text=True,
    )
    rows: dict[str, int] = {}
    for line in out.strip().splitlines():
        name, count = line.split("\t")
        rows[name] = int(count)
    return rows


def main() -> int:
    export = _parse_export_tables()
    db = _db_tables_with_counts()

    print("=== Staging export audit (ko2amiga_db) ===\n")

    missing_active = sorted(ACTIVE - export)
    print("Active tables MISSING from export:")
    if missing_active:
        for t in missing_active:
            print(f"  FAIL  {t}  (rows local: {db.get(t, 'n/a')})")
    else:
        print("  OK — none")

    extra_export = sorted(export - ACTIVE - RETIRED)
    print("\nIn export but not in active register (unexpected):")
    if extra_export:
        for t in extra_export:
            print(f"  WARN  {t}")
    else:
        print("  OK — none")

    print("\nRetired tables in DB (should stay out of export):")
    for t in sorted(RETIRED & db.keys()):
        status = "OK excluded" if t not in export else "FAIL still exported"
        print(f"  {status}  {t}  (rows: {db[t]})")

    unknown_db = sorted(set(db) - ACTIVE - RETIRED)
    if unknown_db:
        print("\nDB tables not in active/retired register:")
        for t in unknown_db:
            in_exp = "exported" if t in export else "NOT exported"
            print(f"  REVIEW  {t}  rows={db[t]}  ({in_exp})")

    print("\n=== Recent slice checklist (Jun 2026) ===")
    for label, desc in RECENT_SLICES.items():
        if desc.startswith("("):
            print(f"  {label}: {desc}")
            continue
        tables = [x.strip() for x in desc.split(",")]
        ok = all(t in export for t in tables)
        mark = "OK" if ok else "FAIL"
        print(f"  {mark}  {label}: {desc}")

    print("\n=== Local row counts — exported tables with 0 rows (staging risk) ===")
    zero_risk = []
    for t in sorted(ACTIVE & export):
        n = db.get(t, -1)
        if n == 0:
            zero_risk.append(t)
    if zero_risk:
        for t in zero_risk:
            print(f"  WARN  {t} — exported but empty locally")
    else:
        print("  OK — all exported active tables have rows locally")

    print("\n=== export_packs.py L5_TABLES drift (community pack, not browser import) ===")
    from scripts.amiga.export_packs import L5_TABLES

    derived_export = export - {
        "tournament_format_templates",
        "tournaments",
        "amiga_players",
        "tournament_entrants",
        "tournament_stages",
        "tournament_stage_players",
        "tournament_fixtures",
        "amiga_games",
        "amiga_tournament_finish_override",
    }
    l5 = set(L5_TABLES)
    print("  In staging export, missing from L5_TABLES:", sorted(derived_export - l5))
    print("  In L5_TABLES, missing from staging export:", sorted(l5 - derived_export))

    return 1 if missing_active else 0


if __name__ == "__main__":
    sys.exit(main())
