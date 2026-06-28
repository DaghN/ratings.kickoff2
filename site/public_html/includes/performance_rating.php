<?php
/**
 * Chess-style performance rating (TPR) — shared by Amiga events and online pair slices.
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';

const PERFORMANCE_RATING_MIN_GAMES = 2;

/** Sort key for ∞ perf cells (above any finite rating in number columns). */
const PERFORMANCE_RATING_INFINITY_SORT_VALUE = '9999999';

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

/** Undefeated run with at least two games — no finite TPR; UI shows ∞. */
function performance_rating_is_perfect_win_record(int $games, int $wins, int $draws, int $losses): bool
{
    if ($games < PERFORMANCE_RATING_MIN_GAMES) {
        return false;
    }

    return $losses === 0 && $draws === 0 && $wins === $games;
}

function performance_rating_infinity_cell_html(): string
{
    return '<span aria-hidden="true">&#8734;</span><span class="visually-hidden">Perfect win record</span>';
}

function performance_rating_display_cell(mixed $rating, bool $showInfinity, string $empty = '-'): string
{
    if ($showInfinity && ($rating === null || $rating === '' || k2_db_is_null($rating))) {
        return performance_rating_infinity_cell_html();
    }
    if ($rating === null || $rating === '' || k2_db_is_null($rating)) {
        return $empty;
    }

    return k2_fmt_int($rating);
}

/** Tooltip for online H2H pair detail row. */
function performance_rating_h2h_pair_help(): string
{
    return 'Rating level implied by your results in rated games against this opponent, '
        . 'using their pre-game rating in each game. Requires at least 2 games; '
        . 'shows ∞ for a perfect win record (all wins, at least 2 games); omitted otherwise.';
}
