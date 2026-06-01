#!/usr/bin/env python3
"""
Build milestone catalog + unlock-event register (lockstep).

Outputs:
  - data/milestone_garden_links.json
  - docs/milestones-catalog.md          (master per-key table)
  - docs/milestones-garden-links.md     (Link + Event index)

Regenerate after catalog or unlock-event edits:
  python scripts/oneoff/build_milestone_garden_links.py

See docs/milestones-README.md · docs/milestones-unlock-event-ui.md
"""
from __future__ import annotations

import json
import sys
from collections import Counter
from pathlib import Path

_REPO = Path(__file__).resolve().parents[2]
if str(_REPO) not in sys.path:
    sys.path.insert(0, str(_REPO))

SEED = _REPO / "data" / "milestones_definitions_seed.json"
OUT_JSON = _REPO / "data" / "milestone_garden_links.json"
OUT_CATALOG_MD = _REPO / "docs" / "milestones-catalog.md"
OUT_GARDEN_MD = _REPO / "docs" / "milestones-garden-links.md"

DEFAULT_LINK = "game"

LINK_VALUES = ("game", "league", "none", "player_day_games")

CONTEXT_VALUES = (
    "match_line",
    "league_period",
    "day_games",
    "lobby_copy",
    "none",
)

DEFAULT_CONTEXT_BY_LINK = {
    "game": "match_line",
    "league": "league_period",
    "player_day_games": "day_games",
    "none": "none",
}

LINK_UI_LABEL = {
    "game": "Game",
    "league": "League",
    "player_day_games": "Games",
    "none": "—",
}

CONTEXT_DESCRIBE_DEFAULT = {
    "match_line": "Scoreline (anchor game)",
    "league_period": "League period label",
    "day_games": "Day games summary",
    "lobby_copy": "Joined the ladder",
    "none": "—",
}

TIER_ORDER = ("legendary", "accomplished", "dedicated", "aspirational")

TIER_LABEL = {
    "legendary": "Legendary",
    "accomplished": "Accomplished",
    "dedicated": "Dedicated",
    "aspirational": "Aspirational",
}

TIER_CHART_TOKEN = {
    "legendary": "holo",
    "accomplished": "amber",
    "dedicated": "chrome",
    "aspirational": "pitch",
}

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
        "event_link": "none",
        "event_context": "lobby_copy",
        "notes": "Lobby registration; no game or league URL.",
    },
    "perfect_day": {
        "event_link": "player_day_games",
        "event_context": "day_games",
        "event_context_label": "All wins that UTC day (5+ rated games).",
        "notes": "All rated games that UTC day (W only, min 5). Link uses achieved_at day-close + player id.",
    },
    "nightmare_day": {
        "event_link": "player_day_games",
        "event_context": "day_games",
        "event_context_label": "Nightmare day — all losses (5+ rated games that UTC day)",
        "notes": "All rated games that UTC day (L only, min 5). Link uses achieved_at day-close + player id.",
    },
}


def md_cell(value: str) -> str:
    return value.replace("|", "\\|").replace("\n", " ")


def profile_for_key(key: str) -> tuple[str, str, str, str]:
    if key in OVERRIDES:
        o = OVERRIDES[key]
        link = o["event_link"]
        context = o.get("event_context", DEFAULT_CONTEXT_BY_LINK[link])
        return link, context, o.get("notes", ""), o.get("event_context_label", "")
    if key in LEAGUE_KEYS:
        return (
            "league",
            "league_period",
            "Status league period (source_league_* on unlock row).",
            "",
        )
    return (
        DEFAULT_LINK,
        DEFAULT_CONTEXT_BY_LINK[DEFAULT_LINK],
        "Single game via source_game_id (ratedresults).",
        "",
    )


def event_describe(entry: dict[str, str]) -> str:
    if entry.get("event_context_label"):
        return entry["event_context_label"]
    return CONTEXT_DESCRIBE_DEFAULT.get(entry["event_context"], entry["event_context"])


def tier_sort_key(defn: dict) -> tuple[int, str]:
    tier = (defn.get("tier_band") or "aspirational").lower()
    try:
        tier_idx = TIER_ORDER.index(tier)
    except ValueError:
        tier_idx = len(TIER_ORDER)
    return tier_idx, defn["milestone_key"]


