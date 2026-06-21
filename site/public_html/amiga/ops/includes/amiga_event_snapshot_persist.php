<?php
/**
 * Persist amiga_player_event_snapshots + amiga_player_current after finalize.
 *
 * @see docs/amiga-event-snapshot-policy.md
 * @see scripts/amiga/snapshot_persist.py (Python parity)
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/amiga_player_geo_year_lib.php';
require_once __DIR__ . '/../includes/amiga_honours_totals_lib.php';
require_once __DIR__ . '/../includes/amiga_career_rise_lib.php';
require_once dirname(__DIR__, 3) . '/ops/includes/post_game_player_state.php';

/**
 * @return list<string>
 */
function amiga_ops_snapshot_participation_columns(): array
{
    return [
        'player_id',
        'tournament_id',
        'event_date',
        'event_chrono',
        'tournament_name',
        'is_cup',
        'country',
        'has_league',
        'has_cup',
        'finalized_at',
        'event_finish_position',
        'event_points',
        'games',
        'wins',
        'draws',
        'losses',
        'goals_for',
        'goals_against',
        'avg_goals_for',
        'avg_goals_against',
        'rating_before',
        'rating_delta',
        'rating_after',
        'performance_rating',
        'games_in_event',
        'is_winner',
        'best_knockout_phase',
    ];
}

/**
 * @return list<string>
 */
function amiga_ops_snapshot_geo_year_columns(): array
{
    return [
        'peak_year_games',
        'peak_year_games_year',
        'peak_year_tournaments',
        'peak_year_tournaments_year',
        'countries_played_in',
        'opponent_countries_faced',
        'opponent_countries_beaten',
    ];
}

/**
 * @return list<string>
 */
function amiga_ops_snapshot_rise_columns(): array
{
    return array_merge(
        amiga_honours_rise_player_columns(),
        amiga_geo_rise_player_columns(),
        amiga_career_rise_player_columns(),
    );
}

/**
 * @return list<string>
 */
function amiga_ops_snapshot_honours_columns(): array
{
    return [
        'tournaments_played',
        'tournaments_won',
        'event_gold',
        'event_silver',
        'event_bronze',
        'event_podiums',
        'wc_played',
        'wc_gold',
        'wc_silver',
        'wc_bronze',
        'wc_podiums',
        'honours_last_event_date',
        'honours_last_tournament_id',
    ];
}

/**
 * @param array<string, mixed> $totals
 * @return array<string, mixed>
 */
function amiga_ops_honours_columns_from_totals_row(array $totals): array
{
    $row = [
        'tournaments_played' => (int) ($totals['tournaments_played'] ?? 0),
        'tournaments_won' => (int) ($totals['tournaments_won'] ?? 0),
        'event_gold' => (int) ($totals['event_gold'] ?? 0),
        'event_silver' => (int) ($totals['event_silver'] ?? 0),
        'event_bronze' => (int) ($totals['event_bronze'] ?? 0),
        'event_podiums' => (int) ($totals['event_podiums'] ?? 0),
        'wc_played' => (int) ($totals['wc_played'] ?? 0),
        'wc_gold' => (int) ($totals['wc_gold'] ?? 0),
        'wc_silver' => (int) ($totals['wc_silver'] ?? 0),
        'wc_bronze' => (int) ($totals['wc_bronze'] ?? 0),
        'wc_podiums' => (int) ($totals['wc_podiums'] ?? 0),
        'honours_last_event_date' => $totals['last_event_date'] ?? null,
        'honours_last_tournament_id' => $totals['last_tournament_id'] ?? null,
    ];
    foreach (amiga_honours_rise_player_columns() as $col) {
        $row[$col] = $totals[$col] ?? null;
    }

    return $row;
}

function amiga_ops_perf_qualifies(?float $performanceRating, int $games): bool
{
    return $performanceRating !== null && $games >= 2;
}

/**
 * @return array{0: ?float, 1: ?int}
 */
function amiga_ops_career_best_performance_fields(
    ?float $performanceRating,
    int $tournamentId,
    int $games,
    ?float $priorRating = null,
    ?int $priorTournamentId = null,
    int $priorGames = 0
): array {
    if (!amiga_ops_perf_qualifies($performanceRating, $games)) {
        return [$priorRating, $priorTournamentId];
    }

    if ($priorRating === null || $priorTournamentId === null) {
        return [$performanceRating, $tournamentId];
    }

    $candidate = [$performanceRating, $games, $tournamentId];
    $prior = [$priorRating, $priorGames, $priorTournamentId];
    if ($candidate > $prior) {
        return [$performanceRating, $tournamentId];
    }

    return [$priorRating, $priorTournamentId];
}

/**
 * @param array<string, mixed> $row
 * @param list<string> $keyColumns
 */
