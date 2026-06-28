"""Unit tests for perfect event rollup helper."""

from __future__ import annotations

import unittest

from scripts.amiga.perfect_event import is_perfect_event_from_rollup


class PerfectEventTests(unittest.TestCase):
    def test_two_wins_qualifies(self) -> None:
        self.assertTrue(is_perfect_event_from_rollup(2, 2, 0, 0))

    def test_one_win_does_not_qualify(self) -> None:
        self.assertFalse(is_perfect_event_from_rollup(1, 1, 0, 0))

    def test_draw_disqualifies(self) -> None:
        self.assertFalse(is_perfect_event_from_rollup(3, 2, 1, 0))

    def test_loss_disqualifies(self) -> None:
        self.assertFalse(is_perfect_event_from_rollup(3, 2, 0, 1))

    def test_all_losses_not_perfect(self) -> None:
        self.assertFalse(is_perfect_event_from_rollup(2, 0, 0, 2))


if __name__ == "__main__":
    unittest.main()