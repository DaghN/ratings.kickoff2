"""Unit tests for World Cup podium finish derivation."""

from __future__ import annotations

import unittest

from scripts.amiga.tournament_honours import (
    compute_wc_podium_finish_from_standings,
    is_world_cup_tournament,
    knockout_scope_label,
)


class WorldCupNameTests(unittest.TestCase):
    def test_matches_catalog_names(self) -> None:
        self.assertTrue(is_world_cup_tournament("World Cup XVII (Landskrona)"))
        self.assertTrue(is_world_cup_tournament("World Cup I (Dartford)"))
        self.assertFalse(is_world_cup_tournament("London XXIII"))
        # Matches PHP ``^World Cup\s+\S`` (same as catalog WC detector).
        self.assertTrue(is_world_cup_tournament("World Cup V KOA Cup"))


class KnockoutScopeLabelTests(unittest.TestCase):
    def test_splits_pair_scope_key(self) -> None:
        self.assertEqual(knockout_scope_label("Final|73-149"), "Final")
        self.assertEqual(knockout_scope_label("3rd Place Final|30-66"), "3rd Place Final")


class ComputeWcPodiumFinishTests(unittest.TestCase):
    def test_final_and_bronze_match(self) -> None:
        rows = [
            {"scope_type": "knockout", "scope_key": "Final|73-149", "player_id": 73, "position": 1},
            {"scope_type": "knockout", "scope_key": "Final|73-149", "player_id": 149, "position": 2},
            {"scope_type": "knockout", "scope_key": "3rd Place Final|30-66", "player_id": 66, "position": 1},
            {"scope_type": "knockout", "scope_key": "3rd Place Final|30-66", "player_id": 30, "position": 2},
        ]
        finish = compute_wc_podium_finish_from_standings(rows)
        self.assertEqual(finish[73], 1)
        self.assertEqual(finish[149], 2)
        self.assertEqual(finish[66], 3)
        self.assertNotIn(30, finish)

    def test_no_finish_from_league_rank_alone(self) -> None:
        finish = compute_wc_podium_finish_from_standings(
            [
                {"scope_type": "league", "scope_key": "Round 1 - Group A", "player_id": 10, "position": 1},
            ],
        )
        self.assertEqual(finish, {})

    def test_shared_semi_bronze_when_no_third_place_final(self) -> None:
        rows = [
            {"scope_type": "knockout", "scope_key": "Final|1-2", "player_id": 1, "position": 1},
            {"scope_type": "knockout", "scope_key": "Final|1-2", "player_id": 2, "position": 2},
            {"scope_type": "knockout", "scope_key": "Semi Finals|1-3", "player_id": 1, "position": 1},
            {"scope_type": "knockout", "scope_key": "Semi Finals|1-3", "player_id": 3, "position": 2},
            {"scope_type": "knockout", "scope_key": "Semi Finals|2-4", "player_id": 2, "position": 1},
            {"scope_type": "knockout", "scope_key": "Semi Finals|2-4", "player_id": 4, "position": 2},
        ]
        finish = compute_wc_podium_finish_from_standings(rows)
        self.assertEqual(finish[1], 1)
        self.assertEqual(finish[2], 2)
        self.assertEqual(finish[3], 3)
        self.assertEqual(finish[4], 3)

    def test_third_place_final_wins_over_semi_bronze(self) -> None:
        rows = [
            {"scope_type": "knockout", "scope_key": "Final|1-2", "player_id": 1, "position": 1},
            {"scope_type": "knockout", "scope_key": "Final|1-2", "player_id": 2, "position": 2},
            {"scope_type": "knockout", "scope_key": "3rd Place Final|3-4", "player_id": 3, "position": 1},
            {"scope_type": "knockout", "scope_key": "3rd Place Final|3-4", "player_id": 4, "position": 2},
            {"scope_type": "knockout", "scope_key": "Semi Finals|1-3", "player_id": 3, "position": 2},
            {"scope_type": "knockout", "scope_key": "Semi Finals|2-4", "player_id": 4, "position": 2},
        ]
        finish = compute_wc_podium_finish_from_standings(rows)
        self.assertEqual(finish[3], 3)
        self.assertNotIn(4, finish)

    def test_incomplete_final_no_bronze_from_semis(self) -> None:
        rows = [
            {"scope_type": "knockout", "scope_key": "Semi Finals|1-3", "player_id": 3, "position": 2},
            {"scope_type": "knockout", "scope_key": "Semi Finals|2-4", "player_id": 4, "position": 2},
        ]
        self.assertEqual(compute_wc_podium_finish_from_standings(rows), {})

    def test_subsidiary_cup_final_ignored(self) -> None:
        rows = [
            {"scope_type": "knockout", "scope_key": "Final|1-2", "player_id": 1, "position": 1},
            {"scope_type": "knockout", "scope_key": "Final|1-2", "player_id": 2, "position": 2},
            {"scope_type": "knockout", "scope_key": "Silver Cup Final|5-6", "player_id": 5, "position": 1},
        ]
        finish = compute_wc_podium_finish_from_standings(rows)
        self.assertEqual(set(finish.values()), {1, 2})


if __name__ == "__main__":
    unittest.main()
