<?php
/**
 * Read-time performance rating for directed nation pairs (country Rivals).
 *
 * @see docs/amiga-country-rivals-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_countries_lib.php';
require_once __DIR__ . '/amiga_db.php';
require_once __DIR__ . '/amiga_player_games_lib.php';
require_once __DIR__ . '/amiga_performance_rating.php';
require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/amiga_country_rivals_load.php';
require_once __DIR__ . '/amiga_country_rivals_h2h_games_lib.php';

/**
 * @return array{
 *     games: int,
 *     performance_rating: ?int,
 *     performance_rating_vs_hero: ?int,
 *     reason: ?string,
 *     reason_vs_hero: ?string
 * }
 */
function amiga_country_rivals_perf_empty(): array
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
function amiga_country_rivals_perf_from_game_rows(array $rows, string $heroCountry): array
{
    $heroCountry = amiga_country_rivals_normalize_token($heroCountry);
    if ($heroCountry === '' || $rows === []) {
        return amiga_country_rivals_perf_empty();
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
        $tokenA = amiga_country_rivals_normalize_token($row['country_a'] ?? '');
        $actualScore = (float) ($row['ActualScore'] ?? 0);

        if ($tokenA === $heroCountry) {
            $heroScore = $actualScore;
            $heroRating = (float) ($row['RatingA'] ?? 0);
            $rivalRating = (float) ($row['RatingB'] ?? 0);
        } elseif (amiga_country_rivals_normalize_token($row['country_b'] ?? '') === $heroCountry) {
            $heroScore = 1.0 - $actualScore;
            $heroRating = (float) ($row['RatingB'] ?? 0);
            $rivalRating = (float) ($row['RatingA'] ?? 0);
        } else {
            continue;
        }

        $pairs[] = ['opponent' => $rivalRating, 'score' => $heroScore];
        $reversePairs[] = ['opponent' => $heroRating, 'score' => 1.0 - $heroScore];

        if ($heroScore >= 1.0 - 1e-9) {
            $wins++;
        } elseif ($heroScore <= 1e-9) {
            $losses++;
        } else {
            $draws++;
        }

        $reverseScore = 1.0 - $heroScore;
        if ($reverseScore >= 1.0 - 1e-9) {
            $reverseWins++;
        } elseif ($reverseScore <= 1e-9) {
            $reverseLosses++;
        } else {
            $reverseDraws++;
        }
    }

    $gameCount = count($pairs);
    if ($gameCount === 0) {
        return amiga_country_rivals_perf_empty();
    }

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
 * Directed nation-pair performance ratings (single rival — H2H hot path).
 *
 * @return array{
 *     games: int,
 *     performance_rating: ?int,
 *     performance_rating_vs_hero: ?int,
 *     reason: ?string,
 *     reason_vs_hero: ?string
 * }
 */
function amiga_country_rivals_perf_ratings_for_pair(
    mysqli $con,
    string $heroCountry,
    string $rivalCountry,
    ?AmigaSnapshotContext $ctx = null
): array {
    $heroCountry = amiga_country_rivals_normalize_token($heroCountry);
    $rivalCountry = amiga_country_rivals_normalize_token($rivalCountry);
    if ($heroCountry === '' || $rivalCountry === '' || amiga_country_rivals_is_domestic_rival($heroCountry, $rivalCountry)) {
        return amiga_country_rivals_perf_empty();
    }

    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();

    return amiga_country_rivals_perf_from_game_rows(
        amiga_country_rivals_h2h_game_rows_raw($con, $heroCountry, $rivalCountry, $ctx),
        $heroCountry
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
function amiga_country_rivals_perf_ratings_batch(
    mysqli $con,
    string $heroCountry,
    ?AmigaSnapshotContext $ctx = null
): array {
    static $cache = [];

    $heroCountry = amiga_country_rivals_normalize_token($heroCountry);
    if ($heroCountry === '') {
        return [];
    }

    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();
    $cacheKey = amiga_country_rivals_rows_cache_key($heroCountry, $ctx, true) . '|batch';
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }
    $tokenA = amiga_country_rivals_games_token_sql('r.country_a');
    $tokenB = amiga_country_rivals_games_token_sql('r.country_b');
    $types = 'ss';
    $params = [$heroCountry, $heroCountry];
    $cutoffTypes = '';
    $cutoffParams = [];
    $cutoffSql = amiga_snapshot_rated_game_cutoff_and_sql($ctx, $cutoffTypes, $cutoffParams, 'r');
    $types .= $cutoffTypes;
    $params = array_merge($params, $cutoffParams);

    $sql = 'SELECT r.idA, r.idB, r.ActualScore, r.RatingA, r.RatingB, r.country_a, r.country_b '
        . amiga_rated_games_from_sql()
        . ' WHERE (' . $tokenA . ' = ? OR ' . $tokenB . ' = ?)'
        . $cutoffSql;

    $rows = amiga_games_query_all($con, $sql, $types, $params);

    /** @var array<string, list<array{opponent: float, score: float}>> $pairBuckets */
    $pairBuckets = [];
    /** @var array<string, list<array{opponent: float, score: float}>> $reversePairBuckets */
    $reversePairBuckets = [];
    /** @var array<string, array{wins: int, draws: int, losses: int}> $recordBuckets */
    $recordBuckets = [];
    /** @var array<string, array{wins: int, draws: int, losses: int}> $reverseRecordBuckets */
    $reverseRecordBuckets = [];

    foreach ($rows as $row) {
        $tokenA = amiga_country_rivals_normalize_token($row['country_a'] ?? '');
        $tokenB = amiga_country_rivals_normalize_token($row['country_b'] ?? '');
        $actualScore = (float) ($row['ActualScore'] ?? 0);
        $idA = (int) ($row['idA'] ?? 0);

        if ($tokenA === $heroCountry) {
            $rivalToken = $tokenB;
            $heroScore = $actualScore;
            $heroRating = (float) ($row['RatingA'] ?? 0);
            $rivalRating = (float) ($row['RatingB'] ?? 0);
        } elseif ($tokenB === $heroCountry) {
            $rivalToken = $tokenA;
            $heroScore = 1.0 - $actualScore;
            $heroRating = (float) ($row['RatingB'] ?? 0);
            $rivalRating = (float) ($row['RatingA'] ?? 0);
        } else {
            continue;
        }

        if (!isset($pairBuckets[$rivalToken])) {
            $pairBuckets[$rivalToken] = [];
            $reversePairBuckets[$rivalToken] = [];
            $recordBuckets[$rivalToken] = ['wins' => 0, 'draws' => 0, 'losses' => 0];
            $reverseRecordBuckets[$rivalToken] = ['wins' => 0, 'draws' => 0, 'losses' => 0];
        }

        $pairBuckets[$rivalToken][] = ['opponent' => $rivalRating, 'score' => $heroScore];
        $reversePairBuckets[$rivalToken][] = ['opponent' => $heroRating, 'score' => 1.0 - $heroScore];

        if ($heroScore >= 1.0 - 1e-9) {
            $recordBuckets[$rivalToken]['wins']++;
        } elseif ($heroScore <= 1e-9) {
            $recordBuckets[$rivalToken]['losses']++;
        } else {
            $recordBuckets[$rivalToken]['draws']++;
        }

        $reverseScore = 1.0 - $heroScore;
        if ($reverseScore >= 1.0 - 1e-9) {
            $reverseRecordBuckets[$rivalToken]['wins']++;
        } elseif ($reverseScore <= 1e-9) {
            $reverseRecordBuckets[$rivalToken]['losses']++;
        } else {
            $reverseRecordBuckets[$rivalToken]['draws']++;
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

    $cache[$cacheKey] = $out;

    return $out;
}