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