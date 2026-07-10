"""Unit tests for tournament phase → standings scope mapping."""

from __future__ import annotations

import unittest

from scripts.amiga.tournament_phases import ScopeType, is_knockout_phase, parse_phase


class KnockoutPhaseDetectionTests(unittest.TestCase):
    def test_plural_knockout_labels(self) -> None:
        self.assertTrue(is_knockout_phase("Quarter Finals"))
        self.assertTrue(is_knockout_phase("Semi Finals"))

    def test_singular_knockout_labels(self) -> None:
        self.assertTrue(is_knockout_phase("Quarter Final"))
        self.assertTrue(is_knockout_phase("Semi Final"))

    def test_singular_parsed_as_knockout_scope(self) -> None:
        scope = parse_phase("Quarter Final")
        self.assertEqual(scope.scope_type, ScopeType.KNOCKOUT)
        self.assertEqual(scope.scope_key, "Quarter Final")

    def test_null_phase_is_implicit_league(self) -> None:
        scope = parse_phase(None)
        self.assertEqual(scope.scope_type, ScopeType.LEAGUE)
        self.assertEqual(scope.scope_key, "")

    def test_group_labels_map_to_league(self) -> None:
        scope = parse_phase("Round 1 - Group A")
        self.assertEqual(scope.scope_type, ScopeType.LEAGUE)
        self.assertEqual(scope.scope_key, "Round 1 - Group A")

    def test_play_outs_and_plural_finals(self) -> None:
        self.assertTrue(is_knockout_phase("Play Outs"))
        scope = parse_phase("Finals")
        self.assertEqual(scope.scope_type, ScopeType.KNOCKOUT)
        self.assertEqual(scope.scope_key, "Final")
        scope3 = parse_phase("3rd Place Finals")
        self.assertEqual(scope3.scope_key, "3rd Place Final")
        scope5 = parse_phase("5th Place Finals")
        self.assertEqual(scope5.scope_key, "5th Place Final")

    def test_game_of_shame_is_knockout(self) -> None:
        self.assertTrue(is_knockout_phase("Game of Shame"))
        scope = parse_phase("Game of Shame")
        self.assertEqual(scope.scope_type, ScopeType.KNOCKOUT)
        self.assertEqual(scope.scope_key, "Game of Shame")

    def test_playouts_band_is_knockout(self) -> None:
        self.assertTrue(is_knockout_phase("Playouts 5-7"))
        self.assertTrue(is_knockout_phase("Playouts Group"))
        scope = parse_phase("Playouts 5-7")
        self.assertEqual(scope.scope_type, ScopeType.KNOCKOUT)
        self.assertEqual(scope.scope_key, "Playouts 5-7")


if __name__ == "__main__":
    unittest.main()
