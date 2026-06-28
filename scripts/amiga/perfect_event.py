"""Perfect event (undefeated tournament run) — policy: docs/amiga-perfect-event-policy.md."""

from __future__ import annotations

PERFECT_EVENT_MIN_GAMES = 2


def is_perfect_event_from_rollup(
    games: int,
    wins: int,
    draws: int,
    losses: int,
) -> bool:
    """True when player won every game in the event (>= PERFECT_EVENT_MIN_GAMES)."""
    games_n = int(games)
    if games_n < PERFECT_EVENT_MIN_GAMES:
        return False
    return int(losses) == 0 and int(draws) == 0 and int(wins) == games_n