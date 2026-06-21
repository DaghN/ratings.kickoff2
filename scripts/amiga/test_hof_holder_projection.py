"""Unit tests for HoF holder projection helpers (Phase B)."""

from __future__ import annotations

import unittest
from datetime import date

from scripts.amiga.realm_incremental import _HOLDER_DATE_FIELD, _holder_record_date
from scripts.amiga.verify_hof_holder_projection import (
    _GAME_ID_TO_DATE_PREFIX,
    _values_equal,
)


class HofHolderProjectionTests(unittest.TestCase):
    def test_holder_date_field_map_covers_rise_career_rows(self) -> None:
        for prefix in (
            "MostGamesPlayed",
            "MostWins",
            "MostTournamentWins",
            "MostCountriesPlayedIn",
        ):
            self.assertIn(prefix, _HOLDER_DATE_FIELD)
            self.assertTrue(_HOLDER_DATE_FIELD[prefix].endswith("_last_rise_event_date"))

    def test_holder_record_date_uses_rise_not_participation(self) -> None:
        row = {
            "number_games_last_rise_event_date": date(2015, 7, 10),
            "last_event_date": date(2024, 12, 1),
            "record_date": "2024-12-01",
        }
        self.assertEqual(_holder_record_date("MostGamesPlayed", row), "2015-07-10")

    def test_holder_record_date_peak_year_uses_calendar_end(self) -> None:
        row = {"peak_year_games_year": 2007}
        self.assertEqual(_holder_record_date("MostGamesInOneYear", row), "2007-12-31")

    def test_holder_record_date_fallback_record_date(self) -> None:
        row = {"record_date": "2010-05-05"}
        self.assertEqual(_holder_record_date("UnknownPrefix", row), "2010-05-05")

    def test_game_id_to_date_prefix_map(self) -> None:
        self.assertEqual(
            _GAME_ID_TO_DATE_PREFIX["MostGoalsScoredInOneGameGameID"],
            "MostGoalsScoredInOneGame",
        )

    def test_values_equal_float_tolerance(self) -> None:
        self.assertTrue(_values_equal(1.5, 1.5, float_field=True))
        self.assertFalse(_values_equal(1.5, 1.6, float_field=True))


if __name__ == "__main__":
    unittest.main()
