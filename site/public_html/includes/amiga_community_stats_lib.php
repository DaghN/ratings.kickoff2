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
    // tournament_id is NOT chronological (late catalog imports get fractional
    // event_chrono slots) — "latest" must be picked by chrono, not MAX(id).
    $res = $con->query(
        'SELECT tournament_id FROM amiga_community_stats_snapshots '
        . 'ORDER BY event_chrono DESC, event_date DESC, tournament_id DESC LIMIT 1'
    );
    if ($res === false) {
        return null;
    }
    $row = $res->fetch_assoc();
    $res->free();
    if ($row === null) {
        return null;
    }
    $tid = (int) ($row['tournament_id'] ?? 0);

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

/*
 * -------------------------------------------------------------------------
 * Activity chart read platform (docs/amiga-activity-charts-policy.md §9).
 * Facts are complete state dumps per finalize event, so reads at a cutoff
 * are a single `tournament_id = ?` fetch. Chrono order = event_chrono.
 * -------------------------------------------------------------------------
 */

/** Count basis for a (slice_type, metric_key) fact grain (registry mirror). */
function amiga_community_fact_count_basis(string $sliceType): string
{
    return $sliceType === 'player_nationality' ? 'participant' : 'game';
}

/** event_date of one community snapshot row, or null. */
function amiga_community_snapshot_event_date(mysqli $con, int $tournamentId): ?string
{
    $stmt = $con->prepare('SELECT event_date FROM amiga_community_stats_snapshots WHERE tournament_id = ? LIMIT 1');
    if ($stmt === false) {
        return null;
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        $stmt->close();

        return null;
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : false;
    $stmt->close();
    if ($row === false || $row === null) {
        return null;
    }
    $date = (string) ($row['event_date'] ?? '');

    return $date !== '' ? $date : null;
}

/**
 * Year facts at a cutoff snapshot: values per calendar year, per slice key.
 *
 * @param list<string>|null $sliceKeys null = all keys for the slice
 * @return array<string, array<int, float>> slice_key => [year => value]
 */
function amiga_community_year_facts_at_cutoff(
    mysqli $con,
    int $cutoffTournamentId,
    string $sliceType,
    string $metricKey,
    ?array $sliceKeys = null,
): array {
    if ($cutoffTournamentId <= 0) {
        return [];
    }

    $sql = "SELECT slice_key, period_key, value FROM amiga_community_stat_facts
            WHERE tournament_id = ? AND period_type = 'year'
              AND slice_type = ? AND metric_key = ? AND count_basis = ?";
    $types = 'isss';
    $params = [$cutoffTournamentId, $sliceType, $metricKey, amiga_community_fact_count_basis($sliceType)];
    if ($sliceKeys !== null && $sliceKeys !== []) {
        $placeholders = implode(', ', array_fill(0, count($sliceKeys), '?'));
        $sql .= " AND slice_key IN ({$placeholders})";
        $types .= str_repeat('s', count($sliceKeys));
        $params = array_merge($params, array_values($sliceKeys));
    }
    $sql .= ' ORDER BY slice_key, period_key';

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare community year facts: ' . $con->error);
    }
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('execute community year facts: ' . $err);
    }
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $year = (int) ($row['period_key'] ?? 0);
        if ($year <= 0) {
            continue;
        }
        $key = (string) ($row['slice_key'] ?? '');
        $out[$key][$year] = (float) ($row['value'] ?? 0);
    }
    $res->free();
    $stmt->close();

    return $out;
}

/**
 * Canonical calendar-year span at a cutoff (realm games years). Charts use
 * this span for all year x-axes so dimensional charts stay comparable.
 *
 * @return array{0:int, 1:int}|null [firstYear, lastYear]
 */
