"""Server hall-of-fame (generalstatstable row id=1) during chronological replay.

Tie policy (a): first holder keeps the record until strictly beaten (use >, not >=).
Ratio/average leaders are not stored here — see playertable + server2.php queries.
"""

from __future__ import annotations

from dataclasses import dataclass, field
from datetime import datetime
from typing import Any

from .player_state import PlayerState


def _fmt_date(value: Any) -> str | None:
    if value is None:
        return None
    return str(value)


@dataclass
class HolderInt:
    value: int = 0
    holder_id: int = 0
    holder_name: str = ""
    date: Any = None
    game_id: int | None = None


@dataclass
class HolderFloat:
    value: float = 0.0
    holder_id: int = 0
    holder_name: str = ""
    date: Any = None


@dataclass
class HolderPair:
    value: int = 0
    holder_id_a: int = 0
    holder_id_b: int = 0
    holder_name_a: str = ""
    holder_name_b: str = ""
    date: Any = None
    game_id: int | None = None


@dataclass
class ServerRecordState:
    most_games: HolderInt = field(default_factory=HolderInt)
    most_wins: HolderInt = field(default_factory=HolderInt)
    most_goals: HolderInt = field(default_factory=HolderInt)
    most_goals_one_game: HolderInt = field(default_factory=HolderInt)
    biggest_win_margin: HolderInt = field(default_factory=HolderInt)
    biggest_draw_sum: HolderPair = field(default_factory=HolderPair)
    biggest_sum_goals: HolderPair = field(default_factory=HolderPair)
    most_double_digits: HolderInt = field(default_factory=HolderInt)
    most_clean_sheets: HolderInt = field(default_factory=HolderInt)
    most_different_opponents: HolderInt = field(default_factory=HolderInt)
    most_different_victims: HolderInt = field(default_factory=HolderInt)
    most_dd_victims: HolderInt = field(default_factory=HolderInt)
    most_cs_victims: HolderInt = field(default_factory=HolderInt)
    biggest_rating_ascent: HolderFloat = field(default_factory=HolderFloat)
    biggest_peak_rating: HolderFloat = field(default_factory=HolderFloat)
    longest_win_streak: HolderInt = field(default_factory=HolderInt)
    longest_draw_streak: HolderInt = field(default_factory=HolderInt)
    longest_non_loss_streak: HolderInt = field(default_factory=HolderInt)


def _try_int_max(
    h: HolderInt,
    value: int,
    holder_id: int,
    holder_name: str,
    game_date: Any,
    *,
    game_id: int | None = None,
) -> None:
    if value > h.value:
        h.value = value
        h.holder_id = holder_id
        h.holder_name = holder_name
        h.date = game_date
        h.game_id = game_id


def _try_float_max(
    h: HolderFloat,
    value: float,
    holder_id: int,
    holder_name: str,
    game_date: Any,
) -> None:
    if value > h.value:
        h.value = value
        h.holder_id = holder_id
        h.holder_name = holder_name
        h.date = game_date


def _try_float_min(
    h: HolderFloat,
    value: float,
    holder_id: int,
    holder_name: str,
    game_date: Any,
) -> None:
    if h.value == 0.0 or value < h.value:
        h.value = value
        h.holder_id = holder_id
        h.holder_name = holder_name
        h.date = game_date


def _try_pair_max(
    h: HolderPair,
    value: int,
    id_a: int,
    id_b: int,
    name_a: str,
    name_b: str,
    game_date: Any,
    game_id: int,
) -> None:
    if value > h.value:
        h.value = value
        h.holder_id_a = id_a
        h.holder_id_b = id_b
        h.holder_name_a = name_a
        h.holder_name_b = name_b
        h.date = game_date
        h.game_id = game_id


