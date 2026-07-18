<?php
/**
 * Organizer / ops writer for Tier E full-ladder finish overrides.
 *
 * Policy: docs/amiga-organizer-finish-confirm-policy.md (FO2)
 * Plan: docs/amiga-organizer-finish-confirm-implementation-plan.md slice 1
 *
 * Secretary path = full ladder only (1..N, one row per registered entrant).
 * Sparse-band Tier E stays CLI/canon.
 */
declare(strict_types=1);

/**
 * Registered entrant player ids for a tournament (order not significant).
 *
 * @return list<int>
 */
function amiga_ops_finish_override_registered_entrant_ids(mysqli $con, int $tournamentId): array
{
    if ($tournamentId < 1) {
        return [];
    }

    $stmt = $con->prepare(
        "SELECT player_id
         FROM tournament_entrants
         WHERE tournament_id = ?
           AND status = 'registered'
         ORDER BY player_id ASC"
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare finish-override entrants: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute finish-override entrants: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $ids = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int) $row['player_id'];
        }
        $res->free();
    }
    $stmt->close();

    return $ids;
}

/**
 * Validate a full finishing ladder against registered entrants.
 *
 * @param array<int, int> $ladder player_id => event_finish_position
 * @param list<int> $entrantIds registered entrant player ids
 */
function amiga_ops_finish_override_validate_full_ladder(array $ladder, array $entrantIds): void
{
    $entrantIds = array_values(array_unique(array_map('intval', $entrantIds)));
    sort($entrantIds, SORT_NUMERIC);
    $n = count($entrantIds);

    if ($n < 1) {
        throw new InvalidArgumentException(
            'finish override refused: tournament has no registered entrants.'
        );
    }
    if (count($ladder) !== $n) {
        throw new InvalidArgumentException(
            "finish override refused: ladder has " . count($ladder)
            . " player(s); expected {$n} registered entrant(s)."
        );
    }

    $ladderPlayerIds = array_map('intval', array_keys($ladder));
    sort($ladderPlayerIds, SORT_NUMERIC);
    if ($ladderPlayerIds !== $entrantIds) {
        $extra = array_values(array_diff($ladderPlayerIds, $entrantIds));
        $missing = array_values(array_diff($entrantIds, $ladderPlayerIds));
        $parts = [];
        if ($missing !== []) {
            $parts[] = 'missing player_id(s): ' . implode(',', $missing);
        }
        if ($extra !== []) {
            $parts[] = 'unknown player_id(s): ' . implode(',', $extra);
        }
        throw new InvalidArgumentException(
            'finish override refused: ladder players must match registered entrants ('
            . implode('; ', $parts) . ').'
        );
    }

    $positions = [];
    foreach ($ladder as $playerId => $position) {
        $playerId = (int) $playerId;
        if ($playerId < 1) {
            throw new InvalidArgumentException('finish override refused: invalid player_id.');
        }
        if (!is_int($position) && !(is_string($position) && ctype_digit((string) $position))) {
            throw new InvalidArgumentException(
                "finish override refused: position for player {$playerId} must be an integer."
            );
        }
        $position = (int) $position;
        if ($position < 1 || $position > $n) {
            throw new InvalidArgumentException(
                "finish override refused: position {$position} for player {$playerId} "
                . "must be in 1..{$n}."
            );
        }
        if (isset($positions[$position])) {
            throw new InvalidArgumentException(
                "finish override refused: duplicate position {$position}."
            );
        }
        $positions[$position] = $playerId;
    }

    for ($i = 1; $i <= $n; $i++) {
        if (!isset($positions[$i])) {
            throw new InvalidArgumentException(
                "finish override refused: missing position {$i} (full ladder 1..{$n} required)."
            );
        }
    }
}

/**
 * Idempotent replace of Tier E rows for one tournament (full ladder).
 *
 * Deletes all existing overrides for the tournament, then inserts the validated ladder.
 *
 * @param array<int, int> $ladder player_id => event_finish_position
 * @param list<int>|null $entrantIds if null, loads registered entrants from DB
 * @param bool $manageTransaction when true (default), begin/commit/rollback locally
 * @return array{tournament_id:int, written:int, entrant_count:int}
 */
function amiga_ops_finish_override_replace_full_ladder(
    mysqli $con,
    int $tournamentId,
    array $ladder,
    ?array $entrantIds = null,
    bool $manageTransaction = true,
): array {
    if ($tournamentId < 1) {
        throw new InvalidArgumentException('finish override refused: tournament_id must be >= 1.');
    }

    if ($entrantIds === null) {
        $entrantIds = amiga_ops_finish_override_registered_entrant_ids($con, $tournamentId);
    }
    amiga_ops_finish_override_validate_full_ladder($ladder, $entrantIds);

    $normalized = [];
    foreach ($ladder as $playerId => $position) {
        $normalized[(int) $playerId] = (int) $position;
    }

    $write = static function () use ($con, $tournamentId, $normalized): void {
        $del = $con->prepare(
            'DELETE FROM amiga_tournament_finish_override WHERE tournament_id = ?'
        );
        if ($del === false) {
            throw new RuntimeException('prepare finish-override delete: ' . $con->error);
        }
        $del->bind_param('i', $tournamentId);
        if (!$del->execute()) {
            throw new RuntimeException('execute finish-override delete: ' . $del->error);
        }
        $del->close();

        $ins = $con->prepare(
            'INSERT INTO amiga_tournament_finish_override
                (tournament_id, player_id, event_finish_position)
             VALUES (?, ?, ?)'
        );
        if ($ins === false) {
            throw new RuntimeException('prepare finish-override insert: ' . $con->error);
        }

        $playerId = 0;
        $position = 0;
        $ins->bind_param('iii', $tournamentId, $playerId, $position);
        foreach ($normalized as $pid => $pos) {
            $playerId = (int) $pid;
            $position = (int) $pos;
            if (!$ins->execute()) {
                throw new RuntimeException('execute finish-override insert: ' . $ins->error);
            }
        }
        $ins->close();
    };

    if ($manageTransaction) {
        $con->begin_transaction();
        try {
            $write();
            $con->commit();
        } catch (Throwable $e) {
            $con->rollback();
            throw $e;
        }
    } else {
        $write();
    }

    return [
        'tournament_id' => $tournamentId,
        'written' => count($normalized),
        'entrant_count' => count($entrantIds),
    ];
}