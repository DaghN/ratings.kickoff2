<?php
/**
 * Community stats persist (mirrors scripts/amiga/community_persist.py).
 *
 * @see docs/amiga-community-stats-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_community_stat_registry.php';
require_once __DIR__ . '/amiga_realm_snapshot_lib.php';

function amiga_community_country_token(?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    $text = trim($value);

    return $text !== '' ? $text : null;
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_community_build_facts_at_cutoff(mysqli $con, int $tournamentId): array
{
    $cutoff = amiga_realm_load_cutoff($con, $tournamentId);
    $cutoffWhere = amiga_realm_game_cutoff_sql('t');
    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tid = (int) $cutoff['tournament_id'];

    $sql = "
        SELECT g.player_a_id, g.player_b_id, g.goals_a, g.goals_b,
               t.event_date, t.country AS host_country,
               pa.country AS country_a, pb.country AS country_b,
               r.sum_of_goals
        FROM amiga_games g
        INNER JOIN amiga_game_ratings r ON r.game_id = g.id
        INNER JOIN tournaments t ON t.id = g.tournament_id
        INNER JOIN amiga_players pa ON pa.id = g.player_a_id
        INNER JOIN amiga_players pb ON pb.id = g.player_b_id
        WHERE {$cutoffWhere}
        ORDER BY g.game_date ASC, g.id ASC
    ";
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare community facts games: ' . $con->error);
    }
    $stmt->bind_param('sdi', $eventDate, $chrono, $tid);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute community facts games: ' . $stmt->error);
    }
    $res = $stmt->get_result();

    /** @var array<string, float> $values */
    $values = [];
    /** @var array<string, array<int, true>> $activeByYear */
    $activeByYear = [];

    $factKey = static function (
        string $periodType,
        string $periodKey,
        string $sliceType,
        string $sliceKey,
        string $metricKey,
        string $countBasis,
    ): string {
        return implode("\0", [$periodType, $periodKey, $sliceType, $sliceKey, $metricKey, $countBasis]);
    };

    $add = static function (string $key, float $delta) use (&$values): void {
        $values[$key] = ($values[$key] ?? 0.0) + $delta;
    };

    while ($row = $res->fetch_assoc()) {
        $eventDateRow = $row['event_date'] ?? null;
        $year = null;
        if ($eventDateRow !== null && $eventDateRow !== '') {
            $year = substr((string) $eventDateRow, 0, 4);
        }
        $host = amiga_community_country_token(isset($row['host_country']) ? (string) $row['host_country'] : null);
        $countryA = amiga_community_country_token(isset($row['country_a']) ? (string) $row['country_a'] : null);
        $countryB = amiga_community_country_token(isset($row['country_b']) ? (string) $row['country_b'] : null);
        $playerA = (int) $row['player_a_id'];
        $playerB = (int) $row['player_b_id'];
        $goalsA = (int) $row['goals_a'];
        $goalsB = (int) $row['goals_b'];
        $sumGoals = (int) ($row['sum_of_goals'] ?? 0);

        if ($year !== null) {
            $add($factKey('year', $year, 'realm', AMIGA_COMMUNITY_REALM_SLICE_KEY, 'games', 'game'), 1);
            $add($factKey('year', $year, 'realm', AMIGA_COMMUNITY_REALM_SLICE_KEY, 'goals', 'game'), (float) $sumGoals);
            $activeByYear[$year][$playerA] = true;
            $activeByYear[$year][$playerB] = true;
            if ($host !== null) {
                $add($factKey('year', $year, 'host_country', $host, 'games', 'game'), 1);
            }
            if ($countryA !== null) {
                $add($factKey('year', $year, 'player_nationality', $countryA, 'games', 'participant'), 1);
                $add($factKey('year', $year, 'player_nationality', $countryA, 'goals', 'participant'), (float) $goalsA);
            }
            if ($countryB !== null) {
                $add($factKey('year', $year, 'player_nationality', $countryB, 'games', 'participant'), 1);
                $add($factKey('year', $year, 'player_nationality', $countryB, 'goals', 'participant'), (float) $goalsB);
            }
        }
        if ($host !== null) {
            $add(
                $factKey('all_time', AMIGA_COMMUNITY_ALL_TIME_PERIOD_KEY, 'host_country', $host, 'games', 'game'),
                1
            );
        }
        if ($countryA !== null) {
            $add(
                $factKey(
                    'all_time',
                    AMIGA_COMMUNITY_ALL_TIME_PERIOD_KEY,
                    'player_nationality',
                    $countryA,
                    'games',
                    'participant'
                ),
                1
            );
        }
        if ($countryB !== null) {
            $add(
                $factKey(
                    'all_time',
                    AMIGA_COMMUNITY_ALL_TIME_PERIOD_KEY,
                    'player_nationality',
                    $countryB,
                    'games',
                    'participant'
                ),
                1
            );
        }
    }
    $stmt->close();

    foreach ($activeByYear as $year => $players) {
        $key = $factKey('year', (string) $year, 'realm', AMIGA_COMMUNITY_REALM_SLICE_KEY, 'active_players', 'game');
        $values[$key] = (float) count($players);
    }

    $facts = [];
    foreach ($values as $key => $value) {
        if ($value == 0.0) {
            continue;
        }
        [$periodType, $periodKey, $sliceType, $sliceKey, $metricKey, $countBasis] = explode("\0", $key, 6);
        $facts[] = [
            'tournament_id' => $tournamentId,
            'period_type' => $periodType,
            'period_key' => $periodKey,
            'slice_type' => $sliceType,
            'slice_key' => $sliceKey,
            'metric_key' => $metricKey,
            'count_basis' => $countBasis,
            'value' => $value,
        ];
    }

    return $facts;
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
        if (in_array($col, ['NumberOfPlayers', 'GamesPlayed', 'NumberOfDecidedGames', 'NumberOfDraws', 'GoalsScored', 'DoubleDigits', 'CleanSheets'], true)) {
            $types .= 'i';
        } else {
            $types .= 'd';
        }
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
        if (in_array($col, ['NumberOfPlayers', 'GamesPlayed', 'NumberOfDecidedGames', 'NumberOfDraws', 'GoalsScored', 'DoubleDigits', 'CleanSheets'], true)) {
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
    $headline = amiga_realm_compute_server_aggregates($con, $cutoff);
    amiga_community_persist_headline($con, $tournamentId, $cutoff, $headline, $finalizedAt);
    $facts = amiga_community_build_facts_at_cutoff($con, $tournamentId);

    return amiga_community_persist_facts($con, $tournamentId, $facts);
}
