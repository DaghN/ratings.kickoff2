"""V1 community stat fact registry (implementation plan § V1 metric registry)."""

from __future__ import annotations

from dataclasses import dataclass
from typing import Literal

PeriodType = Literal["year", "all_time"]
SliceType = Literal["realm", "host_country", "player_nationality"]
MetricKey = Literal["games", "goals", "active_players"]
CountBasis = Literal["game", "participant"]

REALM_SLICE_KEY = "*"
ALL_TIME_PERIOD_KEY = "*"

PERIOD_TYPES: tuple[str, ...] = ("year", "all_time")
SLICE_TYPES: tuple[str, ...] = ("realm", "host_country", "player_nationality")
METRIC_KEYS: tuple[str, ...] = ("games", "goals", "active_players")
COUNT_BASES: tuple[str, ...] = ("game", "participant")


@dataclass(frozen=True)
class CommunityFactSpec:
    period_type: PeriodType
    slice_type: SliceType
    metric_key: MetricKey
    count_basis: CountBasis


# Which grains the writer emits (period_key and slice_key are data-driven).
V1_FACT_SPECS: tuple[CommunityFactSpec, ...] = (
    CommunityFactSpec("year", "realm", "games", "game"),
    CommunityFactSpec("year", "realm", "goals", "game"),
    CommunityFactSpec("year", "realm", "active_players", "game"),
    CommunityFactSpec("year", "host_country", "games", "game"),
    CommunityFactSpec("year", "player_nationality", "games", "participant"),
    CommunityFactSpec("year", "player_nationality", "goals", "participant"),
    CommunityFactSpec("all_time", "host_country", "games", "game"),
    CommunityFactSpec("all_time", "player_nationality", "games", "participant"),
)
