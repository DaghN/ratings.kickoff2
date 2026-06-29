<?php
/**
 * Persist world_cup slice rows at tournament finalize.
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_slice_totals_lib.php';
require_once dirname(__DIR__, 3) . '/includes/amiga_player_slice_lib.php';

/**
 * @param array<string, mixed> $row
 * @param list<string> $keyColumns
 */
function amiga_slice_upsert_row(mysqli $con, string $table, array $row, array $keyColumns): void
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
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException("execute {$table} upsert: " . $err);
    }
    $stmt->close();
}

/**
 * @param list<int> $playerIds
 * @return array<int, array<string, mixed>>
 */
function amiga_ops_load_prior_world_cup_slices(
    mysqli $con,
    int $tournamentId,
    array $playerIds,
): array {
    if ($playerIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($playerIds), '?'));
    $types = str_repeat('i', count($playerIds));
    $sliceKey = amiga_slice_key_world_cup();
    $sql = "SELECT ranked.* FROM ("
        . '  SELECT s.*, ROW_NUMBER() OVER ('
        . '    PARTITION BY s.player_id '
        . '    ORDER BY s.event_date DESC, s.event_chrono DESC, s.as_of_tournament_id DESC'
        . '  ) AS rn '
        . '  FROM amiga_player_slice_at_event s '
        . '  INNER JOIN tournaments tc ON tc.id = ? '
        . "  WHERE s.slice_key = ? AND s.player_id IN ({$placeholders}) "
        . '    AND ('
        . '      s.event_date < tc.event_date '
        . '      OR (s.event_date = tc.event_date AND s.event_chrono < tc.chrono) '
        . '      OR ('
        . '        s.event_date = tc.event_date '
        . '        AND s.event_chrono = tc.chrono '
        . '        AND s.as_of_tournament_id < tc.id'
        . '      )'
        . '    )'
        . ') ranked WHERE ranked.rn = 1';

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        return [];
    }
    $bindTypes = 'is' . $types;
    $bindParams = array_merge([$tournamentId, $sliceKey], $playerIds);
    $stmt->bind_param($bindTypes, ...$bindParams);
    if (!$stmt->execute()) {
        $stmt->close();

        return [];
    }
    $res = $stmt->get_result();
    $out = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $out[(int) $row['player_id']] = amiga_slice_from_totals_row($row);
    }
    $stmt->close();

    return $out;
}

/**
 * @param array<int, array<string, mixed>> $sliceByPlayer cumulative slice per participant
 */
function amiga_ops_persist_world_cup_slices(
    mysqli $con,
    int $tournamentId,
    mixed $eventDate,
    float $eventChrono,
    array $sliceByPlayer,
): int {
    if ($sliceByPlayer === []) {
        return 0;
    }

    $sliceKey = amiga_slice_key_world_cup();
    $written = 0;
    foreach ($sliceByPlayer as $playerId => $totals) {
        $pid = (int) $playerId;
        if ((int) ($totals['tournaments_played'] ?? 0) <= 0) {
            continue;
        }
        $atRow = [
            'player_id' => $pid,
            'slice_key' => $sliceKey,
            'as_of_tournament_id' => $tournamentId,
            'event_date' => $eventDate,
            'event_chrono' => $eventChrono,
            'tournaments_played' => (int) ($totals['tournaments_played'] ?? 0),
            'gold' => (int) ($totals['gold'] ?? 0),
            'silver' => (int) ($totals['silver'] ?? 0),
            'bronze' => (int) ($totals['bronze'] ?? 0),
            'podiums' => (int) ($totals['podiums'] ?? 0),
            'games' => (int) ($totals['games'] ?? 0),
            'wins' => (int) ($totals['wins'] ?? 0),
            'draws' => (int) ($totals['draws'] ?? 0),
            'losses' => (int) ($totals['losses'] ?? 0),
            'goals_for' => (int) ($totals['goals_for'] ?? 0),
            'goals_against' => (int) ($totals['goals_against'] ?? 0),
            'points' => (int) ($totals['points'] ?? 0),
            'goal_ratio' => $totals['goal_ratio'] ?? null,
            'most_goals_scored' => (int) ($totals['most_goals_scored'] ?? 0),
            'most_goals_conceded' => (int) ($totals['most_goals_conceded'] ?? 0),
            'biggest_win_difference' => (int) ($totals['biggest_win_difference'] ?? 0),
            'biggest_loss_difference' => (int) ($totals['biggest_loss_difference'] ?? 0),
            'biggest_sum_of_goals' => (int) ($totals['biggest_sum_of_goals'] ?? 0),
            'biggest_draw_sum' => (int) ($totals['biggest_draw_sum'] ?? 0),
            'double_digits' => (int) ($totals['double_digits'] ?? 0),
            'clean_sheets' => (int) ($totals['clean_sheets'] ?? 0),
            'double_digits_ratio' => $totals['double_digits_ratio'] ?? null,
            'clean_sheets_ratio' => $totals['clean_sheets_ratio'] ?? null,
            'double_digits_conceded' => (int) ($totals['double_digits_conceded'] ?? 0),
            'clean_sheets_conceded' => (int) ($totals['clean_sheets_conceded'] ?? 0),
            'double_digits_conceded_ratio' => $totals['double_digits_conceded_ratio'] ?? null,
            'clean_sheets_conceded_ratio' => $totals['clean_sheets_conceded_ratio'] ?? null,
            'opponent_countries_faced' => (int) ($totals['opponent_countries_faced'] ?? 0),
            'opponent_countries_beaten' => (int) ($totals['opponent_countries_beaten'] ?? 0),
            'different_opponents' => (int) ($totals['different_opponents'] ?? 0),
            'different_victims' => (int) ($totals['different_victims'] ?? 0),
            'double_digits_victims' => (int) ($totals['double_digits_victims'] ?? 0),
            'clean_sheets_victims' => (int) ($totals['clean_sheets_victims'] ?? 0),
            'tournaments_played_last_rise_tournament_id' => $totals['tournaments_played_last_rise_tournament_id'] ?? null,
            'tournaments_played_last_rise_event_date' => $totals['tournaments_played_last_rise_event_date'] ?? null,
            // WC HoF (SCH-046): per-event award counters + single-WC peaks.
            'best_attack_awards' => (int) ($totals['best_attack_awards'] ?? 0),
            'best_defense_awards' => (int) ($totals['best_defense_awards'] ?? 0),
            'best_single_wc_gf_per_game' => $totals['best_single_wc_gf_per_game'] ?? null,
            'best_single_wc_gf_per_game_tournament_id' => $totals['best_single_wc_gf_per_game_tournament_id'] ?? null,
            'best_single_wc_ga_per_game' => $totals['best_single_wc_ga_per_game'] ?? null,
            'best_single_wc_ga_per_game_tournament_id' => $totals['best_single_wc_ga_per_game_tournament_id'] ?? null,
        ];
        amiga_slice_upsert_row(
            $con,
            'amiga_player_slice_at_event',
            $atRow,
            ['player_id', 'slice_key', 'as_of_tournament_id']
        );

        $totalRow = $atRow;
        amiga_slice_upsert_row(
            $con,
            'amiga_player_slice_totals',
            $totalRow,
            ['player_id', 'slice_key']
        );
        $written++;
    }

    return $written;
}
