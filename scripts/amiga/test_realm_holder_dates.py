"""Unit tests for HoF holder date projection from player rise fields."""

from __future__ import annotations

import unittest
from datetime import date

from scripts.amiga.realm_incremental import _career_holders_from_player_rows, _holder_record_date


class RealmHolderDateTests(unittest.TestCase):
    def test_event_gold_uses_last_rise_not_last_participation(self) -> None:
        row = {
            "player_id": 14,
            "player_name": "Alkis P",
            "event_gold": 58,
            "event_gold_last_rise_event_date": date(2025, 9, 20),
            "honours_last_event_date": date(2025, 11, 1),
            "last_event_date": date(2025, 11, 1),
        }
        self.assertEqual(_holder_record_date("MostTournamentWins", row), "2025-09-20")

    def test_year_peak_uses_calendar_year_end(self) -> None:
        row = {"peak_year_games_year": 2007}
        self.assertEqual(_holder_record_date("MostGamesInOneYear", row), "2007-12-31")

    def test_career_holders_patch_uses_rise_date(self) -> None:
        rows = [
            {
                "player_id": 14,
                "player_name": "Alkis P",
                "event_gold": 58,
                "event_gold_last_rise_event_date": date(2025, 9, 20),
                "honours_last_event_date": date(2025, 11, 1),
            },
            {
                "player_id": 99,
                "player_name": "Other",
                "event_gold": 10,
                "event_gold_last_rise_event_date": date(2020, 1, 1),
            },
        ]
        patch = _career_holders_from_player_rows(rows)
        self.assertEqual(patch["MostTournamentWinsID"], 14)
        self.assertEqual(patch["MostTournamentWinsDate"], "2025-09-20")

    def test_most_games_played_uses_career_rise_date(self) -> None:
        row = {
            "player_id": 5,
            "player_name": "Leader",
            "NumberGames": 500,
            "number_games_last_rise_event_date": date(2015, 7, 10),
            "last_event_date": date(2024, 12, 1),
        }
        self.assertEqual(_holder_record_date("MostGamesPlayed", row), "2015-07-10")


if __name__ == "__main__":
    unittest.main()
