<?php
/**
 * Persist cumulative matchup rows at tournament finalize.
 *
 * @see scripts/amiga/matchup_persist.py
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_matchup_cumulative.php';

/**
 * @return list<string>
 */
function amiga_matchup_pair_columns(): array
{
    return [
        'games',
        'wins',
        'draws',
        'losses',
        'goals_for',
        'goals_against',
        'max_goals_for',
        'max_goals_against',
        'min_goals_for',
        'min_goals_against',
        'max_win_margin',
        'max_loss_margin',
        'max_draw_goals',
        'max_goal_sum',
        'min_goal_sum',
        'dd_wins',
        'dd_losses',
        'cs_wins',
        'cs_losses',
        'performance_rating',
    ];
}

/** mysqli bind types for the pair columns ('d' for the decimal perf rating). */
function amiga_matchup_pair_column_types(): string
{
    $types = '';
    foreach (amiga_matchup_pair_columns() as $col) {
        $types .= $col === 'performance_rating' ? 'd' : 'i';
    }

    return $types;
}

/**
 * @param list<int> $participantIds
 */
function amiga_ops_persist_matchup_at_event(
    mysqli $con,
    int $tournamentId,
    string $eventDate,
    float $eventChrono,
    AmigaMatchupCumulative $matchups,
    array $participantIds
): int {
    $stmt = $con->prepare(
        'DELETE FROM amiga_player_matchup_at_event WHERE as_of_tournament_id = ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare matchup at-event delete: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute matchup at-event delete: ' . $stmt->error);
    }
    $stmt->close();

    $cols = amiga_matchup_pair_columns();
    $colList = 'player_id, opponent_id, as_of_tournament_id, event_date, event_chrono, '
        . implode(', ', $cols);
    $placeholders = implode(', ', array_fill(0, 5 + count($cols), '?'));
    $sql = "INSERT INTO amiga_player_matchup_at_event ({$colList}) VALUES ({$placeholders})";
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare matchup at-event insert: ' . $con->error);
    }

    $written = 0;
    sort($participantIds);
    foreach ($participantIds as $pid) {
        $pid = (int) $pid;
        $pairs = $matchups->pairsForPlayer($pid);
        ksort($pairs);
        foreach ($pairs as $oid => $totals) {
            $row = $matchups->pairToRow($pid, (int) $oid, $totals);
            $params = [
                $row['player_id'],
                $row['opponent_id'],
                $tournamentId,
                $eventDate,
                $eventChrono,
            ];
            foreach ($cols as $col) {
                $params[] = $row[$col];
            }
            $types = 'iiisd' . amiga_matchup_pair_column_types();
            $stmt->bind_param($types, ...$params);
            if (!$stmt->execute()) {
                throw new RuntimeException('execute matchup at-event insert: ' . $stmt->error);
            }
            $written++;
        }
    }
    $stmt->close();

    return $written;
}

/**
 * @param list<int> $participantIds
 */
function amiga_ops_upsert_matchup_summary(
    mysqli $con,
    AmigaMatchupCumulative $matchups,
    array $participantIds
): int {
    $cols = amiga_matchup_pair_columns();
    $colList = 'player_id, opponent_id, ' . implode(', ', $cols);
    $updates = implode(', ', array_map(static fn (string $c): string => "{$c}=VALUES({$c})", $cols));
    $placeholders = implode(', ', array_fill(0, 2 + count($cols), '?'));
    $sql = "INSERT INTO amiga_player_matchup_summary ({$colList}) VALUES ({$placeholders}) "
        . "ON DUPLICATE KEY UPDATE {$updates}";
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare matchup summary upsert: ' . $con->error);
    }

    $written = 0;
    sort($participantIds);
    foreach ($participantIds as $pid) {
        $pid = (int) $pid;
        $pairs = $matchups->pairsForPlayer($pid);
        ksort($pairs);
        foreach ($pairs as $oid => $totals) {
            $row = $matchups->pairToRow($pid, (int) $oid, $totals);
            $params = [$row['player_id'], $row['opponent_id']];
            foreach ($cols as $col) {
                $params[] = $row[$col];
            }
            $types = 'ii' . amiga_matchup_pair_column_types();
            $stmt->bind_param($types, ...$params);
            if (!$stmt->execute()) {
                throw new RuntimeException('execute matchup summary upsert: ' . $stmt->error);
            }
            $written++;
        }
    }
    $stmt->close();

    return $written;
}
