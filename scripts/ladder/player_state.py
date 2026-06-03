"""Per-player career state during replay (aligned with docs/ratings_cpp.txt)."""

from __future__ import annotations

from dataclasses import dataclass, field
from datetime import datetime

from .constants import ESTABLISHED_MIN_GAMES, START_RATING

SENTINEL_LEAST_GOALS = 50
SENTINEL_LOWEST_RATING = 5000.0
SENTINEL_GOAL_RATIO = -1.0


@dataclass
class GameRecordFlags:
    new_opponent: bool = False
    new_victim: bool = False
    new_dd_victim: bool = False
    new_cs_victim: bool = False


def _ratio(num: int, den: int) -> float | None:
    if den <= 0:
        return None
    return round(num / den, 4)


def _db_count(value: int, games: int) -> int | None:
    return None if games <= 0 else value


def _goal_ratio(goals_for: int, goals_against: int, games: int) -> float | None:
    if games <= 0:
        return None
    if goals_against <= 0:
        return SENTINEL_GOAL_RATIO
    return round(goals_for / goals_against, 4)


def _transfer_record_count(
    players: dict[int, "PlayerState"],
    prev_opponent_id: int,
    new_opponent_id: int,
    *,
    opponent_attr: str,
) -> None:
    """C++ semantics: adjust counter on previous/new opponent rows, not on self."""
    if prev_opponent_id == new_opponent_id:
        return
    if prev_opponent_id > 0:
        prev = players.setdefault(prev_opponent_id, PlayerState())
        cur = getattr(prev, opponent_attr)
        setattr(prev, opponent_attr, max(0, cur - 1))
    if new_opponent_id > 0:
        nxt = players.setdefault(new_opponent_id, PlayerState())
        cur = getattr(nxt, opponent_attr)
        setattr(nxt, opponent_attr, cur + 1)


