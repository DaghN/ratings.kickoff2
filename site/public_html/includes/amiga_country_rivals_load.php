<?php
/**
 * Amiga country Rivals — second roll-up from stored pair matchup rows.
 *
 * @see docs/amiga-country-rivals-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_countries_lib.php';
require_once __DIR__ . '/amiga_player_opponents_load.php';
require_once __DIR__ . '/amiga_player_opponents_country_load.php';
require_once __DIR__ . '/amiga_matchup_snapshot_lib.php';
require_once __DIR__ . '/amiga_snapshot_context.php';

function amiga_country_rivals_normalize_token(?string $country): string
{
    return amiga_player_opponents_country_token_from_field($country);
}

function amiga_country_rivals_is_domestic_rival(string $heroCountry, string $rivalCountry): bool
{
    $heroCountry = amiga_country_rivals_normalize_token($heroCountry);
    $rivalCountry = amiga_country_rivals_normalize_token($rivalCountry);

    return $heroCountry !== '' && $heroCountry === $rivalCountry;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function amiga_country_rivals_filter_cross_border_rows(array $rows, string $heroCountry): array
{
    $heroCountry = amiga_country_rivals_normalize_token($heroCountry);

    return array_values(array_filter(
        $rows,
        static function (array $row) use ($heroCountry): bool {
            return (string) ($row['rival_token'] ?? '') !== $heroCountry;
        }
    ));
}

/**
 * @return array<string, mixed>
 */
function amiga_country_rivals_empty_bucket(string $rivalToken): array
{
    return [
        'rival_token' => $rivalToken,
        'games' => 0,
        'wins' => 0,
        'draws' => 0,
        'losses' => 0,
        'goals_for' => 0,
        'goals_against' => 0,
        'max_goals_for' => 0,
        'max_goals_against' => 0,
        'min_goals_for' => 0,
        'min_goals_against' => 0,
        'max_win_margin' => null,
        'max_loss_margin' => null,
        'max_draw_goals' => null,
        'max_goal_sum' => 0,
        'min_goal_sum' => 0,
        'double_digits' => 0,
        'double_digits_conceded' => 0,
        'clean_sheets' => 0,
        'clean_sheets_conceded' => 0,
        'performance_rating' => null,
        'performance_rating_vs_hero' => null,
    ];
}

/**
 * @param list<array<string, mixed>> $pairRows
 * @return list<array<string, mixed>>
 */
function amiga_country_rivals_rollup_from_pair_rows(array $pairRows): array
{
    /** @var array<string, array<string, mixed>> $buckets */
    $buckets = [];

    foreach ($pairRows as $row) {
        $token = amiga_country_rivals_normalize_token($row['opponent_country'] ?? '');
        if (!isset($buckets[$token])) {
            $buckets[$token] = amiga_country_rivals_empty_bucket($token);
        }
        $bucket = &$buckets[$token];

        $bucket['games'] += (int) $row['games'];
        $bucket['wins'] += (int) $row['wins'];
        $bucket['draws'] += (int) $row['draws'];
        $bucket['losses'] += (int) $row['losses'];
        $bucket['goals_for'] += (int) $row['goals_for'];
        $bucket['goals_against'] += (int) $row['goals_against'];
        $bucket['max_goals_for'] = max($bucket['max_goals_for'], (int) $row['max_goals_for']);
        $bucket['max_goals_against'] = max($bucket['max_goals_against'], (int) $row['max_goals_against']);
        $bucket['min_goals_for'] = $bucket['min_goals_for'] === 0
            ? (int) $row['min_goals_for']
            : min($bucket['min_goals_for'], (int) $row['min_goals_for']);
        $bucket['min_goals_against'] = $bucket['min_goals_against'] === 0
            ? (int) $row['min_goals_against']
            : min($bucket['min_goals_against'], (int) $row['min_goals_against']);
        $bucket['max_goal_sum'] = max($bucket['max_goal_sum'], (int) $row['max_goal_sum']);
        $bucket['min_goal_sum'] = $bucket['min_goal_sum'] === 0
            ? (int) $row['min_goal_sum']
            : min($bucket['min_goal_sum'], (int) $row['min_goal_sum']);
        $bucket['double_digits'] += (int) $row['double_digits'];
        $bucket['double_digits_conceded'] += (int) $row['double_digits_conceded'];
        $bucket['clean_sheets'] += (int) $row['clean_sheets'];
        $bucket['clean_sheets_conceded'] += (int) $row['clean_sheets_conceded'];

        foreach (['max_win_margin', 'max_loss_margin', 'max_draw_goals'] as $marginKey) {
            if (!array_key_exists($marginKey, $row) || $row[$marginKey] === null) {
                continue;
            }
            $value = (int) $row[$marginKey];
            if (!array_key_exists($marginKey, $bucket) || $bucket[$marginKey] === null) {
                $bucket[$marginKey] = $value;
            } else {
                $bucket[$marginKey] = max((int) $bucket[$marginKey], $value);
            }
        }
        unset($bucket);
    }

    $rows = array_values($buckets);
    usort(
        $rows,
        static function (array $a, array $b): int {
            $gamesCmp = (int) $b['games'] <=> (int) $a['games'];
            if ($gamesCmp !== 0) {
                return $gamesCmp;
            }

            return strcasecmp((string) $a['rival_token'], (string) $b['rival_token']);
        }
    );

    return $rows;
}

