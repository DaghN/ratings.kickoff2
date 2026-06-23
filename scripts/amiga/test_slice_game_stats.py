"""Tests for World Cup slice V2 game stats tracker."""

from __future__ import annotations

import unittest

from scripts.amiga.slice_game_stats import (
    WorldCupSliceTracker,
    apply_world_cup_tournament_games,
    build_v2_oracle_for_player,
)
from scripts.amiga.slice_totals import empty_world_cup_slice, increment_world_cup_slice


class TestWorldCupSliceGameStats(unittest.TestCase):
    def test_dd_and_clean_sheet(self) -> None:
        tracker = WorldCupSliceTracker.from_totals_row(empty_world_cup_slice())
        tracker.apply_perspective(
            opponent_id=2,
            opponent_country="England",
            goals_for=10,
            goals_against=0,
            actual_score=1.0,
            dd_for=True,
        )
        tracker.row["games"] = 1
        tracker.flush_v2_into(tracker.row)
        self.assertEqual(tracker.row["double_digits"], 1)
        self.assertEqual(tracker.row["clean_sheets"], 1)
        self.assertEqual(tracker.row["double_digits_victims"], 1)
        self.assertEqual(tracker.row["clean_sheets_victims"], 1)

    def test_incremental_tournament_apply(self) -> None:
        slice_accum: dict[int, dict] = {1: empty_world_cup_slice(), 2: empty_world_cup_slice()}
        increment_world_cup_slice(
            slice_accum[1],
            {
                "tournament_id": 1,
                "tournament_name": "World Cup I",
                "event_date": "2010-01-01",
                "games": 1,
                "wins": 1,
                "draws": 0,
                "losses": 0,
                "goals_for": 10,
                "goals_against": 0,
            },
        )
        increment_world_cup_slice(
            slice_accum[2],
            {
                "tournament_id": 1,
                "tournament_name": "World Cup I",
                "event_date": "2010-01-01",
                "games": 1,
                "wins": 0,
                "draws": 0,
                "losses": 1,
                "goals_for": 0,
                "goals_against": 10,
            },
        )
        trackers: dict[int, WorldCupSliceTracker] = {}
        games = [{"idA": 1, "idB": 2, "GoalsA": 10, "GoalsB": 0}]
        apply_world_cup_tournament_games(
            slice_accum,
            trackers,
            games,
            {1: "Germany", 2: "England"},
            {1, 2},
        )
        self.assertEqual(slice_accum[1]["double_digits"], 1)
        self.assertEqual(slice_accum[1]["different_opponents"], 1)
        self.assertEqual(slice_accum[1]["opponent_countries_faced"], 2)

    def test_oracle_matches_tracker(self) -> None:
        v1 = empty_world_cup_slice()
        v1["games"] = 2
        v1["wins"] = 1
        v1["goals_for"] = 12
        v1["goals_against"] = 3
        games = [
            {"idA": 1, "idB": 2, "GoalsA": 10, "GoalsB": 2},
            {"idA": 3, "idB": 1, "GoalsA": 1, "GoalsB": 1},
        ]
        oracle = build_v2_oracle_for_player(v1, games, {1: "Germany", 2: "England", 3: "France"}, 1)
        self.assertEqual(oracle["double_digits"], 1)
        self.assertEqual(oracle["most_goals_scored"], 10)
        self.assertEqual(oracle["different_opponents"], 2)


if __name__ == "__main__":
    unittest.main()
