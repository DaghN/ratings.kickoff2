"""Tests for World Cup country slice game stats tracker."""

from __future__ import annotations

import unittest

from scripts.amiga.country_slice_game_stats import CountryWorldCupSliceTracker


class TestCountryWorldCupSliceGameStats(unittest.TestCase):
    def test_dd_victim_without_win(self) -> None:
        tracker = CountryWorldCupSliceTracker(country_token="Italy")
        tracker.apply_player_game_perspective(
            opponent_id=2,
            opponent_country_token="England",
            goals_for=10,
            goals_against=9,
            actual_score=0.0,
            dd_for=True,
            opponent_rating=1500.0,
        )
        row = {"games": 1, "players": 1, "realm_wc_tournament_count": 1, "realm_wc_player_games": 1, "realm_wc_goals_for": 19}
        tracker.flush_into(row)
        self.assertEqual(row["double_digits_victims"], 1)

    def test_domestic_and_international(self) -> None:
        tracker = CountryWorldCupSliceTracker(country_token="Italy")
        tracker.apply_player_game_perspective(
            opponent_id=2,
            opponent_country_token="Italy",
            goals_for=3,
            goals_against=2,
            actual_score=1.0,
            dd_for=False,
            opponent_rating=1400.0,
        )
        tracker.apply_player_game_perspective(
            opponent_id=3,
            opponent_country_token="Germany",
            goals_for=2,
            goals_against=1,
            actual_score=1.0,
            dd_for=False,
            opponent_rating=1600.0,
        )
        row = {
            "games": 2,
            "players": 1,
            "realm_wc_tournament_count": 1,
            "realm_wc_player_games": 2,
            "realm_wc_goals_for": 8,
        }
        tracker.flush_into(row)
        self.assertEqual(row["domestic_games"], 1)
        self.assertEqual(row["international_games"], 1)


if __name__ == "__main__":
    unittest.main()
