<?php
/**
 * Read-time performance rating for hero vs opponent country (country grain).
 *
 * @see docs/amiga-opponents-country-grain-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_countries_lib.php';
require_once __DIR__ . '/amiga_db.php';
require_once __DIR__ . '/amiga_player_games_lib.php';
require_once __DIR__ . '/amiga_performance_rating.php';
require_once __DIR__ . '/performance_rating.php';
require_once __DIR__ . '/amiga_snapshot_context.php';

/**
 * @return array{
 *     games: int,
 *     performance_rating: ?int,
 *     performance_rating_vs_hero: ?int,
 *     reason: ?string,
 *     reason_vs_hero: ?string
 * }
 */
function amiga_player_opponents_country_perf_empty(): array
{
    return [
        'games' => 0,
        'performance_rating' => null,
        'performance_rating_vs_hero' => null,
        'reason' => null,
        'reason_vs_hero' => null,
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array{
 *     games: int,
 *     performance_rating: ?int,
 *     performance_rating_vs_hero: ?int,
 *     reason: ?string,
 *     reason_vs_hero: ?string
 * }
 */
function amiga_player_opponents_country_perf_from_game_rows(array $rows, int $playerId): array
{
    if ($playerId < 1 || $rows === []) {
        return amiga_player_opponents_country_perf_empty();
    }

    $pairs = [];
    $reversePairs = [];
    $wins = 0;
    $draws = 0;
    $losses = 0;
    $reverseWins = 0;
    $reverseDraws = 0;
    $reverseLosses = 0;

    foreach ($rows as $row) {
        $idA = (int) ($row['idA'] ?? 0);
        $actualScore = (float) ($row['ActualScore'] ?? 0);
        if ($idA === $playerId) {
            $playerScore = $actualScore;
            $oppRating = (float) ($row['RatingB'] ?? 0);
            $reverseScore = 1.0 - $actualScore;
            $heroRating = (float) ($row['RatingA'] ?? 0);
        } else {
            $playerScore = 1.0 - $actualScore;
            $oppRating = (float) ($row['RatingA'] ?? 0);
            $reverseScore = $actualScore;
            $heroRating = (float) ($row['RatingB'] ?? 0);
        }

        $pairs[] = ['opponent' => $oppRating, 'score' => $playerScore];
        $reversePairs[] = ['opponent' => $heroRating, 'score' => $reverseScore];

        if ($playerScore >= 1.0 - 1e-9) {
            $wins++;
        } elseif ($playerScore <= 1e-9) {
            $losses++;
        } else {
            $draws++;
        }
        if ($reverseScore >= 1.0 - 1e-9) {
            $reverseWins++;
        } elseif ($reverseScore <= 1e-9) {
            $reverseLosses++;
        } else {
            $reverseDraws++;
        }
    }

    $gameCount = count($pairs);
    $perf = amiga_performance_rating_from_pairs($pairs);
    $reason = null;
    if ($gameCount < AMIGA_PERFORMANCE_RATING_MIN_GAMES) {
        $reason = 'min_games';
    } elseif ($perf === null && performance_rating_is_perfect_win_record($gameCount, $wins, $draws, $losses)) {
        $reason = 'perfect_win_record';
    } elseif ($perf === null) {
        $reason = 'undefined_record';
    }

    $perfVsHero = amiga_performance_rating_from_pairs($reversePairs);
    $reasonVsHero = null;
    if ($gameCount < AMIGA_PERFORMANCE_RATING_MIN_GAMES) {
        $reasonVsHero = 'min_games';
    } elseif ($perfVsHero === null && performance_rating_is_perfect_win_record($gameCount, $reverseWins, $reverseDraws, $reverseLosses)) {
        $reasonVsHero = 'perfect_win_record';
    } elseif ($perfVsHero === null) {
        $reasonVsHero = 'undefined_record';
    }

    return [
        'games' => $gameCount,
        'performance_rating' => $perf !== null ? (int) round($perf) : null,
        'performance_rating_vs_hero' => $perfVsHero !== null ? (int) round($perfVsHero) : null,
        'reason' => $reason,
        'reason_vs_hero' => $reasonVsHero,
    ];
}

/**
 * Scoped perf for one opponent country (H2H hot path).
 *
 * @return array{
 *     games: int,
 *     performance_rating: ?int,
 *     performance_rating_vs_hero: ?int,
 *     reason: ?string,
 *     reason_vs_hero: ?string
 * }
 */
function amiga_player_opponents_country_perf_ratings_for_token(
    mysqli $con,
    int $playerId,
    string $countryToken,
    ?AmigaSnapshotContext $ctx = null
): array {
    require_once __DIR__ . '/amiga_player_opponents_country_load.php';

    $countryToken = amiga_player_opponents_country_token_from_field($countryToken);
    if ($playerId < 1 || $countryToken === '') {
        return amiga_player_opponents_country_perf_empty();
    }

    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();

    require_once __DIR__ . '/amiga_player_h2h_country_lib.php';

    return amiga_player_opponents_country_perf_from_game_rows(
        amiga_player_h2h_country_game_rows_raw(
            $con,
            $playerId,
            $countryToken,
            $ctx,
            'r.idA, r.idB, r.ActualScore, r.RatingA, r.RatingB, r.country_a, r.country_b'
        ),
        $playerId
    );
}
/**
 * @return array<string, array{
 *     games: int,
 *     performance_rating: ?int,
 *     performance_rating_vs_hero: ?int,
 *     reason: ?string,
 *     reason_vs_hero: ?string
 * }>
 */
function amiga_player_opponents_country_perf_ratings_batch(
    mysqli $con,
    int $playerId,
    ?AmigaSnapshotContext $ctx = null
): array {
    static $cache = [];

    if ($playerId < 1) {
        return [];
    }

    require_once __DIR__ . '/amiga_player_opponents_country_load.php';

    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();
    $cacheKey = amiga_player_opponents_country_rows_cache_key($playerId, $ctx, true) . '|batch';
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $whereTypes = '';
    $whereParams = [];
    $whereSql = amiga_games_where_clause(
        $playerId,
        'all',
        0,
        0,
        'all',
        '',
        '',
        '',
        0,
        0,
        0,
        -1,
        -1,
        -1,
        null,
        $whereTypes,
        $whereParams,
        $ctx
    );

    $rows = amiga_games_query_all(
        $con,
        'SELECT r.idA, r.idB, r.ActualScore, r.RatingA, r.RatingB, r.country_a, r.country_b '
            . amiga_rated_games_from_sql($playerId)
            . ' WHERE '
            . $whereSql,
        $whereTypes,
        $whereParams
    );

    /** @var array<string, list<array<string, mixed>>> $rowsByToken */
    $rowsByToken = [];
    foreach ($rows as $row) {
        $idA = (int) ($row['idA'] ?? 0);
        $token = $idA === $playerId
            ? amiga_player_opponents_country_token_from_field($row['country_b'] ?? '')
            : amiga_player_opponents_country_token_from_field($row['country_a'] ?? '');
        $rowsByToken[$token][] = $row;
    }

    $out = [];
    foreach ($rowsByToken as $token => $tokenRows) {
        $out[$token] = amiga_player_opponents_country_perf_from_game_rows($tokenRows, $playerId);
    }

    $cache[$cacheKey] = $out;

    return $out;
}

/**
 * @return array{games: int, performance_rating: ?int, reason: ?string}
 */
function amiga_player_country_matchup_performance_rating(
    mysqli $con,
    int $playerId,
    string $countryToken,
    ?AmigaSnapshotContext $ctx = null
): array {
    $countryToken = amiga_player_opponents_country_token_from_field($countryToken);
    $row = amiga_player_opponents_country_perf_ratings_for_token($con, $playerId, $countryToken, $ctx);

    return [
        'games' => (int) ($row['games'] ?? 0),
        'performance_rating' => $row['performance_rating'] ?? null,
        'reason' => $row['reason'] ?? 'min_games',
    ];
}

/**
 * Aggregate performance rating for nationals from a country vs the hero (reverse perspective).
 *
 * @return array{games: int, performance_rating: ?int, reason: ?string}
 */
function amiga_player_country_matchup_performance_rating_vs_hero(
    mysqli $con,
    int $playerId,
    string $countryToken,
    ?AmigaSnapshotContext $ctx = null
): array {
    $countryToken = amiga_player_opponents_country_token_from_field($countryToken);
    $row = amiga_player_opponents_country_perf_ratings_for_token($con, $playerId, $countryToken, $ctx);

    return [
        'games' => (int) ($row['games'] ?? 0),
        'performance_rating' => $row['performance_rating_vs_hero'] ?? null,
        'reason' => $row['reason_vs_hero'] ?? 'min_games',
    ];
}
