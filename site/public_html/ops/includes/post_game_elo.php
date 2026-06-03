<?php
/**
 * Elo adjustment (mirrors scripts/ladder/elo.py).
 */
declare(strict_types=1);

require_once __DIR__ . '/post_game_constants.php';

/**
 * @return array{
 *   rating_a: float,
 *   rating_b: float,
 *   expected_a: float,
 *   expected_b: float,
 *   adjustment_a: float,
 *   adjustment_b: float,
 *   new_rating_a: float,
 *   new_rating_b: float,
 *   rating_difference: float
 * }
 */
function k2_post_game_compute_elo(float $ratingA, float $ratingB, float $actualScore): array
{
    $expectedA = 1.0 / (1.0 + 10 ** (($ratingB - $ratingA) / 400.0));
    $expectedB = 1.0 - $expectedA;
    $adjustmentA = K2_POST_GAME_K_FACTOR * ($actualScore - $expectedA);
    $adjustmentB = -$adjustmentA;
    $newA = $ratingA + $adjustmentA;
    $newB = $ratingB + $adjustmentB;

    return [
        'rating_a' => $ratingA,
        'rating_b' => $ratingB,
        'expected_a' => $expectedA,
        'expected_b' => $expectedB,
        'adjustment_a' => $adjustmentA,
        'adjustment_b' => $adjustmentB,
        'new_rating_a' => $newA,
        'new_rating_b' => $newB,
        'rating_difference' => $ratingA - $ratingB,
    ];
}
