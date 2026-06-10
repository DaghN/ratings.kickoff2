"""Unit tests for tournament performance rating."""

from __future__ import annotations

import unittest

from scripts.amiga.performance_rating import (
    PERFORMANCE_RATING_MIN_GAMES,
    performance_rating_from_pairs,
    solve_performance_rating,
)


class SolvePerformanceRatingTests(unittest.TestCase):
    def test_returns_none_below_min_games(self) -> None:
        self.assertIsNone(solve_performance_rating([1600.0], [1.0]))

    def test_returns_none_for_perfect_wins(self) -> None:
        self.assertIsNone(solve_performance_rating([1600.0, 1700.0], [1.0, 1.0]))

    def test_returns_none_for_perfect_losses(self) -> None:
        self.assertIsNone(solve_performance_rating([1600.0, 1700.0], [0.0, 0.0]))

    def test_even_score_matches_opponent_strength(self) -> None:
        opps = [1600.0, 1600.0]
        scores = [0.5, 0.5]
        perf = solve_performance_rating(opps, scores)
        self.assertIsNotNone(perf)
        assert perf is not None
        self.assertAlmostEqual(perf, 1600.0, delta=5.0)

    def test_split_record_between_two_opponents(self) -> None:
        opps = [1600.0, 1600.0]
        scores = [1.0, 0.0]
        perf = solve_performance_rating(opps, scores)
        self.assertIsNotNone(perf)
        assert perf is not None
        self.assertAlmostEqual(perf, 1600.0, delta=5.0)

    def test_beating_higher_rated_opponents_raises_performance(self) -> None:
        opps = [1800.0, 1800.0]
        scores = [1.0, 0.5]
        perf = solve_performance_rating(opps, scores)
        self.assertIsNotNone(perf)
        assert perf is not None
        self.assertGreater(perf, 1800.0)

    def test_min_games_constant_used_by_helper(self) -> None:
        pairs = [(1600.0, 1.0)]
        self.assertEqual(PERFORMANCE_RATING_MIN_GAMES, 2)
        self.assertIsNone(performance_rating_from_pairs(pairs))
        pairs.append((1600.0, 0.0))
        self.assertIsNotNone(performance_rating_from_pairs(pairs))


if __name__ == "__main__":
    unittest.main()
