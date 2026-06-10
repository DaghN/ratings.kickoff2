"""Unit tests for participation placement derivation."""

from __future__ import annotations

import unittest

from scripts.amiga.participation_placement import (
    compute_knockout_event_finish,
    derive_participation_positions,
    derive_wc_group_positions,
    participation_is_winner,
)


class ParticipationPlacementTests(unittest.TestCase):
    def test_bournemouth_ii_knockout_finish(self) -> None:
        rows = [
            {"scope_type": "knockout", "scope_key": "Final|73-134", "player_id": 134, "position": 1},
            {"scope_type": "knockout", "scope_key": "Final|73-134", "player_id": 73, "position": 2},
            {"scope_type": "knockout", "scope_key": "Semi Final|73-286", "player_id": 73, "position": 1},
            {"scope_type": "knockout", "scope_key": "Semi Final|73-286", "player_id": 286, "position": 2},
            {"scope_type": "knockout", "scope_key": "Semi Final|30-134", "player_id": 134, "position": 1},
            {"scope_type": "knockout", "scope_key": "Semi Final|30-134", "player_id": 30, "position": 1},
            {"scope_type": "knockout", "scope_key": "Quarter Final|73-422", "player_id": 73, "position": 1},
            {"scope_type": "knockout", "scope_key": "Quarter Final|73-422", "player_id": 422, "position": 2},
            {"scope_type": "knockout", "scope_key": "Quarter Final|134-421", "player_id": 134, "position": 1},
            {"scope_type": "knockout", "scope_key": "Quarter Final|134-421", "player_id": 421, "position": 2},
            {"scope_type": "knockout", "scope_key": "Quarter Final|30-405", "player_id": 30, "position": 1},
            {"scope_type": "knockout", "scope_key": "Quarter Final|30-405", "player_id": 405, "position": 2},
        ]

        positions = derive_participation_positions(rows, tournament_name="Bournemouth II")

        self.assertEqual(positions[134], 1)
        self.assertEqual(positions[73], 2)
        self.assertIn(positions[286], (3, 4))
        self.assertIn(positions[30], (3, 4))
        self.assertGreaterEqual(positions[422], 5)
        self.assertGreaterEqual(positions[421], 5)
        self.assertGreaterEqual(positions[405], 5)

    def test_overall_scope_wins_over_knockout(self) -> None:
        rows = [
            {"scope_type": "overall", "scope_key": "", "player_id": 1, "position": 3},
            {"scope_type": "knockout", "scope_key": "Final|1-2", "player_id": 1, "position": 1},
        ]
        positions = derive_participation_positions(rows, tournament_name="London XXIII")
        self.assertEqual(positions[1], 3)

    def test_wc_uses_group_rank(self) -> None:
        rows = [
            {"scope_type": "group", "scope_key": "Group B", "player_id": 9, "position": 2},
            {"scope_type": "group", "scope_key": "Group A", "player_id": 9, "position": 4},
            {"scope_type": "knockout", "scope_key": "Final|9-10", "player_id": 9, "position": 1},
        ]
        positions = derive_participation_positions(rows, tournament_name="World Cup XII")
        self.assertEqual(positions[9], 4)

    def test_wc_winner_flag_uses_medal(self) -> None:
        self.assertTrue(
            participation_is_winner(
                tournament_name="World Cup XII",
                overall_position=4,
                wc_medal="gold",
            )
        )
        self.assertFalse(
            participation_is_winner(
                tournament_name="World Cup XII",
                overall_position=1,
                wc_medal="none",
            )
        )

    def test_derive_wc_group_positions_picks_lexicographic_group(self) -> None:
        rows = [
            {"scope_type": "group", "scope_key": "Group B", "player_id": 5, "position": 1},
            {"scope_type": "group", "scope_key": "Group A", "player_id": 5, "position": 3},
        ]
        self.assertEqual(derive_wc_group_positions(rows)[5], 3)


if __name__ == "__main__":
    unittest.main()
