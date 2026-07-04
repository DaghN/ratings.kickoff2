<?php
/**
 * Amiga country Rivals — directed nation-pair game reads (H2H depth).
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_country_rivals_load.php';
require_once __DIR__ . '/amiga_player_games_lib.php';
require_once __DIR__ . '/amiga_player_h2h_pair_lib.php';
require_once __DIR__ . '/player_opponents_h2h_moments.php';

function amiga_country_rivals_h2h_game_select_sql(): string
{
    return 'r.id, r.`Date`, r.idA, r.idB, r.NameA, r.NameB, r.GoalsA, r.GoalsB, r.ActualScore, r.WinnerID, r.SumOfGoals, r.RatingA, r.RatingB, r.country_a, r.country_b';
}

function amiga_country_rivals_h2h_game_rows_cache_key(
    string $heroCountry,
    string $rivalCountry,
    AmigaSnapshotContext $ctx
): string {
    if (!$ctx->isActive()) {
        return $heroCountry . '|' . $rivalCountry . '|present';
    }
    $cutoff = $ctx->cutoff();

    return $heroCountry . '|' . $rivalCountry . '|at:'
        . (int) ($cutoff['tournament_id'] ?? 0) . ':' . (string) ($cutoff['event_date'] ?? '') . ':' . (string) ($cutoff['chrono'] ?? '');
}

function amiga_country_rivals_games_token_sql(string $col): string
{
    return 'CASE WHEN TRIM(' . $col . ') IS NULL OR TRIM(' . $col . ') = \'\' '
        . 'THEN \'' . AMIGA_COUNTRIES_UNKNOWN_TOKEN . '\' ELSE TRIM(' . $col . ') END';
}

/**
 * Push hero (and optional rival) player-id scope into the inner amiga_games scan.
 *
 * @param list<int> $heroPlayerIds
 * @param list<int>|null $rivalPlayerIds
 */
function amiga_country_rivals_games_inner_scope_sql(array $heroPlayerIds, ?array $rivalPlayerIds = null): string
{
    $heroIn = amiga_country_rivals_sql_int_in_list($heroPlayerIds);
    if ($heroIn === '') {
        return '1 = 0';
    }

    if ($rivalPlayerIds === null) {
        return "(g.player_a_id IN ({$heroIn}) OR g.player_b_id IN ({$heroIn}))";
    }

    $rivalIn = amiga_country_rivals_sql_int_in_list($rivalPlayerIds);
    if ($rivalIn === '') {
        return '1 = 0';
    }

    return "((g.player_a_id IN ({$heroIn}) AND g.player_b_id IN ({$rivalIn}))"
        . " OR (g.player_a_id IN ({$rivalIn}) AND g.player_b_id IN ({$heroIn})))";
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
    ?AmigaSnapshotContext $ctx = null
): array {
    static $cache = [];

    $heroCountry = amiga_country_rivals_normalize_token($heroCountry);
    $rivalCountry = amiga_country_rivals_normalize_token($rivalCountry);
    if ($heroCountry === '' || $rivalCountry === '' || amiga_country_rivals_is_domestic_rival($heroCountry, $rivalCountry)) {
        return [];
    }

    $ctx = amiga_player_h2h_ctx($ctx);
    $cacheKey = amiga_country_rivals_h2h_game_rows_cache_key($heroCountry, $rivalCountry, $ctx);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $types = '';
    $params = [];
    $heroPlayerIds = amiga_country_rivals_player_ids($con, $heroCountry, $ctx);
    $rivalPlayerIds = amiga_country_rivals_player_ids($con, $rivalCountry, $ctx);
    $innerScopeSql = amiga_country_rivals_games_inner_scope_sql($heroPlayerIds, $rivalPlayerIds);
    $whereSql = amiga_country_rivals_games_where_sql($heroCountry, $rivalCountry, $ctx, $types, $params);
    $sql = 'SELECT ' . amiga_country_rivals_h2h_game_select_sql() . ' ' . amiga_rated_games_from_sql(null, null, null, $innerScopeSql)
        . ' WHERE ' . $whereSql
        . ' ORDER BY r.`Date` ASC, r.id ASC';

    $cache[$cacheKey] = amiga_games_query_all($con, $sql, $types, $params);

    return $cache[$cacheKey];
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
        $rows[] = amiga_country_rivals_h2h_normalize_game_row($row, $heroCountry);
    }

    return $rows;
}

