"""Player tournament slice tables — column registry (world_cup v1)."""

from __future__ import annotations

SLICE_KEY_WORLD_CUP = "world_cup"

SLICE_STAT_COLUMNS: tuple[str, ...] = (
    "tournaments_played",
    "gold",
    "silver",
    "bronze",
    "podiums",
    "games",
    "wins",
    "draws",
    "losses",
    "goals_for",
    "goals_against",
    "points",
)

SLICE_RISE_METRICS: tuple[str, ...] = ("tournaments_played",)

SLICE_RISE_COLUMNS: tuple[str, ...] = tuple(
    f"{metric}_last_rise_tournament_id" for metric in SLICE_RISE_METRICS
) + tuple(f"{metric}_last_rise_event_date" for metric in SLICE_RISE_METRICS)

SLICE_TOTALS_COLUMNS: tuple[str, ...] = (
    "player_id",
    "slice_key",
    *SLICE_STAT_COLUMNS,
    *SLICE_RISE_COLUMNS,
)

SLICE_AT_EVENT_COLUMNS: tuple[str, ...] = (
    "player_id",
    "slice_key",
    "as_of_tournament_id",
    "event_date",
    "event_chrono",
    *SLICE_STAT_COLUMNS,
    *SLICE_RISE_COLUMNS,
)