def update_server_records_after_game(
    state: ServerRecordState,
    *,
    game_id: int,
    game_date: datetime | Any,
    id_a: int,
    id_b: int,
    name_a: str,
    name_b: str,
    pa: PlayerState,
    pb: PlayerState,
    actual_score: float,
    goal_difference: int,
    sum_of_goals: int,
    goals_a: int,
    goals_b: int,
    dd_a: bool,
    dd_b: bool,
    cs_a: bool,
    cs_b: bool,
    players: dict[int, PlayerState],
    names: dict[int, str],
) -> None:
    """Apply PG-004 tie policy after one replayed game (A then B, matching C++ order)."""

    for pid, pname, st in (
        (id_a, name_a, pa),
        (id_b, name_b, pb),
    ):
        _try_int_max(state.most_games, st.games, pid, pname, game_date)
        _try_int_max(state.most_wins, st.wins, pid, pname, game_date)
        _try_int_max(state.most_goals, st.goals_for, pid, pname, game_date)

        if st.game_flags.new_opponent:
            _try_int_max(
                state.most_different_opponents,
                st.network_opponent_count(),
                pid,
                pname,
                game_date,
            )
        if st.game_flags.new_victim:
            _try_int_max(
                state.most_different_victims,
                st.network_victim_count(),
                pid,
                pname,
                game_date,
            )
        if st.game_flags.new_dd_victim:
            _try_int_max(
                state.most_dd_victims,
                st.network_dd_victim_count(),
                pid,
                pname,
                game_date,
            )
        if st.game_flags.new_cs_victim:
            _try_int_max(
                state.most_cs_victims,
                st.network_cs_victim_count(),
                pid,
                pname,
                game_date,
            )

        if (dd_a and pid == id_a) or (dd_b and pid == id_b):
            _try_int_max(state.most_double_digits, st.double_digits, pid, pname, game_date)
        if (cs_a and pid == id_a) or (cs_b and pid == id_b):
            _try_int_max(state.most_clean_sheets, st.clean_sheets, pid, pname, game_date)

        _try_float_max(
            state.biggest_rating_ascent,
            st.current_rating_ascent,
            pid,
            pname,
            game_date,
        )
        _try_float_max(state.biggest_peak_rating, st.peak_rating, pid, pname, game_date)

        # Use career longest (not current streak): matches intended C++ fix
        # LongestWinningStreakA > LongestWinningStreakS — avoids date refresh on every
        # game after the record is set (see PG-004c).
        _try_int_max(
            state.longest_win_streak,
            st.longest_winning_streak,
            pid,
            pname,
            game_date,
        )
        _try_int_max(
            state.longest_non_loss_streak,
            st.longest_non_loss_streak,
            pid,
            pname,
            game_date,
        )
        if actual_score == 0.5:
            _try_int_max(
                state.longest_draw_streak,
                st.longest_drawing_streak,
                pid,
                pname,
                game_date,
            )

    _try_int_max(
        state.most_goals_one_game,
        goals_a,
        id_a,
        name_a,
        game_date,
        game_id=game_id,
    )
    _try_int_max(
        state.most_goals_one_game,
        goals_b,
        id_b,
        name_b,
        game_date,
        game_id=game_id,
    )

    if actual_score == 1.0:
        _try_int_max(
            state.biggest_win_margin,
            goal_difference,
            id_a,
            name_a,
            game_date,
            game_id=game_id,
        )
    elif actual_score == 0.0:
        _try_int_max(
            state.biggest_win_margin,
            goal_difference,
            id_b,
            name_b,
            game_date,
            game_id=game_id,
        )

    if actual_score == 0.5:
        _try_pair_max(
            state.biggest_draw_sum,
            sum_of_goals,
            id_a,
            id_b,
            name_a,
            name_b,
            game_date,
            game_id,
        )

    _try_pair_max(
        state.biggest_sum_goals,
        sum_of_goals,
        id_a,
        id_b,
        name_a,
        name_b,
        game_date,
        game_id,
    )


