"""Player tournament slice tables — column registry (world_cup v1 + v2)."""

from __future__ import annotations

SLICE_KEY_WORLD_CUP = "world_cup"

SLICE_STAT_COLUMNS_V1: tuple[str, ...] = (
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

SLICE_STAT_COLUMNS_V2: tuple[str, ...] = (
    "goal_ratio",
    "most_goals_scored",
    "most_goals_conceded",
    "biggest_win_difference",
    "biggest_loss_difference",
    "biggest_sum_of_goals",
    "biggest_draw_sum",
    "double_digits",
    "clean_sheets",
    "double_digits_ratio",
    "clean_sheets_ratio",
    "double_digits_conceded",
    "clean_sheets_conceded",
    "double_digits_conceded_ratio",
    "clean_sheets_conceded_ratio",
    "opponent_countries_faced",
    "opponent_countries_beaten",
    "different_opponents",
    "different_victims",
    "double_digits_victims",
    "clean_sheets_victims",
    "different_culprits",
    "double_digits_culprits",
    "clean_sheets_culprits",
)

# World Cup Hall of Fame slice extensions (SCH-046) — award counters + single-WC
# peaks. Per-metric rise columns are intentionally NOT added (decision ID1: HoF
# dates are derived from the slice_at_event timeline at compute time).
SLICE_STAT_COLUMNS_WC_HOF: tuple[str, ...] = (
    "best_attack_awards",
    "best_defense_awards",
    "best_single_wc_gf_per_game",
    "best_single_wc_gf_per_game_tournament_id",
    "best_single_wc_ga_per_game",
    "best_single_wc_ga_per_game_tournament_id",
)

SLICE_STAT_COLUMNS: tuple[str, ...] = (
    SLICE_STAT_COLUMNS_V1 + SLICE_STAT_COLUMNS_V2 + SLICE_STAT_COLUMNS_WC_HOF
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
