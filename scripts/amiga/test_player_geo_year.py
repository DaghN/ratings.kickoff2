"""Unit tests for player_geo_year tracker."""

from __future__ import annotations

import unittest
from datetime import date

from scripts.amiga.player_geo_year import (
    GEO_RISE_METRICS,
    PlayerGeoYearTracker,
    calendar_year,
    normalize_country,
)


class PlayerGeoYearTrackerTests(unittest.TestCase):
    def test_normalize_country(self) -> None:
        self.assertEqual(normalize_country(" Denmark "), "Denmark")
        self.assertIsNone(normalize_country(""))
        self.assertIsNone(normalize_country(None))

    def test_calendar_year(self) -> None:
        self.assertEqual(calendar_year(date(1998, 6, 15)), 1998)
        self.assertIsNone(calendar_year(None))

    def test_host_country_from_event_only(self) -> None:
        tracker = PlayerGeoYearTracker()
        tracker.apply_tournament(
            tournament_id=1,
            event_date=date(1998, 1, 1),
            host_country="Denmark",
            games=[],
            games_in_event={1: 5},
            participant_ids={1},
            player_countries={1: "Denmark"},
        )
        scalars = tracker.scalars_for(1, "Denmark")
        self.assertEqual(scalars["countries_played_in"], 1)
        self.assertEqual(scalars["opponent_countries_faced"], 0)
        self.assertEqual(scalars["opponent_countries_beaten"], 0)
        self.assertEqual(scalars["opponent_countries_beaten_by"], 0)
        self.assertEqual(scalars["peak_year_games"], 5)
        self.assertEqual(scalars["peak_year_games_year"], 1998)
        self.assertEqual(scalars["countries_played_in_last_rise_tournament_id"], 1)

    def test_opponent_country_beaten_on_win(self) -> None:
        tracker = PlayerGeoYearTracker()
        tracker.apply_tournament(
            tournament_id=2,
            event_date=date(1999, 1, 1),
            host_country="Sweden",
            games=[{"idA": 1, "idB": 2, "GoalsA": 3, "GoalsB": 1}],
            games_in_event={1: 1, 2: 1},
            participant_ids={1, 2},
            player_countries={1: "Denmark", 2: "Sweden"},
        )
        p1 = tracker.scalars_for(1, "Denmark")
        self.assertEqual(p1["opponent_countries_beaten"], 1)
        self.assertEqual(p1["opponent_countries_faced"], 1)
        self.assertEqual(p1["opponent_countries_beaten_by"], 0)
        self.assertEqual(p1["opponent_countries_beaten_last_rise_tournament_id"], 2)
        p2 = tracker.scalars_for(2, "Sweden")
        self.assertEqual(p2["opponent_countries_beaten"], 0)
        self.assertEqual(p2["opponent_countries_beaten_by"], 1)
        self.assertIsNone(p2["opponent_countries_beaten_last_rise_tournament_id"])

    def test_geo_rise_on_new_host_country(self) -> None:
        tracker = PlayerGeoYearTracker()
        tracker.apply_tournament(
            tournament_id=10,
            event_date=date(2023, 11, 18),
            host_country="Spain",
            games=[],
            games_in_event={66: 3},
            participant_ids={66},
            player_countries={66: "England"},
        )
        first = tracker.scalars_for(66, "England")
        self.assertEqual(first["countries_played_in"], 1)
        self.assertEqual(first["countries_played_in_last_rise_tournament_id"], 10)
        self.assertEqual(first["countries_played_in_last_rise_event_date"], date(2023, 11, 18))

        tracker.apply_tournament(
            tournament_id=25,
            event_date=date(2025, 11, 1),
            host_country="Italy",
            games=[],
            games_in_event={66: 2},
            participant_ids={66},
            player_countries={66: "England"},
        )
        second = tracker.scalars_for(66, "England")
        self.assertEqual(second["countries_played_in"], 2)
        self.assertEqual(second["countries_played_in_last_rise_tournament_id"], 25)
        self.assertEqual(second["countries_played_in_last_rise_event_date"], date(2025, 11, 1))

    def test_flat_event_preserves_prior_geo_rise(self) -> None:
        """Count unchanged at later event — rise stays at the event where count last grew."""
        tracker = PlayerGeoYearTracker()
        tracker.apply_tournament(
            tournament_id=16,
            event_date=date(2023, 11, 18),
            host_country="Spain",
            games=[],
            games_in_event={66: 1},
            participant_ids={66},
            player_countries={66: "England"},
        )
        tracker.apply_tournament(
            tournament_id=17,
            event_date=date(2024, 5, 25),
            host_country="France",
            games=[],
            games_in_event={66: 1},
            participant_ids={66},
            player_countries={66: "England"},
        )
        at_peak = tracker.scalars_for(66, "England")
        self.assertEqual(at_peak["countries_played_in"], 2)
        rise_tid = at_peak["countries_played_in_last_rise_tournament_id"]
        rise_date = at_peak["countries_played_in_last_rise_event_date"]
        self.assertEqual(rise_tid, 17)

        tracker.apply_tournament(
            tournament_id=25,
            event_date=date(2025, 11, 1),
            host_country="France",
            games=[],
            games_in_event={66: 1},
            participant_ids={66},
            player_countries={66: "England"},
        )
        after = tracker.scalars_for(66, "England")
        self.assertEqual(after["countries_played_in"], 2)
        self.assertEqual(after["countries_played_in_last_rise_tournament_id"], rise_tid)
        self.assertEqual(after["countries_played_in_last_rise_event_date"], rise_date)

    def test_scalars_include_all_rise_field_keys(self) -> None:
        tracker = PlayerGeoYearTracker()
        scalars = tracker.scalars_for(999, None)
        for metric in GEO_RISE_METRICS:
            self.assertIn(f"{metric}_last_rise_tournament_id", scalars)
            self.assertIn(f"{metric}_last_rise_event_date", scalars)
            self.assertIsNone(scalars[f"{metric}_last_rise_tournament_id"])


if __name__ == "__main__":
    unittest.main()
