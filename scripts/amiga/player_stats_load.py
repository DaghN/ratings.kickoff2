"""Load prior event snapshots into PlayerState for ops/finalize bootstrap (S4)."""

from __future__ import annotations

from datetime import datetime
from typing import Any

import pymysql

from scripts.k2_rating_core.constants import START_RATING
from scripts.k2_rating_core.player_state import PlayerState, SENTINEL_LEAST_GOALS, SENTINEL_LOWEST_RATING


def _int(row: dict[str, Any], key: str, default: int = 0) -> int:
    val = row.get(key)
    if val is None:
        return default
    return int(val)


def _float(row: dict[str, Any], key: str, default: float) -> float:
    val = row.get(key)
    if val is None:
        return default
    return float(val)


def player_state_from_stats_row(row: dict[str, Any]) -> PlayerState:
    """Map one career row (current or legacy stats) to in-memory career state."""
    games = _int(row, "NumberGames")
    st = PlayerState()
    st.rating = _float(row, "Rating", START_RATING)
    st.display = _int(row, "Display")
    st.games = games
    st.wins = _int(row, "NumberWins")
    st.draws = _int(row, "NumberDraws")
    st.losses = _int(row, "NumberLosses")
    st.goals_for = _int(row, "GoalsFor")
    st.goals_against = _int(row, "GoalsAgainst")
    st.most_goals_scored = _int(row, "MostGoalsScored")
    st.least_goals_scored = _int(row, "LeastGoalsScored", SENTINEL_LEAST_GOALS)
    st.most_goals_conceded = _int(row, "MostGoalsConceded")
    st.least_goals_conceded = _int(row, "LeastGoalsConceded", SENTINEL_LEAST_GOALS)
    st.biggest_win_difference = _int(row, "BiggestWinDifference")
    st.biggest_draw_sum = _int(row, "BiggestDrawSum")
    st.biggest_loss_difference = _int(row, "BiggestLossDifference")
    st.smallest_sum_of_goals = _int(row, "SmallestSumOfGoals", SENTINEL_LEAST_GOALS)
    st.biggest_sum_of_goals = _int(row, "BiggestSumOfGoals")
    st.double_digits = _int(row, "DoubleDigits")
    st.clean_sheets = _int(row, "CleanSheets")
    st.double_digits_conceded = _int(row, "DoubleDigitsConceded")
    st.clean_sheets_conceded = _int(row, "CleanSheetsConceded")
    st.different_opponents = _int(row, "DifferentOpponents")
    st.different_victims = _int(row, "DifferentVictims")
    st.double_digits_victims = _int(row, "DoubleDigitsVictims")
    st.clean_sheets_victims = _int(row, "CleanSheetsVictims")
    st.most_goals_conceded_victims = _int(row, "MostGoalsConcededVictims")
    st.least_goals_scored_victims = _int(row, "LeastGoalsScoredVictims")
    st.biggest_loss_victims = _int(row, "BiggestLossVictims")
    st.different_culprits = _int(row, "DifferentCulprits")
    st.double_digits_culprits = _int(row, "DoubleDigitsCulprits")
    st.clean_sheets_culprits = _int(row, "CleanSheetsCulprits")
    st.most_goals_scored_culprits = _int(row, "MostGoalsScoredCulprits")
    st.least_goals_conceded_culprits = _int(row, "LeastGoalsConcededCulprits")
    st.biggest_win_culprits = _int(row, "BiggestWinCulprits")
    st.sum_opponents_rating = _float(row, "SumOfOpponentsRating", 0.0)
    st.highest_rated_victim = _float(row, "HighestRatedVictim", 0.0)
    st.lowest_rated_culprit = _float(row, "LowestRatedCulprit", SENTINEL_LOWEST_RATING)
    st.current_rating_ascent = _float(row, "CurrentRatingAscent", 0.0)
    st.biggest_rating_ascent = _float(row, "BiggestRatingAscent", 0.0)
    st.current_rating_descent = _float(row, "CurrentRatingDescent", 0.0)
    st.biggest_rating_descent = _float(row, "BiggestRatingDescent", 0.0)
    st.lowest_rating = _float(row, "LowestRating", SENTINEL_LOWEST_RATING)
    st.peak_rating = _float(row, "PeakRating", 0.0)
    st.winning_streak = _int(row, "WinningStreak")
    st.drawing_streak = _int(row, "DrawingStreak")
    st.losing_streak = _int(row, "LosingStreak")
    st.non_win_streak = _int(row, "NonWinStreak")
    st.non_draw_streak = _int(row, "NonDrawStreak")
    st.non_loss_streak = _int(row, "NonLossStreak")
    st.longest_winning_streak = _int(row, "LongestWinningStreak")
    st.longest_drawing_streak = _int(row, "LongestDrawingStreak")
    st.longest_losing_streak = _int(row, "LongestLosingStreak")
    st.longest_non_win_streak = _int(row, "LongestNonWinStreak")
    st.longest_non_draw_streak = _int(row, "LongestNonDrawStreak")
    st.longest_non_loss_streak = _int(row, "LongestNonLossStreak")
    st.score_streak = _int(row, "ScoreStreak")
    st.merchant_streak = _int(row, "MerchantStreak")
    st.exact_ten_goal_streak = _int(row, "ExactTenGoalStreak")
    st.win_margin_one_streak = _int(row, "WinMarginOneStreak")
    st.loss_margin_one_streak = _int(row, "LossMarginOneStreak")

    last_game = row.get("LastGame")
    if isinstance(last_game, datetime):
        st.last_game = last_game

    for attr, col in (
        ("last_game_id", "LastGameGameID"),
        ("last_win_game_id", "LastWinGameID"),
        ("last_draw_game_id", "LastDrawGameID"),
        ("last_loss_game_id", "LastLossGameID"),
        ("lowest_rating_game_id", "LowestRatingGameID"),
        ("peak_rating_game_id", "PeakRatingGameID"),
        ("most_goals_scored_game_id", "MostGoalsScoredGameID"),
        ("least_goals_scored_game_id", "LeastGoalsScoredGameID"),
        ("most_goals_conceded_game_id", "MostGoalsConcededGameID"),
        ("least_goals_conceded_game_id", "LeastGoalsConcededGameID"),
        ("biggest_win_game_id", "BiggestWinGameID"),
        ("biggest_draw_game_id", "BiggestDrawGameID"),
        ("biggest_loss_game_id", "BiggestLossGameID"),
        ("smallest_sum_of_goals_game_id", "SmallestSumOfGoalsGameID"),
        ("biggest_sum_of_goals_game_id", "BiggestSumOfGoalsGameID"),
        ("highest_rated_victim_game_id", "HighestRatedVictimGameID"),
        ("lowest_rated_culprit_game_id", "LowestRatedCulpritGameID"),
    ):
        val = row.get(col)
        if val is not None:
            setattr(st, attr, int(val))

    for attr, col in (
        ("most_goals_scored_victim_id", "MostGoalsScoredVictimID"),
        ("least_goals_conceded_victim_id", "LeastGoalsConcededVictimID"),
        ("biggest_win_victim_id", "BiggestWinVictimID"),
        ("most_goals_conceded_culprit_id", "MostGoalsConcededCulpritID"),
        ("least_goals_scored_culprit_id", "LeastGoalsScoredCulpritID"),
        ("biggest_loss_culprit_id", "BiggestLossCulpritID"),
    ):
        val = row.get(col)
        setattr(st, attr, int(val) if val is not None else 0)

    return st


