<?php
/**
 * Chess-style performance rating (TPR) — shared by Amiga events and online pair slices.
 */
declare(strict_types=1);

const PERFORMANCE_RATING_MIN_GAMES = 2;

function performance_rating_elo_expected(float $playerRating, float $opponentRating): float
{
    return 1.0 / (1.0 + 10 ** (($opponentRating - $playerRating) / 400.0));
}

/**
 * @param list<float> $opponentRatings
 * @param list<float> $scores
 */
function performance_rating_solve(array $opponentRatings, array $scores): ?float
{
    $n = count($opponentRatings);
    if ($n !== count($scores) || $n < PERFORMANCE_RATING_MIN_GAMES) {
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
            $sum += performance_rating_elo_expected($rating, (float) $opponentRating);
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
function performance_rating_from_pairs(array $pairs): ?float
{
    if (count($pairs) < PERFORMANCE_RATING_MIN_GAMES) {
        return null;
    }

    $opponents = [];
    $scores = [];
    foreach ($pairs as $pair) {
        $opponents[] = (float) $pair['opponent'];
        $scores[] = (float) $pair['score'];
    }

    return performance_rating_solve($opponents, $scores);
}

/** Tooltip for online H2H pair detail row. */
function performance_rating_h2h_pair_help(): string
{
    return 'Rating level implied by your results in rated games against this opponent, '
        . 'using their pre-game rating in each game. Requires at least 2 games; '
        . 'omitted for perfect win or loss records.';
}
