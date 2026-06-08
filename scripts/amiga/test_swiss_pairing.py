"""Unit tests for Swiss pairing helpers."""

from __future__ import annotations

import unittest

from scripts.amiga.swiss_pairing import (
    collect_played_pairs,
    standings_totals,
    swiss_round1_pairings,
    swiss_round_count,
    swiss_round_pairings,
)


class SwissPairingTests(unittest.TestCase):
    def test_round_count(self) -> None:
        self.assertEqual(swiss_round_count(4), 2)
        self.assertEqual(swiss_round_count(8), 3)
        self.assertEqual(swiss_round_count(2), 1)

    def test_round1_pairs_by_seed(self) -> None:
        pairings, bye = swiss_round1_pairings([10, 20, 30, 40])
        self.assertIsNone(bye)
        self.assertEqual(len(pairings), 2)
        self.assertEqual((pairings[0].player_a_id, pairings[0].player_b_id), (10, 20))
        self.assertEqual((pairings[1].player_a_id, pairings[1].player_b_id), (30, 40))

    def test_round1_bye_odd_count(self) -> None:
        pairings, bye = swiss_round1_pairings([1, 2, 3])
        self.assertEqual(bye, 3)
        self.assertEqual(len(pairings), 1)

    def test_round2_avoids_rematch_when_possible(self) -> None:
        games = [
            {"player_a_id": 1, "player_b_id": 2, "goals_a": 3, "goals_b": 0},
            {"player_a_id": 3, "player_b_id": 4, "goals_a": 3, "goals_b": 0},
        ]
        points, gf, ga = standings_totals(games)
        played = collect_played_pairs(games)
        pairings, bye = swiss_round_pairings(
            round_no=2,
            player_ids=[1, 2, 3, 4],
            points=points,
            goals_for=gf,
            goals_against=ga,
            played_pairs=played,
        )
        self.assertIsNone(bye)
        self.assertEqual(len(pairings), 2)
        pairs = {
            frozenset({pairings[0].player_a_id, pairings[0].player_b_id}),
            frozenset({pairings[1].player_a_id, pairings[1].player_b_id}),
        }
        self.assertIn(frozenset({1, 3}), pairs)
        self.assertIn(frozenset({2, 4}), pairs)


if __name__ == "__main__":
    unittest.main()