def load_prior_snapshot_rows_before_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    player_ids: list[int],
) -> dict[int, dict[str, Any]]:
    """Latest participated snapshot per player strictly before ``tournament_id``."""
    if not player_ids:
        return {}

    placeholders = ", ".join(["%s"] * len(player_ids))
    with conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT ranked.*
            FROM (
                SELECT s_inner.*,
                       ROW_NUMBER() OVER (
                           PARTITION BY s_inner.player_id
                           ORDER BY s_inner.event_date DESC, s_inner.event_chrono DESC,
                                    s_inner.tournament_id DESC
                       ) AS rn
                FROM amiga_player_event_snapshots s_inner
                INNER JOIN tournaments tc ON tc.id = %s
                WHERE s_inner.player_id IN ({placeholders})
                  AND (
                    s_inner.event_date < tc.event_date
                    OR (s_inner.event_date = tc.event_date AND s_inner.event_chrono < tc.chrono)
                    OR (
                      s_inner.event_date = tc.event_date
                      AND s_inner.event_chrono = tc.chrono
                      AND s_inner.tournament_id < tc.id
                    )
                  )
            ) ranked
            WHERE ranked.rn = 1
            """,
            (tournament_id, *player_ids),
        )
        rows = cur.fetchall()

    out: dict[int, dict[str, Any]] = {}
    for row in rows:
        clean = dict(row)
        clean.pop("rn", None)
        out[int(clean["player_id"])] = clean
    return out


def load_player_states_before_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    participant_ids: set[int] | list[int],
) -> dict[int, PlayerState]:
    """Bootstrap in-memory career state from prior snapshots (S4 — not ``amiga_player_current``)."""
    ids = sorted({int(pid) for pid in participant_ids})
    rows = load_prior_snapshot_rows_before_tournament(conn, tournament_id, ids)
    return {pid: player_state_from_stats_row(row) for pid, row in rows.items()}