@dataclass
class PlayerState:
    rating: float = START_RATING
    display: int = 0
    games: int = 0
    wins: int = 0
    draws: int = 0
    losses: int = 0
    goals_for: int = 0
    goals_against: int = 0
    most_goals_scored: int = 0
    least_goals_scored: int = SENTINEL_LEAST_GOALS
    most_goals_conceded: int = 0
    least_goals_conceded: int = SENTINEL_LEAST_GOALS
    biggest_win_difference: int = 0
    biggest_draw_sum: int = 0
    biggest_loss_difference: int = 0
    smallest_sum_of_goals: int = SENTINEL_LEAST_GOALS
    biggest_sum_of_goals: int = 0
    double_digits: int = 0
    clean_sheets: int = 0
    double_digits_conceded: int = 0
    clean_sheets_conceded: int = 0
    different_opponents: int = 0
    different_victims: int = 0
    double_digits_victims: int = 0
    clean_sheets_victims: int = 0
    most_goals_conceded_victims: int = 0
    least_goals_scored_victims: int = 0
    biggest_loss_victims: int = 0
    different_culprits: int = 0
    double_digits_culprits: int = 0
    clean_sheets_culprits: int = 0
    most_goals_scored_culprits: int = 0
    least_goals_conceded_culprits: int = 0
    biggest_win_culprits: int = 0
    sum_opponents_rating: float = 0.0
    highest_rated_victim: float = 0.0
    lowest_rated_culprit: float = SENTINEL_LOWEST_RATING
    current_rating_ascent: float = 0.0
    biggest_rating_ascent: float = 0.0
    current_rating_descent: float = 0.0
    biggest_rating_descent: float = 0.0
    lowest_rating: float = SENTINEL_LOWEST_RATING
    peak_rating: float = 0.0
    winning_streak: int = 0
    drawing_streak: int = 0
    losing_streak: int = 0
    non_win_streak: int = 0
    non_draw_streak: int = 0
    non_loss_streak: int = 0
    longest_winning_streak: int = 0
    longest_drawing_streak: int = 0
    longest_losing_streak: int = 0
    longest_non_win_streak: int = 0
    longest_non_draw_streak: int = 0
    longest_non_loss_streak: int = 0
    score_streak: int = 0
    merchant_streak: int = 0
    exact_ten_goal_streak: int = 0
    win_margin_one_streak: int = 0
    loss_margin_one_streak: int = 0
    last_game: datetime | None = None
    last_game_id: int | None = None
    last_win_game_id: int | None = None
    last_draw_game_id: int | None = None
    last_loss_game_id: int | None = None
    lowest_rating_game_id: int | None = None
    peak_rating_game_id: int | None = None
    most_goals_scored_game_id: int | None = None
    least_goals_scored_game_id: int | None = None
    most_goals_conceded_game_id: int | None = None
    least_goals_conceded_game_id: int | None = None
    biggest_win_game_id: int | None = None
    biggest_draw_game_id: int | None = None
    biggest_loss_game_id: int | None = None
    smallest_sum_of_goals_game_id: int | None = None
    biggest_sum_of_goals_game_id: int | None = None
    most_goals_scored_victim_id: int = 0
    least_goals_conceded_victim_id: int = 0
    biggest_win_victim_id: int = 0
    most_goals_conceded_culprit_id: int = 0
    least_goals_scored_culprit_id: int = 0
    biggest_loss_culprit_id: int = 0
    highest_rated_victim_game_id: int | None = None
    lowest_rated_culprit_game_id: int | None = None
    game_flags: GameRecordFlags = field(default_factory=GameRecordFlags)
    _network_opponents: set[int] = field(default_factory=set, repr=False)
    _network_victims: set[int] = field(default_factory=set, repr=False)
    _network_dd_victims: set[int] = field(default_factory=set, repr=False)
    _network_cs_victims: set[int] = field(default_factory=set, repr=False)

    def network_opponent_count(self) -> int:
        return len(self._network_opponents)

    def network_victim_count(self) -> int:
        return len(self._network_victims)

    def network_dd_victim_count(self) -> int:
        return len(self._network_dd_victims)

    def network_cs_victim_count(self) -> int:
        return len(self._network_cs_victims)

    def _apply_career_peak_nadir(self, new_rating: float, game_id: int) -> None:
        if self.games < ESTABLISHED_MIN_GAMES:
            return
        if self.games == ESTABLISHED_MIN_GAMES:
            self.peak_rating = new_rating
            self.peak_rating_game_id = game_id
            self.lowest_rating = new_rating
            self.lowest_rating_game_id = game_id
            return
        if new_rating > self.peak_rating:
            self.peak_rating = new_rating
            self.peak_rating_game_id = game_id
        if new_rating < self.lowest_rating:
            self.lowest_rating = new_rating
            self.lowest_rating_game_id = game_id

    def apply_match(
        self,
        *,
        players: dict[int, PlayerState],
        opponent_id: int,
        opponent_rating_before: float,
        goals_for: int,
        goals_against: int,
        actual_score: float,
        goal_difference: int,
        sum_of_goals: int,
        dd_for: int,
        cs_for: int,
        old_rating: float,
        new_rating: float,
        adjustment: float,
        game_id: int,
        game_date: datetime,
    ) -> None:
        self.game_flags = GameRecordFlags()

        self.games += 1
        if self.games >= 1:
            self.display = 1

        won = actual_score == 1.0
        drew = actual_score == 0.5
        lost = actual_score == 0.0

        if won:
            self.wins += 1
        elif drew:
            self.draws += 1
        else:
            self.losses += 1

        self.goals_for += goals_for
        self.goals_against += goals_against
        self.rating = new_rating
        self.last_game = game_date
        self.last_game_id = game_id

        before_opp = len(self._network_opponents)
        self._network_opponents.add(opponent_id)
        self.game_flags.new_opponent = len(self._network_opponents) > before_opp
        self.different_opponents = len(self._network_opponents)

        if won:
            before_vic = len(self._network_victims)
            self._network_victims.add(opponent_id)
            self.game_flags.new_victim = len(self._network_victims) > before_vic
            self.different_victims = len(self._network_victims)
        if dd_for:
            before_dd = len(self._network_dd_victims)
            self._network_dd_victims.add(opponent_id)
            self.game_flags.new_dd_victim = len(self._network_dd_victims) > before_dd
            self.double_digits_victims = len(self._network_dd_victims)
        if cs_for:
            before_cs = len(self._network_cs_victims)
            self._network_cs_victims.add(opponent_id)
            self.game_flags.new_cs_victim = len(self._network_cs_victims) > before_cs
            self.clean_sheets_victims = len(self._network_cs_victims)

        self.sum_opponents_rating += opponent_rating_before

        if goals_for >= 1 and goals_for > self.most_goals_scored:
            if self.most_goals_scored_victim_id != opponent_id:
                _transfer_record_count(
                    players,
                    self.most_goals_scored_victim_id,
                    opponent_id,
                    opponent_attr="most_goals_scored_culprits",
                )
                self.most_goals_scored_victim_id = opponent_id
            self.most_goals_scored = goals_for
            self.most_goals_scored_game_id = game_id

        if goals_for < self.least_goals_scored:
            if self.least_goals_scored_culprit_id != opponent_id:
                _transfer_record_count(
                    players,
                    self.least_goals_scored_culprit_id,
                    opponent_id,
                    opponent_attr="least_goals_scored_victims",
                )
                self.least_goals_scored_culprit_id = opponent_id
            self.least_goals_scored = goals_for
            self.least_goals_scored_game_id = game_id

        if goals_against > self.most_goals_conceded:
            if self.most_goals_conceded_culprit_id != opponent_id:
                _transfer_record_count(
                    players,
                    self.most_goals_conceded_culprit_id,
                    opponent_id,
                    opponent_attr="most_goals_conceded_victims",
                )
                self.most_goals_conceded_culprit_id = opponent_id
            self.most_goals_conceded = goals_against
            self.most_goals_conceded_game_id = game_id

        if goals_against < self.least_goals_conceded:
            if self.least_goals_conceded_victim_id != opponent_id:
                _transfer_record_count(
                    players,
                    self.least_goals_conceded_victim_id,
                    opponent_id,
                    opponent_attr="least_goals_conceded_culprits",
                )
                self.least_goals_conceded_victim_id = opponent_id
            self.least_goals_conceded = goals_against
            self.least_goals_conceded_game_id = game_id

        if won and goal_difference > self.biggest_win_difference:
            if self.biggest_win_victim_id != opponent_id:
                _transfer_record_count(
                    players,
                    self.biggest_win_victim_id,
                    opponent_id,
                    opponent_attr="biggest_win_culprits",
                )
                self.biggest_win_victim_id = opponent_id
            self.biggest_win_difference = goal_difference
            self.biggest_win_game_id = game_id

        if drew and sum_of_goals > self.biggest_draw_sum:
            self.biggest_draw_sum = sum_of_goals
            self.biggest_draw_game_id = game_id

        if lost and goal_difference > self.biggest_loss_difference:
            if self.biggest_loss_culprit_id != opponent_id:
                _transfer_record_count(
                    players,
                    self.biggest_loss_culprit_id,
                    opponent_id,
                    opponent_attr="biggest_loss_victims",
                )
                self.biggest_loss_culprit_id = opponent_id
            self.biggest_loss_difference = goal_difference
            self.biggest_loss_game_id = game_id

        if sum_of_goals < self.smallest_sum_of_goals:
            self.smallest_sum_of_goals = sum_of_goals
            self.smallest_sum_of_goals_game_id = game_id
        if sum_of_goals > self.biggest_sum_of_goals:
            self.biggest_sum_of_goals = sum_of_goals
            self.biggest_sum_of_goals_game_id = game_id

        if dd_for:
            self.double_digits += 1
        if goals_against >= 10:
            self.double_digits_conceded += 1
        if cs_for:
            self.clean_sheets += 1
        if goals_for == 0:
            self.clean_sheets_conceded += 1

        if won and opponent_rating_before > self.highest_rated_victim:
            self.highest_rated_victim = opponent_rating_before
            self.highest_rated_victim_game_id = game_id
        if lost and opponent_rating_before < self.lowest_rated_culprit:
            self.lowest_rated_culprit = opponent_rating_before
            self.lowest_rated_culprit_game_id = game_id

        if new_rating > old_rating:
            self.current_rating_ascent += abs(adjustment)
            self.current_rating_descent = 0.0
        elif new_rating < old_rating:
            self.current_rating_descent += abs(adjustment)
            self.current_rating_ascent = 0.0

        if self.current_rating_ascent > self.biggest_rating_ascent:
            self.biggest_rating_ascent = self.current_rating_ascent
        if self.current_rating_descent > self.biggest_rating_descent:
            self.biggest_rating_descent = self.current_rating_descent

        self._apply_career_peak_nadir(new_rating, game_id)

        self._update_streaks(won, drew, lost)
        self._update_milestone_streaks(goals_for, goals_against, won, lost)

        if won:
            self.last_win_game_id = game_id
        elif drew:
            self.last_draw_game_id = game_id
        else:
            self.last_loss_game_id = game_id

    def _update_streaks(self, won: bool, drew: bool, lost: bool) -> None:
        if won:
            self.winning_streak += 1
            self.drawing_streak = 0
            self.losing_streak = 0
            self.non_win_streak = 0
            self.non_draw_streak += 1
            self.non_loss_streak += 1
        elif drew:
            self.winning_streak = 0
            self.drawing_streak += 1
            self.losing_streak = 0
            self.non_win_streak += 1
            self.non_draw_streak = 0
            self.non_loss_streak += 1
        else:
            self.winning_streak = 0
            self.drawing_streak = 0
            self.losing_streak += 1
            self.non_win_streak += 1
            self.non_draw_streak += 1
            self.non_loss_streak = 0

        self.longest_winning_streak = max(self.longest_winning_streak, self.winning_streak)
        self.longest_drawing_streak = max(self.longest_drawing_streak, self.drawing_streak)
        self.longest_losing_streak = max(self.longest_losing_streak, self.losing_streak)
        self.longest_non_win_streak = max(self.longest_non_win_streak, self.non_win_streak)
        self.longest_non_draw_streak = max(self.longest_non_draw_streak, self.non_draw_streak)
        self.longest_non_loss_streak = max(self.longest_non_loss_streak, self.non_loss_streak)

    def _update_milestone_streaks(
        self, goals_for: int, goals_against: int, won: bool, lost: bool
    ) -> None:
        if goals_for > 0:
            self.score_streak += 1
        else:
            self.score_streak = 0
        if goals_for >= 10:
            self.merchant_streak += 1
        else:
            self.merchant_streak = 0
        if goals_for == 10:
            self.exact_ten_goal_streak += 1
        else:
            self.exact_ten_goal_streak = 0
        margin = abs(goals_for - goals_against) if (won or lost) else 0
        if won and margin == 1:
            self.win_margin_one_streak += 1
        else:
            self.win_margin_one_streak = 0
        if lost and margin == 1:
            self.loss_margin_one_streak += 1
        else:
            self.loss_margin_one_streak = 0

    def to_db_row(self, player_id: int) -> dict:
        g = self.games
        gf, ga = self.goals_for, self.goals_against
        return {
            "ID": player_id,
            "Display": self.display if g else 0,
            "Rating": self.rating,
            "NumberGames": _db_count(g, g),
            "NumberWins": _db_count(self.wins, g),
            "NumberDraws": _db_count(self.draws, g),
            "NumberLosses": _db_count(self.losses, g),
            "WinRatio": _ratio(self.wins, g),
            "DrawRatio": _ratio(self.draws, g),
            "LossRatio": _ratio(self.losses, g),
            "GoalsFor": _db_count(gf, g),
            "GoalsAgainst": _db_count(ga, g),
            "AverageGoalsFor": round(gf / g, 4) if g else None,
            "AverageGoalsAgainst": round(ga / g, 4) if g else None,
            "GoalRatio": _goal_ratio(gf, ga, g),
            "MostGoalsScored": self.most_goals_scored or None,
            "LeastGoalsScored": self.least_goals_scored,
            "MostGoalsConceded": self.most_goals_conceded or None,
            "LeastGoalsConceded": self.least_goals_conceded,
            "BiggestWinDifference": self.biggest_win_difference or None,
            "BiggestDrawSum": self.biggest_draw_sum or None,
            "BiggestLossDifference": self.biggest_loss_difference or None,
            "SmallestSumOfGoals": self.smallest_sum_of_goals,
            "BiggestSumOfGoals": self.biggest_sum_of_goals or None,
            "DoubleDigits": self.double_digits or None,
            "CleanSheets": self.clean_sheets or None,
            "DoubleDigitsConceded": self.double_digits_conceded or None,
            "CleanSheetsConceded": self.clean_sheets_conceded or None,
            "DoubleDigitsRatio": _ratio(self.double_digits, g),
            "CleanSheetsRatio": _ratio(self.clean_sheets, g),
            "DoubleDigitsConcededRatio": _ratio(self.double_digits_conceded, g),
            "CleanSheetsConcededRatio": _ratio(self.clean_sheets_conceded, g),
            "DifferentOpponents": _db_count(self.different_opponents, g),
            "DifferentVictims": _db_count(self.different_victims, g),
            "DoubleDigitsVictims": _db_count(self.double_digits_victims, g),
            "CleanSheetsVictims": _db_count(self.clean_sheets_victims, g),
            "MostGoalsConcededVictims": _db_count(self.most_goals_conceded_victims, g),
            "LeastGoalsScoredVictims": _db_count(self.least_goals_scored_victims, g),
            "BiggestLossVictims": _db_count(self.biggest_loss_victims, g),
            "DifferentCulprits": _db_count(self.different_culprits, g),
            "DoubleDigitsCulprits": _db_count(self.double_digits_culprits, g),
            "CleanSheetsCulprits": _db_count(self.clean_sheets_culprits, g),
            "MostGoalsScoredCulprits": _db_count(self.most_goals_scored_culprits, g),
            "LeastGoalsConcededCulprits": _db_count(self.least_goals_conceded_culprits, g),
            "BiggestWinCulprits": _db_count(self.biggest_win_culprits, g),
            "SumOfOpponentsRating": self.sum_opponents_rating or None,
            "AverageOpponentRating": round(self.sum_opponents_rating / g, 3) if g else None,
            "HighestRatedVictim": self.highest_rated_victim or None,
            "LowestRatedCulprit": self.lowest_rated_culprit,
            "CurrentRatingAscent": self.current_rating_ascent or None,
            "BiggestRatingAscent": self.biggest_rating_ascent or None,
            "CurrentRatingDescent": self.current_rating_descent or None,
            "BiggestRatingDescent": self.biggest_rating_descent or None,
            "LowestRating": self.lowest_rating,
            "PeakRating": self.peak_rating or None,
            "WinningStreak": self.winning_streak or None,
            "DrawingStreak": self.drawing_streak or None,
            "LosingStreak": self.losing_streak or None,
            "NonWinStreak": self.non_win_streak or None,
            "NonDrawStreak": self.non_draw_streak or None,
            "NonLossStreak": self.non_loss_streak or None,
            "LongestWinningStreak": self.longest_winning_streak or None,
            "LongestDrawingStreak": self.longest_drawing_streak or None,
            "LongestLosingStreak": self.longest_losing_streak or None,
            "LongestNonWinStreak": self.longest_non_win_streak or None,
            "LongestNonDrawStreak": self.longest_non_draw_streak or None,
            "LongestNonLossStreak": self.longest_non_loss_streak or None,
            "ScoreStreak": self.score_streak,
            "MerchantStreak": self.merchant_streak,
            "ExactTenGoalStreak": self.exact_ten_goal_streak,
            "WinMarginOneStreak": self.win_margin_one_streak,
            "LossMarginOneStreak": self.loss_margin_one_streak,
            "LastGame": self.last_game,
            "LastGameGameID": self.last_game_id,
            "LastWinGameID": self.last_win_game_id,
            "LastDrawGameID": self.last_draw_game_id,
            "LastLossGameID": self.last_loss_game_id,
            "LowestRatingGameID": self.lowest_rating_game_id,
            "PeakRatingGameID": self.peak_rating_game_id,
            "MostGoalsScoredGameID": self.most_goals_scored_game_id,
            "LeastGoalsScoredGameID": self.least_goals_scored_game_id,
            "MostGoalsConcededGameID": self.most_goals_conceded_game_id,
            "LeastGoalsConcededGameID": self.least_goals_conceded_game_id,
            "BiggestWinGameID": self.biggest_win_game_id,
            "BiggestDrawGameID": self.biggest_draw_game_id,
            "BiggestLossGameID": self.biggest_loss_game_id,
            "SmallestSumOfGoalsGameID": self.smallest_sum_of_goals_game_id,
            "BiggestSumOfGoalsGameID": self.biggest_sum_of_goals_game_id,
            "MostGoalsScoredVictimID": self.most_goals_scored_victim_id or None,
            "LeastGoalsConcededVictimID": self.least_goals_conceded_victim_id or None,
            "BiggestWinVictimID": self.biggest_win_victim_id or None,
            "MostGoalsConcededCulpritID": self.most_goals_conceded_culprit_id or None,
            "LeastGoalsScoredCulpritID": self.least_goals_scored_culprit_id or None,
            "BiggestLossCulpritID": self.biggest_loss_culprit_id or None,
            "HighestRatedVictimGameID": self.highest_rated_victim_game_id,
            "LowestRatedCulpritGameID": self.lowest_rated_culprit_game_id,
        }
