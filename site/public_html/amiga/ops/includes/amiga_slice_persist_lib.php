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
            'tournaments_played_last_rise_tournament_id' => $totals['tournaments_played_last_rise_tournament_id'] ?? null,
            'tournaments_played_last_rise_event_date' => $totals['tournaments_played_last_rise_event_date'] ?? null,
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
