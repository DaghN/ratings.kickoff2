"""World Cup country slice tables — column registry."""

from __future__ import annotations

from scripts.amiga.slice_columns import SLICE_KEY_WORLD_CUP

COUNTRY_UNKNOWN_TOKEN = "Unknown"

COUNTRY_SLICE_PARTICIPATION_COLUMNS: tuple[str, ...] = (
    "players",
    "wc_participations",
    "wc_participations_per_player",
    "games_per_player",
    "domestic_games",
    "domestic_game_share",
    "international_games",
    "international_game_share",
    "games_share",
    "goals_share",
    "realm_wc_tournament_count",
    "realm_wc_player_games",
    "realm_wc_goals_for",
)

COUNTRY_SLICE_HONOURS_COLUMNS: tuple[str, ...] = (
    "tournaments_with_nation",
    "gold",
    "silver",
    "bronze",
    "podiums",
)

COUNTRY_SLICE_RESULTS_COLUMNS: tuple[str, ...] = (
    "games",
    "wins",
    "draws",
    "losses",
    "points",
    "points_per_realm_wc",
    "win_rate",
    "average_opponent_rating",
    "performance_rating",
)

COUNTRY_SLICE_GOALS_COLUMNS: tuple[str, ...] = (
    "goals_for",
    "goals_against",
    "goal_ratio",
    "most_goals_scored",
    "most_goals_conceded",
    "biggest_win_difference",
    "biggest_loss_difference",
    "biggest_sum_of_goals",
    "biggest_draw_sum",
)

COUNTRY_SLICE_DDS_COLUMNS: tuple[str, ...] = (
    "double_digits",
    "clean_sheets",
    "double_digits_ratio",
    "clean_sheets_ratio",
    "double_digits_conceded",
    "clean_sheets_conceded",
    "double_digits_conceded_ratio",
    "clean_sheets_conceded_ratio",
)

COUNTRY_SLICE_OPPONENTS_COLUMNS: tuple[str, ...] = (
    "opponent_countries_faced",
    "opponent_countries_beaten",
    "different_opponents",
    "different_victims",
    "double_digits_victims",
    "clean_sheets_victims",
)

COUNTRY_SLICE_STAT_COLUMNS: tuple[str, ...] = (
    *COUNTRY_SLICE_PARTICIPATION_COLUMNS,
    *COUNTRY_SLICE_HONOURS_COLUMNS,
    *COUNTRY_SLICE_RESULTS_COLUMNS,
    *COUNTRY_SLICE_GOALS_COLUMNS,
    *COUNTRY_SLICE_DDS_COLUMNS,
    *COUNTRY_SLICE_OPPONENTS_COLUMNS,
)

COUNTRY_SLICE_TOTALS_COLUMNS: tuple[str, ...] = (
    "country_token",
    "slice_key",
    *COUNTRY_SLICE_STAT_COLUMNS,
)

COUNTRY_SLICE_AT_EVENT_COLUMNS: tuple[str, ...] = (
    "country_token",
    "slice_key",
    "as_of_tournament_id",
    "event_date",
    "event_chrono",
    *COUNTRY_SLICE_STAT_COLUMNS,
)

__all__ = [
    "SLICE_KEY_WORLD_CUP",
    "COUNTRY_UNKNOWN_TOKEN",
    "COUNTRY_SLICE_STAT_COLUMNS",
    "COUNTRY_SLICE_TOTALS_COLUMNS",
    "COUNTRY_SLICE_AT_EVENT_COLUMNS",
]
