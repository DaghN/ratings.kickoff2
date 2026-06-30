<?php
/**
 * Amiga country Rivals — directed nation-pair game reads (H2H depth).
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_country_rivals_load.php';
require_once __DIR__ . '/amiga_player_games_lib.php';
require_once __DIR__ . '/amiga_player_h2h_pair_lib.php';
require_once __DIR__ . '/player_opponents_h2h_moments.php';

function amiga_country_rivals_games_token_sql(string $col): string
{
    return 'CASE WHEN TRIM(' . $col . ') IS NULL OR TRIM(' . $col . ') = \'\' '
        . 'THEN \'' . AMIGA_COUNTRIES_UNKNOWN_TOKEN . '\' ELSE TRIM(' . $col . ') END';
}

function amiga_country_rivals_games_where_sql(
    string $heroCountry,
    string $rivalCountry,
    ?AmigaSnapshotContext $ctx,
    string &$types,
    array &$params
): string {
    $heroCountry = amiga_country_rivals_normalize_token($heroCountry);
    $rivalCountry = amiga_country_rivals_normalize_token($rivalCountry);
    $tokenA = amiga_country_rivals_games_token_sql('r.country_a');
    $tokenB = amiga_country_rivals_games_token_sql('r.country_b');
    $types = 'ssss';
    $params = [$heroCountry, $rivalCountry, $heroCountry, $rivalCountry];
    $where = '((' . $tokenA . ' = ? AND ' . $tokenB . ' = ?) OR (' . $tokenB . ' = ? AND ' . $tokenA . ' = ?))';
    $where .= amiga_snapshot_rated_game_cutoff_and_sql($ctx, $types, $params, 'r');

    return $where;
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_country_rivals_h2h_game_rows_raw(
    mysqli $con,
    string $heroCountry,
    string $rivalCountry,
    ?AmigaSnapshotContext $ctx = null,
    string $select = 'r.id, r.`Date`, r.idA, r.idB, r.NameA, r.NameB, r.GoalsA, r.GoalsB, r.ActualScore, r.WinnerID, r.SumOfGoals, r.country_a, r.country_b'
): array {
    $heroCountry = amiga_country_rivals_normalize_token($heroCountry);
    $rivalCountry = amiga_country_rivals_normalize_token($rivalCountry);
    if ($heroCountry === '' || $rivalCountry === '' || amiga_country_rivals_is_domestic_rival($heroCountry, $rivalCountry)) {
        return [];
    }

    $ctx = amiga_player_h2h_ctx($ctx);
    $types = '';
    $params = [];
    $whereSql = amiga_country_rivals_games_where_sql($heroCountry, $rivalCountry, $ctx, $types, $params);
    $sql = 'SELECT ' . $select . ' ' . amiga_rated_games_from_sql()
        . ' WHERE ' . $whereSql
        . ' ORDER BY r.`Date` ASC, r.id ASC';

    return amiga_games_query_all($con, $sql, $types, $params);
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_country_rivals_h2h_games_rows(
    mysqli $con,
    string $heroCountry,
    string $rivalCountry,
    ?AmigaSnapshotContext $ctx = null
): array {
    $heroCountry = amiga_country_rivals_normalize_token($heroCountry);
    $rows = [];
    foreach (amiga_country_rivals_h2h_game_rows_raw($con, $heroCountry, $rivalCountry, $ctx) as $row) {
        $tokenA = amiga_country_rivals_normalize_token($row['country_a'] ?? '');
        $subjectId = $tokenA === $heroCountry ? (int) ($row['idA'] ?? 0) : (int) ($row['idB'] ?? 0);
        if ($subjectId < 1) {
            continue;
        }
        $norm = player_opponents_h2h_normalize_game_row($row, $subjectId);
        $norm['href'] = k2_amiga_game_page_url((int) $norm['game_id']);
        $rows[] = $norm;
    }

    return $rows;
}

/**
 * @return array<string, mixed>
 */
