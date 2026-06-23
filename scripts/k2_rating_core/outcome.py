"""Derive match outcome fields from goals (legacy C++ semantics)."""

from __future__ import annotations

from dataclasses import dataclass


@dataclass(frozen=True)
class MatchOutcome:
    actual_score: float
    winner_id: int
    sum_of_goals: int
    goal_difference: int
    home_win: int
    draw: int
    away_win: int
    dd_player_a: int
    dd_player_b: int
    cs_player_a: int
    cs_player_b: int


def outcome_from_goals(goals_a: int, goals_b: int, id_a: int, id_b: int) -> MatchOutcome:
    if goals_a > goals_b:
        actual_score = 1.0
        winner_id = id_a
        goal_difference = goals_a - goals_b
        home_win, draw, away_win = 1, 0, 0
    elif goals_a < goals_b:
        actual_score = 0.0
        winner_id = id_b
        goal_difference = goals_b - goals_a
        home_win, draw, away_win = 0, 0, 1
    else:
        actual_score = 0.5
        winner_id = -1
        goal_difference = 0
        home_win, draw, away_win = 0, 1, 0

    return MatchOutcome(
        actual_score=actual_score,
        winner_id=winner_id,
        sum_of_goals=goals_a + goals_b,
        goal_difference=goal_difference,
        home_win=home_win,
        draw=draw,
        away_win=away_win,
        dd_player_a=1 if goals_a >= 10 else 0,
        dd_player_b=1 if goals_b >= 10 else 0,
        cs_player_a=1 if goals_b == 0 else 0,
        cs_player_b=1 if goals_a == 0 else 0,
    )
