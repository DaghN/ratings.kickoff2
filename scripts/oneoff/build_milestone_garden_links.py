#!/usr/bin/env python3
"""
Build data/milestone_garden_links.json and docs/milestones-garden-links.md.

Garden link profiles are UX-only (lockstep with milestone_garden_links.php).
Regenerate after catalog changes:
  python scripts/oneoff/build_milestone_garden_links.py
"""
from __future__ import annotations

import json
import sys
from pathlib import Path

_REPO = Path(__file__).resolve().parents[2]
if str(_REPO) not in sys.path:
    sys.path.insert(0, str(_REPO))

SEED = _REPO / "data" / "milestones_definitions_seed.json"
OUT_JSON = _REPO / "data" / "milestone_garden_links.json"
OUT_MD = _REPO / "docs" / "milestones-garden-links.md"

DEFAULT_LINK = "game"

LEAGUE_KEYS = frozenset(
    {
        "moment_of_glory",
        "activity_king",
        "league_daily_points_medal",
        "league_daily_activity_medal",
        "league_daily_activity_winner",
        "league_weekly_points_medal",
        "league_weekly_activity_medal",
        "league_weekly_points_winner",
        "league_weekly_activity_winner",
        "league_monthly_points_medal",
        "league_monthly_activity_medal",
        "league_monthly_points_winner",
        "league_yearly_points_medal",
        "league_yearly_activity_medal",
        "league_yearly_points_winner",
        "league_yearly_activity_winner",
        "league_wins_10",
        "league_wins_50",
        "league_wins_100",
        "league_wins_500",
    }
)

OVERRIDES: dict[str, dict[str, str]] = {
    "entered_arena": {
        "garden_link": "none",
        "notes": "Lobby registration; no game or league URL.",
    },
    "perfect_day": {
        "garden_link": "player_day_games",
        "notes": "All rated games that UTC day (W only, min 5). Link uses achieved_at day-close + player id.",
    },
    "nightmare_day": {
        "garden_link": "player_day_games",
        "notes": "All rated games that UTC day (L only, min 5). Link uses achieved_at day-close + player id.",
    },
}


def profile_for_key(key: str) -> tuple[str, str]:
    if key in OVERRIDES:
        o = OVERRIDES[key]
        return o["garden_link"], o.get("notes", "")
    if key in LEAGUE_KEYS:
        return "league", "Status league period (source_league_* on unlock row)."
    return DEFAULT_LINK, "Single game via source_game_id (ratedresults)."


def main() -> None:
    seed = json.loads(SEED.read_text(encoding="utf-8"))
    keys = sorted(d["milestone_key"] for d in seed["definitions"])
    entries: dict[str, dict[str, str]] = {}
    for key in keys:
        link, notes = profile_for_key(key)
        entries[key] = {"garden_link": link, "notes": notes}

    payload = {
        "version": seed.get("version", "2026-05-curated"),
        "default_garden_link": DEFAULT_LINK,
        "garden_link_values": [
            "game",
            "league",
            "none",
            "player_day_games",
        ],
        "keys": entries,
    }
    OUT_JSON.write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")

    lines = [
        "# Milestone garden links (living register)",
        "",
        "**Purpose:** Per-key **garden** deep-link behavior on `individual_milestones.php`. "
        "Indicative UX register — lockstep with "
        "`site/public_html/includes/milestone_garden_links.php` and "
        "`data/milestone_garden_links.json`.",
        "",
        "**Not** stored on `player_milestones`. DB `source_*` columns remain evidence for rebuild/post-game.",
        "",
        f"**Generated:** `python scripts/oneoff/build_milestone_garden_links.py` · "
        f"**Keys:** {len(keys)}",
        "",
        "| `milestone_key` | Garden link | Notes |",
        "|-----------------|-------------|-------|",
    ]
    for key in keys:
        e = entries[key]
        notes = e["notes"].replace("|", "\\|")
        lines.append(f"| `{key}` | `{e['garden_link']}` | {notes} |")
    lines.append("")
    OUT_MD.write_text("\n".join(lines), encoding="utf-8")
    print(f"Wrote {OUT_JSON} ({len(keys)} keys)")
    print(f"Wrote {OUT_MD}")


if __name__ == "__main__":
    main()
