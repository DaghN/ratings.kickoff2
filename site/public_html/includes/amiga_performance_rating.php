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
        . 'Requires at least 2 games; shows ∞ for a perfect win record (all wins); omitted for other cases.';
}

function amiga_perf_rating_games_list_help(): string
{
    return 'Rating level implied by your results in the games matching these filters, against each opponent\'s frozen pre-game rating. '
        . 'Requires at least 2 games; shows ∞ for a perfect win record (all wins); omitted otherwise.';
}

/** Tooltip for ∞ in Perfect perf-rating sub-wing. */
function amiga_perf_rating_perfect_infinity_help(): string
{
    return 'All wins in this event (at least 2 games). A finite performance rating cannot be defined for a perfect win record.';
}

/** Sort key for perf-rating LB Date column (newest event first on Perfect wing). */
function amiga_lb_perf_rating_date_sort_value(array $row): string
{
    if (isset($row['event_chrono']) && $row['event_chrono'] !== null && $row['event_chrono'] !== '') {
        $chrono = (float) $row['event_chrono'];
    } else {
        $chrono = 0.0;
        $eventDate = $row['event_date'] ?? null;
        if ($eventDate !== null && $eventDate !== '') {
            $ts = strtotime((string) $eventDate);
            $chrono = $ts !== false ? (float) $ts : 0.0;
        }
    }
    $tournamentId = (int) ($row['tournament_id'] ?? 0);

    return sprintf('%013.3f.%010d', $chrono, $tournamentId);
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
