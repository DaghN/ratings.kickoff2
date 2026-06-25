"""Career peak/nadir rating event anchors at tournament finalize (Amiga M6)."""

from __future__ import annotations

_RATING_EPS = 1e-9


def compute_peak_rating_tournament_id(
    rating_after: float,
    tournament_id: int,
    prior_peak: float,
    prior_peak_tournament_id: int | None,
) -> int | None:
    """First event where career PeakRating was reached; ties keep prior tournament."""
    if prior_peak <= 0 or rating_after > prior_peak + _RATING_EPS:
        return tournament_id
    return prior_peak_tournament_id


def compute_lowest_rating_tournament_id(
    rating_after: float,
    tournament_id: int,
    prior_lowest: float,
    prior_lowest_tournament_id: int | None,
) -> int | None:
    """First event where career LowestRating was reached; ties keep prior tournament."""
    from scripts.k2_rating_core.player_state import SENTINEL_LOWEST_RATING

    if prior_lowest <= 0 or prior_lowest >= SENTINEL_LOWEST_RATING - 1:
        return tournament_id
    if rating_after < prior_lowest - _RATING_EPS:
        return tournament_id
    return prior_lowest_tournament_id