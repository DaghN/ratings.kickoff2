<?php
/**
 * Amiga H2H reads filtered by opponent country (country grain).
 *
 * @see docs/amiga-opponents-country-grain-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_player_h2h_pair_lib.php';
require_once __DIR__ . '/amiga_player_opponents_country_load.php';

function amiga_player_h2h_country_opponent_label(string $countryToken): string
{
    $label = $countryToken === AMIGA_COUNTRIES_UNKNOWN_TOKEN ? 'Unknown' : $countryToken;

    return 'players from ' . $label;
}

function amiga_player_h2h_country_where_sql(
    int $playerId,
    string $countryToken,
    ?AmigaSnapshotContext $ctx,
    string &$types,
    array &$params
): string {
    $countryToken = amiga_player_opponents_country_token_from_field($countryToken);

    return amiga_games_where_clause(
        $playerId,
        'all',
        0,
        0,
        'all',
        '',
        $countryToken,
        '',
        0,
        0,
        0,
        -1,
        -1,
        -1,
        null,
        $types,
        $params,
        $ctx
    );
}

function amiga_player_h2h_country_game_rows_cache_key(
    int $playerId,
    string $countryToken,
    AmigaSnapshotContext $ctx,
    string $select
): string {
    if (!$ctx->isActive()) {
        $cutoffKey = 'present';
    } else {
        $cutoff = $ctx->cutoff();
        $cutoffKey = $cutoff === null
            ? 'at:empty'
            : 'at:' . (int) ($cutoff['tournament_id'] ?? 0) . ':' . (string) ($cutoff['event_date'] ?? '') . ':' . (string) ($cutoff['chrono'] ?? '');
    }

    return $playerId . '|' . $countryToken . '|' . md5($select) . '|' . $cutoffKey;
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_player_h2h_country_game_rows_raw(
    mysqli $con,
    int $playerId,
    string $countryToken,
    ?AmigaSnapshotContext $ctx = null,
    string $select = 'r.id, r.`Date`, r.idA, r.idB, r.NameA, r.NameB, r.GoalsA, r.GoalsB, r.ActualScore, r.WinnerID, r.SumOfGoals'
): array {
    static $cache = [];

    $playerId = max(0, $playerId);
    $countryToken = amiga_player_opponents_country_token_from_field($countryToken);
    if ($playerId < 1 || $countryToken === '') {
        return [];
    }

    $ctx = amiga_player_h2h_ctx($ctx);
    $cacheKey = amiga_player_h2h_country_game_rows_cache_key($playerId, $countryToken, $ctx, $select);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $types = '';
    $params = [];
    $whereSql = amiga_player_h2h_country_where_sql($playerId, $countryToken, $ctx, $types, $params);
    $sql = 'SELECT ' . $select . ' ' . amiga_rated_games_from_sql($playerId)
        . ' WHERE ' . $whereSql
        . ' ORDER BY r.`Date` ASC, r.id ASC';

    $cache[$cacheKey] = amiga_games_query_all($con, $sql, $types, $params);

    return $cache[$cacheKey];
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_player_h2h_country_games_rows(
    mysqli $con,
    int $playerId,
    string $countryToken,
    ?AmigaSnapshotContext $ctx = null
): array {
    $rawRows = amiga_player_h2h_country_game_rows_raw($con, $playerId, $countryToken, $ctx);
    $rows = [];
    foreach ($rawRows as $row) {
        $norm = player_opponents_h2h_normalize_game_row($row, $playerId);
        $norm['href'] = k2_amiga_game_page_url((int) $norm['game_id']);
        $rows[] = $norm;
    }

    return $rows;
}

/**
 * @return array<string, mixed>
 */
function amiga_player_h2h_cumulative_by_country_payload(
    mysqli $con,
    int $playerId,
    string $countryToken,
    ?AmigaSnapshotContext $ctx = null
): array {
    $rows = amiga_player_h2h_country_game_rows_raw($con, $playerId, $countryToken, $ctx);
    $points = [];
    $playerWins = 0;
    $opponentWins = 0;
    $playerGoals = 0;
    $opponentGoals = 0;
    $draws = 0;
    $gameNumber = 0;

    foreach ($rows as $row) {
        $gameNumber++;
        $actualScore = (float) ($row['ActualScore'] ?? 0);
        $winnerId = (int) ($row['WinnerID'] ?? 0);
        $idA = (int) ($row['idA'] ?? 0);
        $goalsA = (int) ($row['GoalsA'] ?? 0);
        $goalsB = (int) ($row['GoalsB'] ?? 0);

        if ($idA === $playerId) {
            $playerGoals += $goalsA;
            $opponentGoals += $goalsB;
        } else {
            $playerGoals += $goalsB;
            $opponentGoals += $goalsA;
        }

        if (abs($actualScore - 0.5) < 0.001) {
            $draws++;
        } elseif ($winnerId === $playerId) {
            $playerWins++;
        } else {
            $opponentWins++;
        }

        $points[] = [
            'game_number' => $gameNumber,
            'player_wins' => $playerWins,
            'opponent_wins' => $opponentWins,
            'player_goals' => $playerGoals,
            'opponent_goals' => $opponentGoals,
        ];
    }

    return [
        'total_games' => $gameNumber,
        'draws' => $draws,
        'player_goals_total' => $playerGoals,
        'opponent_goals_total' => $opponentGoals,
        'points' => $points,
    ];
}

