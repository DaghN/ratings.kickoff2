"""Column manifests for amiga_community_stats tables."""

from __future__ import annotations

from scripts.amiga.generalstats_columns import GENERALSTATS_AGGREGATE_COLUMNS

# Headline payload = same 14 realm aggregate scalars as legacy generalstats block.
COMMUNITY_HEADLINE_COLUMNS: tuple[str, ...] = GENERALSTATS_AGGREGATE_COLUMNS

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