def main() -> None:
    seed = json.loads(SEED.read_text(encoding="utf-8"))
    definitions = sorted(seed["definitions"], key=tier_sort_key)
    keys = [d["milestone_key"] for d in definitions]

    entries: dict[str, dict[str, str]] = {}
    for key in keys:
        link, context, notes, context_label = profile_for_key(key)
        entry: dict[str, str] = {
            "event_link": link,
            "event_context": context,
            "garden_link": link,
            "notes": notes,
        }
        if context_label:
            entry["event_context_label"] = context_label
        entries[key] = entry

    payload = {
        "version": seed.get("version", "2026-05-curated"),
        "milestone_count": len(keys),
        "default_event_link": DEFAULT_LINK,
        "default_garden_link": DEFAULT_LINK,
        "event_link_values": list(LINK_VALUES),
        "event_context_values": list(CONTEXT_VALUES),
        "garden_link_values": list(LINK_VALUES),
        "keys": entries,
    }
    OUT_JSON.write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")

    tier_counts = Counter((d.get("tier_band") or "").lower() for d in definitions)
    version = seed.get("version", "2026-05-curated")

    catalog_lines = [
        "# Milestone catalog (generated)",
        "",
        "**Start here:** [`milestones-README.md`](milestones-README.md).",
        "",
        "Per-key **intended + implemented UI** view: identity (tier, title, rule) plus unlock-event "
        "**Link** and **Event**. Rebuild probe hints come from the definitions seed.",
        "",
        "**Machine sources:** `data/milestones_definitions_seed.json` · "
        "`data/milestone_garden_links.json` · PHP `milestone_garden_links.php`.",
        "",
        "**DB / rebuild contract:** [`website-data-contract.md`](website-data-contract.md) § "
        "`player_milestones` · families [`milestones-facilitation.md`](milestones-facilitation.md).",
        "",
        f"**Regenerate:** `python scripts/oneoff/build_milestone_garden_links.py` · "
        f"**Seed version:** `{version}` · **Keys:** {len(keys)}",
        "",
        "## Summary by tier",
        "",
        "| Band | Chart token | Keys |",
        "|------|-------------|-----:|",
    ]
    for tier in TIER_ORDER:
        catalog_lines.append(
            f"| {TIER_LABEL[tier]} | `{TIER_CHART_TOKEN[tier]}` | "
            f"{tier_counts.get(tier, 0)} |"
        )
    catalog_lines.append(f"| **Total** | — | **{len(keys)}** |")
    catalog_lines.extend(
        [
            "",
            "## Full catalog",
            "",
            "Sorted: Legendary → Accomplished → Dedicated → Aspirational, then `milestone_key`.",
            "",
            "| `milestone_key` | Tier | Display name | Rule (short) | Link | Event | `rule_probe` |",
            "|-----------------|------|--------------|--------------|------|-------|--------------|",
        ]
    )

    defn_by_key = {d["milestone_key"]: d for d in definitions}
    for key in keys:
        d = defn_by_key[key]
        e = entries[key]
        tier = (d.get("tier_band") or "").lower()
        tier_label = TIER_LABEL.get(tier, tier)
        display = md_cell(str(d.get("display_name", "")))
        rule = md_cell(str(d.get("rule_short", "")))
        probe = md_cell(str(d.get("rule_probe", "")))
        link_ui = LINK_UI_LABEL.get(e["event_link"], e["event_link"])
        event = md_cell(event_describe(e))
        catalog_lines.append(
            f"| `{key}` | {tier_label} | {display} | {rule} | {link_ui} | {event} | {probe} |"
        )
    catalog_lines.append("")
    OUT_CATALOG_MD.write_text("\n".join(catalog_lines), encoding="utf-8")

    garden_lines = [
        "# Milestone unlock event index (generated)",
        "",
        "**Link + Event only** — subset of [`milestones-catalog.md`](milestones-catalog.md).",
        "",
        "Behaviour spec: [`milestones-unlock-event-ui.md`](milestones-unlock-event-ui.md).",
        "",
        f"**Regenerate:** `python scripts/oneoff/build_milestone_garden_links.py` · **Keys:** {len(keys)}",
        "",
        "| `milestone_key` | Link | Event | Notes |",
        "|-----------------|------|-------|-------|",
    ]
    for key in keys:
        e = entries[key]
        notes = md_cell(e["notes"])
        link_ui = LINK_UI_LABEL.get(e["event_link"], e["event_link"])
        event = md_cell(event_describe(e))
        garden_lines.append(f"| `{key}` | {link_ui} | {event} | {notes} |")
    garden_lines.append("")
    OUT_GARDEN_MD.write_text("\n".join(garden_lines), encoding="utf-8")

    print(f"Wrote {OUT_JSON} ({len(keys)} keys)")
    print(f"Wrote {OUT_CATALOG_MD}")
    print(f"Wrote {OUT_GARDEN_MD}")


if __name__ == "__main__":
    main()
