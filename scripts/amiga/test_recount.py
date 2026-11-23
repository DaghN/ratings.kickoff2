"""Tests for the independent recount (no database). Expected numbers are worked out by hand."""

from __future__ import annotations

import ast
import datetime as dt
import sys
import unittest
from collections import Counter, defaultdict
from decimal import Decimal
from pathlib import Path

from scripts.amiga.modern import recount as module
from scripts.amiga.modern.recount import (
    NO_GOAL_RATIO,
    NOT_RECOUNTED,
    Career,
    Compare,
    Country,
    Pair,
    Realm,
    Records,
    Side,
    Tally,
    expected,
    performance,
    world_cup_page_columns,
    world_cup_player_columns,
)

SOURCE = Path(module.__file__)
#: Connection settings only. Nothing that calculates may be added here (docs/amiga-recount-policy.md).
ALLOWED_PROJECT_IMPORTS = {"scripts.amiga.config", "scripts.amiga.modern.constants"}


def side(game_id: int, me: int, opp: int, gf: int, ga: int, rating: float = 1600.0, nations=("Norway", "Italy")) -> Side:
    game = {"id": game_id, "game_date": dt.datetime(2020, 1, 1, 12, game_id % 60)}
    return Side(game, me, opp, gf, ga, rating, *nations)


def both(game_id: int, a: int, b: int, ga: int, gb: int) -> tuple[Side, Side]:
    return side(game_id, a, b, ga, gb), side(game_id, b, a, gb, ga, nations=("Italy", "Norway"))


def comparer(columns: dict[str, tuple[str, int]]) -> Compare:
    class Cursor:
        def execute(self, *args):
            pass

        def fetchall(self):
            return [{"t": "t", "c": c, "s": s, "d": d, "k": ""} for c, (d, s) in columns.items()]

    return Compare(Cursor(), "db")


class IndependenceTests(unittest.TestCase):
    def test_no_calculation_code_is_imported(self) -> None:
        found = set()
        for node in ast.walk(ast.parse(SOURCE.read_text(encoding="utf-8"))):
            if isinstance(node, ast.Import):
                found |= {a.name for a in node.names}
            elif isinstance(node, ast.ImportFrom):
                self.assertEqual(node.level, 0, "relative import")
                found.add(node.module)
        project = {m for m in found if m.split(".")[0] not in sys.stdlib_module_names and m.split(".")[0] != "pymysql"}
        self.assertEqual(project, ALLOWED_PROJECT_IMPORTS)

    def test_no_dynamic_imports(self) -> None:
        text = SOURCE.read_text(encoding="utf-8")
        for word in ("importlib", "__import__", "exec(", "eval(", "subprocess"):
            self.assertNotIn(word, text)

    def test_every_left_out_column_has_a_reason(self) -> None:
        for table, columns in NOT_RECOUNTED.items():
            for column, reason in columns.items():
                self.assertTrue(reason.strip(), f"{table}.{column}")


class RatingTests(unittest.TestCase):
    def test_equal_ratings_expect_half(self) -> None:
        self.assertEqual(expected(1600, 1600), 0.5)

    def test_four_hundred_points_expect_ten_to_one(self) -> None:
        self.assertAlmostEqual(expected(2000, 1600), 10 / 11)

    def test_performance_rating(self) -> None:
        self.assertAlmostEqual(performance([(1600, 1.0), (1600, 0.0)]), 1600, places=6)
        self.assertAlmostEqual(performance([(1600, 1.0)] * 10 + [(1600, 0.0)]), 2000, places=6)

    def test_no_performance_rating_for_one_game_or_a_clean_record(self) -> None:
        self.assertIsNone(performance([(1600, 0.5)]))
        self.assertIsNone(performance([(1600, 1.0), (1700, 1.0)]))
        self.assertIsNone(performance([(1600, 0.0), (1700, 0.0)]))


