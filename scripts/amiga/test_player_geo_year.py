"""Unit tests for player_geo_year tracker."""

from __future__ import annotations

import unittest
from datetime import date

from scripts.amiga.player_geo_year import PlayerGeoYearTracker, calendar_year, normalize_country


class PlayerGeoYearTrackerTests(unittest.TestCase):
    def test_normalize_country(self) -> None:
        self.assertEqual(normalize_country(" Denmark "), "Denmark")
        self.assertIsNone(normalize_country(""))
        self.assertIsNone(normalize_country(None))

    def test_calendar_year(self) -> None:
        self.assertEqual(calendar_year(date(1998, 6, 15)), 1998)
        self.assertIsNone(calendar_year(None))

    def test_own_country_seeded_in_host_and_faced(self) -> None:
        tracker = PlayerGeoYearTracker()
        tracker.apply_tournament(
            event_date=date(1998, 1, 1),
            host_country="Denmark",
            games=[],
            games_in_event={1: 5},
            participant_ids={1},
            player_countries={1: "Denmark"},
        )
        scalars = tracker.scalars_for(1, "Denmark")
        self.assertEqual(scalars["countries_played_in"], 1)
        self.assertEqual(scalars["opponent_countries_faced"], 1)
        self.assertEqual(scalars["opponent_countries_beaten"], 0)
        self.assertEqual(scalars["peak_year_games"], 5)
        self.assertEqual(scalars["peak_year_games_year"], 1998)

    def test_opponent_country_beaten_on_win(self) -> None:
        tracker = PlayerGeoYearTracker()
        tracker.apply_tournament(
            event_date=date(1999, 1, 1),
            host_country="Sweden",
            games=[{"idA": 1, "idB": 2, "GoalsA": 3, "GoalsB": 1}],
            games_in_event={1: 1, 2: 1},
            participant_ids={1, 2},
            player_countries={1: "Denmark", 2: "Sweden"},
        )
        p1 = tracker.scalars_for(1, "Denmark")
        self.assertEqual(p1["opponent_countries_beaten"], 1)
        self.assertEqual(p1["opponent_countries_faced"], 2)
        p2 = tracker.scalars_for(2, "Sweden")
        self.assertEqual(p2["opponent_countries_beaten"], 0)


if __name__ == "__main__":
    unittest.main()