def holder_patch(state: ServerRecordState) -> dict[str, Any]:
    """Map in-memory holders to generalstatstable column names."""
    s = state
    return {
        "MostGamesPlayed": s.most_games.value,
        "MostGamesPlayedID": s.most_games.holder_id,
        "MostGamesPlayedName": s.most_games.holder_name,
        "MostGamesPlayedDate": _fmt_date(s.most_games.date),
        "MostWins": s.most_wins.value,
        "MostWinsID": s.most_wins.holder_id,
        "MostWinsName": s.most_wins.holder_name,
        "MostWinsDate": _fmt_date(s.most_wins.date),
        "MostGoalsScored": s.most_goals.value,
        "MostGoalsScoredID": s.most_goals.holder_id,
        "MostGoalsScoredName": s.most_goals.holder_name,
        "MostGoalsScoredDate": _fmt_date(s.most_goals.date),
        "MostGoalsScoredInOneGame": s.most_goals_one_game.value,
        "MostGoalsScoredInOneGameID": s.most_goals_one_game.holder_id,
        "MostGoalsScoredInOneGameName": s.most_goals_one_game.holder_name,
        "MostGoalsScoredInOneGameDate": _fmt_date(s.most_goals_one_game.date),
        "MostGoalsScoredInOneGameGameID": s.most_goals_one_game.game_id,
        "BiggestWinDifference": s.biggest_win_margin.value,
        "BiggestWinDifferenceID": s.biggest_win_margin.holder_id,
        "BiggestWinDifferenceName": s.biggest_win_margin.holder_name,
        "BiggestWinDifferenceDate": _fmt_date(s.biggest_win_margin.date),
        "BiggestWinDifferenceGameID": s.biggest_win_margin.game_id,
        "BiggestDrawSum": s.biggest_draw_sum.value,
        "BiggestDrawSumIDA": s.biggest_draw_sum.holder_id_a,
        "BiggestDrawSumIDB": s.biggest_draw_sum.holder_id_b,
        "BiggestDrawSumNameA": s.biggest_draw_sum.holder_name_a,
        "BiggestDrawSumNameB": s.biggest_draw_sum.holder_name_b,
        "BiggestDrawSumDate": _fmt_date(s.biggest_draw_sum.date),
        "BiggestDrawSumGameID": s.biggest_draw_sum.game_id,
        "BiggestSumOfGoals": s.biggest_sum_goals.value,
        "BiggestSumOfGoalsIDA": s.biggest_sum_goals.holder_id_a,
        "BiggestSumOfGoalsIDB": s.biggest_sum_goals.holder_id_b,
        "BiggestSumOfGoalsNameA": s.biggest_sum_goals.holder_name_a,
        "BiggestSumOfGoalsNameB": s.biggest_sum_goals.holder_name_b,
        "BiggestSumOfGoalsDate": _fmt_date(s.biggest_sum_goals.date),
        "BiggestSumOfGoalsGameID": s.biggest_sum_goals.game_id,
        "MostDoubleDigits": s.most_double_digits.value,
        "MostDoubleDigitsID": s.most_double_digits.holder_id,
        "MostDoubleDigitsName": s.most_double_digits.holder_name,
        "MostDoubleDigitsDate": _fmt_date(s.most_double_digits.date),
        "MostCleanSheets": s.most_clean_sheets.value,
        "MostCleanSheetsID": s.most_clean_sheets.holder_id,
        "MostCleanSheetsName": s.most_clean_sheets.holder_name,
        "MostCleanSheetsDate": _fmt_date(s.most_clean_sheets.date),
        "MostDifferentOpponents": s.most_different_opponents.value,
        "MostDifferentOpponentsID": s.most_different_opponents.holder_id,
        "MostDifferentOpponentsName": s.most_different_opponents.holder_name,
        "MostDifferentOpponentsDate": _fmt_date(s.most_different_opponents.date),
        "MostDifferentVictims": s.most_different_victims.value,
        "MostDifferentVictimsID": s.most_different_victims.holder_id,
        "MostDifferentVictimsName": s.most_different_victims.holder_name,
        "MostDifferentVictimsDate": _fmt_date(s.most_different_victims.date),
        "MostDoubleDigitsVictims": s.most_dd_victims.value,
        "MostDoubleDigitsVictimsID": s.most_dd_victims.holder_id,
        "MostDoubleDigitsVictimsName": s.most_dd_victims.holder_name,
        "MostDoubleDigitsVictimsDate": _fmt_date(s.most_dd_victims.date),
        "MostCleanSheetsVictims": s.most_cs_victims.value,
        "MostCleanSheetsVictimsID": s.most_cs_victims.holder_id,
        "MostCleanSheetsVictimsName": s.most_cs_victims.holder_name,
        "MostCleanSheetsVictimsDate": _fmt_date(s.most_cs_victims.date),
        "BiggestRatingAscent": s.biggest_rating_ascent.value or None,
        "BiggestRatingAscentID": s.biggest_rating_ascent.holder_id,
        "BiggestRatingAscentName": s.biggest_rating_ascent.holder_name,
        "BiggestRatingAscentDate": _fmt_date(s.biggest_rating_ascent.date),
        "BiggestPeakRating": s.biggest_peak_rating.value or None,
        "BiggestPeakRatingID": s.biggest_peak_rating.holder_id,
        "BiggestPeakRatingName": s.biggest_peak_rating.holder_name,
        "BiggestPeakRatingDate": _fmt_date(s.biggest_peak_rating.date),
        "LongestWinningStreak": s.longest_win_streak.value,
        "LongestWinningStreakID": s.longest_win_streak.holder_id,
        "LongestWinningStreakName": s.longest_win_streak.holder_name,
        "LongestWinningStreakDate": _fmt_date(s.longest_win_streak.date),
        "LongestDrawingStreak": s.longest_draw_streak.value,
        "LongestDrawingStreakID": s.longest_draw_streak.holder_id,
        "LongestDrawingStreakName": s.longest_draw_streak.holder_name,
        "LongestDrawingStreakDate": _fmt_date(s.longest_draw_streak.date),
        "LongestNonLossStreak": s.longest_non_loss_streak.value,
        "LongestNonLossStreakID": s.longest_non_loss_streak.holder_id,
        "LongestNonLossStreakName": s.longest_non_loss_streak.holder_name,
        "LongestNonLossStreakDate": _fmt_date(s.longest_non_loss_streak.date),
    }
