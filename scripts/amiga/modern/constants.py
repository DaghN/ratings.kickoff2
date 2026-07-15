"""Shared constants for Amiga modern ground cutover."""

from __future__ import annotations

from pathlib import Path
from typing import Final

_REPO = Path(__file__).resolve().parents[3]

WORK_DB: Final[str] = "ko2amiga_work"
ORACLE_DB: Final[str] = "ko2amiga_db"
DAY0_DIR: Final[Path] = _REPO / "data" / "amiga" / "day0"

DAY0_L3_TABLES: Final[tuple[str, ...]] = (
    "tournament_format_templates",
    "tournaments",
    "amiga_players",
    "amiga_tournament_finish_override",
    "amiga_games",
)

DAY0_SCHEMA_PART: Final[str] = "day0_01_schema.sql"

# L3 witness counts simul must not mutate (living ground — may exceed day 0).
L3_GROUND_COUNT_KEYS: Final[tuple[str, ...]] = (
    "tournaments",
    "players",
    "games",
)

# P-1 parity scope — mirrors Export-Ko2AmigaStaging.ps1 table list.
PARITY_TABLES: Final[tuple[str, ...]] = (
    "tournament_format_templates",
    "tournaments",
    "amiga_players",
    "tournament_entrants",
    "tournament_stages",
    "tournament_stage_players",
    "tournament_fixtures",
    "amiga_games",
    "amiga_tournament_finish_override",
    "amiga_game_ratings",
    "amiga_player_event_snapshots",
    "amiga_player_current",
    "amiga_player_elo_rank_at_event",
    "amiga_player_inverse_count_at_event",
    "amiga_player_matchup_at_event",
    "amiga_player_matchup_summary",
    "amiga_tournament_standings",
    "amiga_tournament_catalog_stats",
    "amiga_generalstats",
    "amiga_realm_snapshots",
    "amiga_community_stats",
    "amiga_community_stats_snapshots",
    "amiga_community_stat_facts",
    "amiga_world_cup_stats",
    "amiga_player_slice_totals",
    "amiga_player_slice_at_event",
    "amiga_country_slice_totals",
    "amiga_country_slice_at_event",
    "amiga_wc_hof_snapshots",
    "amiga_wc_hof_present",
)

# Tournament video file compartments (V-1).
SHARED_VIDEO_DIR: Final[Path] = _REPO / "data" / "amiga" / "tournament_videos"
ORACLE_VIDEO_DIR: Final[Path] = _REPO / "data" / "amiga" / "oracle" / "tournament_videos"
WORK_VIDEO_DIR: Final[Path] = _REPO / "data" / "amiga" / "work" / "tournament_videos"
WORK_MANIFEST_JSON: Final[Path] = _REPO / "data" / "amiga" / "work" / "tournament_videos.json"
LEGACY_MANIFEST_JSON: Final[Path] = (
    _REPO / "site" / "public_html" / "data" / "amiga" / "tournament_videos.json"
)