/**
 * @param 'subject'|'rival' $side subject = hero GF; rival = hero GA (nationals scored)
 * @return list<array{goals: int, games: int}>
 */
function amiga_player_h2h_country_goals_scored_buckets(
    mysqli $con,
    int $playerId,
    string $countryToken,
    ?AmigaSnapshotContext $ctx = null,
    string $side = 'subject'
): array {
    $rows = amiga_player_h2h_country_game_rows_raw($con, $playerId, $countryToken, $ctx);
    $counts = [];
    foreach ($rows as $row) {
        $idA = (int) ($row['idA'] ?? 0);
        if ($side === 'rival') {
            $goalsFor = $idA === $playerId ? (int) ($row['GoalsB'] ?? 0) : (int) ($row['GoalsA'] ?? 0);
        } else {
            $goalsFor = $idA === $playerId ? (int) ($row['GoalsA'] ?? 0) : (int) ($row['GoalsB'] ?? 0);
        }
        $counts[$goalsFor] = ($counts[$goalsFor] ?? 0) + 1;
    }
    if ($counts === []) {
        return [];
    }
    ksort($counts);
    $maxGoals = (int) max(array_keys($counts));
    $buckets = [];
    for ($g = 0; $g <= $maxGoals; $g++) {
        $buckets[] = [
            'goals' => $g,
            'games' => (int) ($counts[$g] ?? 0),
        ];
    }

    return $buckets;
}

/**
 * @return list<array{goals: int, games: int}>
 */
function amiga_player_h2h_country_total_goals_buckets(
    mysqli $con,
    int $playerId,
    string $countryToken,
    ?AmigaSnapshotContext $ctx = null
): array {
    $rows = amiga_player_h2h_country_game_rows_raw($con, $playerId, $countryToken, $ctx);
    $counts = [];
    foreach ($rows as $row) {
        $sum = (int) ($row['SumOfGoals'] ?? 0);
        $counts[$sum] = ($counts[$sum] ?? 0) + 1;
    }
    if ($counts === []) {
        return [];
    }
    ksort($counts);
    $maxGoals = (int) max(array_keys($counts));
    $buckets = [];
    for ($g = 0; $g <= $maxGoals; $g++) {
        $buckets[] = [
            'goals' => $g,
            'games' => (int) ($counts[$g] ?? 0),
        ];
    }

    return $buckets;
}

/**
 * @return array{max_goals_for: int, max_goals_against: int, grid_axis_max: int, cells: list<array<string, mixed>>}
 */
function amiga_player_h2h_country_scoreline_heatmap_payload(
    mysqli $con,
    int $playerId,
    string $countryToken,
    ?AmigaSnapshotContext $ctx = null
): array {
    $rows = amiga_player_h2h_country_game_rows_raw($con, $playerId, $countryToken, $ctx);
    if ($rows === []) {
        return [
            'max_goals_for' => 0,
            'max_goals_against' => 0,
            'grid_axis_max' => 0,
            'cells' => [],
        ];
    }

    $counts = [];
    foreach ($rows as $row) {
        $idA = (int) ($row['idA'] ?? 0);
        if ($idA === $playerId) {
            $gf = (int) ($row['GoalsA'] ?? 0);
            $ga = (int) ($row['GoalsB'] ?? 0);
        } else {
            $gf = (int) ($row['GoalsB'] ?? 0);
            $ga = (int) ($row['GoalsA'] ?? 0);
        }
        $key = $gf . ':' . $ga;
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    $pairMaxGoalsFor = 0;
    $pairMaxGoalsAgainst = 0;
    $cells = [];
    foreach ($counts as $key => $games) {
        [$gf, $ga] = array_map('intval', explode(':', $key, 2));
        if ($gf > $pairMaxGoalsFor) {
            $pairMaxGoalsFor = $gf;
        }
        if ($ga > $pairMaxGoalsAgainst) {
            $pairMaxGoalsAgainst = $ga;
        }
        $outcome = 'draw';
        if ($gf > $ga) {
            $outcome = 'win';
        } elseif ($gf < $ga) {
            $outcome = 'loss';
        }
        $cells[] = [
            'goals_for' => $gf,
            'goals_against' => $ga,
            'games' => (int) $games,
            'outcome' => $outcome,
        ];
    }

    usort(
        $cells,
        static function (array $a, array $b): int {
            $byGf = $a['goals_for'] <=> $b['goals_for'];
            if ($byGf !== 0) {
                return $byGf;
            }

            return $a['goals_against'] <=> $b['goals_against'];
        }
    );

    return [
        'max_goals_for' => $pairMaxGoalsFor,
        'max_goals_against' => $pairMaxGoalsAgainst,
        'grid_axis_max' => max($pairMaxGoalsFor, $pairMaxGoalsAgainst),
        'cells' => $cells,
    ];
}
