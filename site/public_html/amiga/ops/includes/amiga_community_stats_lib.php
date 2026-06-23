<?php
/**
 * Community stats persist (mirrors scripts/amiga/community_persist.py).
 *
 * @see docs/amiga-community-stats-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_community_stat_registry.php';
require_once __DIR__ . '/amiga_community_realm_scan_lib.php';
require_once __DIR__ . '/amiga_realm_snapshot_lib.php';

/**
 * @return list<array<string, mixed>>
 */
function amiga_community_build_facts_at_cutoff(mysqli $con, int $tournamentId): array
{
    return amiga_community_build_realm_scan($con, $tournamentId)['facts'];
}

/**
 * @param array<string, mixed> $headline
 * @param array{event_date: string, chrono: float|int, tournament_id: int, tournament_name: string} $cutoff
 */
function amiga_community_persist_headline(
    mysqli $con,
    int $tournamentId,
    array $cutoff,
    array $headline,
    string $finalizedAt,
): void {
    $headlineCols = amiga_community_headline_column_names();
    $intCols = [
        'NumberOfPlayers',
        'GamesPlayed',
        'NumberOfDecidedGames',
        'NumberOfDraws',
        'GoalsScored',
        'DoubleDigits',
        'CleanSheets',
        'TournamentsFinalized',
        'DistinctHostCountries',
        'WcGamesPlayed',
        'DistinctOpponentPairs',
        'PlayersDebuted',
    ];
    $cols = ['tournament_id', 'event_date', 'event_chrono', 'tournament_name', 'finalized_at', ...$headlineCols];
    $placeholders = implode(', ', array_fill(0, count($cols), '?'));
    $updates = [];
    foreach ($cols as $col) {
        if ($col === 'tournament_id') {
            continue;
        }
        $updates[] = "`{$col}` = VALUES(`{$col}`)";
    }
    $sql = 'INSERT INTO amiga_community_stats_snapshots (`' . implode('`, `', $cols) . '`) VALUES ('
        . $placeholders . ') ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare community headline snapshot: ' . $con->error);
    }

    $bind = [
        $tournamentId,
        $cutoff['event_date'],
        $cutoff['chrono'],
        $cutoff['tournament_name'],
        $finalizedAt,
    ];
    foreach ($headlineCols as $col) {
        $bind[] = $headline[$col] ?? null;
    }

    $types = 'isdss';
    foreach ($headlineCols as $col) {
        $types .= in_array($col, $intCols, true) ? 'i' : 'd';
    }
    $stmt->bind_param($types, ...$bind);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute community headline snapshot: ' . $stmt->error);
    }
    $stmt->close();

    $setParts = [];
    foreach ($headlineCols as $col) {
        $setParts[] = "`{$col}` = ?";
    }
    $updateSql = 'UPDATE amiga_community_stats SET ' . implode(', ', $setParts) . ' WHERE id = 1';
    $ustmt = $con->prepare($updateSql);
    if ($ustmt === false) {
        throw new RuntimeException('prepare community present: ' . $con->error);
    }
    $hbind = [];
    $htypes = '';
    foreach ($headlineCols as $col) {
        $val = $headline[$col] ?? null;
        if (in_array($col, $intCols, true)) {
            $htypes .= 'i';
            $hbind[] = $val === null ? null : (int) $val;
        } else {
            $htypes .= 'd';
            $hbind[] = $val === null ? null : (float) $val;
        }
    }
    $ustmt->bind_param($htypes, ...$hbind);
    if (!$ustmt->execute()) {
        throw new RuntimeException('execute community present: ' . $ustmt->error);
    }
    $ustmt->close();
}

/**
 * @param list<array<string, mixed>> $facts
 */
function amiga_community_persist_facts(mysqli $con, int $tournamentId, array $facts): int
{
    $del = $con->prepare('DELETE FROM amiga_community_stat_facts WHERE tournament_id = ?');
    if ($del === false) {
        throw new RuntimeException('prepare delete community facts: ' . $con->error);
    }
    $del->bind_param('i', $tournamentId);
    if (!$del->execute()) {
        throw new RuntimeException('execute delete community facts: ' . $del->error);
    }
    $del->close();

    if ($facts === []) {
        return 0;
    }

    $sql = 'INSERT INTO amiga_community_stat_facts (
        tournament_id, period_type, period_key, slice_type, slice_key, metric_key, count_basis, value
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare insert community facts: ' . $con->error);
    }
    $count = 0;
    foreach ($facts as $fact) {
        $stmt->bind_param(
            'issssssd',
            $fact['tournament_id'],
            $fact['period_type'],
            $fact['period_key'],
            $fact['slice_type'],
            $fact['slice_key'],
            $fact['metric_key'],
            $fact['count_basis'],
            $fact['value']
        );
        if (!$stmt->execute()) {
            throw new RuntimeException('execute insert community fact: ' . $stmt->error);
        }
        $count++;
    }
    $stmt->close();

    return $count;
}

function amiga_community_persist_for_tournament(
    mysqli $con,
    int $tournamentId,
    string $finalizedAt,
): int {
    $cutoff = amiga_realm_load_cutoff($con, $tournamentId);
    $scan = amiga_community_build_realm_scan($con, $tournamentId);
    $headline = amiga_realm_compute_server_aggregates($con, $cutoff);
    $headline = array_merge($headline, amiga_community_headline_extensions_from_scan($scan));
    amiga_community_persist_headline($con, $tournamentId, $cutoff, $headline, $finalizedAt);
    $facts = $scan['facts'];

    return amiga_community_persist_facts($con, $tournamentId, $facts);
}
