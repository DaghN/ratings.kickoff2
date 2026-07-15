<?php
/**
 * Amiga inverse-count changelog reads (TT + present overlay).
 *
 * @see docs/amiga-player-inverse-count-timeline-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_snapshot_context.php';

/**
 * SQL fragment: LEFT JOIN latest inverse counts at cutoff (alias ``inv``).
 * Binds three cutoff params (sdi) when used with TT snapshot FROM.
 */
function amiga_inverse_count_latest_join_sql(string $alias = 'inv'): string
{
    return "LEFT JOIN (\n"
        . "    SELECT player_id,\n"
        . "        MAX(CASE WHEN metric = 'mgs_culprits' THEN value_after END) AS MostGoalsScoredCulprits,\n"
        . "        MAX(CASE WHEN metric = 'bw_culprits' THEN value_after END) AS BiggestWinCulprits,\n"
        . "        MAX(CASE WHEN metric = 'mgc_victims' THEN value_after END) AS MostGoalsConcededVictims,\n"
        . "        MAX(CASE WHEN metric = 'bl_victims' THEN value_after END) AS BiggestLossVictims\n"
        . "    FROM (\n"
        . "        SELECT player_id, metric, value_after,\n"
        . "            ROW_NUMBER() OVER (\n"
        . "                PARTITION BY player_id, metric\n"
        . "                ORDER BY event_date DESC, event_chrono DESC, tournament_id DESC\n"
        . "            ) AS rn\n"
        . "        FROM amiga_player_inverse_count_at_event\n"
        . "        WHERE (event_date, event_chrono, tournament_id) <= (?, ?, ?)\n"
        . "    ) x\n"
        . "    WHERE rn = 1\n"
        . "    GROUP BY player_id\n"
        . ") {$alias} ON {$alias}.player_id = p.id";
}

/**
 * Load four inverse counts for one player at cutoff (or present = no cutoff filter).
 *
 * @return array{MostGoalsScoredCulprits: int, BiggestWinCulprits: int, MostGoalsConcededVictims: int, BiggestLossVictims: int}
 */
function amiga_inverse_count_values_for_player(
    mysqli $con,
    int $playerId,
    ?AmigaSnapshotContext $ctx = null
): array {
    $defaults = [
        'MostGoalsScoredCulprits' => 0,
        'BiggestWinCulprits' => 0,
        'MostGoalsConcededVictims' => 0,
        'BiggestLossVictims' => 0,
    ];
    if ($playerId < 1) {
        return $defaults;
    }

    if ($ctx === null || !$ctx->isActive()) {
        $sql = 'SELECT MostGoalsScoredCulprits, BiggestWinCulprits, MostGoalsConcededVictims, BiggestLossVictims'
            . ' FROM amiga_player_current WHERE player_id = ? LIMIT 1';
        $stmt = $con->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('prepare inverse present: ' . $con->error);
        }
        $stmt->bind_param('i', $playerId);
        if (!$stmt->execute()) {
            throw new RuntimeException('execute inverse present: ' . $stmt->error);
        }
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if ($row === null) {
            return $defaults;
        }

        return [
            'MostGoalsScoredCulprits' => (int) ($row['MostGoalsScoredCulprits'] ?? 0),
            'BiggestWinCulprits' => (int) ($row['BiggestWinCulprits'] ?? 0),
            'MostGoalsConcededVictims' => (int) ($row['MostGoalsConcededVictims'] ?? 0),
            'BiggestLossVictims' => (int) ($row['BiggestLossVictims'] ?? 0),
        ];
    }

    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return $defaults;
    }
    $sql = 'SELECT'
        . " MAX(CASE WHEN metric = 'mgs_culprits' THEN value_after END) AS MostGoalsScoredCulprits,"
        . " MAX(CASE WHEN metric = 'bw_culprits' THEN value_after END) AS BiggestWinCulprits,"
        . " MAX(CASE WHEN metric = 'mgc_victims' THEN value_after END) AS MostGoalsConcededVictims,"
        . " MAX(CASE WHEN metric = 'bl_victims' THEN value_after END) AS BiggestLossVictims"
        . ' FROM ('
        . '   SELECT metric, value_after,'
        . '     ROW_NUMBER() OVER ('
        . '       PARTITION BY metric'
        . '       ORDER BY event_date DESC, event_chrono DESC, tournament_id DESC'
        . '     ) AS rn'
        . '   FROM amiga_player_inverse_count_at_event'
        . '   WHERE player_id = ?'
        . '     AND (event_date, event_chrono, tournament_id) <= (?, ?, ?)'
        . ' ) x WHERE rn = 1';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare inverse TT: ' . $con->error);
    }
    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tournamentId = $cutoff['tournament_id'];
    $stmt->bind_param('isdi', $playerId, $eventDate, $chrono, $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute inverse TT: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row === null) {
        return $defaults;
    }

    return [
        'MostGoalsScoredCulprits' => (int) ($row['MostGoalsScoredCulprits'] ?? 0),
        'BiggestWinCulprits' => (int) ($row['BiggestWinCulprits'] ?? 0),
        'MostGoalsConcededVictims' => (int) ($row['MostGoalsConcededVictims'] ?? 0),
        'BiggestLossVictims' => (int) ($row['BiggestLossVictims'] ?? 0),
    ];
}

/**
 * Overlay inverse counts onto a profile/LB row (mutates).
 *
 * @param array<string, mixed> $row
 */
function amiga_inverse_count_overlay_row(
    mysqli $con,
    int $playerId,
    array &$row,
    ?AmigaSnapshotContext $ctx = null
): void {
    $vals = amiga_inverse_count_values_for_player($con, $playerId, $ctx);
    foreach ($vals as $col => $val) {
        $row[$col] = $val;
    }
}