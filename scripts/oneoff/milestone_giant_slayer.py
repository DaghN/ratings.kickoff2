"""Giant slayer milestone — kickoff active #1 (shared by chrono generator and parity probe).

Active #1 is the highest-rated active player at game start (before this game's Elo
is applied). Tie → highest player_id (matches PHP ORDER BY Rating DESC, ID DESC).
"""
from __future__ import annotations

from datetime import datetime, timedelta

GIANT_SLAYER_ACTIVE_DAYS = 365


def giant_slayer_active_top_id(
    ratings: dict[int, float],
    last_game: dict[int, datetime],
    at: datetime,
    *,
    in_game: tuple[int, int],
) -> int:
    """
    Highest rating among players active at `at`.

    Active = rated game within the last GIANT_SLAYER_ACTIVE_DAYS (UTC rolling), or
    one of the two players in the current game (they are playing now).
    Ties: highest player_id wins (matches PHP ORDER BY Rating DESC, ID DESC).
    """
    cutoff = at - timedelta(days=GIANT_SLAYER_ACTIVE_DAYS)
    playing = frozenset(in_game)
    cands: list[int] = []
    for k in ratings:
        if k in playing:
            cands.append(k)
            continue
        t = last_game.get(k)
        if t is not None and t >= cutoff:
            cands.append(k)
    if not cands:
        return 0
    return max(cands, key=lambda k: (ratings[k], k))


def giant_slayer_qualifies(
    *,
    won: bool,
    pid: int,
    opp: int,
    top_id: int,
    r_pre: float,
    r_opp: float,
) -> bool:
    return bool(won and opp == top_id and opp != pid and r_opp >= r_pre)
