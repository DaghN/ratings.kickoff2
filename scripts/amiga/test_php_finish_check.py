"""Tests for the php-finish-check comparison (no database)."""

from __future__ import annotations

import datetime as dt
import unittest
from decimal import Decimal

from scripts.amiga.modern.php_finish_check import (
    CHECK_DB,
    GROUND_TABLES,
    KNOWN_DIFFERENT_COLUMNS,
    _PHP_DRIVER,
    Column,
    calculation_files,
    classify,
    compare_rows,
)

RATING = Column("Rating", "decimal", 6)
RATIO = Column("WinRatio", "decimal", 4)
AVERAGE = Column("GamesPlayedAverage", "decimal", 3)
COUNT = Column("NumberGames", "int", 0)
WHOLE = Column("whole", "decimal", 0)
CHRONO = Column("event_chrono", "double")
KEY = Column("player_id", "int", 0)


class ClassifyTests(unittest.TestCase):
    def test_equal_values(self) -> None:
        self.assertEqual(classify(COUNT, 5, 5), "same")
        self.assertEqual(classify(RATING, None, None), "same")

    def test_counts_must_be_equal(self) -> None:
        self.assertEqual(classify(COUNT, 5, 6), "different")
        self.assertEqual(classify(WHOLE, Decimal("5"), Decimal("6")), "different")

    def test_missing_value_is_different(self) -> None:
        self.assertEqual(classify(RATING, None, Decimal("0.000000")), "different")
        self.assertEqual(classify(COUNT, 0, None), "different")

    def test_one_step_in_the_last_decimal_is_rounding(self) -> None:
        self.assertEqual(classify(RATIO, Decimal("0.0062"), Decimal("0.0063")), "rounding")
        self.assertEqual(classify(AVERAGE, Decimal("28.063"), Decimal("28.062")), "rounding")

    def test_two_steps_are_different(self) -> None:
        self.assertEqual(classify(RATIO, Decimal("0.0061"), Decimal("0.0063")), "different")
        self.assertEqual(classify(AVERAGE, Decimal("28.064"), Decimal("28.062")), "different")

    def test_ratings_some_hundred_thousandths_apart_are_rounding(self) -> None:
        difference = Column("rating_difference", "decimal", 6)
        self.assertEqual(classify(difference, Decimal("-161.746085"), Decimal("-161.746096")), "rounding")
        self.assertEqual(classify(RATING, Decimal("1600.000000"), Decimal("1600.000100")), "different")

    def test_rating_allowance_is_for_ratings_only(self) -> None:
        share = Column("goals_share", "decimal", 6)
        self.assertEqual(classify(share, Decimal("0.500001"), Decimal("0.500002")), "rounding")
        self.assertEqual(classify(share, Decimal("0.500000"), Decimal("0.500010")), "different")

    def test_sums_of_ratings_may_differ_relative_to_their_size(self) -> None:
        self.assertEqual(classify(RATING, Decimal("1420219.865845"), Decimal("1420219.865720")), "rounding")
        self.assertEqual(classify(RATING, Decimal("1420219.865845"), Decimal("1420219.905720")), "different")

    def test_text_dates_and_doubles(self) -> None:
        self.assertEqual(classify(Column("name", "varchar"), "a", "b"), "different")
        day = Column("event_date", "date")
        self.assertEqual(classify(day, dt.date(2020, 1, 1), dt.date(2020, 1, 2)), "different")
        self.assertEqual(classify(CHRONO, 597.0, 598.0), "different")


class CompareRowsTests(unittest.TestCase):
    columns = [KEY, COUNT, RATING, Column("finalized_at", "datetime"), Column("LeastGoalsScoredVictims", "int", 0)]

    def _row(self, pid: int, games: int = 10, rating: str = "1600.000000", when: int = 1, least: int = 3) -> tuple:
        return (pid, games, Decimal(rating), dt.datetime(2026, 1, when), least)

    def _compare(self, php: list, python: list):
        return compare_rows("t", self.columns, ["player_id"], php, python)

    def test_same_rows(self) -> None:
        result = self._compare([self._row(1), self._row(2)], [self._row(2), self._row(1)])
        self.assertEqual(result.failures, 0)
        self.assertEqual(result.values, 6)  # count, rating, known column x 2; key and time are not compared

    def test_time_of_calculation_is_ignored(self) -> None:
        self.assertEqual(self._compare([self._row(1, when=1)], [self._row(1, when=20)]).failures, 0)

    def test_one_wrong_value_fails(self) -> None:
        result = self._compare([self._row(1), self._row(2, games=11)], [self._row(1), self._row(2)])
        self.assertEqual(result.failures, 1)
        self.assertEqual(result.samples["NumberGames"], [((2,), 11, 10)])
        self.assertEqual(result.different_keys, {(2,)})

    def test_rounding_is_counted_but_passes(self) -> None:
        result = self._compare([self._row(1, rating="1600.000001")], [self._row(1)])
        self.assertEqual((result.failures, sum(result.rounding.values())), (0, 1))

    def test_missing_and_extra_rows_fail(self) -> None:
        result = self._compare([self._row(1), self._row(3)], [self._row(1), self._row(2)])
        self.assertEqual((result.only_php, result.only_python, result.failures), ([(3,)], [(2,)], 2))

    def test_known_difference_is_counted_but_passes(self) -> None:
        result = self._compare([self._row(1, least=4)], [self._row(1, least=3)])
        self.assertEqual((result.failures, sum(result.known.values())), (0, 1))


class SetupTests(unittest.TestCase):
    def test_check_database_is_not_a_real_one(self) -> None:
        self.assertNotIn(CHECK_DB, {"ko2amiga_work", "ko2amiga_db", "ko2amiga_staging_cmp"})

    def test_driver_uses_the_same_database(self) -> None:
        self.assertIn(f"'{CHECK_DB}'", _PHP_DRIVER.read_text(encoding="utf-8"))

    def test_known_columns_stay_few(self) -> None:
        self.assertEqual(len(KNOWN_DIFFERENT_COLUMNS), 2)

    def test_ground_tables_match_the_rebuild_reset(self) -> None:
        text = (_PHP_DRIVER.parent / "clear_derived.py").read_text(encoding="utf-8")
        for table in GROUND_TABLES:
            self.assertNotIn(f"DELETE FROM {table}", text)

    def test_calculation_files_cover_the_rules(self) -> None:
        names = {p.name for p in calculation_files()}
        for name in ("finalize_tournament.py", "scoring_contract.py", "world_cup_stats.py", "player_state.py"):
            self.assertIn(name, names)


if __name__ == "__main__":
    unittest.main()
