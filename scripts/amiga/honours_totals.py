"""Running career honours totals (as-of each finalized event)."""

from __future__ import annotations

from typing import Any

from scripts.amiga.tournament_honours import is_world_cup_tournament


def empty_honours_totals() -> dict[str, Any]:
    return {
        "tournaments_played": 0,
        "tournaments_won": 0,
        "event_gold": 0,
        "event_silver": 0,
        "event_bronze": 0,
        "event_podiums": 0,
        "wc_played": 0,
        "wc_gold": 0,
        "wc_silver": 0,
        "wc_bronze": 0,
        "wc_podiums": 0,
        "last_event_date": None,
        "last_tournament_id": None,
    }


def increment_honours_totals(totals: dict[str, Any], participation: dict[str, Any]) -> None:
    """Apply one participation-shaped row to running career honours."""
    totals["tournaments_played"] = int(totals["tournaments_played"]) + 1

    pos = participation.get("event_finish_position")
    if pos is not None:
        pos = int(pos)

    if int(participation.get("is_winner") or 0) == 1 or pos == 1:
        totals["tournaments_won"] = int(totals["tournaments_won"]) + 1

    if pos == 1:
        totals["event_gold"] = int(totals["event_gold"]) + 1
    elif pos == 2:
        totals["event_silver"] = int(totals["event_silver"]) + 1
    elif pos == 3:
        totals["event_bronze"] = int(totals["event_bronze"]) + 1

    if pos is not None and pos <= 3:
        totals["event_podiums"] = int(totals["event_podiums"]) + 1

    if is_world_cup_tournament(str(participation.get("tournament_name") or "")):
        totals["wc_played"] = int(totals["wc_played"]) + 1
        if pos == 1:
            totals["wc_gold"] = int(totals["wc_gold"]) + 1
        elif pos == 2:
            totals["wc_silver"] = int(totals["wc_silver"]) + 1
        elif pos == 3:
            totals["wc_bronze"] = int(totals["wc_bronze"]) + 1
        if pos is not None and pos <= 3:
            totals["wc_podiums"] = int(totals["wc_podiums"]) + 1

    totals["last_event_date"] = participation.get("event_date")
    totals["last_tournament_id"] = int(participation["tournament_id"])


def honours_from_current_row(row: dict[str, Any]) -> dict[str, Any]:
    """Map ``amiga_player_current`` honours columns to totals dict shape."""
    return {
        "tournaments_played": int(row.get("tournaments_played") or 0),
        "tournaments_won": int(row.get("tournaments_won") or 0),
        "event_gold": int(row.get("event_gold") or 0),
        "event_silver": int(row.get("event_silver") or 0),
        "event_bronze": int(row.get("event_bronze") or 0),
        "event_podiums": int(row.get("event_podiums") or 0),
        "wc_played": int(row.get("wc_played") or 0),
        "wc_gold": int(row.get("wc_gold") or 0),
        "wc_silver": int(row.get("wc_silver") or 0),
        "wc_bronze": int(row.get("wc_bronze") or 0),
        "wc_podiums": int(row.get("wc_podiums") or 0),
        "last_event_date": row.get("last_event_date"),
        "last_tournament_id": row.get("last_tournament_id"),
    }
