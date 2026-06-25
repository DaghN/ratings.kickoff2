#!/usr/bin/env python3
"""Unit tests for peak Elo rank running minimum."""

from __future__ import annotations

import unittest

from scripts.amiga.elo_rank import compute_peak_elo_rank


class PeakEloRankTests(unittest.TestCase):
    def test_first_rank(self) -> None:
        peak, tid = compute_peak_elo_rank(42, 7, None, None)
        self.assertEqual((peak, tid), (42, 7))

    def test_improvement_updates_peak_and_tournament(self) -> None:
        peak, tid = compute_peak_elo_rank(10, 99, 42, 7)
        self.assertEqual((peak, tid), (10, 99))

    def test_worse_rank_keeps_prior_peak(self) -> None:
        peak, tid = compute_peak_elo_rank(55, 100, 10, 99)
        self.assertEqual((peak, tid), (10, 99))

    def test_tie_keeps_first_tournament(self) -> None:
        peak, tid = compute_peak_elo_rank(10, 101, 10, 99)
        self.assertEqual((peak, tid), (10, 99))


if __name__ == "__main__":
    unittest.main()