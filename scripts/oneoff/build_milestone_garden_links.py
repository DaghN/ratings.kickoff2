#!/usr/bin/env python3
"""
Build data/milestone_garden_links.json and docs/milestones-garden-links.md.

Per-key unlock event UX (link + context) — lockstep with milestone_garden_links.php.
See docs/milestones-unlock-event-ui.md.

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
        "event_context_label": "Perfect day — all wins (5+ rated games that UTC day)",
        "notes": "All rated games that UTC day (W only, min 5). Link uses achieved_at day-close + player id.",
    },
    "nightmare_day": {
        "event_link": "player_day_games",
        "event_context": "day_games",
        "event_context_label": "Nightmare day — all losses (5+ rated games that UTC day)",
        "notes": "All rated games that UTC day (L only, min 5). Link uses achieved_at day-close + player id.",
    },
}


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


def main() -> None:
    seed = json.loads(SEED.read_text(encoding="utf-8"))
    keys = sorted(d["milestone_key"] for d in seed["definitions"])
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
        "default_event_link": DEFAULT_LINK,
        "default_garden_link": DEFAULT_LINK,
        "event_link_values": list(LINK_VALUES),
        "event_context_values": list(CONTEXT_VALUES),
        "garden_link_values": list(LINK_VALUES),
        "keys": entries,
    }
    OUT_JSON.write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")

    lines = [
        "# Milestone unlock event register (generated)",
        "",
        "**Human-readable master table** for all milestone keys: **Link** (where the UI goes) "
        "and **Event** (what achievers / detail surfaces describe).",
        "",
        "**Authority / behaviour:** [`milestones-unlock-event-ui.md`](milestones-unlock-event-ui.md).",
        "",
        "Lockstep: `data/milestone_garden_links.json` · "
        "`site/public_html/includes/milestone_garden_links.php`.",
        "",
        "**Not** stored on `player_milestones`. **Not** the tier-planning docs "
        "([`milestones-tier-curated.md`](milestones-tier-curated.md) is tiers only).",
        "",
        f"**Regenerate after edits:** `python scripts/oneoff/build_milestone_garden_links.py` · "
        f"**Keys:** {len(keys)}",
        "",
        "| `milestone_key` | Link | Event | Notes |",
        "|-----------------|------|-------|-------|",
    ]
    for key in keys:
        e = entries[key]
        notes = e["notes"].replace("|", "\\|")
        link_ui = LINK_UI_LABEL.get(e["event_link"], e["event_link"])
        event_desc = e.get("event_context_label") or CONTEXT_DESCRIBE_DEFAULT.get(
            e["event_context"], e["event_context"]
        )
        event_desc = event_desc.replace("|", "\\|")
        lines.append(f"| `{key}` | {link_ui} | {event_desc} | {notes} |")
    lines.append("")
    OUT_MD.write_text("\n".join(lines), encoding="utf-8")
    print(f"Wrote {OUT_JSON} ({len(keys)} keys)")
    print(f"Wrote {OUT_MD}")


if __name__ == "__main__":
    main()