function amiga_country_rivals_h2h_cumulative_payload(
    mysqli $con,
    string $heroCountry,
    string $rivalCountry,
    ?AmigaSnapshotContext $ctx = null
): array {
    $heroCountry = amiga_country_rivals_normalize_token($heroCountry);
    $points = [];
    $heroWins = 0;
    $rivalWins = 0;
    $heroGoals = 0;
    $rivalGoals = 0;
    $draws = 0;
    $gameNumber = 0;

    foreach (amiga_country_rivals_h2h_game_rows_raw($con, $heroCountry, $rivalCountry, $ctx) as $row) {
        $gameNumber++;
        $tokenA = amiga_country_rivals_normalize_token($row['country_a'] ?? '');
        $idA = (int) ($row['idA'] ?? 0);
        $goalsA = (int) ($row['GoalsA'] ?? 0);
        $goalsB = (int) ($row['GoalsB'] ?? 0);
        $actualScore = (float) ($row['ActualScore'] ?? 0);
        $winnerId = (int) ($row['WinnerID'] ?? 0);

        if ($tokenA === $heroCountry) {
            $heroGoals += $goalsA;
            $rivalGoals += $goalsB;
            $heroSideId = $idA;
        } else {
            $heroGoals += $goalsB;
            $rivalGoals += $goalsA;
            $heroSideId = (int) ($row['idB'] ?? 0);
        }

        if (abs($actualScore - 0.5) < 0.001) {
            $draws++;
        } elseif ($winnerId === $heroSideId) {
            $heroWins++;
        } else {
            $rivalWins++;
        }

        $points[] = [
            'game_number' => $gameNumber,
            'player_wins' => $heroWins,
            'opponent_wins' => $rivalWins,
            'player_goals' => $heroGoals,
            'opponent_goals' => $rivalGoals,
        ];
    }

    return [
        'total_games' => $gameNumber,
        'draws' => $draws,
        'player_goals_total' => $heroGoals,
        'opponent_goals_total' => $rivalGoals,
        'points' => $points,
    ];
}

/**
 * @param 'subject'|'rival' $side subject = hero nationals GF; rival = hero nationals GA
 * @return list<array{goals: int, games: int}>
 */
function amiga_country_rivals_h2h_goals_buckets(
    mysqli $con,
    string $heroCountry,
    string $rivalCountry,
    ?AmigaSnapshotContext $ctx = null,
    string $side = 'subject'
): array {
    $heroCountry = amiga_country_rivals_normalize_token($heroCountry);
    $counts = [];
    foreach (amiga_country_rivals_h2h_game_rows_raw($con, $heroCountry, $rivalCountry, $ctx) as $row) {
        $tokenA = amiga_country_rivals_normalize_token($row['country_a'] ?? '');
        if ($side === 'rival') {
            $goalsFor = $tokenA === $heroCountry ? (int) ($row['GoalsB'] ?? 0) : (int) ($row['GoalsA'] ?? 0);
        } else {
            $goalsFor = $tokenA === $heroCountry ? (int) ($row['GoalsA'] ?? 0) : (int) ($row['GoalsB'] ?? 0);
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
function amiga_country_rivals_h2h_total_goals_buckets(
    mysqli $con,
    string $heroCountry,
    string $rivalCountry,
    ?AmigaSnapshotContext $ctx = null
): array {
    $counts = [];
    foreach (amiga_country_rivals_h2h_game_rows_raw($con, $heroCountry, $rivalCountry, $ctx) as $row) {
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
 * @return list<array{goals_a: int, goals_b: int, games: int}>
 */
function amiga_country_rivals_h2h_scoreline_buckets(
    mysqli $con,
    string $heroCountry,
    string $rivalCountry,
    ?AmigaSnapshotContext $ctx = null
): array {
    $heroCountry = amiga_country_rivals_normalize_token($heroCountry);
    $counts = [];
    foreach (amiga_country_rivals_h2h_game_rows_raw($con, $heroCountry, $rivalCountry, $ctx) as $row) {
        $tokenA = amiga_country_rivals_normalize_token($row['country_a'] ?? '');
        if ($tokenA === $heroCountry) {
            $gf = (int) ($row['GoalsA'] ?? 0);
            $ga = (int) ($row['GoalsB'] ?? 0);
        } else {
            $gf = (int) ($row['GoalsB'] ?? 0);
            $ga = (int) ($row['GoalsA'] ?? 0);
        }
        $key = $gf . '-' . $ga;
        if (!isset($counts[$key])) {
            $counts[$key] = ['goals_a' => $gf, 'goals_b' => $ga, 'games' => 0];
        }
        $counts[$key]['games']++;
    }

    return array_values($counts);
}

/**
 * @return array{max_goals_for: int, max_goals_against: int, grid_axis_max: int, cells: list<array<string, mixed>>}
 */
function amiga_country_rivals_h2h_scoreline_heatmap_payload(
    mysqli $con,
    string $heroCountry,
    string $rivalCountry,
    ?AmigaSnapshotContext $ctx = null
): array {
    $rows = amiga_country_rivals_h2h_game_rows_raw($con, $heroCountry, $rivalCountry, $ctx);
    if ($rows === []) {
        return [
            'max_goals_for' => 0,
            'max_goals_against' => 0,
            'grid_axis_max' => 0,
            'cells' => [],
        ];
    }

    $heroCountry = amiga_country_rivals_normalize_token($heroCountry);
    $counts = [];
    foreach ($rows as $row) {
        $tokenA = amiga_country_rivals_normalize_token($row['country_a'] ?? '');
        if ($tokenA === $heroCountry) {
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
        $pairMaxGoalsFor = max($pairMaxGoalsFor, $gf);
        $pairMaxGoalsAgainst = max($pairMaxGoalsAgainst, $ga);
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