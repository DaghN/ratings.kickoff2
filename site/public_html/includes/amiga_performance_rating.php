<?php
/**
 * Chess-style performance rating for one Amiga tournament (frozen opponent inputs).
 *
 * @see docs/amiga-performance-rating.md
 */
declare(strict_types=1);

const AMIGA_PERFORMANCE_RATING_MIN_GAMES = 2;

function amiga_performance_elo_expected(float $playerRating, float $opponentRating): float
{
    return 1.0 / (1.0 + 10 ** (($opponentRating - $playerRating) / 400.0));
}

/**
 * @param list<float> $opponentRatings
 * @param list<float> $scores
 */
function amiga_solve_performance_rating(array $opponentRatings, array $scores): ?float
{
    $n = count($opponentRatings);
    if ($n !== count($scores) || $n < AMIGA_PERFORMANCE_RATING_MIN_GAMES) {
        return null;
    }

    $allWins = true;
    $allLosses = true;
    foreach ($scores as $score) {
        if (abs($score - 1.0) >= 1e-9) {
            $allWins = false;
        }
        if (abs($score) >= 1e-9) {
            $allLosses = false;
        }
    }
    if ($allWins || $allLosses) {
        return null;
    }

    $totalScore = array_sum($scores);
    $sumExpected = static function (float $rating) use ($opponentRatings): float {
        $sum = 0.0;
        foreach ($opponentRatings as $opponentRating) {
            $sum += amiga_performance_elo_expected($rating, (float) $opponentRating);
        }

        return $sum;
    };

    $lo = -800.0;
    $hi = 4000.0;
    while ($sumExpected($hi) < $totalScore) {
        $hi += 400.0;
    }

    for ($i = 0; $i < 64; $i++) {
        $mid = ($lo + $hi) / 2.0;
        if ($sumExpected($mid) < $totalScore) {
            $lo = $mid;
        } else {
            $hi = $mid;
        }
    }

    return round(($lo + $hi) / 2.0, 6);
}

/**
 * @param list<array{opponent: float, score: float}> $pairs
 */
function amiga_performance_rating_from_pairs(array $pairs): ?float
{
    if (count($pairs) < AMIGA_PERFORMANCE_RATING_MIN_GAMES) {
        return null;
    }

    $opponents = [];
    $scores = [];
    foreach ($pairs as $pair) {
        $opponents[] = (float) $pair['opponent'];
        $scores[] = (float) $pair['score'];
    }

    return amiga_solve_performance_rating($opponents, $scores);
}
