"""Running career honours totals (as-of each finalized event)."""

from __future__ import annotations

from typing import Any

from scripts.amiga.tournament_honours import is_world_cup_tournament

# Metrics tracked for HoF last-rise dates (policy: amiga-hof-record-date-policy.md).
HONOURS_RISE_METRICS: tuple[str, ...] = (
    "tournaments_played",
    "event_gold",
    "wc_played",
)


def _empty_rise_fields() -> dict[str, Any]:
    out: dict[str, Any] = {}
    for metric in HONOURS_RISE_METRICS:
        out[f"{metric}_last_rise_tournament_id"] = None
        out[f"{metric}_last_rise_event_date"] = None
    return out


def _set_last_rise(
    totals: dict[str, Any],
    metric: str,
    *,
    tournament_id: int,
    event_date: Any,
) -> None:
    totals[f"{metric}_last_rise_tournament_id"] = int(tournament_id)
    totals[f"{metric}_last_rise_event_date"] = event_date


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
        **_empty_rise_fields(),
    }


def increment_honours_totals(totals: dict[str, Any], participation: dict[str, Any]) -> None:
    """Apply one participation-shaped row to running career honours."""
    prior_tournaments_played = int(totals["tournaments_played"])
    prior_event_gold = int(totals["event_gold"])
    prior_wc_played = int(totals["wc_played"])

    totals["tournaments_played"] = prior_tournaments_played + 1

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

    tournament_id = int(participation["tournament_id"])
    event_date = participation.get("event_date")
    totals["last_event_date"] = event_date
    totals["last_tournament_id"] = tournament_id

    if int(totals["tournaments_played"]) > prior_tournaments_played:
        _set_last_rise(
            totals,
            "tournaments_played",
            tournament_id=tournament_id,
            event_date=event_date,
        )
    if int(totals["event_gold"]) > prior_event_gold:
        _set_last_rise(
            totals,
            "event_gold",
            tournament_id=tournament_id,
            event_date=event_date,
        )
    if int(totals["wc_played"]) > prior_wc_played:
        _set_last_rise(
            totals,
            "wc_played",
            tournament_id=tournament_id,
            event_date=event_date,
        )


def honours_from_current_row(row: dict[str, Any]) -> dict[str, Any]:
    """Map ``amiga_player_current`` honours columns to totals dict shape."""
    out: dict[str, Any] = {
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
    for metric in HONOURS_RISE_METRICS:
        out[f"{metric}_last_rise_tournament_id"] = row.get(f"{metric}_last_rise_tournament_id")
        out[f"{metric}_last_rise_event_date"] = row.get(f"{metric}_last_rise_event_date")
    return out