function amiga_ops_upsert_row(mysqli $con, string $table, array $row, array $keyColumns): void
{
    $columns = array_keys($row);
    $colList = implode(', ', array_map(static fn (string $c): string => "`{$c}`", $columns));
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $updates = [];
    foreach ($columns as $col) {
        if (!in_array($col, $keyColumns, true)) {
            $updates[] = "`{$col}` = VALUES(`{$col}`)";
        }
    }
    $sql = "INSERT INTO `{$table}` ({$colList}) VALUES ({$placeholders}) "
        . 'ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException("prepare {$table} upsert: " . $con->error);
    }

    $types = '';
    $values = [];
    foreach ($columns as $col) {
        $val = $row[$col];
        if ($val === null) {
            $types .= 's';
            $values[] = null;
        } elseif (is_int($val)) {
            $types .= 'i';
            $values[] = $val;
        } elseif (is_float($val)) {
            $types .= 'd';
            $values[] = $val;
        } else {
            $types .= 's';
            $values[] = (string) $val;
        }
    }

    $bind = [$types];
    foreach ($values as $i => $v) {
        $bind[] = &$values[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
    if (!$stmt->execute()) {
        throw new RuntimeException("execute {$table} upsert: " . $stmt->error);
    }
    $stmt->close();
}

/**
 * @param array<string, mixed> $snapshot
 * @return array<string, mixed>
 */
function amiga_ops_current_row_from_snapshot(array $snapshot): array
{
    $careerTemplate = k2_post_game_player_to_db_row(k2_post_game_player_state_new(), 0);
    unset($careerTemplate['ID']);
    $careerColumns = array_keys($careerTemplate);

    $current = [
        'player_id' => (int) $snapshot['player_id'],
        'last_tournament_id' => (int) $snapshot['tournament_id'],
        'last_event_date' => $snapshot['event_date'] ?? null,
        'last_finalized_at' => $snapshot['finalized_at'] ?? null,
    ];

    foreach ($careerColumns as $col) {
        $current[$col] = $snapshot[$col] ?? null;
    }

    foreach (amiga_ops_snapshot_honours_columns() as $col) {
        if ($col === 'honours_last_event_date' || $col === 'honours_last_tournament_id') {
            continue;
        }
        $current[$col] = $snapshot[$col] ?? 0;
    }

    $current['career_best_performance_rating'] = $snapshot['career_best_performance_rating'] ?? null;
    $current['career_best_performance_tournament_id'] = $snapshot['career_best_performance_tournament_id'] ?? null;

    foreach (amiga_ops_snapshot_geo_year_columns() as $col) {
        $current[$col] = $snapshot[$col] ?? 0;
    }

    foreach (amiga_ops_snapshot_rise_columns() as $col) {
        $current[$col] = $snapshot[$col] ?? null;
    }

    return $current;
}

/**
 * @param array<string, mixed> $participation
 * @param array<string, mixed> $careerDbRow from k2_post_game_player_to_db_row (no ID)
 * @param array<string, mixed> $honours
 * @return array<string, mixed>
 */
function amiga_ops_build_event_snapshot_row(
    array $participation,
    array $careerDbRow,
    array $honours,
    ?float $careerBestPerformanceRating,
    ?int $careerBestPerformanceTournamentId
): array {
    $snapshot = [];
    foreach (amiga_ops_snapshot_participation_columns() as $col) {
        $snapshot[$col] = $participation[$col] ?? null;
    }
    unset($careerDbRow['ID']);
    $snapshot = array_merge($snapshot, $careerDbRow);
    $snapshot = array_merge($snapshot, $honours);
    $snapshot['career_best_performance_rating'] = $careerBestPerformanceRating;
    $snapshot['career_best_performance_tournament_id'] = $careerBestPerformanceTournamentId;

    return $snapshot;
}

/**
 * @param array<string, mixed> $snapshot
 * @param array<string, mixed> $geoScalars
 * @return array<string, mixed>
 */
function amiga_ops_apply_geo_year_to_snapshot(array $snapshot, array $geoScalars): array
{
    foreach (amiga_ops_snapshot_geo_year_columns() as $col) {
        if (array_key_exists($col, $geoScalars)) {
            $snapshot[$col] = $geoScalars[$col];
            continue;
        }
        $snapshot[$col] = str_ends_with($col, '_year') ? null : 0;
    }
    foreach (amiga_geo_rise_player_columns() as $col) {
        $snapshot[$col] = $geoScalars[$col] ?? null;
    }

    return $snapshot;
}

/**
 * @param list<int> $playerIds
 * @return array<int, array<string, mixed>>
 */
function amiga_ops_load_prior_snapshot_carry_before_tournament(
    mysqli $con,
    int $tournamentId,
    array $playerIds,
): array {
    if ($playerIds === []) {
        return [];
    }
    $placeholders = implode(', ', array_fill(0, count($playerIds), '?'));
    $types = str_repeat('i', count($playerIds));
    $careerValueCols = array_map(
        static fn (array $spec): string => $spec[0],
        AMIGA_CAREER_RISE_SPECS,
    );
    $careerColsSql = implode(', ', array_map(static fn (string $c): string => "s.`{$c}`", $careerValueCols));
    $riseCols = implode(', ', array_map(static fn (string $c): string => "s.`{$c}`", amiga_ops_snapshot_rise_columns()));
    $sql = 'SELECT s.player_id, s.tournaments_played, s.tournaments_won, '
        . 's.event_gold, s.event_silver, s.event_bronze, s.event_podiums, '
        . 's.wc_played, s.wc_gold, s.wc_silver, s.wc_bronze, s.wc_podiums, '
        . 's.honours_last_event_date, s.honours_last_tournament_id, '
        . $careerColsSql . ', ' . $riseCols . ' '
        . 'FROM ('
        . '  SELECT s_inner.*, '
        . '         ROW_NUMBER() OVER ('
        . '           PARTITION BY s_inner.player_id '
        . '           ORDER BY s_inner.event_date DESC, s_inner.event_chrono DESC, s_inner.tournament_id DESC'
        . '         ) AS rn '
        . '  FROM amiga_player_event_snapshots s_inner '
        . '  INNER JOIN tournaments tc ON tc.id = ? '
        . "  WHERE s_inner.player_id IN ({$placeholders}) "
        . '    AND ('
        . '      s_inner.event_date < tc.event_date '
        . '      OR (s_inner.event_date = tc.event_date AND s_inner.event_chrono < tc.chrono) '
        . '      OR ('
        . '        s_inner.event_date = tc.event_date '
        . '        AND s_inner.event_chrono = tc.chrono '
        . '        AND s_inner.tournament_id < tc.id'
        . '      )'
        . '    )'
        . ') s WHERE s.rn = 1';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare prior snapshot carry: ' . $con->error);
    }
    $bindTypes = 'i' . $types;
    $bindParams = array_merge([$tournamentId], $playerIds);
    $stmt->bind_param($bindTypes, ...$bindParams);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute prior snapshot carry: ' . $stmt->error);
    }
    $out = [];
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $out[(int) $row['player_id']] = $row;
    }
    $stmt->close();

    return $out;
}

