<?php
/**
 * Chess-style performance rating for one Amiga tournament (frozen opponent inputs).
 *
 * @see docs/amiga-performance-rating.md
 */
declare(strict_types=1);

require_once __DIR__ . '/performance_rating.php';

/** Full column name for tooltips (header may stay abbreviated). */
function amiga_perf_rating_column_label(): string
{
    return 'Performance rating';
}

/** Column tooltip body for Perf. rating on profile, tournament, and leaderboard surfaces. */
function amiga_perf_rating_column_help(): string
{
    return 'Rating level implied by your results in this event against the opponents you faced (frozen ratings). '
        . 'Requires at least 2 games; omitted for perfect win or loss records.';
}

function amiga_perf_rating_games_list_help(): string
{
    return 'Rating level implied by your results in the games matching these filters, against each opponent\'s frozen pre-game rating. '
        . 'Requires at least 2 games; omitted for perfect win or loss records.';
}

const AMIGA_PERFORMANCE_RATING_MIN_GAMES = PERFORMANCE_RATING_MIN_GAMES;

function amiga_performance_elo_expected(float $playerRating, float $opponentRating): float
{
    return performance_rating_elo_expected($playerRating, $opponentRating);
}

/**
 * @param list<float> $opponentRatings
 * @param list<float> $scores
 */
function amiga_solve_performance_rating(array $opponentRatings, array $scores): ?float
{
    return performance_rating_solve($opponentRatings, $scores);
}

/**
 * @param list<array{opponent: float, score: float}> $pairs
 */
function amiga_performance_rating_from_pairs(array $pairs): ?float
{
    return performance_rating_from_pairs($pairs);
}
