"""Unit tests for peak/nadir rating event anchor helpers."""

from __future__ import annotations

import unittest

from scripts.amiga.peak_rating_event import (
    compute_lowest_rating_tournament_id,
    compute_peak_rating_tournament_id,
)
from scripts.k2_rating_core.player_state import SENTINEL_LOWEST_RATING


class PeakRatingEventTests(unittest.TestCase):
    def test_peak_first_attainment(self) -> None:
        tid = compute_peak_rating_tournament_id(2100.0, 5, 0.0, None)
        self.assertEqual(tid, 5)

    def test_peak_improves_new_event(self) -> None:
        tid = compute_peak_rating_tournament_id(2200.0, 9, 2100.0, 5)
        self.assertEqual(tid, 9)

    def test_peak_tie_keeps_first(self) -> None:
        tid = compute_peak_rating_tournament_id(2200.0, 10, 2200.0, 9)
        self.assertEqual(tid, 9)

    def test_lowest_first_attainment(self) -> None:
        tid = compute_lowest_rating_tournament_id(1800.0, 3, 0.0, None)
        self.assertEqual(tid, 3)

    def test_lowest_drops_new_event(self) -> None:
        tid = compute_lowest_rating_tournament_id(1700.0, 8, 1800.0, 3)
        self.assertEqual(tid, 8)

    def test_lowest_tie_keeps_first(self) -> None:
        tid = compute_lowest_rating_tournament_id(1700.0, 11, 1700.0, 8)
        self.assertEqual(tid, 8)

    def test_lowest_sentinel_prior(self) -> None:
        tid = compute_lowest_rating_tournament_id(1900.0, 2, SENTINEL_LOWEST_RATING, None)
        self.assertEqual(tid, 2)


if __name__ == "__main__":
    unittest.main()