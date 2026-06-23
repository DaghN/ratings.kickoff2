"""Per-game Elo + ratedresults row shape (shared by Amiga finalize and PHP ops mirrors)."""

from __future__ import annotations

from typing import Any

from .elo import compute_elo
from .outcome import outcome_from_goals
from .player_state import PlayerState
from .server_records import ServerRecordState, update_server_records_after_game


def apply_game_row(
    game: dict[str, Any],
    players: dict[int, PlayerState],
    *,
    names: dict[int, str],
    server_records: ServerRecordState | None = None,
    frozen_ratings: dict[int, float] | None = None,
    commit_rating: bool = True,
) -> dict[str, Any]:
    id_a = int(game["idA"])
    id_b = int(game["idB"])
    goals_a = int(game["GoalsA"])
    goals_b = int(game["GoalsB"])
    game_id = int(game["id"])
    game_date = game["Date"]

    pa = players.setdefault(id_a, PlayerState())
    pb = players.setdefault(id_b, PlayerState())

    outcome = outcome_from_goals(goals_a, goals_b, id_a, id_b)
    rating_a = frozen_ratings[id_a] if frozen_ratings is not None else pa.rating
    rating_b = frozen_ratings[id_b] if frozen_ratings is not None else pb.rating
    elo = compute_elo(rating_a, rating_b, outcome.actual_score)

    pa.apply_match(
        players=players,
        opponent_id=id_b,
        opponent_rating_before=elo.rating_b,
        goals_for=goals_a,
        goals_against=goals_b,
        actual_score=outcome.actual_score,
        goal_difference=outcome.goal_difference,
        sum_of_goals=outcome.sum_of_goals,
        dd_for=bool(outcome.dd_player_a),
        cs_for=goals_b == 0,
        old_rating=elo.rating_a,
        new_rating=elo.new_rating_a,
        adjustment=elo.adjustment_a,
        game_id=game_id,
        game_date=game_date,
        commit_rating=commit_rating,
    )
    pb.apply_match(
        players=players,
        opponent_id=id_a,
        opponent_rating_before=elo.rating_a,
        goals_for=goals_b,
        goals_against=goals_a,
        actual_score=1.0 - outcome.actual_score if outcome.actual_score != 0.5 else 0.5,
        goal_difference=outcome.goal_difference,
        sum_of_goals=outcome.sum_of_goals,
        dd_for=bool(outcome.dd_player_b),
        cs_for=goals_a == 0,
        old_rating=elo.rating_b,
        new_rating=elo.new_rating_b,
        adjustment=elo.adjustment_b,
        game_id=game_id,
        game_date=game_date,
        commit_rating=commit_rating,
    )

    if server_records is not None:
        update_server_records_after_game(
            server_records,
            game_id=game_id,
            game_date=game_date,
            id_a=id_a,
            id_b=id_b,
            name_a=names.get(id_a, ""),
            name_b=names.get(id_b, ""),
            pa=pa,
            pb=pb,
            actual_score=outcome.actual_score,
            goal_difference=outcome.goal_difference,
            sum_of_goals=outcome.sum_of_goals,
            goals_a=goals_a,
            goals_b=goals_b,
            dd_a=bool(outcome.dd_player_a),
            dd_b=bool(outcome.dd_player_b),
            cs_a=bool(outcome.cs_player_a),
            cs_b=bool(outcome.cs_player_b),
            players=players,
            names=names,
        )

    return {
        "id": game_id,
        "idA": id_a,
        "idB": id_b,
        "RatingA": elo.rating_a,
        "RatingB": elo.rating_b,
        "ExpectedScoreA": elo.expected_a,
        "ExpectedScoreB": elo.expected_b,
        "AdjustmentA": elo.adjustment_a,
        "AdjustmentB": elo.adjustment_b,
        "NewRatingA": elo.new_rating_a,
        "NewRatingB": elo.new_rating_b,
        "RatingDifference": elo.rating_difference,
        "ActualScore": outcome.actual_score,
        "WinnerID": outcome.winner_id,
        "SumOfGoals": outcome.sum_of_goals,
        "GoalDifference": outcome.goal_difference,
        "HomeWin": outcome.home_win,
        "Draw": outcome.draw,
        "AwayWin": outcome.away_win,
        "DDPlayerA": outcome.dd_player_a,
        "DDPlayerB": outcome.dd_player_b,
        "CSPlayerA": outcome.cs_player_a,
        "CSPlayerB": outcome.cs_player_b,
    }
