"""Unit tests for cumulative matchup goal extremes (SCH-031)."""

from __future__ import annotations

import unittest

from scripts.amiga.matchup_cumulative import MatchupCumulative, PairTotals, _apply_directed_outcome


class PairTotalsExtremesTests(unittest.TestCase):
    def test_first_win_sets_margin(self) -> None:
        t = PairTotals()
        _apply_directed_outcome(t, 1, 0, 0, 5, 2)
        self.assertEqual(t.max_win_margin, 3)
        self.assertIsNone(t.max_loss_margin)
        self.assertIsNone(t.max_draw_goals)

    def test_draw_then_higher_draw(self) -> None:
        t = PairTotals()
        _apply_directed_outcome(t, 0, 1, 0, 2, 2)
        _apply_directed_outcome(t, 0, 1, 0, 4, 4)
        self.assertEqual(t.max_draw_goals, 4)

    def test_apply_game_both_directions(self) -> None:
        m = MatchupCumulative()
        m.apply_game({"player_a_id": 1, "player_b_id": 2, "goals_a": 10, "goals_b": 0})
        ab = m.pairs_for_player(1)[2]
        ba = m.pairs_for_player(2)[1]
        self.assertEqual(ab.max_goals_for, 10)
        self.assertEqual(ab.max_goals_against, 0)
        self.assertEqual(ba.max_goals_for, 0)
        self.assertEqual(ba.max_loss_margin, 10)


if __name__ == "__main__":
    unittest.main()
