"""Unit tests for player tournament participation rebuild."""

from __future__ import annotations

import unittest
from datetime import datetime

from scripts.amiga.player_tournament_participation import (
    participation_avg_goals_per_game,
    participation_row_from_parts,
)


class ParticipationRowFromPartsTests(unittest.TestCase):
    def test_maps_standing_tournament_and_rating_event(self) -> None:
        standing = {
            "tournament_id": 42,
            "player_id": 7,
            "event_finish_position": 1,
            "points": 9,
            "games": 3,
            "wins": 3,
            "draws": 0,
            "losses": 0,
            "goals_for": 10,
            "goals_against": 2,
        }
        tournament = {
            "name": "London XXIII",
            "event_date": "2010-05-01",
            "chrono": 1.5,
            "is_cup": 0,
            "country": "England",
            "has_league": 1,
            "has_cup": 0,
        }
        rating_event = {
            "rating_before": 1650.0,
            "rating_delta": 12.5,
            "rating_after": 1662.5,
            "games_in_event": 3,
            "finalized_at": datetime(2010, 5, 1, 18, 0, 0),
        }

        row = participation_row_from_parts(standing, tournament, rating_event)

        self.assertEqual(row["player_id"], 7)
        self.assertEqual(row["tournament_id"], 42)
        self.assertEqual(row["tournament_name"], "London XXIII")
        self.assertEqual(row["event_finish_position"], 1)
        self.assertEqual(row["event_points"], 9)
        self.assertEqual(row["is_winner"], 1)
        self.assertEqual(row["wc_medal"], "none")
        self.assertEqual(row["rating_after"], 1662.5)
        self.assertEqual(row["games_in_event"], 3)
        self.assertEqual(row["avg_goals_for"], 3.3333)
        self.assertEqual(row["avg_goals_against"], 0.6667)

    def test_non_winner_without_rating_event(self) -> None:
        standing = {
            "tournament_id": 99,
            "player_id": 3,
            "event_finish_position": 4,
            "points": 3,
            "games": 3,
            "wins": 1,
            "draws": 0,
            "losses": 2,
            "goals_for": 4,
            "goals_against": 7,
        }
        tournament = {
            "name": "Kitchen Test",
            "event_date": None,
            "chrono": None,
            "is_cup": 1,
            "country": "Denmark",
            "has_league": 0,
            "has_cup": 1,
        }

        row = participation_row_from_parts(standing, tournament)

        self.assertEqual(row["is_winner"], 0)
        self.assertEqual(row["event_points"], 3)
        self.assertIsNone(row["rating_before"])
        self.assertEqual(row["games_in_event"], 0)
        self.assertEqual(row["has_cup"], 1)
        self.assertAlmostEqual(row["avg_goals_for"], 4 / 3, places=4)
        self.assertAlmostEqual(row["avg_goals_against"], 7 / 3, places=4)

    def test_avg_goals_null_when_no_games(self) -> None:
        self.assertIsNone(participation_avg_goals_per_game(5, 0))


if __name__ == "__main__":
    unittest.main()
