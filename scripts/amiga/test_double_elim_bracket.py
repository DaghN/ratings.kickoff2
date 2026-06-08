"""Unit tests for double-elimination bracket definitions."""

from __future__ import annotations

import unittest

from scripts.amiga.double_elim_bracket import (
    expected_fixture_count,
    initial_round_defs,
    resolve_fixture_players,
    seed_map,
)


class DoubleElimBracketTests(unittest.TestCase):
    def test_fixture_counts(self) -> None:
        self.assertEqual(expected_fixture_count(4), 6)
        self.assertEqual(expected_fixture_count(8), 14)

    def test_round1_seeding_4(self) -> None:
        seeds = seed_map([101, 102, 103, 104])
        outcomes: dict[str, dict[str, int]] = {}
        round0 = initial_round_defs(4)
        self.assertEqual(len(round0), 2)
        a, b = resolve_fixture_players(round0[0], seeds=seeds, outcomes=outcomes)
        self.assertEqual((a, b), (101, 104))

    def test_winner_slot_resolution(self) -> None:
        seeds = seed_map([1, 2, 3, 4])
        outcomes = {
            "wb-r1-m1": {"winner_id": 1, "loser_id": 4},
            "wb-r1-m2": {"winner_id": 2, "loser_id": 3},
        }
        from scripts.amiga.double_elim_bracket import BRACKET_ROUNDS

        wb_final = BRACKET_ROUNDS[4][1][0]
        a, b = resolve_fixture_players(wb_final, seeds=seeds, outcomes=outcomes)
        self.assertEqual((a, b), (1, 2))


if __name__ == "__main__":
    unittest.main()
