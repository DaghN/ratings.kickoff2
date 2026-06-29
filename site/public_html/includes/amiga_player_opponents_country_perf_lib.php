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
    if ($playerId < 1) {
        return [];
    }

    require_once __DIR__ . '/amiga_player_opponents_country_load.php';

    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();
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
            . amiga_rated_games_from_sql()
            . ' WHERE '
            . $whereSql,
        $whereTypes,
        $whereParams
    );

    /** @var array<string, list<array{opponent: float, score: float}>> $pairBuckets */
    $pairBuckets = [];
    /** @var array<string, list<array{opponent: float, score: float}>> $reversePairBuckets */
    $reversePairBuckets = [];
    /** @var array<string, array{wins: int, draws: int, losses: int}> $recordBuckets */
    $recordBuckets = [];
    /** @var array<string, array{wins: int, draws: int, losses: int}> $reverseRecordBuckets */
    $reverseRecordBuckets = [];

    foreach ($rows as $row) {
        $idA = (int) ($row['idA'] ?? 0);
        $actualScore = (float) ($row['ActualScore'] ?? 0);
        if ($idA === $playerId) {
            $token = amiga_player_opponents_country_token_from_field($row['country_b'] ?? '');
            $playerScore = $actualScore;
            $oppRating = (float) ($row['RatingB'] ?? 0);
            $reverseScore = 1.0 - $actualScore;
            $heroRating = (float) ($row['RatingA'] ?? 0);
        } else {
            $token = amiga_player_opponents_country_token_from_field($row['country_a'] ?? '');
            $playerScore = 1.0 - $actualScore;
            $oppRating = (float) ($row['RatingA'] ?? 0);
            $reverseScore = $actualScore;
            $heroRating = (float) ($row['RatingB'] ?? 0);
        }

        if (!isset($pairBuckets[$token])) {
            $pairBuckets[$token] = [];
            $reversePairBuckets[$token] = [];
            $recordBuckets[$token] = ['wins' => 0, 'draws' => 0, 'losses' => 0];
            $reverseRecordBuckets[$token] = ['wins' => 0, 'draws' => 0, 'losses' => 0];
        }

        $pairBuckets[$token][] = ['opponent' => $oppRating, 'score' => $playerScore];
        $reversePairBuckets[$token][] = ['opponent' => $heroRating, 'score' => $reverseScore];
        if ($playerScore >= 1.0 - 1e-9) {
            $recordBuckets[$token]['wins']++;
        } elseif ($playerScore <= 1e-9) {
            $recordBuckets[$token]['losses']++;
        } else {
            $recordBuckets[$token]['draws']++;
        }
        if ($reverseScore >= 1.0 - 1e-9) {
            $reverseRecordBuckets[$token]['wins']++;
        } elseif ($reverseScore <= 1e-9) {
            $reverseRecordBuckets[$token]['losses']++;
        } else {
            $reverseRecordBuckets[$token]['draws']++;
        }
    }

    $out = [];
    foreach ($pairBuckets as $token => $pairs) {
        $gameCount = count($pairs);
        $wins = (int) $recordBuckets[$token]['wins'];
        $draws = (int) $recordBuckets[$token]['draws'];
        $losses = (int) $recordBuckets[$token]['losses'];
        $perf = amiga_performance_rating_from_pairs($pairs);
        $reason = null;
        if ($gameCount < AMIGA_PERFORMANCE_RATING_MIN_GAMES) {
            $reason = 'min_games';
        } elseif ($perf === null && performance_rating_is_perfect_win_record($gameCount, $wins, $draws, $losses)) {
            $reason = 'perfect_win_record';
        } elseif ($perf === null) {
            $reason = 'undefined_record';
        }

        $reversePairs = $reversePairBuckets[$token] ?? [];
        $reverseWins = (int) $reverseRecordBuckets[$token]['wins'];
        $reverseDraws = (int) $reverseRecordBuckets[$token]['draws'];
        $reverseLosses = (int) $reverseRecordBuckets[$token]['losses'];
        $perfVsHero = amiga_performance_rating_from_pairs($reversePairs);
        $reasonVsHero = null;
        if ($gameCount < AMIGA_PERFORMANCE_RATING_MIN_GAMES) {
            $reasonVsHero = 'min_games';
        } elseif ($perfVsHero === null && performance_rating_is_perfect_win_record($gameCount, $reverseWins, $reverseDraws, $reverseLosses)) {
            $reasonVsHero = 'perfect_win_record';
        } elseif ($perfVsHero === null) {
            $reasonVsHero = 'undefined_record';
        }

        $out[$token] = [
            'games' => $gameCount,
            'performance_rating' => $perf !== null ? (int) round($perf) : null,
            'performance_rating_vs_hero' => $perfVsHero !== null ? (int) round($perfVsHero) : null,
            'reason' => $reason,
            'reason_vs_hero' => $reasonVsHero,
        ];
    }

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
    $batch = amiga_player_opponents_country_perf_ratings_batch($con, $playerId, $ctx);

    return $batch[$countryToken] ?? [
        'games' => 0,
        'performance_rating' => null,
        'reason' => 'min_games',
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
    $batch = amiga_player_opponents_country_perf_ratings_batch($con, $playerId, $ctx);
    $row = $batch[$countryToken] ?? null;

    return [
        'games' => is_array($row) ? (int) ($row['games'] ?? 0) : 0,
        'performance_rating' => is_array($row) ? ($row['performance_rating_vs_hero'] ?? null) : null,
        'reason' => is_array($row) ? ($row['reason_vs_hero'] ?? 'min_games') : 'min_games',
    ];
}
