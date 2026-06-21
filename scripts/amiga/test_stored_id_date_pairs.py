"""Unit tests for Phase C id/date pairing helpers."""

from __future__ import annotations

import unittest

from scripts.amiga.generalstats_columns import RECORD_RISE_PLAYER_COLUMNS
from scripts.amiga.verify_stored_id_date_pairs import (
    _empty_career_best,
    _rise_id_date_pairs,
    advance_career_best_state,
)


class StoredIdDatePairsTests(unittest.TestCase):
    def test_rise_pairs_cover_all_rise_columns(self) -> None:
        pairs = _rise_id_date_pairs()
        tid_cols = {tid for tid, _date in pairs}
        date_cols = {date for _tid, date in pairs}
        for col in RECORD_RISE_PLAYER_COLUMNS:
            if col.endswith("_last_rise_tournament_id"):
                self.assertIn(col, tid_cols)
            elif col.endswith("_last_rise_event_date"):
                self.assertIn(col, date_cols)
        self.assertEqual(len(pairs), len(tid_cols))
        self.assertEqual(len(pairs), len(date_cols))

    def test_career_best_replay_keeps_prior_on_lower_perf(self) -> None:
        state = advance_career_best_state(
            _empty_career_best(),
            {
                "tournament_id": 10,
                "performance_rating": 1500.0,
                "games_in_event": 3,
            },
        )
        self.assertEqual(state.tournament_id, 10)
        self.assertEqual(state.games_at_tournament, 3)

        state = advance_career_best_state(
            state,
            {
                "tournament_id": 20,
                "performance_rating": 1400.0,
                "games_in_event": 5,
            },
        )
        self.assertEqual(state.tournament_id, 10)
        self.assertEqual(state.games_at_tournament, 3)

    def test_career_best_replay_updates_on_better_perf(self) -> None:
        state = advance_career_best_state(
            _empty_career_best(),
            {
                "tournament_id": 10,
                "performance_rating": 1500.0,
                "games_in_event": 3,
            },
        )
        state = advance_career_best_state(
            state,
            {
                "tournament_id": 30,
                "performance_rating": 1600.0,
                "games_in_event": 2,
            },
        )
        self.assertEqual(state.tournament_id, 30)
        self.assertEqual(state.games_at_tournament, 2)

    def test_career_best_skips_under_two_games(self) -> None:
        state = advance_career_best_state(
            _empty_career_best(),
            {
                "tournament_id": 5,
                "performance_rating": 2000.0,
                "games_in_event": 1,
            },
        )
        self.assertIsNone(state.tournament_id)


if __name__ == "__main__":
    unittest.main()