/**
 * @param array<int, array<string, mixed>> $players
 * @param list<int> $participantIds
 */
function amiga_ops_persist_tournament_event_snapshots(
    mysqli $con,
    int $tournamentId,
    array $players,
    array $participantIds,
    ?array $participationByPlayer = null,
): int {
    $activeIds = [];
    foreach ($participantIds as $pid) {
        $pid = (int) $pid;
        if ((int) ($players[$pid]['games'] ?? 0) > 0) {
            $activeIds[] = $pid;
        }
    }
    if ($activeIds === []) {
        return 0;
    }

    $placeholders = implode(', ', array_fill(0, count($activeIds), '?'));
    $types = str_repeat('i', count($activeIds));

    if ($participationByPlayer === null) {
        throw new RuntimeException(
            'amiga_ops_persist_tournament_event_snapshots requires participation_by_player'
        );
    }

    $totalsByPlayer = [];
    $carryByPlayer = [];
    $careerValueCols = array_map(
        static fn (array $spec): string => $spec[0],
        AMIGA_CAREER_RISE_SPECS,
    );
    $careerColsSql = implode(', ', array_map(static fn (string $c): string => "`{$c}`", $careerValueCols));
    $riseCols = implode(', ', amiga_ops_snapshot_rise_columns());
    $sql = "SELECT player_id, tournaments_played, tournaments_won,
                   event_gold, event_silver, event_bronze, event_podiums,
                   wc_played, wc_gold, wc_silver, wc_bronze, wc_podiums,
                   last_event_date, last_tournament_id,
                   {$careerColsSql},
                   {$riseCols}
            FROM amiga_player_current
            WHERE player_id IN ({$placeholders})";
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare totals load: ' . $con->error);
    }
    $stmt->bind_param($types, ...$activeIds);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute totals load: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $pid = (int) $row['player_id'];
        $carryByPlayer[$pid] = $row;
        $totalsByPlayer[$pid] = amiga_honours_totals_from_current_row($row);
    }
    $stmt->close();

    $missingIds = array_values(array_filter(
        $activeIds,
        static fn (int $pid): bool => !isset($carryByPlayer[$pid])
    ));
    if ($missingIds !== []) {
        foreach (amiga_ops_load_prior_snapshot_carry_before_tournament($con, $tournamentId, $missingIds) as $pid => $row) {
            $carryByPlayer[$pid] = $row;
            if (!isset($totalsByPlayer[$pid])) {
                $totalsByPlayer[$pid] = amiga_honours_totals_from_snapshot_row($row);
            }
        }
    }

    $priorBest = [];
    $sql = 'SELECT ranked.player_id, ranked.career_best_performance_rating, '
        . 'ranked.career_best_performance_tournament_id, pg.games AS prior_games '
        . 'FROM ('
        . '  SELECT s.player_id, s.career_best_performance_rating, '
        . '         s.career_best_performance_tournament_id, '
        . '         ROW_NUMBER() OVER ('
        . '           PARTITION BY s.player_id '
        . '           ORDER BY s.event_date DESC, s.event_chrono DESC, s.tournament_id DESC'
        . '         ) AS rn '
        . '  FROM amiga_player_event_snapshots s '
        . '  INNER JOIN tournaments tc ON tc.id = ? '
        . "  WHERE s.player_id IN ({$placeholders}) "
        . '    AND ('
        . '      s.event_date < tc.event_date '
        . '      OR (s.event_date = tc.event_date AND s.event_chrono < tc.chrono) '
        . '      OR ('
        . '        s.event_date = tc.event_date '
        . '        AND s.event_chrono = tc.chrono '
        . '        AND s.tournament_id < tc.id'
        . '      )'
        . '    )'
        . ') ranked '
        . 'LEFT JOIN amiga_player_event_snapshots pg '
        . '  ON pg.player_id = ranked.player_id '
        . ' AND pg.tournament_id = ranked.career_best_performance_tournament_id '
        . 'WHERE ranked.rn = 1';
    $stmt = $con->prepare($sql);
    if ($stmt !== false) {
        $bindTypes = 'i' . $types;
        $bindParams = array_merge([$tournamentId], $activeIds);
        $stmt->bind_param($bindTypes, ...$bindParams);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $pid = (int) $row['player_id'];
                $priorBest[$pid] = [
                    'rating' => $row['career_best_performance_rating'] !== null
                        ? (float) $row['career_best_performance_rating'] : null,
                    'tournament_id' => $row['career_best_performance_tournament_id'] !== null
                        ? (int) $row['career_best_performance_tournament_id'] : null,
                    'games' => (int) ($row['prior_games'] ?? 0),
                ];
            }
        }
        $stmt->close();
    }

    $written = 0;
    $playerCountries = amiga_geo_year_load_player_countries($con);
    $geoTracker = amiga_geo_year_tracker_through_tournament($con, $tournamentId);
    foreach ($activeIds as $pid) {
        $participation = $participationByPlayer[$pid] ?? null;
        if ($participation === null) {
            continue;
        }

        $totals = $totalsByPlayer[$pid] ?? amiga_honours_empty_totals();
        if (!isset($totalsByPlayer[$pid])) {
            $totals['last_event_date'] = $participation['event_date'] ?? null;
            $totals['last_tournament_id'] = $tournamentId;
        }

        $eventTotals = $totals;
        amiga_honours_increment_totals($eventTotals, $participation);

        $prior = $priorBest[$pid] ?? ['rating' => null, 'tournament_id' => null, 'games' => 0];
        $perf = $participation['performance_rating'] !== null
            ? (float) $participation['performance_rating'] : null;
        $games = (int) ($participation['games'] ?? 0);
        [$bestRating, $bestTid] = amiga_ops_career_best_performance_fields(
            $perf,
            $tournamentId,
            $games,
            $prior['rating'],
            $prior['tournament_id'],
            $prior['games']
        );

        $careerDbRow = k2_post_game_player_to_db_row($players[$pid], $pid);
        unset($careerDbRow['ID']);

        $carry = $carryByPlayer[$pid] ?? [];
        $careerRise = amiga_career_apply_rise_fields(
            amiga_career_rise_from_row($carry),
            amiga_career_prior_values_from_row($carry),
            $careerDbRow,
            $tournamentId,
            $participation['event_date'] ?? null,
        );

        $snapshot = amiga_ops_build_event_snapshot_row(
            $participation,
            $careerDbRow,
            amiga_ops_honours_columns_from_totals_row($eventTotals),
            $bestRating,
            $bestTid
        );
        foreach ($careerRise as $col => $val) {
            $snapshot[$col] = $val;
        }
        $geoScalars = $geoTracker->scalarsFor($pid, $playerCountries[$pid] ?? null);
        $snapshot = amiga_ops_apply_geo_year_to_snapshot($snapshot, $geoScalars);
        $current = amiga_ops_current_row_from_snapshot($snapshot);

        amiga_ops_upsert_row($con, 'amiga_player_event_snapshots', $snapshot, ['player_id', 'tournament_id']);
        amiga_ops_upsert_row($con, 'amiga_player_current', $current, ['player_id']);
        $written++;
    }

    return $written;
}
