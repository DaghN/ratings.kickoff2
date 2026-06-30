<?php
/**
 * Community stats read helpers (present + time-travel cutoff).
 *
 * @see docs/amiga-community-stats-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_community_stat_registry.php';
require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/k2_safety.php';

/**
 * @return array<string, mixed>|null
 */
function amiga_community_headline_load(mysqli $con, ?int $cutoffTournamentId = null): ?array
{
    if ($cutoffTournamentId !== null) {
        $cols = implode(', ', array_map(static fn (string $c): string => "`{$c}`", amiga_community_headline_column_names()));
        $stmt = $con->prepare(
            "SELECT {$cols} FROM amiga_community_stats_snapshots WHERE tournament_id = ? LIMIT 1"
        );
        if ($stmt === false) {
            return null;
        }
        $stmt->bind_param('i', $cutoffTournamentId);
        if (!$stmt->execute()) {
            $stmt->close();

            return null;
        }
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : false;
        $stmt->close();

        return $row === false ? null : $row;
    }

    $stmt = $con->prepare('SELECT * FROM amiga_community_stats WHERE id = 1 LIMIT 1');
    if ($stmt === false) {
        return null;
    }
    if (!$stmt->execute()) {
        $stmt->close();

        return null;
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : false;
    $stmt->close();

    return $row === false ? null : $row;
}

function amiga_community_latest_snapshot_tournament_id(mysqli $con): ?int
{
    $res = $con->query('SELECT MAX(tournament_id) AS tid FROM amiga_community_stats_snapshots');
    if ($res === false) {
        return null;
    }
    $row = $res->fetch_assoc();
    $res->free();
    if ($row === null) {
        return null;
    }
    $tid = (int) ($row['tid'] ?? 0);

    return $tid > 0 ? $tid : null;
}

/**
 * Snapshot tournament_id for community fact reads (present or time-travel cutoff).
 */
function amiga_community_cutoff_tournament_id_for_read(mysqli $con, ?AmigaSnapshotContext $ctx = null): ?int
{
    $ctx ??= amiga_snapshot_context_peek();
    if ($ctx instanceof AmigaSnapshotContext && $ctx->isActive()) {
        $cutoff = $ctx->cutoff();
        if ($cutoff !== null) {
            return (int) $cutoff['tournament_id'];
        }
    }

    return amiga_community_latest_snapshot_tournament_id($con);
}

/**
 * Realm rated-game totals by calendar year at a community-stat cutoff.
 *
 * @param list<int|string> $calendarYears
 * @return array<int, int>
 */
function amiga_community_year_realm_games_at_cutoff(mysqli $con, int $cutoffTournamentId, array $calendarYears): array
{
    if ($cutoffTournamentId <= 0 || $calendarYears === []) {
        return [];
    }

    $yearKeys = [];
    foreach ($calendarYears as $year) {
        $yearKey = trim((string) $year);
        if ($yearKey !== '' && preg_match('/^\d{4}$/', $yearKey) === 1) {
            $yearKeys[$yearKey] = true;
        }
    }
    if ($yearKeys === []) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($yearKeys), '?'));
    $sql = "SELECT f.period_key, f.value
            FROM amiga_community_stat_facts f
            INNER JOIN (
                SELECT period_key, MAX(tournament_id) AS tid
                FROM amiga_community_stat_facts
                WHERE tournament_id <= ?
                  AND period_type = 'year'
                  AND slice_type = 'realm'
                  AND slice_key = ?
                  AND metric_key = 'games'
                  AND count_basis = 'game'
                  AND period_key IN ({$placeholders})
                GROUP BY period_key
            ) pick ON pick.tid = f.tournament_id AND pick.period_key = f.period_key
            WHERE f.period_type = 'year'
              AND f.slice_type = 'realm'
              AND f.slice_key = ?
              AND f.metric_key = 'games'
              AND f.count_basis = 'game'";
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare community year realm games: ' . $con->error);
    }

    $realmSlice = AMIGA_COMMUNITY_REALM_SLICE_KEY;
    $types = 'is' . str_repeat('s', count($yearKeys)) . 's';
    $params = array_merge(
        [$cutoffTournamentId, $realmSlice],
        array_keys($yearKeys),
        [$realmSlice],
    );
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('execute community year realm games: ' . $err);
    }
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $year = (int) ($row['period_key'] ?? 0);
        if ($year > 0) {
            $out[$year] = (int) round((float) ($row['value'] ?? 0));
        }
    }
    $res->free();
    $stmt->close();

    return $out;
}

function amiga_community_share_ratio(int $numerator, int $denominator): ?float
{
    if ($denominator <= 0) {
        return null;
    }

    return round($numerator / $denominator, 8);
}

function amiga_community_first_event_label(mysqli $con): string
{
    $res = mysqli_query(
        $con,
        'SELECT MIN(t.event_date) AS first_event FROM tournaments t '
        . 'INNER JOIN amiga_games g ON g.tournament_id = t.id'
    );
    if ($res === false) {
        return 'the first rated game';
    }
    $row = mysqli_fetch_assoc($res);
    mysqli_free_result($res);
    $first = (string) ($row['first_event'] ?? '');
    if ($first === '') {
        return 'the first rated game';
    }
    $ts = strtotime($first);

    return $ts !== false ? date('F j, Y', $ts) : htmlspecialchars($first, ENT_QUOTES, 'UTF-8');
}
