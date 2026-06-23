"""Column manifests for amiga_community_stats tables."""

from __future__ import annotations

from scripts.amiga.generalstats_columns import GENERALSTATS_AGGREGATE_COLUMNS

# Legacy 14 realm aggregate scalars (v1).
COMMUNITY_HEADLINE_BASE_COLUMNS: tuple[str, ...] = GENERALSTATS_AGGREGATE_COLUMNS

# v2 extension scalars (question catalog step 3).
COMMUNITY_HEADLINE_EXTENSION_COLUMNS: tuple[str, ...] = (
    "TournamentsFinalized",
    "DistinctHostCountries",
    "WcGamesPlayed",
    "DistinctOpponentPairs",
    "PlayersDebuted",
)

COMMUNITY_HEADLINE_COLUMNS: tuple[str, ...] = (
    COMMUNITY_HEADLINE_BASE_COLUMNS + COMMUNITY_HEADLINE_EXTENSION_COLUMNS
)

COMMUNITY_SNAPSHOT_KEY_COLUMNS: tuple[str, ...] = (
    "tournament_id",
    "event_date",
    "event_chrono",
    "tournament_name",
    "finalized_at",
)

COMMUNITY_SNAPSHOT_COLUMNS: tuple[str, ...] = (
    COMMUNITY_SNAPSHOT_KEY_COLUMNS + COMMUNITY_HEADLINE_COLUMNS
)

COMMUNITY_FACT_KEY_COLUMNS: tuple[str, ...] = (
    "tournament_id",
    "period_type",
    "period_key",
    "slice_type",
    "slice_key",
    "metric_key",
    "count_basis",
)

COMMUNITY_FACT_COLUMNS: tuple[str, ...] = COMMUNITY_FACT_KEY_COLUMNS + ("value",)