/**
 * Pair matchup columns for country rollup (opponent rating unused in nation buckets).
 *
 * @return list<string>
 */
function amiga_country_rivals_pair_select_columns(): array
{
    $cols = amiga_matchup_opponents_select_columns(false);
    foreach ($cols as $index => $col) {
        if (str_contains($col, 'opponent_rating')) {
            $cols[$index] = '0 AS opponent_rating';
        }
    }

    return $cols;
}

/**
 * Latest at-event pair rows for one hero country — hero filter inside the window (pattern A).
 */
function amiga_country_rivals_matchup_at_event_latest_from_sql(string $alias = 'm'): string
{
    $heroTokenSql = amiga_countries_token_sql('h');

    return 'FROM amiga_player_matchup_at_event ' . $alias . "\n"
        . 'INNER JOIN (' . "\n"
        . '    SELECT x.player_id, x.opponent_id, x.as_of_tournament_id FROM (' . "\n"
        . '        SELECT m.player_id, m.opponent_id, m.as_of_tournament_id, m.games,' . "\n"
        . '            ROW_NUMBER() OVER (' . "\n"
        . '                PARTITION BY m.player_id, m.opponent_id' . "\n"
        . '                ORDER BY m.event_date DESC, m.event_chrono DESC, m.as_of_tournament_id DESC' . "\n"
        . '            ) AS rn' . "\n"
        . '        FROM amiga_player_matchup_at_event m' . "\n"
        . '        INNER JOIN amiga_players h ON h.id = m.player_id AND ' . $heroTokenSql . ' = ?' . "\n"
        . '        WHERE (m.event_date, m.event_chrono, m.as_of_tournament_id) <= (?, ?, ?)' . "\n"
        . '    ) x WHERE x.rn = 1 AND x.games > 0' . "\n"
        . ') ' . $alias . '_latest ON ' . $alias . '.player_id = ' . $alias . '_latest.player_id'
        . ' AND ' . $alias . '.opponent_id = ' . $alias . '_latest.opponent_id'
        . ' AND ' . $alias . '.as_of_tournament_id = ' . $alias . '_latest.as_of_tournament_id';
}

