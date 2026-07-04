<?php
declare(strict_types=1);

require __DIR__ . '/../../site/public_html/includes/amiga_lb_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_matchup_snapshot_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_player_opponents_load.php';
require __DIR__ . '/../../site/public_html/includes/amiga_player_opponents_country_load.php';
require __DIR__ . '/../../site/public_html/includes/amiga_player_opponents_country_perf_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) { fwrite(STDERR, "connect fail\n"); exit(1); }
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

function legacy_matchup_at_event_latest_from_sql(string $alias = 'm'): string
{
    return "FROM (\n"
        . "    SELECT x.* FROM (\n"
        . "        SELECT m.*,\n"
        . "            ROW_NUMBER() OVER (\n"
        . "                PARTITION BY m.player_id, m.opponent_id\n"
        . "                ORDER BY m.event_date DESC, m.event_chrono DESC, m.as_of_tournament_id DESC\n"
        . "            ) AS rn\n"
        . "        FROM amiga_player_matchup_at_event m\n"
        . "        WHERE m.player_id = ?\n"
        . "          AND (m.event_date, m.event_chrono, m.as_of_tournament_id) <= (?, ?, ?)\n"
        . "    ) x\n"
        . "    WHERE x.rn = 1\n"
        . ") {$alias}";
}

function legacy_player_matchup_opponent_rows(mysqli $con, int $playerId, AmigaSnapshotContext $ctx): array
{
    if ($playerId < 1) {
        return [];
    }
    $select = 'SELECT ' . implode(', ', amiga_matchup_opponents_select_columns($ctx->isActive()));
    if (!$ctx->isActive()) {
        $sql = $select
            . ' FROM amiga_player_matchup_summary m'
            . ' LEFT JOIN amiga_players p ON p.id = m.opponent_id'
            . ' LEFT JOIN amiga_player_current c ON c.player_id = m.opponent_id'
            . ' WHERE m.player_id = ? AND m.games > 0'
            . ' ORDER BY m.games DESC, opponent_name ASC';
        $stmt = $con->prepare($sql);
        if (!$stmt) { return []; }
        $stmt->bind_param('i', $playerId);
    } else {
        $cutoff = $ctx->cutoff();
        if ($cutoff === null) { return []; }
        $sql = $select
            . ' ' . legacy_matchup_at_event_latest_from_sql('m')
            . ' LEFT JOIN amiga_players p ON p.id = m.opponent_id'
            . ' ' . amiga_matchup_opponents_rating_at_cutoff_join_sql()
            . ' WHERE m.games > 0'
            . ' ORDER BY m.games DESC, opponent_name ASC';
        $stmt = $con->prepare($sql);
        if (!$stmt) { return []; }
        $eventDate = $cutoff['event_date'];
        $chrono = $cutoff['chrono'];
        $tournamentId = $cutoff['tournament_id'];
        $stmt->bind_param('isdisdi', $playerId, $eventDate, $chrono, $tournamentId, $eventDate, $chrono, $tournamentId);
    }
    if (!$stmt->execute()) { $stmt->close(); return []; }
    $res = $stmt->get_result();
    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) { $rows[] = $row; }
        $res->free();
    }
    $stmt->close();
    return $rows;
}

function row_signature(array $row): string
{
    ksort($row);
    $parts = [];
    foreach ($row as $key => $value) {
        $parts[] = $key . '=' . (string) $value;
    }
    return implode('|', $parts);
}

$players = [382, 120, 45];
$cutoffs = [
    'present' => '',
    'event:22' => 'event:22',
    'event:589' => 'event:589',
    'month:2014-07' => 'month:2014-07',
    'month:2025-09' => 'month:2025-09',
    'year:2024' => 'year:2024',
    'year:2001' => 'year:2001',
];
$statKeys = [
    'games', 'wins', 'draws', 'losses', 'goals_for', 'goals_against',
    'double_digits', 'double_digits_conceded', 'clean_sheets', 'clean_sheets_conceded',
    'max_goals_for', 'max_goals_against', 'min_goals_for', 'min_goals_against', 'max_goal_sum', 'min_goal_sum',
];
$fail = 0;

foreach ($cutoffs as $label => $as) {
    if ($as !== '') { $_GET['as'] = $as; } else { unset($_GET['as']); }
    $GLOBALS['_amiga_snapshot_context'] = null;
    $ctx = amiga_lb_context($con);

    foreach ($players as $playerId) {
        $legacyRaw = legacy_player_matchup_opponent_rows($con, $playerId, $ctx);
        $newRaw = amiga_player_matchup_opponent_rows($con, $playerId, $ctx);
        $legacyNorm = array_map('amiga_player_opponents_normalize_matchup_row', $legacyRaw);
        $newNorm = array_map('amiga_player_opponents_normalize_matchup_row', $newRaw);

        if (count($legacyNorm) !== count($newNorm)) {
            echo "FAIL matchup count player=$playerId @ $label: legacy=" . count($legacyNorm) . ' new=' . count($newNorm) . "\n";
            $fail++;
        } else {
            $legacySig = array_map('row_signature', $legacyNorm);
            sort($legacySig);
            $newSig = array_map('row_signature', $newNorm);
            sort($newSig);
            if ($legacySig !== $newSig) {
                echo "FAIL matchup rows player=$playerId @ $label\n";
                $fail++;
            }
        }

        $rollup = amiga_player_opponents_country_rollup_from_pair_rows($newNorm);
        $noPerf = amiga_player_opponents_country_rows($con, $playerId, $ctx, false);
        $withPerf = amiga_player_opponents_country_rows($con, $playerId, $ctx, true);

        if (count($rollup) !== count($noPerf) || count($rollup) !== count($withPerf)) {
            echo "FAIL country count player=$playerId @ $label\n";
            $fail++;
            continue;
        }

        $byToken = [];
        foreach ($withPerf as $row) {
            $byToken[(string) $row['country_token']] = $row;
        }

        foreach ($rollup as $bucket) {
            $token = (string) $bucket['country_token'];
            $full = $byToken[$token] ?? null;
            if ($full === null) {
                echo "FAIL missing country $token player=$playerId @ $label\n";
                $fail++;
                continue;
            }
            foreach ($statKeys as $key) {
                if ((int) ($bucket[$key] ?? -1) !== (int) ($full[$key] ?? -2)) {
                    echo "FAIL stat $key player=$playerId vs $token @ $label: {$bucket[$key]} != {$full[$key]}\n";
                    $fail++;
                }
            }
        }

        if ($rollup !== []) {
            $sample = (string) $rollup[0]['country_token'];
            $batch = amiga_player_opponents_country_perf_ratings_batch($con, $playerId, $ctx);
            $scoped = amiga_player_opponents_country_perf_ratings_for_token($con, $playerId, $sample, $ctx);
            $batchRow = $batch[$sample] ?? null;
            if ($batchRow === null) {
                echo "FAIL perf batch missing player=$playerId vs $sample @ $label\n";
                $fail++;
            } else {
                foreach (['games', 'performance_rating', 'performance_rating_vs_hero'] as $pkey) {
                    if (($batchRow[$pkey] ?? null) !== ($scoped[$pkey] ?? null)) {
                        echo "FAIL perf $pkey player=$playerId vs $sample @ $label: batch={$batchRow[$pkey]} scoped={$scoped[$pkey]}\n";
                        $fail++;
                    }
                }
            }
        }
    }
}

$con->close();
if ($fail > 0) {
    echo "PARITY FAIL ($fail checks)\n";
    exit(1);
}
echo "PARITY OK\n";