/**
 * Subject-side metrics from one raw nation-pair game row (moments scan only).
 *
 * @return array{
 *     subject_id: int,
 *     total_goals: int,
 *     subject_gf: int,
 *     subject_ga: int,
 *     draw: bool,
 *     subject_win: bool,
 *     subject_loss: bool,
 *     win_margin_subject: int,
 *     win_margin_opponent: int
 * }
 */
function amiga_country_rivals_h2h_raw_row_moment_metrics(array $row, string $heroCountry): array
{
    $heroCountry = amiga_country_rivals_normalize_token($heroCountry);
    $tokenA = amiga_country_rivals_normalize_token($row['country_a'] ?? '');
    $heroOnA = $tokenA === $heroCountry;
    $idA = (int) ($row['idA'] ?? 0);
    $idB = (int) ($row['idB'] ?? 0);
    $goalsA = (int) ($row['GoalsA'] ?? 0);
    $goalsB = (int) ($row['GoalsB'] ?? 0);
    $subjectId = $heroOnA ? $idA : $idB;
    $subjectGf = $heroOnA ? $goalsA : $goalsB;
    $subjectGa = $heroOnA ? $goalsB : $goalsA;

    if ($goalsA > $goalsB) {
        $winner = 'a';
    } elseif ($goalsB > $goalsA) {
        $winner = 'b';
    } else {
        $winner = 'draw';
    }

    $subjectWin = ($heroOnA && $winner === 'a') || (!$heroOnA && $winner === 'b');
    $subjectLoss = ($heroOnA && $winner === 'b') || (!$heroOnA && $winner === 'a');
    $draw = $winner === 'draw';

    return [
        'subject_id' => $subjectId,
        'total_goals' => $goalsA + $goalsB,
        'subject_gf' => $subjectGf,
        'subject_ga' => $subjectGa,
        'draw' => $draw,
        'subject_win' => $subjectWin,
        'subject_loss' => $subjectLoss,
        'win_margin_subject' => $subjectWin ? $subjectGf - $subjectGa : 0,
        'win_margin_opponent' => $subjectLoss ? $subjectGa - $subjectGf : 0,
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_country_rivals_h2h_normalize_game_row(array $row, string $heroCountry): array
{
    $heroCountry = amiga_country_rivals_normalize_token($heroCountry);
    $metrics = amiga_country_rivals_h2h_raw_row_moment_metrics($row, $heroCountry);
    $norm = player_opponents_h2h_normalize_game_row($row, (int) $metrics['subject_id']);
    $norm['href'] = k2_amiga_game_page_url((int) $norm['game_id']);

    return $norm;
}

/**
 * Moments grid without normalizing every pair game (hot path for large nation pairs).
 *
 * @return list<array<string, mixed>>
 */
function amiga_country_rivals_h2h_moments_slots(
    mysqli $con,
    string $heroCountry,
    string $rivalCountry,
    ?AmigaSnapshotContext $ctx = null
): array {
    $heroCountry = amiga_country_rivals_normalize_token($heroCountry);
    $rivalCountry = amiga_country_rivals_normalize_token($rivalCountry);
    $rivalLabel = $rivalCountry === AMIGA_COUNTRIES_UNKNOWN_TOKEN ? 'Unknown' : $rivalCountry;
    $rawRows = amiga_country_rivals_h2h_game_rows_raw($con, $heroCountry, $rivalCountry, $ctx);
    if ($rawRows === []) {
        return player_opponents_h2h_moments_slots(
            [],
            amiga_country_rivals_nation_label($heroCountry),
            amiga_country_rivals_nation_label($rivalCountry),
            $rivalLabel
        );
    }

    $firstRaw = $rawRows[0];
    $lastRaw = $rawRows[count($rawRows) - 1];
    $mostTotalRaw = null;
    $fewestTotalRaw = null;
    $mostSubjectRaw = null;
    $mostOpponentRaw = null;
    $biggestDrawRaw = null;
    $subjectBestWinRaw = null;
    $opponentBestWinRaw = null;
    $mostTotal = null;
    $fewestTotal = null;
    $mostSubject = null;
    $mostOpponent = null;
    $biggestDraw = null;
    $subjectBestWin = null;
    $opponentBestWin = null;

    foreach ($rawRows as $row) {
        $metrics = amiga_country_rivals_h2h_raw_row_moment_metrics($row, $heroCountry);
        $total = (int) $metrics['total_goals'];
        if ($mostTotal === null || $total > (int) $mostTotal['total_goals']) {
            $mostTotal = $metrics;
            $mostTotalRaw = $row;
        }
        if ($fewestTotal === null || $total < (int) $fewestTotal['total_goals']) {
            $fewestTotal = $metrics;
            $fewestTotalRaw = $row;
        }

        $subjectGf = (int) $metrics['subject_gf'];
        if ($mostSubject === null || $subjectGf > (int) $mostSubject['subject_gf']) {
            $mostSubject = $metrics;
            $mostSubjectRaw = $row;
        }

        $subjectGa = (int) $metrics['subject_ga'];
        if ($mostOpponent === null || $subjectGa > (int) $mostOpponent['subject_ga']) {
            $mostOpponent = $metrics;
            $mostOpponentRaw = $row;
        }

        if (!empty($metrics['draw'])) {
            if ($biggestDraw === null || $subjectGf > (int) $biggestDraw['subject_gf']) {
                $biggestDraw = $metrics;
                $biggestDrawRaw = $row;
            }
        }

        if (!empty($metrics['subject_win'])) {
            $margin = (int) $metrics['win_margin_subject'];
            if ($subjectBestWin === null || $margin > (int) $subjectBestWin['win_margin_subject']) {
                $subjectBestWin = $metrics;
                $subjectBestWinRaw = $row;
            }
        }

        if (!empty($metrics['subject_loss'])) {
            $margin = (int) $metrics['win_margin_opponent'];
            if ($opponentBestWin === null || $margin > (int) $opponentBestWin['win_margin_opponent']) {
                $opponentBestWin = $metrics;
                $opponentBestWinRaw = $row;
            }
        }
    }

    $normalizePick = static function (?array $rawRow) use ($heroCountry): ?array {
        if ($rawRow === null) {
            return null;
        }

        return amiga_country_rivals_h2h_normalize_game_row($rawRow, $heroCountry);
    };

    $byKey = [
        'first_game' => ['active' => true, 'game' => $normalizePick($firstRaw)],
        'last_game' => ['active' => true, 'game' => $normalizePick($lastRaw)],
        'most_goals_in_game' => ['active' => true, 'game' => $normalizePick($mostTotalRaw)],
        'most_scored_subject' => ['active' => true, 'game' => $normalizePick($mostSubjectRaw)],
        'most_scored_opponent' => ['active' => true, 'game' => $normalizePick($mostOpponentRaw)],
        'fewest_goals_in_game' => ['active' => true, 'game' => $normalizePick($fewestTotalRaw)],
        'biggest_draw' => ['active' => $biggestDrawRaw !== null, 'game' => $normalizePick($biggestDrawRaw)],
        'subject_biggest_win' => ['active' => $subjectBestWinRaw !== null, 'game' => $normalizePick($subjectBestWinRaw)],
        'opponent_biggest_win' => ['active' => $opponentBestWinRaw !== null, 'game' => $normalizePick($opponentBestWinRaw)],
    ];

    $subjectShort = k2_h2h_moment_short_name(amiga_country_rivals_nation_label($heroCountry));
    $opponentShort = k2_h2h_moment_short_name(amiga_country_rivals_nation_label($rivalCountry));
    $defs = player_opponents_h2h_moment_slot_defs($subjectShort, $opponentShort);
    $slots = [];
    foreach ($defs as $def) {
        $key = $def['key'];
        $slots[] = array_merge($def, [
            'active' => (bool) $byKey[$key]['active'],
            'game' => $byKey[$key]['game'],
        ]);
    }

    return $slots;
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