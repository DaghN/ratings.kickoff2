"""Community stat fact registry (v1 + v2 question catalog ship set)."""

from __future__ import annotations

from dataclasses import dataclass
from typing import Literal

PeriodType = Literal["year", "all_time"]
SliceType = Literal["realm", "host_country", "player_nationality", "world_cup"]
MetricKey = Literal[
    "games",
    "goals",
    "active_players",
    "tournaments",
    "draws",
    "double_digits",
    "clean_sheets",
    "high_scoring_games",
    "distinct_pairs",
    "player_debuts",
    "distinct_host_countries",
    "distinct_nationalities",
    "wc_active_players",
]
CountBasis = Literal["game", "participant"]

REALM_SLICE_KEY = "*"
ALL_TIME_PERIOD_KEY = "*"
WORLD_CUP_SLICE_KEY = "*"

PERIOD_TYPES: tuple[str, ...] = ("year", "all_time")
SLICE_TYPES: tuple[str, ...] = ("realm", "host_country", "player_nationality", "world_cup")
METRIC_KEYS: tuple[str, ...] = (
    "games",
    "goals",
    "active_players",
    "tournaments",
    "draws",
    "double_digits",
    "clean_sheets",
    "high_scoring_games",
    "distinct_pairs",
    "player_debuts",
    "distinct_host_countries",
    "distinct_nationalities",
    "wc_active_players",
)
COUNT_BASES: tuple[str, ...] = ("game", "participant")


@dataclass(frozen=True)
class CommunityFactSpec:
    period_type: PeriodType
    slice_type: SliceType
    metric_key: MetricKey
    count_basis: CountBasis


# v1 grains (unchanged names for parity docs).
V1_FACT_SPECS: tuple[CommunityFactSpec, ...] = (
    CommunityFactSpec("year", "realm", "games", "game"),
    CommunityFactSpec("year", "realm", "goals", "game"),
    CommunityFactSpec("year", "realm", "active_players", "game"),
    CommunityFactSpec("year", "host_country", "games", "game"),
    CommunityFactSpec("year", "player_nationality", "games", "participant"),
    CommunityFactSpec("year", "player_nationality", "goals", "participant"),
    CommunityFactSpec("year", "player_nationality", "active_players", "participant"),
    CommunityFactSpec("year", "player_nationality", "player_debuts", "participant"),
    CommunityFactSpec("year", "player_nationality", "wc_active_players", "participant"),
    CommunityFactSpec("all_time", "host_country", "games", "game"),
    CommunityFactSpec("all_time", "player_nationality", "games", "participant"),
    CommunityFactSpec("all_time", "player_nationality", "active_players", "participant"),
)

# Additional v2 grains (catalog step 3 ship set).
V2_EXTRA_FACT_SPECS: tuple[CommunityFactSpec, ...] = (
    CommunityFactSpec("year", "realm", "tournaments", "game"),
    CommunityFactSpec("year", "realm", "draws", "game"),
    CommunityFactSpec("year", "realm", "double_digits", "game"),
    CommunityFactSpec("year", "realm", "clean_sheets", "game"),
    CommunityFactSpec("year", "realm", "high_scoring_games", "game"),
    CommunityFactSpec("year", "realm", "distinct_pairs", "game"),
    CommunityFactSpec("year", "realm", "player_debuts", "game"),
    CommunityFactSpec("year", "realm", "distinct_host_countries", "game"),
    CommunityFactSpec("year", "realm", "distinct_nationalities", "game"),
    CommunityFactSpec("year", "host_country", "goals", "game"),
    CommunityFactSpec("year", "host_country", "tournaments", "game"),
    CommunityFactSpec("all_time", "host_country", "goals", "game"),
    CommunityFactSpec("all_time", "host_country", "tournaments", "game"),
    CommunityFactSpec("all_time", "player_nationality", "goals", "participant"),
    CommunityFactSpec("year", "world_cup", "games", "game"),
    CommunityFactSpec("year", "world_cup", "goals", "game"),
    CommunityFactSpec("year", "world_cup", "active_players", "game"),
    CommunityFactSpec("year", "world_cup", "distinct_nationalities", "game"),
)

FACT_SPECS: tuple[CommunityFactSpec, ...] = V1_FACT_SPECS + V2_EXTRA_FACT_SPECS

# Specs incremented per rated game (not post-pass aggregates).
PER_GAME_FACT_SPECS: frozenset[CommunityFactSpec] = frozenset(
    spec
    for spec in FACT_SPECS
    if spec.metric_key
    not in {
        "active_players",
        "distinct_host_countries",
        "distinct_nationalities",
        "tournaments",
        "player_debuts",
        "distinct_pairs",
        "wc_active_players",
    }
)
