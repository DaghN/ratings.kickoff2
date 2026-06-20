"""Unit tests for event snapshot row builder."""

from __future__ import annotations

import unittest
from datetime import datetime

from scripts.amiga.player_tournament_participation import participation_row_from_parts
from scripts.amiga.snapshot_row import (
    CURRENT_COLUMNS,
    SNAPSHOT_COLUMNS,
    build_event_snapshot_row,
    build_snapshot_from_finalize_parts,
    career_best_performance_fields,
    career_columns_from_player_state,
    current_row_from_snapshot,
    current_upsert_sql,
    honours_columns_from_totals_row,
    snapshot_insert_sql,
)
from scripts.ladder.player_state import PlayerState


class CareerBestPerformanceTests(unittest.TestCase):
    def test_first_qualifying_event(self) -> None:
        rating, tid = career_best_performance_fields(
            performance_rating=1850.5,
            tournament_id=10,
            games=4,
        )
        self.assertEqual(rating, 1850.5)
        self.assertEqual(tid, 10)

    def test_perfect_record_does_not_qualify(self) -> None:
        rating, tid = career_best_performance_fields(
            performance_rating=None,
            tournament_id=10,
            games=4,
            prior_rating=1800.0,
            prior_tournament_id=5,
            prior_games=3,
        )
        self.assertEqual(rating, 1800.0)
        self.assertEqual(tid, 5)

    def test_tiebreak_keeps_prior_when_games_higher(self) -> None:
        rating, tid = career_best_performance_fields(
            performance_rating=1900.0,
            tournament_id=20,
            games=2,
            prior_rating=1900.0,
            prior_tournament_id=15,
            prior_games=5,
        )
        self.assertEqual(tid, 15)

    def test_tiebreak_takes_new_when_strictly_better(self) -> None:
        rating, tid = career_best_performance_fields(
            performance_rating=1925.0,
            tournament_id=30,
            games=6,
            prior_rating=1900.0,
            prior_tournament_id=15,
            prior_games=5,
        )
        self.assertEqual(rating, 1925.0)
        self.assertEqual(tid, 30)


class SnapshotRowBuildTests(unittest.TestCase):
    def test_build_from_participation_career_honours(self) -> None:
        standing = {
            "tournament_id": 42,
            "player_id": 7,
            "event_finish_position": 2,
            "games": 4,
            "wins": 2,
            "draws": 1,
            "losses": 1,
            "goals_for": 8,
            "goals_against": 6,
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
            "performance_rating": 1710.25,
            "games_in_event": 4,
            "finalized_at": datetime(2010, 5, 1, 18, 0, 0),
        }
        participation = participation_row_from_parts(standing, tournament, rating_event)

        state = PlayerState()
        state.games = 10
        state.wins = 6
        state.rating = 1662.5
        career = career_columns_from_player_state(7, state)

        honours = honours_columns_from_totals_row(
            {
                "tournaments_played": 3,
                "tournaments_won": 0,
                "event_gold": 0,
                "event_silver": 1,
                "event_bronze": 0,
                "event_podiums": 1,
                "wc_played": 1,
                "wc_gold": 0,
                "wc_silver": 0,
                "wc_bronze": 0,
                "wc_podiums": 0,
                "last_event_date": "2010-05-01",
                "last_tournament_id": 42,
            }
        )

        snapshot = build_event_snapshot_row(
            participation=participation,
            career=career,
            honours=honours,
            career_best_performance_rating=1710.25,
            career_best_performance_tournament_id=42,
        )

        self.assertEqual(set(snapshot.keys()), set(SNAPSHOT_COLUMNS))
        self.assertEqual(snapshot["player_id"], 7)
        self.assertEqual(snapshot["tournament_id"], 42)
        self.assertEqual(snapshot["Rating"], 1662.5)
        self.assertEqual(snapshot["event_silver"], 1)
        self.assertEqual(snapshot["honours_last_tournament_id"], 42)
        self.assertEqual(snapshot["career_best_performance_rating"], 1710.25)

        current = current_row_from_snapshot(snapshot)
        self.assertEqual(set(current.keys()), set(CURRENT_COLUMNS))
        self.assertEqual(current["last_tournament_id"], 42)
        self.assertEqual(current["NumberGames"], 10)

    def test_build_snapshot_from_finalize_parts(self) -> None:
        participation = participation_row_from_parts(
            {
                "tournament_id": 1,
                "player_id": 2,
                "event_finish_position": 1,
                "games": 3,
                "wins": 2,
                "draws": 1,
                "losses": 0,
                "goals_for": 5,
                "goals_against": 2,
            },
            {
                "name": "Test Cup",
                "event_date": "2000-01-01",
                "chrono": 1.0,
                "is_cup": 1,
                "country": "X",
                "has_league": 0,
                "has_cup": 1,
            },
            {
                "rating_before": 1600.0,
                "rating_delta": 5.0,
                "rating_after": 1605.0,
                "performance_rating": 1620.0,
                "games_in_event": 3,
                "finalized_at": datetime(2000, 1, 1, 12, 0, 0),
            },
        )
        state = PlayerState()
        state.games = 3
        state.rating = 1605.0

        snapshot, current = build_snapshot_from_finalize_parts(
            participation=participation,
            player_state=state,
            honours_totals={
                "tournaments_played": 1,
                "tournaments_won": 1,
                "event_gold": 1,
                "event_silver": 0,
                "event_bronze": 0,
                "event_podiums": 1,
                "wc_played": 0,
                "wc_gold": 0,
                "wc_silver": 0,
                "wc_bronze": 0,
                "wc_podiums": 0,
                "last_event_date": "2000-01-01",
                "last_tournament_id": 1,
            },
        )

        self.assertEqual(snapshot["event_gold"], 1)
        self.assertEqual(current["player_id"], 2)
        self.assertEqual(current["career_best_performance_tournament_id"], 1)


class SnapshotSqlTests(unittest.TestCase):
    def test_insert_sql_targets_tables(self) -> None:
        self.assertIn("amiga_player_event_snapshots", snapshot_insert_sql())
        self.assertIn("amiga_player_current", current_upsert_sql())
        self.assertIn("ON DUPLICATE KEY UPDATE", snapshot_insert_sql())


if __name__ == "__main__":
    unittest.main()
