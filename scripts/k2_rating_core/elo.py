"""Elo adjustment (v1: K=32)."""

from __future__ import annotations

from dataclasses import dataclass

from .constants import K_FACTOR


@dataclass(frozen=True)
class EloResult:
    rating_a: float
    rating_b: float
    expected_a: float
    expected_b: float
    adjustment_a: float
    adjustment_b: float
    new_rating_a: float
    new_rating_b: float
    rating_difference: float


def compute_elo(rating_a: float, rating_b: float, actual_score: float) -> EloResult:
    expected_a = 1.0 / (1.0 + 10 ** ((rating_b - rating_a) / 400.0))
    expected_b = 1.0 - expected_a
    adjustment_a = K_FACTOR * (actual_score - expected_a)
    adjustment_b = -adjustment_a
    new_a = rating_a + adjustment_a
    new_b = rating_b + adjustment_b
    return EloResult(
        rating_a=rating_a,
        rating_b=rating_b,
        expected_a=expected_a,
        expected_b=expected_b,
        adjustment_a=adjustment_a,
        adjustment_b=adjustment_b,
        new_rating_a=new_a,
        new_rating_b=new_b,
        rating_difference=rating_a - rating_b,
    )
