<?php
/**
 * Integer tournament chrono — bump/decrement for Case C insert/delete.
 *
 * @see docs/amiga-chrono-integer-policy.md
 */
declare(strict_types=1);

/**
 * SQL: tournament row strictly after cutoff N. Bind: event_date, event_date, chrono, event_date, chrono, id.
 */
function amiga_case_c_chrono_after_sql(string $alias = 't'): string
{
    $a = $alias === '' ? '' : rtrim($alias, '.') . '.';

    return '('
        . "{$a}event_date > ? "
        . "OR ({$a}event_date = ? AND {$a}chrono > ?) "
        . "OR ({$a}event_date = ? AND {$a}chrono = ? AND {$a}id > ?)"
        . ')';
}

function amiga_chrono_persist_tournament_chrono(mysqli $con, int $tournamentId, float $chrono): void
{
    $stmt = $con->prepare('UPDATE tournaments SET chrono = ? WHERE id = ?');
    if ($stmt === false) {
        throw new RuntimeException('prepare chrono persist: ' . $con->error);
    }
    $stmt->bind_param('di', $chrono, $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute chrono persist: ' . $stmt->error);
    }
    $stmt->close();
}

/**
 * Tournaments strictly after cutoff N in catalog order.
 *
 * @param array{id:int, event_date:?string, chrono:?float} $cutoffN
 * @return list<array{id:int, name:string, event_date:?string, chrono:?float}>
 */
function amiga_chrono_list_strictly_after_cutoff(
    mysqli $con,
    array $cutoffN,
    ?int $excludeTournamentId = null,
    bool $finalizedOnly = false
): array {
    $cutoffId = (int) $cutoffN['id'];
    $eventDate = (string) ($cutoffN['event_date'] ?? '');
    $chrono = (float) ($cutoffN['chrono'] ?? 0);
    $sql = 'SELECT id, name, event_date, chrono FROM tournaments t WHERE '
        . amiga_case_c_chrono_after_sql('t');
    if ($excludeTournamentId !== null && $excludeTournamentId > 0) {
        $sql .= ' AND t.id <> ' . (int) $excludeTournamentId;
    }
    if ($finalizedOnly) {
        $sql .= ' AND COALESCE(t.rating_finalized, 0) = 1';
    }
    $sql .= ' ORDER BY t.event_date ASC, t.chrono ASC, t.id ASC';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare chrono list after cutoff: ' . $con->error);
    }
    $stmt->bind_param('ssdsdi', $eventDate, $eventDate, $chrono, $eventDate, $chrono, $cutoffId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute chrono list after cutoff: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $out = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $out[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'event_date' => $row['event_date'] !== null ? (string) $row['event_date'] : null,
                'chrono' => $row['chrono'] !== null ? (float) $row['chrono'] : null,
            ];
        }
    }
    $stmt->close();

    return $out;
}

/**
 * Preview slot integer for mid-history insert (no writes).
 *
 * @param array{id:int, event_date:?string, chrono:?float} $cutoffN
 */
function amiga_chrono_integer_insert_slot_preview(mysqli $con, array $cutoffN, int $excludeMId): int
{
    $after = amiga_chrono_list_strictly_after_cutoff($con, $cutoffN, $excludeMId, false);
    if ($after === []) {
        return (int) round((float) ($cutoffN['chrono'] ?? 0)) + 1;
    }

    return (int) round((float) ($after[0]['chrono'] ?? 0));
}

/**
 * +1 ground chrono on all tournaments strictly after N (excluding M). Returns M slot integer.
 *
 * @param array{id:int, event_date:?string, chrono:?float} $cutoffN
 */
function amiga_chrono_integer_bump_forward_after_cutoff(
    mysqli $con,
    array $cutoffN,
    int $excludeMId
): int {
    $after = amiga_chrono_list_strictly_after_cutoff($con, $cutoffN, $excludeMId, false);
    if ($after === []) {
        $slot = (int) round((float) ($cutoffN['chrono'] ?? 0)) + 1;
        return $slot;
    }
    $slot = (int) round((float) ($after[0]['chrono'] ?? 0));
    $desc = array_reverse($after);
    foreach ($desc as $row) {
        $tid = (int) $row['id'];
        $newChrono = (float) $row['chrono'] + 1.0;
        amiga_chrono_persist_tournament_chrono($con, $tid, $newChrono);
    }

    return $slot;
}

/**
 * -1 ground chrono on all tournaments strictly after N (after M deleted).
 *
 * @param array{id:int, event_date:?string, chrono:?float} $cutoffN
 */
function amiga_chrono_integer_decrement_forward_after_cutoff(mysqli $con, array $cutoffN): void
{
    $after = amiga_chrono_list_strictly_after_cutoff($con, $cutoffN, null, false);
    foreach ($after as $row) {
        $newChrono = (float) $row['chrono'] - 1.0;
        if ($newChrono < 1.0) {
            throw new RuntimeException(
                'chrono decrement would drop below 1 for tournament_id=' . (int) $row['id']
            );
        }
        amiga_chrono_persist_tournament_chrono($con, (int) $row['id'], $newChrono);
    }
}