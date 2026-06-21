"""Unit tests for career cumulative last-rise date tracking (SCH-030)."""

from __future__ import annotations

import unittest
from datetime import date

from scripts.amiga.career_rise import (
    apply_career_rise_fields,
    empty_career_rise_state,
    prior_career_values_from_row,
)


class CareerRiseDatesTests(unittest.TestCase):
    def test_empty_has_null_rise_fields(self) -> None:
        rise = empty_career_rise_state()
        self.assertIsNone(rise["number_games_last_rise_tournament_id"])
        self.assertIsNone(rise["number_games_last_rise_event_date"])

    def test_number_games_rises_when_career_count_increases(self) -> None:
        rise = empty_career_rise_state()
        prior = prior_career_values_from_row({"NumberGames": 10})
        new = {"NumberGames": 12}
        rise = apply_career_rise_fields(
            rise,
            prior,
            new,
            tournament_id=42,
            event_date=date(2020, 3, 15),
        )
        self.assertEqual(rise["number_games_last_rise_tournament_id"], 42)
        self.assertEqual(rise["number_games_last_rise_event_date"], date(2020, 3, 15))

    def test_no_rise_when_career_count_unchanged(self) -> None:
        rise = empty_career_rise_state()
        rise["number_games_last_rise_tournament_id"] = 10
        rise["number_games_last_rise_event_date"] = date(2019, 1, 1)
        prior = prior_career_values_from_row({"NumberGames": 50})
        new = {"NumberGames": 50}
        rise = apply_career_rise_fields(
            rise,
            prior,
            new,
            tournament_id=99,
            event_date=date(2021, 6, 1),
        )
        self.assertEqual(rise["number_games_last_rise_tournament_id"], 10)
        self.assertEqual(rise["number_games_last_rise_event_date"], date(2019, 1, 1))

    def test_biggest_rating_ascent_rises_on_strict_increase(self) -> None:
        rise = empty_career_rise_state()
        prior = prior_career_values_from_row({"BiggestRatingAscent": 12.5})
        new = {"BiggestRatingAscent": 15.0}
        rise = apply_career_rise_fields(
            rise,
            prior,
            new,
            tournament_id=7,
            event_date=date(2022, 8, 8),
        )
        self.assertEqual(rise["biggest_rating_ascent_last_rise_tournament_id"], 7)
        self.assertEqual(rise["biggest_rating_ascent_last_rise_event_date"], date(2022, 8, 8))

    def test_biggest_rating_ascent_no_rise_on_tie(self) -> None:
        rise = empty_career_rise_state()
        rise["biggest_rating_ascent_last_rise_tournament_id"] = 3
        rise["biggest_rating_ascent_last_rise_event_date"] = date(2018, 4, 4)
        prior = prior_career_values_from_row({"BiggestRatingAscent": 20.0})
        new = {"BiggestRatingAscent": 20.0}
        rise = apply_career_rise_fields(
            rise,
            prior,
            new,
            tournament_id=8,
            event_date=date(2023, 1, 1),
        )
        self.assertEqual(rise["biggest_rating_ascent_last_rise_tournament_id"], 3)
        self.assertEqual(rise["biggest_rating_ascent_last_rise_event_date"], date(2018, 4, 4))


if __name__ == "__main__":
    unittest.main()
