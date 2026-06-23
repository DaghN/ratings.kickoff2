"""Shared online Elo / player-state library (Amiga holy path + PHP mirror reference)."""

from .apply_game import apply_game_row
from .config import DbConfig, load_db_config
from .connection import connect
from .constants import START_RATING
from .elo import compute_elo
from .outcome import outcome_from_goals
from .player_state import PlayerState

__all__ = [
    "DbConfig",
    "PlayerState",
    "START_RATING",
    "apply_game_row",
    "compute_elo",
    "connect",
    "load_db_config",
    "outcome_from_goals",
]
