"""Running World Cup slice totals (as-of each finalized event)."""

from __future__ import annotations

from typing import Any

from scripts.amiga.slice_columns import SLICE_KEY_WORLD_CUP, SLICE_RISE_METRICS
from scripts.amiga.tournament_honours import tournament_is_world_cup


def _empty_rise_fields() -> dict[str, Any]:
    out: dict[str, Any] = {}
    for metric in SLICE_RISE_METRICS:
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


def empty_world_cup_slice() -> dict[str, Any]:
    return {
        "slice_key": SLICE_KEY_WORLD_CUP,
        "tournaments_played": 0,
        "gold": 0,
        "silver": 0,
        "bronze": 0,
        "podiums": 0,
        "games": 0,
        "wins": 0,
        "draws": 0,
        "losses": 0,
        "goals_for": 0,
        "goals_against": 0,
        "points": 0,
        "goal_ratio": None,
        "most_goals_scored": 0,
        "most_goals_conceded": 0,
        "biggest_win_difference": 0,
        "biggest_loss_difference": 0,
        "biggest_sum_of_goals": 0,
        "biggest_draw_sum": 0,
        "double_digits": 0,
        "clean_sheets": 0,
        "double_digits_ratio": None,
        "clean_sheets_ratio": None,
        "double_digits_conceded": 0,
        "clean_sheets_conceded": 0,
        "double_digits_conceded_ratio": None,
        "clean_sheets_conceded_ratio": None,
        "opponent_countries_faced": 0,
        "opponent_countries_beaten": 0,
        "different_opponents": 0,
        "different_victims": 0,
        "double_digits_victims": 0,
        "clean_sheets_victims": 0,
        "different_culprits": 0,
        "double_digits_culprits": 0,
        "clean_sheets_culprits": 0,
        # WC Hall of Fame slice extensions (SCH-046) — populated by the WC-finalize
        # award/peak writer (WCH-2); default here so persist carries them.
        "best_attack_awards": 0,
        "best_defense_awards": 0,
        "best_single_wc_gf_per_game": None,
        "best_single_wc_gf_per_game_tournament_id": None,
        "best_single_wc_ga_per_game": None,
        "best_single_wc_ga_per_game_tournament_id": None,
        **_empty_rise_fields(),
    }


def _recompute_points(totals: dict[str, Any]) -> None:
    totals["points"] = 3 * int(totals["wins"]) + int(totals["draws"])


def _recompute_podiums(totals: dict[str, Any]) -> None:
    totals["podiums"] = (
        int(totals["gold"]) + int(totals["silver"]) + int(totals["bronze"])
    )


def increment_world_cup_slice(totals: dict[str, Any], participation: dict[str, Any]) -> None:
    """Apply one participation row when the tournament is a World Cup."""
    if not tournament_is_world_cup(participation):
        return

    prior_tournaments_played = int(totals["tournaments_played"])
    totals["tournaments_played"] = prior_tournaments_played + 1

    pos = participation.get("event_finish_position")
    if pos is not None:
        pos = int(pos)
    if pos == 1:
        totals["gold"] = int(totals["gold"]) + 1
    elif pos == 2:
        totals["silver"] = int(totals["silver"]) + 1
    elif pos == 3:
        totals["bronze"] = int(totals["bronze"]) + 1
    _recompute_podiums(totals)

    totals["games"] = int(totals["games"]) + int(participation.get("games") or 0)
    totals["wins"] = int(totals["wins"]) + int(participation.get("wins") or 0)
    totals["draws"] = int(totals["draws"]) + int(participation.get("draws") or 0)
    totals["losses"] = int(totals["losses"]) + int(participation.get("losses") or 0)
    totals["goals_for"] = int(totals["goals_for"]) + int(participation.get("goals_for") or 0)
    totals["goals_against"] = int(totals["goals_against"]) + int(
        participation.get("goals_against") or 0
    )
    _recompute_points(totals)

    tournament_id = int(participation["tournament_id"])
    event_date = participation.get("event_date")
    if int(totals["tournaments_played"]) > prior_tournaments_played:
        _set_last_rise(
            totals,
            "tournaments_played",
            tournament_id=tournament_id,
            event_date=event_date,
        )


def slice_from_totals_row(row: dict[str, Any]) -> dict[str, Any]:
    """Map ``amiga_player_slice_totals`` row to in-memory dict."""
    out = empty_world_cup_slice()
    for key in out:
        if key == "slice_key":
            continue
        if key in row:
            out[key] = row[key]
    out["tournaments_played"] = int(row.get("tournaments_played") or 0)
    out["gold"] = int(row.get("gold") or 0)
    out["silver"] = int(row.get("silver") or 0)
    out["bronze"] = int(row.get("bronze") or 0)
    out["podiums"] = int(row.get("podiums") or 0)
    out["games"] = int(row.get("games") or 0)
    out["wins"] = int(row.get("wins") or 0)
    out["draws"] = int(row.get("draws") or 0)
    out["losses"] = int(row.get("losses") or 0)
    out["goals_for"] = int(row.get("goals_for") or 0)
    out["goals_against"] = int(row.get("goals_against") or 0)
    out["points"] = int(row.get("points") or 0)
    for metric in SLICE_RISE_METRICS:
        out[f"{metric}_last_rise_tournament_id"] = row.get(f"{metric}_last_rise_tournament_id")
        out[f"{metric}_last_rise_event_date"] = row.get(f"{metric}_last_rise_event_date")
    return out


def slice_from_at_event_row(row: dict[str, Any]) -> dict[str, Any]:
    """Map prior ``amiga_player_slice_at_event`` row to in-memory dict."""
    return slice_from_totals_row(row)
