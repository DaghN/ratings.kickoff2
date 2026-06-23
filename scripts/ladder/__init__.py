"""Deprecated package — use scripts.k2_rating_core (Jun 2026).

Re-exports remain for transitional imports only; do not add new references here.
"""

from scripts.k2_rating_core.apply_game import apply_game_row
from scripts.k2_rating_core.config import DbConfig, _parse_php_config, load_db_config
from scripts.k2_rating_core.connection import connect
from scripts.k2_rating_core.constants import START_RATING
from scripts.k2_rating_core.elo import compute_elo
from scripts.k2_rating_core.outcome import outcome_from_goals
from scripts.k2_rating_core.player_state import PlayerState

__all__ = [
    "DbConfig",
    "PlayerState",
    "START_RATING",
    "_parse_php_config",
    "apply_game_row",
    "compute_elo",
    "connect",
    "load_db_config",
    "outcome_from_goals",
]