function amiga_community_year_span_at_cutoff(mysqli $con, int $cutoffTournamentId): ?array
{
    $stmt = $con->prepare(
        "SELECT MIN(period_key) AS min_y, MAX(period_key) AS max_y
         FROM amiga_community_stat_facts
         WHERE tournament_id = ? AND period_type = 'year'
           AND slice_type = 'realm' AND metric_key = 'games' AND count_basis = 'game'"
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
    if ($row === false || $row === null) {
        return null;
    }
    $minYear = (int) ($row['min_y'] ?? 0);
    $maxYear = (int) ($row['max_y'] ?? 0);
    if ($minYear <= 0 || $maxYear < $minYear) {
        return null;
    }

    return [$minYear, $maxYear];
}

/**
 * Slice keys with data at a cutoff, ranked by all-time value desc
 * (country pickers + race-chart defaults).
 *
 * @return array<string, float> slice_key => all-time value
 */
function amiga_community_slice_keys_at_cutoff(
    mysqli $con,
    int $cutoffTournamentId,
    string $sliceType,
    string $metricKey,
): array {
    if ($cutoffTournamentId <= 0) {
        return [];
    }

    $stmt = $con->prepare(
        "SELECT slice_key, value FROM amiga_community_stat_facts
         WHERE tournament_id = ? AND period_type = 'all_time'
           AND slice_type = ? AND metric_key = ? AND count_basis = ?
         ORDER BY value DESC, slice_key ASC"
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare community slice keys: ' . $con->error);
    }
    $basis = amiga_community_fact_count_basis($sliceType);
    $stmt->bind_param('isss', $cutoffTournamentId, $sliceType, $metricKey, $basis);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('execute community slice keys: ' . $err);
    }
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $key = (string) ($row['slice_key'] ?? '');
        if ($key !== '' && $key !== AMIGA_COMMUNITY_REALM_SLICE_KEY) {
            $out[$key] = (float) ($row['value'] ?? 0);
        }
    }
    $res->free();
    $stmt->close();

    return $out;
}

/**
 * Headline column series across snapshots in chrono order, optionally
 * truncated at a cutoff chrono (time travel). Column MUST be validated
 * against amiga_community_headline_column_names() by the caller.
 *
 * @return list<array{t: int, date: string, name: string, value: float|null}>
 */
function amiga_community_snapshot_series(mysqli $con, string $column, ?float $cutoffChrono = null): array
{
    if (!in_array($column, amiga_community_headline_column_names(), true)) {
        throw new RuntimeException('unknown headline column: ' . $column);
    }

    $sql = "SELECT tournament_id, event_date, tournament_name, `{$column}` AS v
            FROM amiga_community_stats_snapshots";
    if ($cutoffChrono !== null) {
        $sql .= ' WHERE event_chrono <= ?';
    }
    $sql .= ' ORDER BY event_chrono ASC';

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare community snapshot series: ' . $con->error);
    }
    if ($cutoffChrono !== null) {
        $stmt->bind_param('d', $cutoffChrono);
    }
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('execute community snapshot series: ' . $err);
    }
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = [
            't' => (int) ($row['tournament_id'] ?? 0),
            'date' => (string) ($row['event_date'] ?? ''),
            'name' => (string) ($row['tournament_name'] ?? ''),
            'value' => $row['v'] === null ? null : (float) $row['v'],
        ];
    }
    $res->free();
    $stmt->close();

    return $out;
}

/**
 * All-time reference value for a derived year rate at cutoff (texture bars).
 * High-scoring rate has no headline column — derived from summed year facts.
 */
function amiga_community_year_rate_reference_at_cutoff(mysqli $con, int $cutoffTournamentId, string $rate): ?float
{
    if ($rate === 'high_scoring_rate') {
        $gamesFacts = amiga_community_year_facts_at_cutoff($con, $cutoffTournamentId, 'realm', 'games');
        $hsFacts = amiga_community_year_facts_at_cutoff($con, $cutoffTournamentId, 'realm', 'high_scoring_games');
        $gamesByYear = $gamesFacts[AMIGA_COMMUNITY_REALM_SLICE_KEY] ?? [];
        $hsByYear = $hsFacts[AMIGA_COMMUNITY_REALM_SLICE_KEY] ?? [];
        $totalGames = array_sum($gamesByYear);
        if ($totalGames <= 0) {
            return null;
        }

        return round(array_sum($hsByYear) / $totalGames, 4);
    }

    $headlineCol = match ($rate) {
        'goals_per_game' => 'GoalsPerGameAverage',
        'draw_rate' => 'DrawsRatio',
        'dd_rate' => 'DoubleDigitsRatio',
        'cs_rate' => 'CleanSheetsRatio',
        default => null,
    };
    if ($headlineCol === null) {
        return null;
    }

    $headline = amiga_community_headline_load($con, $cutoffTournamentId);
    if ($headline === null || !isset($headline[$headlineCol])) {
        return null;
    }

    return round((float) $headline[$headlineCol], 4);
}
