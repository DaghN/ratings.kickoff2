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

    def test_group_labels_stay_group(self) -> None:
        scope = parse_phase("Round 1 - Group A")
        self.assertEqual(scope.scope_type, ScopeType.GROUP)
        self.assertEqual(scope.scope_key, "Round 1 - Group A")


if __name__ == "__main__":
    unittest.main()
