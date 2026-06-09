"""Unit tests for World Cup medal derivation."""

from __future__ import annotations

import unittest

from scripts.amiga.tournament_honours import (
    compute_wc_medals_from_standings,
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


class ComputeWcMedalsTests(unittest.TestCase):
    def test_final_and_bronze_match(self) -> None:
        rows = [
            {"scope_type": "knockout", "scope_key": "Final|73-149", "player_id": 73, "position": 1},
            {"scope_type": "knockout", "scope_key": "Final|73-149", "player_id": 149, "position": 2},
            {"scope_type": "knockout", "scope_key": "3rd Place Final|30-66", "player_id": 66, "position": 1},
            {"scope_type": "knockout", "scope_key": "3rd Place Final|30-66", "player_id": 30, "position": 2},
        ]
        medals = compute_wc_medals_from_standings(rows)
        self.assertEqual(medals[73], "gold")
        self.assertEqual(medals[149], "silver")
        self.assertEqual(medals[66], "bronze")
        self.assertNotIn(30, medals)

    def test_overall_fallback_when_no_knockout(self) -> None:
        medals = compute_wc_medals_from_standings(
            [],
            overall_positions={10: 1, 11: 2, 12: 3, 13: 4},
        )
        self.assertEqual(medals, {10: "gold", 11: "silver", 12: "bronze"})


if __name__ == "__main__":
    unittest.main()