class CareerTests(unittest.TestCase):
    def setUp(self) -> None:
        self.c = Career()
        # win 3-1, win 3-1 (same margin), draw 2-2, win 10-0, loss 0-4, loss 1-2
        for s in (
            side(1, 7, 8, 3, 1), side(2, 7, 9, 3, 1, 1700), side(3, 7, 8, 2, 2), side(4, 7, 8, 10, 0),
            side(5, 7, 9, 0, 4, 1500), side(6, 7, 10, 1, 2, 1900),
        ):
            self.c.add(s)
        self.c.peak = self.c.low = (1600.0, 1)
        self.v = self.c.columns()

    def test_counts(self) -> None:
        v = self.v
        self.assertEqual((v["NumberGames"], v["NumberWins"], v["NumberDraws"], v["NumberLosses"]), (6, 3, 1, 2))
        self.assertEqual((v["GoalsFor"], v["GoalsAgainst"], v["DoubleDigits"], v["CleanSheets"], v["CleanSheetsConceded"]), (19, 10, 1, 1, 1))
        self.assertEqual((v["DifferentOpponents"], v["DifferentVictims"], v["DifferentCulprits"]), (3, 2, 2))
        self.assertAlmostEqual(v["WinRatio"], 0.5)

    def test_first_game_keeps_the_record(self) -> None:
        self.assertEqual((self.v["LeastGoalsScored"], self.v["LeastGoalsScoredGameID"]), (0, 5))
        self.assertEqual((self.v["BiggestWinDifference"], self.v["BiggestWinGameID"], self.v["BiggestWinVictimID"]), (10, 4, 8))
        c = Career()
        c.add(side(1, 7, 8, 3, 1))
        c.add(side(2, 7, 9, 3, 1))
        self.assertEqual((c.game("win"), c.against("win")), (1, 8))

    def test_victims_and_culprits(self) -> None:
        self.assertEqual((self.v["HighestRatedVictim"], self.v["HighestRatedVictimGameID"]), (1700, 2))
        self.assertEqual((self.v["LowestRatedCulprit"], self.v["LowestRatedCulpritGameID"]), (1500, 5))
        self.assertAlmostEqual(self.v["SumOfOpponentsRating"], 1600 + 1700 + 1600 + 1600 + 1500 + 1900)

    def test_streaks(self) -> None:
        v = self.v
        self.assertEqual((v["LongestWinningStreak"], v["WinningStreak"], v["LosingStreak"], v["LongestNonLossStreak"]), (2, 0, 2, 4))
        self.assertEqual((v["LossMarginOneStreak"], v["ScoreStreak"]), (1, 1))

    def test_goal_ratio_without_a_goal_conceded(self) -> None:
        c = Career()
        c.add(side(1, 7, 8, 3, 0))
        c.peak = c.low = (1600.0, 1)
        self.assertEqual(c.columns()["GoalRatio"], NO_GOAL_RATIO)

    def test_nil_nil_is_no_draw_record(self) -> None:
        t = Tally()
        t.add(side(1, 7, 8, 0, 0))
        self.assertIsNone(t.top("draw"))
        self.assertEqual(t.top("least_goals"), 0)

    def test_best_year_is_the_earliest_on_a_tie(self) -> None:
        self.c.year_games.update({2010: 5, 2008: 5, 2009: 4})
        v = self.c.columns()
        self.assertEqual((v["peak_year_games"], v["peak_year_games_year"]), (5, 2008))


class PairTests(unittest.TestCase):
    def test_columns(self) -> None:
        p = Pair()
        p.sides += [side(1, 7, 8, 3, 1), side(2, 7, 8, 0, 0), side(3, 7, 8, 1, 12)]
        v = p.columns()
        self.assertEqual((v["games"], v["wins"], v["draws"], v["losses"], v["goals_for"], v["goals_against"]), (3, 1, 1, 1, 4, 13))
        self.assertEqual((v["max_win_margin"], v["max_loss_margin"], v["max_draw_goals"], v["max_goal_sum"], v["min_goal_sum"]), (2, 11, 0, 13, 0))
        self.assertEqual((v["dd_wins"], v["dd_losses"], v["cs_wins"], v["cs_losses"]), (0, 1, 1, 1))

    def test_no_win_has_no_win_margin(self) -> None:
        p = Pair()
        p.sides.append(side(1, 7, 8, 0, 1))
        self.assertIsNone(p.columns()["max_win_margin"])


