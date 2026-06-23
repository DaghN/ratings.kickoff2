"""Column manifest for amiga_world_cup_stats."""

from __future__ import annotations

WORLD_CUP_STATS_IDENTITY_COLUMNS: tuple[str, ...] = (
    "tournament_id",
    "tournament_name",
    "calendar_year",
    "event_date",
    "event_chrono",
    "host_country",
    "host_city",
)

WORLD_CUP_STATS_METRIC_COLUMNS: tuple[str, ...] = (
    "rated_games",
    "decided_games",
    "draws",
    "goals",
    "double_digit_slots",
    "clean_sheet_slots",
    "high_scoring_games",
    "low_scoring_games",
    "blowout_games",
    "knockout_games",
    "group_games",
    "goals_per_game",
    "draw_rate",
    "decided_rate",
    "double_digit_rate",
    "clean_sheet_rate",
    "high_scoring_rate",
    "low_scoring_rate",
    "distinct_players",
    "distinct_player_nationalities",
    "max_games_one_player",
    "first_time_wc_players",
    "distinct_opponent_pairs",
    "avg_games_per_player",
    "avg_opponents_per_player",
    "distinct_host_country_players",
    "distinct_guest_players",
    "guest_player_share",
    "distinct_opponent_countries_pairs",
    "highest_goal_sum",
    "highest_goal_sum_game_id",
    "lowest_goal_sum",
    "lowest_goal_sum_game_id",
    "biggest_margin",
    "biggest_margin_game_id",
    "highest_scoring_draw_sum",
    "highest_scoring_draw_game_id",
    "most_goals_one_player_game",
    "most_goals_one_player_game_id",
    "gold_player_id",
    "silver_player_id",
    "bronze_player_id",
    "champion_game_count",
    "share_of_year_games",
)

WORLD_CUP_STATS_COLUMNS: tuple[str, ...] = (
    WORLD_CUP_STATS_IDENTITY_COLUMNS
    + WORLD_CUP_STATS_METRIC_COLUMNS
    + ("finalized_at",)
)
