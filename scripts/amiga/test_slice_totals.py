"""Tests for world_cup slice totals increment."""

from __future__ import annotations

import unittest
from datetime import date

from scripts.amiga.slice_totals import (
    empty_world_cup_slice,
    increment_world_cup_slice,
)


class TestWorldCupSliceTotals(unittest.TestCase):
    def test_empty_slice(self) -> None:
        row = empty_world_cup_slice()
        self.assertEqual(row["tournaments_played"], 0)
        self.assertEqual(row["points"], 0)

    def test_wc_increments_honours_and_games(self) -> None:
        totals = empty_world_cup_slice()
        increment_world_cup_slice(
            totals,
            {
                "tournament_id": 21,
                "tournament_name": "World Cup XXI",
                "event_date": date(2022, 11, 1),
                "event_finish_position": 1,
                "games": 5,
                "wins": 3,
                "draws": 1,
                "losses": 1,
                "goals_for": 20,
                "goals_against": 10,
            },
        )
        self.assertEqual(totals["tournaments_played"], 1)
        self.assertEqual(totals["gold"], 1)
        self.assertEqual(totals["podiums"], 1)
        self.assertEqual(totals["games"], 5)
        self.assertEqual(totals["points"], 10)
        self.assertEqual(totals["tournaments_played_last_rise_tournament_id"], 21)

    def test_non_wc_ignored(self) -> None:
        totals = empty_world_cup_slice()
        increment_world_cup_slice(
            totals,
            {
                "tournament_id": 1,
                "tournament_name": "Kitchen Marathon",
                "event_date": date(2010, 1, 1),
                "games": 10,
                "wins": 10,
                "draws": 0,
                "losses": 0,
                "goals_for": 50,
                "goals_against": 0,
            },
        )
        self.assertEqual(totals["tournaments_played"], 0)
        self.assertEqual(totals["games"], 0)


if __name__ == "__main__":
    unittest.main()