class RealmTests(unittest.TestCase):
    def setUp(self) -> None:
        self.r = Realm()
        self.r.add_game("2020", "Italy", both(1, 7, 8, 10, 1), True)
        self.r.add_game("2020", "Italy", both(2, 7, 8, 1, 1), False)
        self.r.add_tournament("2020", "Italy")

    def test_facts(self) -> None:
        f = self.r.all_facts()
        self.assertEqual(f[("year", "2020", "realm", "*", "games", "game")], 2)
        self.assertEqual(f[("year", "2020", "realm", "*", "draws", "game")], 1)
        self.assertEqual(f[("year", "2020", "realm", "*", "high_scoring_games", "game")], 1)
        self.assertEqual(f[("year", "2020", "realm", "*", "low_scoring_games", "game")], 1)
        self.assertEqual(f[("year", "2020", "world_cup", "*", "goals", "game")], 11)
        self.assertEqual(f[("all_time", "*", "player_nationality", "Norway", "goals", "participant")], 11)
        self.assertEqual(f[("all_time", "*", "host_country", "Italy", "tournaments", "game")], 1)
        self.assertEqual(f[("year", "2020", "realm", "*", "player_debuts", "game")], 2)

    def test_headline(self) -> None:
        v = self.r.headline({})
        self.assertEqual((v["GamesPlayed"], v["NumberOfDraws"], v["NumberOfDecidedGames"], v["WcGamesPlayed"]), (2, 1, 1, 1))
        self.assertAlmostEqual(v["DrawsRatio"], 0.5)


class RecordTests(unittest.TestCase):
    def setUp(self) -> None:
        self.r = Records()
        self.r.add_game(both(1, 9, 8, 5, 0), dt.date(2020, 1, 1))
        self.r.add_game(both(2, 7, 8, 5, 0), dt.date(2020, 2, 1))
        self.names = {7: "A", 8: "B", 9: "C"}

    def test_first_game_keeps_a_tied_record(self) -> None:
        v = self.r.columns({}, self.names)
        self.assertEqual((v["BiggestWinDifference"], v["BiggestWinDifferenceGameID"], v["BiggestWinDifferenceID"]), (5, 1, 9))
        self.assertEqual(v["MostGoalsScoredInOneGameDate"], dt.date(2020, 1, 1))

    def test_a_better_game_takes_it(self) -> None:
        self.r.add_game(both(3, 7, 8, 6, 0), dt.date(2020, 3, 1))
        v = self.r.columns({}, self.names)
        self.assertEqual((v["BiggestWinDifference"], v["BiggestWinDifferenceGameID"], v["BiggestWinDifferenceID"]), (6, 3, 7))

    def test_career_record_tie_goes_to_the_lowest_number(self) -> None:
        careers = {}
        for pid in (9, 7):
            c = careers[pid] = Career()
            c.values = defaultdict(int, {"NumberGames": 4})
            c.rise = {"number_games": (3, dt.date(2020, 3, 1))}
        v = self.r.columns(careers, self.names)
        self.assertEqual((v["MostGamesPlayed"], v["MostGamesPlayedID"], v["MostGamesPlayedName"]), (4, 7, "A"))
        self.assertIsNone(v["BiggestWinRatio"])  # nobody has twenty games

    def test_ratio_record_needs_twenty_games(self) -> None:
        careers = {}
        for pid, games, share in ((7, 20, 0.5), (9, 19, 0.9)):
            c = careers[pid] = Career()
            c.n["games"] = games
            c.values = defaultdict(int, {"WinRatio": share})
        v = self.r.columns(careers, self.names)
        self.assertEqual((v["BiggestWinRatio"], v["BiggestWinRatioID"]), (0.5, 7))