function amiga_country_rivals_rows_cache_key(string $heroCountry, AmigaSnapshotContext $ctx, bool $withPerf): string
{
    if (!$ctx->isActive()) {
        $cutoffKey = 'present';
    } else {
        $cutoff = $ctx->cutoff();
        $cutoffKey = $cutoff === null
            ? 'at:empty'
            : 'at:' . (int) ($cutoff['tournament_id'] ?? 0) . ':' . (string) ($cutoff['event_date'] ?? '') . ':' . (string) ($cutoff['chrono'] ?? '');
    }

    return $heroCountry . '|' . $cutoffKey . '|perf:' . ($withPerf ? '1' : '0');
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_country_rivals_pair_rows_raw(mysqli $con, string $heroCountry, ?AmigaSnapshotContext $ctx = null): array
{
    $heroCountry = amiga_country_rivals_normalize_token($heroCountry);
    if ($heroCountry === '') {
        return [];
    }

    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();
    $heroTokenSql = amiga_countries_token_sql('h');
    $selectCols = amiga_country_rivals_pair_select_columns();

    if (!$ctx->isActive()) {
        $sql = 'SELECT ' . implode(', ', $selectCols)
            . ' FROM amiga_player_matchup_summary m'
            . ' INNER JOIN amiga_players h ON h.id = m.player_id'
            . ' LEFT JOIN amiga_players p ON p.id = m.opponent_id'
            . ' WHERE ' . $heroTokenSql . ' = ? AND m.games > 0';
        $stmt = $con->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('s', $heroCountry);
    } else {
        $cutoff = $ctx->cutoff();
        if ($cutoff === null) {
            return [];
        }
        $sql = 'SELECT ' . implode(', ', $selectCols)
            . ' ' . amiga_country_rivals_matchup_at_event_latest_from_sql('m')
            . ' LEFT JOIN amiga_players p ON p.id = m.opponent_id';
        $stmt = $con->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $eventDate = $cutoff['event_date'];
        $chrono = $cutoff['chrono'];
        $tournamentId = $cutoff['tournament_id'];
        $stmt->bind_param('ssdi', $heroCountry, $eventDate, $chrono, $tournamentId);
    }

    if (!$stmt->execute()) {
        $stmt->close();

        return [];
    }

    $res = $stmt->get_result();
    $rawRows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rawRows[] = $row;
        }
        $res->free();
    }
    $stmt->close();

    return $rawRows;
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_country_rivals_pair_rows(mysqli $con, string $heroCountry, ?AmigaSnapshotContext $ctx = null): array
{
    $rows = [];
    foreach (amiga_country_rivals_pair_rows_raw($con, $heroCountry, $ctx) as $row) {
        $rows[] = amiga_player_opponents_normalize_matchup_row($row);
    }

    return $rows;
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_country_rivals_rows(
    mysqli $con,
    string $heroCountry,
    ?AmigaSnapshotContext $ctx = null,
    bool $withPerf = false
): array {
    static $cache = [];

    $heroCountry = amiga_country_rivals_normalize_token($heroCountry);
    if ($heroCountry === '') {
        return [];
    }

    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();
    $cacheKey = amiga_country_rivals_rows_cache_key($heroCountry, $ctx, $withPerf);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $pairRows = amiga_country_rivals_pair_rows($con, $heroCountry, $ctx);
    $rows = amiga_country_rivals_filter_cross_border_rows(
        amiga_country_rivals_rollup_from_pair_rows($pairRows),
        $heroCountry
    );
    if ($rows === []) {
        $cache[$cacheKey] = [];

        return [];
    }

    if ($withPerf) {
        require_once __DIR__ . '/amiga_country_rivals_perf_lib.php';
        $perfByRival = amiga_country_rivals_perf_ratings_batch($con, $heroCountry, $ctx);
        foreach ($rows as $index => $row) {
            $token = (string) $row['rival_token'];
            $perf = $perfByRival[$token] ?? null;
            $rows[$index]['performance_rating'] = is_array($perf) ? ($perf['performance_rating'] ?? null) : null;
            $rows[$index]['performance_rating_vs_hero'] = is_array($perf) ? ($perf['performance_rating_vs_hero'] ?? null) : null;
        }
    }

    $cache[$cacheKey] = $rows;

    return $rows;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>|null
 */
function amiga_country_rivals_bucket_from_rows(array $rows, string $rivalCountry): ?array
{
    $rivalCountry = amiga_country_rivals_normalize_token($rivalCountry);
    if ($rivalCountry === '') {
        return null;
    }

    foreach ($rows as $row) {
        if ((string) $row['rival_token'] === $rivalCountry) {
            return $row;
        }
    }

    return null;
}

/**
 * @param array<string, mixed> $bucket
 * @return array<string, mixed>
 */
function amiga_country_rivals_attach_perf_to_bucket(
    array $bucket,
    mysqli $con,
    string $heroCountry,
    ?AmigaSnapshotContext $ctx = null
): array {
    require_once __DIR__ . '/amiga_country_rivals_perf_lib.php';
    $rivalCountry = (string) ($bucket['rival_token'] ?? '');
    $perf = amiga_country_rivals_perf_ratings_for_pair($con, $heroCountry, $rivalCountry, $ctx);
    $bucket['performance_rating'] = $perf['performance_rating'] ?? null;
    $bucket['performance_rating_vs_hero'] = $perf['performance_rating_vs_hero'] ?? null;

    return $bucket;
}

/**
 * @return array<string, mixed>|null
 */
function amiga_country_rivals_bucket(
    mysqli $con,
    string $heroCountry,
    string $rivalCountry,
    ?AmigaSnapshotContext $ctx = null
): ?array {
    $rivalCountry = amiga_country_rivals_normalize_token($rivalCountry);
    if ($rivalCountry === '' || amiga_country_rivals_is_domestic_rival($heroCountry, $rivalCountry)) {
        return null;
    }

    return amiga_country_rivals_bucket_from_rows(
        amiga_country_rivals_rows($con, $heroCountry, $ctx, false),
        $rivalCountry
    );
}