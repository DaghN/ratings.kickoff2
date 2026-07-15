<?php
/**
 * Sparse inverse victim/culprit count changelog at tournament finalize.
 *
 * @see scripts/amiga/inverse_count_changelog.py
 * @see docs/amiga-player-inverse-count-timeline-policy.md
 */
declare(strict_types=1);

/** @return list<array{metric: string, attr: string, column: string}> */
function amiga_ops_inverse_count_metrics(): array
{
    return [
        ['metric' => 'mgs_culprits', 'attr' => 'most_goals_scored_culprits', 'column' => 'MostGoalsScoredCulprits'],
        ['metric' => 'bw_culprits', 'attr' => 'biggest_win_culprits', 'column' => 'BiggestWinCulprits'],
        ['metric' => 'mgc_victims', 'attr' => 'most_goals_conceded_victims', 'column' => 'MostGoalsConcededVictims'],
        ['metric' => 'bl_victims', 'attr' => 'biggest_loss_victims', 'column' => 'BiggestLossVictims'],
    ];
}

/**
 * @return array<string, int> key "playerId|metric" => value_after
 */
function amiga_ops_load_latest_inverse_changelog_values(mysqli $con): array
{
    $sql = 'SELECT player_id, metric, value_after FROM ('
        . 'SELECT player_id, metric, value_after,'
        . ' ROW_NUMBER() OVER ('
        . '   PARTITION BY player_id, metric'
        . '   ORDER BY event_date DESC, event_chrono DESC, tournament_id DESC'
        . ' ) AS rn'
        . ' FROM amiga_player_inverse_count_at_event'
        . ') x WHERE rn = 1';
    $result = $con->query($sql);
    if ($result === false) {
        throw new RuntimeException('load latest inverse changelog: ' . $con->error);
    }
    $out = [];
    while ($row = $result->fetch_assoc()) {
        $out[(int) $row['player_id'] . '|' . (string) $row['metric']] = (int) $row['value_after'];
    }
    $result->free();

    return $out;
}

/**
 * @param array<int, array<string, mixed>> $players
 */
function amiga_ops_persist_inverse_count_changelog_at_tournament(
    mysqli $con,
    int $tournamentId,
    mixed $eventDate,
    float $eventChrono,
    array $players,
): int {
    $prev = amiga_ops_load_latest_inverse_changelog_values($con);
    $metrics = amiga_ops_inverse_count_metrics();
    $rows = [];
    $currentUpdates = [];

    foreach ($players as $pid => $st) {
        $pid = (int) $pid;
        if ((int) ($st['games'] ?? 0) <= 0) {
            continue;
        }
        foreach ($metrics as $m) {
            $mem = (int) ($st[$m['attr']] ?? 0);
            $key = $pid . '|' . $m['metric'];
            $last = $prev[$key] ?? null;
            if ($last === null) {
                if ($mem === 0) {
                    continue;
                }
            } elseif ($mem === $last) {
                continue;
            }
            $rows[] = [
                'player_id' => $pid,
                'tournament_id' => $tournamentId,
                'metric' => $m['metric'],
                'value_after' => $mem,
                'event_date' => $eventDate,
                'event_chrono' => $eventChrono,
                'column' => $m['column'],
            ];
            $currentUpdates[$pid][$m['column']] = $mem;
        }
    }

    if ($rows === []) {
        return 0;
    }

    $sql = 'INSERT INTO amiga_player_inverse_count_at_event'
        . ' (player_id, tournament_id, metric, value_after, event_date, event_chrono)'
        . ' VALUES (?, ?, ?, ?, ?, ?)'
        . ' ON DUPLICATE KEY UPDATE'
        . ' value_after = VALUES(value_after),'
        . ' event_date = VALUES(event_date),'
        . ' event_chrono = VALUES(event_chrono)';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare inverse changelog upsert: ' . $con->error);
    }
    foreach ($rows as $row) {
        $pid = (int) $row['player_id'];
        $tid = (int) $row['tournament_id'];
        $metric = (string) $row['metric'];
        $val = (int) $row['value_after'];
        $ed = $row['event_date'] !== null ? (string) $row['event_date'] : null;
        $ec = (float) $row['event_chrono'];
        $stmt->bind_param('iisisd', $pid, $tid, $metric, $val, $ed, $ec);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException('execute inverse changelog upsert: ' . $err);
        }
    }
    $stmt->close();

    foreach ($currentUpdates as $pid => $cols) {
        $sets = [];
        $types = '';
        $values = [];
        foreach ($cols as $col => $val) {
            $sets[] = '`' . $col . '` = ?';
            $types .= 'i';
            $values[] = $val;
        }
        $types .= 'i';
        $values[] = (int) $pid;
        $sqlUp = 'UPDATE amiga_player_current SET ' . implode(', ', $sets) . ' WHERE player_id = ?';
        $up = $con->prepare($sqlUp);
        if ($up === false) {
            throw new RuntimeException('prepare inverse current update: ' . $con->error);
        }
        $up->bind_param($types, ...$values);
        if (!$up->execute()) {
            $err = $up->error;
            $up->close();
            throw new RuntimeException('execute inverse current update: ' . $err);
        }
        $up->close();
    }

    return count($rows);
}