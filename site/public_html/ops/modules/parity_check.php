<?php
/**
 * Parity checks after prepare (read-only).
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/ops_prepare_constants.php';
require_once __DIR__ . '/../includes/ops_work_target.php';
require_once __DIR__ . '/../includes/ops_bootstrap.php';
require_once __DIR__ . '/../includes/ops_paths.php';

/**
 * @return list<array{name: string, ok: bool, detail: string}>
 */
function k2_ops_run_parity_checks(K2OpsWorkTarget $target): array
{
    $results = [];
    $work = $target->workDatabase;
    $baseline = $target->baselineDatabase;

    if (!k2_ops_database_exists($target, $work)) {
        return [['name' => 'work_exists', 'ok' => false, 'detail' => "{$work} missing"]];
    }

    $expectedMilestones = 112;
    $seedPath = k2_ops_milestones_seed_path();
    if (is_file($seedPath)) {
        $payload = json_decode((string) file_get_contents($seedPath), true);
        if (is_array($payload)) {
            $expectedMilestones = (int) ($payload['milestone_count'] ?? count($payload['definitions'] ?? []));
        }
    }

    $con = k2_ops_connect_work($target);
    try {
        if (k2_ops_database_exists($target, $baseline)) {
            $workEsc = $con->real_escape_string($work);
            $baseEsc = $con->real_escape_string($baseline);

            $res = $con->query("SELECT COUNT(*) AS n FROM `{$baseEsc}`.ratedresults");
            $baseGames = $res ? (int) $res->fetch_assoc()['n'] : 0;
            if ($res) {
                $res->free();
            }
            $res = $con->query('SELECT COUNT(*) AS n FROM ratedresults');
            $workGames = $res ? (int) $res->fetch_assoc()['n'] : 0;
            if ($res) {
                $res->free();
            }
            $results[] = [
                'name' => 'ratedresults_count_vs_baseline',
                'ok' => $workGames === $baseGames,
                'detail' => "work={$workGames} baseline={$baseGames}",
            ];

            $res = $con->query(
                "SELECT COUNT(*) AS n FROM `{$workEsc}`.ratedresults w "
                . "INNER JOIN `{$baseEsc}`.ratedresults b ON b.id = w.id "
                . 'WHERE w.idA <> b.idA OR w.idB <> b.idB OR w.Date <> b.Date'
            );
            $core = $res ? (int) $res->fetch_assoc()['n'] : -1;
            if ($res) {
                $res->free();
            }
            $results[] = [
                'name' => 'ratedresults_core_ids_match_baseline',
                'ok' => $core === 0,
                'detail' => "idA/idB/Date mismatches={$core} (UTC session)",
            ];

            $res = $con->query(
                "SELECT COUNT(*) AS n FROM `{$workEsc}`.ratedresults w "
                . "INNER JOIN `{$baseEsc}`.ratedresults b ON b.id = w.id "
                . 'WHERE w.GoalsA <> b.GoalsA OR w.GoalsB <> b.GoalsB '
                . 'OR (w.GoalsA IS NULL) <> (b.GoalsA IS NULL) OR (w.GoalsB IS NULL) <> (b.GoalsB IS NULL)'
            );
            $goals = $res ? (int) $res->fetch_assoc()['n'] : -1;
            if ($res) {
                $res->free();
            }
            $results[] = [
                'name' => 'ratedresults_goals_match_baseline',
                'ok' => $goals === 0,
                'detail' => "GoalsA/B mismatches={$goals}",
            ];

            $res = $con->query("SELECT MIN(id) AS mn, MAX(id) AS mx FROM `{$baseEsc}`.ratedresults");
            $bRow = $res ? $res->fetch_assoc() : [];
            if ($res) {
                $res->free();
            }
            $res = $con->query('SELECT MIN(id) AS mn, MAX(id) AS mx FROM ratedresults');
            $wRow = $res ? $res->fetch_assoc() : [];
            if ($res) {
                $res->free();
            }
            $results[] = [
                'name' => 'ratedresults_id_range_vs_baseline',
                'ok' => ($bRow['mn'] ?? null) === ($wRow['mn'] ?? null) && ($bRow['mx'] ?? null) === ($wRow['mx'] ?? null),
                'detail' => 'work min/max id=' . ($wRow['mn'] ?? '') . '/' . ($wRow['mx'] ?? '')
                    . ' baseline=' . ($bRow['mn'] ?? '') . '/' . ($bRow['mx'] ?? ''),
            ];
        }

        foreach (K2_OPS_REQUIRED_RATEDRESULTS_INDEXES as $idx) {
            $idxEsc = $con->real_escape_string($idx);
            $res = $con->query(
                "SELECT COUNT(*) AS n FROM information_schema.statistics "
                . "WHERE table_schema = DATABASE() AND table_name = 'ratedresults' AND index_name = '{$idxEsc}'"
            );
            $exists = $res && (int) $res->fetch_assoc()['n'] > 0;
            if ($res) {
                $res->free();
            }
            $results[] = [
                'name' => 'index_' . $idx,
                'ok' => $exists,
                'detail' => $exists ? 'present' : 'MISSING',
            ];
        }

        $res = $con->query(
            "SELECT COUNT(*) AS n FROM information_schema.COLUMNS "
            . "WHERE table_schema = DATABASE() AND COLUMN_NAME LIKE 'KungFu%'"
        );
        $kungfu = $res ? (int) $res->fetch_assoc()['n'] : -1;
        if ($res) {
            $res->free();
        }
        $results[] = [
            'name' => 'kungfu_columns_absent',
            'ok' => $kungfu === 0,
            'detail' => "KungFu% columns remaining={$kungfu}",
        ];

        $res = $con->query(
            "SELECT COUNT(*) AS n FROM information_schema.COLUMNS "
            . "WHERE table_schema = DATABASE() AND table_name = 'playertable' "
            . "AND column_name = 'RecentAverageRating'"
        );
        $recentAvg = $res ? (int) $res->fetch_assoc()['n'] : -1;
        if ($res) {
            $res->free();
        }
        $results[] = [
            'name' => 'recent_average_rating_column_absent',
            'ok' => $recentAvg === 0,
            'detail' => 'RecentAverageRating column ' . ($recentAvg === 0 ? 'absent' : 'still present'),
        ];

        $res = $con->query('SELECT COUNT(*) AS n FROM ratedresults WHERE NewRatingA IS NOT NULL');
        $derived = $res ? (int) $res->fetch_assoc()['n'] : -1;
        if ($res) {
            $res->free();
        }
        $results[] = [
            'name' => 'ratedresults_derived_cleared',
            'ok' => $derived === 0,
            'detail' => "NewRatingA NOT NULL rows={$derived}",
        ];

        $res = $con->query(
            'SELECT COUNT(*) AS n FROM playertable WHERE Rating <> ' . (int) K2_OPS_START_RATING . ' OR Rating IS NULL'
        );
        $nonDefault = $res ? (int) $res->fetch_assoc()['n'] : -1;
        if ($res) {
            $res->free();
        }
        $res = $con->query('SELECT COUNT(*) AS n FROM playertable');
        $players = $res ? (int) $res->fetch_assoc()['n'] : 0;
        if ($res) {
            $res->free();
        }
        $results[] = [
            'name' => 'playertable_rating_day_zero',
            'ok' => $nonDefault === 0,
            'detail' => "players not at 1600: {$nonDefault} / {$players}",
        ];

        if (k2_ops_table_exists($con, 'milestone_definitions')) {
            $res = $con->query('SELECT COUNT(*) AS n FROM milestone_definitions');
            $md = $res ? (int) $res->fetch_assoc()['n'] : 0;
            if ($res) {
                $res->free();
            }
            $results[] = [
                'name' => 'milestone_definitions_seeded',
                'ok' => $md === $expectedMilestones,
                'detail' => "rows={$md} expected={$expectedMilestones}",
            ];
        } else {
            $results[] = [
                'name' => 'milestone_definitions_seeded',
                'ok' => false,
                'detail' => 'table missing',
            ];
        }

        foreach (['player_period_games', 'server_daily_activity'] as $table) {
            if (!k2_ops_table_exists($con, $table)) {
                $results[] = ['name' => "{$table}_empty_or_absent", 'ok' => true, 'detail' => 'table missing (pre-migrate OK)'];
                continue;
            }
            $res = $con->query("SELECT COUNT(*) AS n FROM `{$table}`");
            $n = $res ? (int) $res->fetch_assoc()['n'] : -1;
            if ($res) {
                $res->free();
            }
            $results[] = ['name' => "{$table}_empty", 'ok' => $n === 0, 'detail' => "rows={$n}"];
        }

        if (!k2_ops_table_exists($con, 'player_milestones')) {
            $results[] = [
                'name' => 'player_milestones_lobby_seeded',
                'ok' => true,
                'detail' => 'table missing (pre-migrate OK)',
            ];
        } else {
            $res = $con->query(
                'SELECT COUNT(*) AS n FROM playertable WHERE ' . K2_OPS_JOIN_DATE_VALID_WHERE
            );
            $eligible = $res ? (int) $res->fetch_assoc()['n'] : 0;
            if ($res) {
                $res->free();
            }
            $res = $con->query(
                "SELECT COUNT(*) AS n FROM player_milestones WHERE milestone_key = 'entered_arena'"
            );
            $lobbyRows = $res ? (int) $res->fetch_assoc()['n'] : 0;
            if ($res) {
                $res->free();
            }
            $res = $con->query(
                "SELECT COUNT(*) AS n FROM player_milestones WHERE milestone_key <> 'entered_arena'"
            );
            $otherRows = $res ? (int) $res->fetch_assoc()['n'] : 0;
            if ($res) {
                $res->free();
            }
            $results[] = [
                'name' => 'player_milestones_lobby_seeded',
                'ok' => $lobbyRows === $eligible && $otherRows === 0,
                'detail' => "entered_arena={$lobbyRows} eligible={$eligible} other_keys={$otherRows}",
            ];
        }

        $res = $con->query(
            'SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema = DATABASE()'
        );
        $tables = $res ? (int) $res->fetch_assoc()['n'] : 0;
        if ($res) {
            $res->free();
        }
        $results[] = [
            'name' => 'work_table_count',
            'ok' => $tables >= 5,
            'detail' => "tables={$tables}",
        ];
    } finally {
        $con->close();
    }

    return $results;
}

function k2_ops_print_parity_report(array $results): int
{
    $failed = 0;
    foreach ($results as $r) {
        $status = $r['ok'] ? 'PASS' : 'FAIL';
        k2_ops_log("[{$status}] {$r['name']} — {$r['detail']}");
        if (!$r['ok']) {
            $failed++;
        }
    }
    if ($failed > 0) {
        fwrite(STDERR, "Parity: {$failed} check(s) failed\n");
        return 1;
    }
    k2_ops_log('Parity: all checks passed');
    return 0;
}