class WorldCupTests(unittest.TestCase):
    def test_page(self) -> None:
        games = [both(1, 7, 8, 6, 1), both(2, 7, 9, 2, 2), both(3, 8, 9, 5, 5)]
        t = {"name": "World Cup I (Test)", "event_date": dt.date(2020, 1, 1), "chrono": 1, "country": "Italy"}
        v = world_cup_page_columns(t, games, {7: 1, 8: 2, 9: 3}, 2, 6)
        self.assertEqual((v["rated_games"], v["draws"], v["decided_games"], v["goals"]), (3, 2, 1, 21))
        self.assertEqual((v["high_scoring_games"], v["low_scoring_games"], v["blowout_games"]), (1, 0, 1))
        self.assertEqual((v["highest_scoring_draw_sum"], v["highest_scoring_draw_game_id"]), (10, 3))
        self.assertEqual((v["gold_player_id"], v["silver_player_id"], v["bronze_player_id"], v["champion_game_count"]), (7, 8, 9, 2))
        self.assertEqual((v["distinct_opponent_pairs"], v["first_time_wc_players"], v["share_of_year_games"]), (3, 2, 0.5))

    def test_player_and_country(self) -> None:
        t = Tally()
        for s in (side(1, 7, 8, 12, 0), side(2, 7, 8, 1, 1)):
            t.add(s)
        v = world_cup_player_columns(t, Counter(played=1, bronze=1))
        self.assertEqual((v["points"], v["podiums"], v["double_digits"], v["different_victims"], v["biggest_draw_sum"]), (4, 1, 1, 1, 2))
        n = Country()
        n.all, n.players, n.entries, n.world_cups, n.international = t, {7}, 1, {5}, 2
        c = n.columns("Norway", Counter(tournaments=2, player_games=8, goals=20), {7: t})
        self.assertEqual((c["points_per_realm_wc"], c["win_rate"], c["games_share"], c["most_goals_scored"]), (2, 0.75, 0.25, 12))


class CompareTests(unittest.TestCase):
    def test_rounding_is_allowed_one_step_only(self) -> None:
        c = comparer({"share": ("decimal", 4), "count": ("int", 0), "Rating": ("decimal", 6), "day": ("date", 0)})
        c.value("t", "share", 1, 0.33333333, Decimal("0.3333"))
        c.value("t", "count", 1, 0, None)
        c.value("t", "Rating", 1, 1600.0011, Decimal("1600.000000"))
        c.value("t", "day", 1, dt.date(2020, 1, 1), "2020-01-01")
        self.assertEqual(dict(c.different), {})
        c.value("t", "share", 1, 0.3331, Decimal("0.3333"))
        c.value("t", "count", 1, 1, None)
        c.value("t", "Rating", 1, 1600.01, Decimal("1600.000000"))
        c.value("t", "day", 1, dt.date(2020, 1, 1), "2020-01-02")
        self.assertEqual(dict(c.different), {"t.share": 1, "t.count": 1, "t.Rating": 1, "t.day": 1})

    def test_missing_and_extra_rows(self) -> None:
        c = comparer({"count": ("int", 0)})
        c.rows("t", {1: {"count": 1}, 2: {"count": 2}}, {1: {"count": 1}, 3: {"count": 3}})
        self.assertEqual(c.different["t.(row)"], 2)

    def test_performance_rating_is_checked_by_its_equation(self) -> None:
        c = comparer({"performance_rating": ("decimal", 6)})
        results = [(1600.0, 1.0)] * 10 + [(1600.0, 0.0)]
        c.equation("t", 1, results, Decimal("2000.000000"))
        c.equation("t", 1, [(1600.0, 1.0), (1600.0, 1.0)], None)
        self.assertEqual(dict(c.different), {})
        c.equation("t", 1, results, Decimal("2001.000000"))
        c.equation("t", 1, results, None)
        c.equation("t", 1, [(1600.0, 1.0), (1600.0, 1.0)], Decimal("2000"))
        self.assertEqual(c.different["t.performance_rating"], 3)

    def test_a_column_without_a_recount_is_reported(self) -> None:
        c = comparer({"known": ("int", 0), "brand_new": ("int", 0)})
        c.row("t", 1, {"known": 1}, {"known": 1, "brand_new": 5})
        self.assertEqual(c.uncovered(), ["t.brand_new"])


if __name__ == "__main__":
    unittest.main()
