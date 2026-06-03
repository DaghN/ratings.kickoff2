<?php
/**
 * Staging one-shot: REP-008 — full player_milestones backfill (110 keys).
 *
 * CLI only. Prerequisites:
 *   - staging-sql/011 + 012 applied (source columns)
 *   - load_milestone_definitions.php run (REP-014)
 *   - REP-012 done (player_league_award populated)
 *   - staging-sql/milestones/*.sql uploaded (from repo scripts/ladder/sql/)
 *
 * Run from public_html:
 *   php staging-scripts/run_player_milestones_rebuild.php
 *
 * Then always run giant_slayer surgical fix (same script does step 2).
 */
declare(strict_types=1);

require_once __DIR__ . '/_staging_milestones_bootstrap.php';

$con = k2_staging_milestones_bootstrap();
$sqlDir = dirname(__DIR__) . '/staging-sql/milestones';
$marker = '-- League wave: first matching award row';

if (!k2_staging_table_exists($con, 'player_milestones')) {
    fwrite(STDERR, "Table player_milestones missing — apply SCH-008 (007_stored_truth) first.\n");
    exit(1);
}
if (!k2_staging_table_exists($con, 'milestone_definitions')) {
    fwrite(STDERR, "Apply staging-sql/010 + load_milestone_definitions.php first.\n");
    exit(1);
}

$colRes = $con->query("SHOW COLUMNS FROM player_milestones LIKE 'source_kind'");
if ($colRes === false || $colRes->num_rows === 0) {
    if ($colRes) {
        $colRes->free();
    }
    fwrite(STDERR, "Apply staging-sql/011_player_milestones_source.sql first.\n");
    exit(1);
}
$colRes->free();

$awardRes = $con->query('SELECT COUNT(*) AS n FROM player_league_award');
$awardRow = $awardRes ? $awardRes->fetch_assoc() : null;
if ($awardRes) {
    $awardRes->free();
}
$awardCount = (int) ($awardRow['n'] ?? 0);
if ($awardCount < 1) {
    fwrite(STDERR, "player_league_award is empty — run REP-012 (ops/run_finalize_league.php rebuild-all) first.\n");
    exit(1);
}
echo "player_league_award rows: {$awardCount}\n";

$corePath = $sqlDir . '/player_milestones_rebuild.sql';
$core = file_get_contents($corePath);
if ($core === false) {
    fwrite(STDERR, "Missing {$corePath}\n");
    exit(1);
}
$idx = strpos($core, $marker);
if ($idx === false) {
    fwrite(STDERR, "League marker not found in player_milestones_rebuild.sql\n");
    exit(1);
}

$parts = [
    $sqlDir . '/player_milestones_rebuild_exists.sql',
    $sqlDir . '/player_milestones_rebuild_streaks.sql',
    $sqlDir . '/player_milestones_rebuild_chrono.sql',
    $sqlDir . '/player_milestones_rebuild_tail.sql',
    $sqlDir . '/player_milestones_rebuild_period.sql',
    $sqlDir . '/player_milestones_rebuild_play_streak_100.sql',
    $sqlDir . '/player_milestones_rebuild_year_in_heaven.sql',
];
$spliced = substr($core, 0, $idx);
foreach ($parts as $part) {
    if (!is_file($part)) {
        fwrite(STDERR, "Missing {$part}\n");
        exit(1);
    }
    $spliced .= file_get_contents($part);
}
$spliced .= substr($core, $idx);

$dbRes = $con->query('SELECT DATABASE() AS db');
$dbName = ($dbRes && ($r = $dbRes->fetch_assoc())) ? (string) $r['db'] : '?';
if ($dbRes) {
    $dbRes->free();
}
echo "REP-008: full player_milestones rebuild on {$dbName}...\n";
$t0 = microtime(true);
k2_staging_exec_sql($con, $spliced, 'player_milestones full rebuild');

$gsPath = $sqlDir . '/player_milestones_rebuild_giant_slayer.sql';
echo "-> giant_slayer surgical fix (active #1 rule)\n";
k2_staging_exec_sql_file($con, $gsPath, 'giant_slayer fix');

$ms = round((microtime(true) - $t0) * 1000);
echo "Rebuild finished in {$ms} ms\n";

$checks = [
    'distinct_keys' => "SELECT COUNT(DISTINCT milestone_key) AS n FROM player_milestones",
    'total_rows' => 'SELECT COUNT(*) AS n FROM player_milestones',
    'null_source' => "SELECT COUNT(*) AS n FROM player_milestones WHERE source_kind IS NULL",
    'giant_slayer' => "SELECT COUNT(*) AS n FROM player_milestones WHERE milestone_key = 'giant_slayer'",
    'definitions' => 'SELECT COUNT(*) AS n FROM milestone_definitions',
    'play_streak_100' => "SELECT COUNT(*) AS n FROM player_milestones WHERE milestone_key = 'play_streak_100'",
    'dd_merchant_10' => "SELECT COUNT(*) AS n FROM player_milestones WHERE milestone_key = 'dd_merchant_10'",
    'established_20' => "SELECT COUNT(*) AS n FROM player_milestones WHERE milestone_key = 'established_20'",
];
foreach ($checks as $label => $sql) {
    $res = $con->query($sql);
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    echo "{$label}: " . ($row['n'] ?? '?') . "\n";
}

$diffRes = $con->query(
    'SELECT (
      SELECT COUNT(*) FROM player_milestones WHERE milestone_key = \'established_20\'
    ) - (
      SELECT COUNT(*) FROM playertable WHERE NumberGames >= 20
    ) AS diff'
);
$diffRow = $diffRes ? $diffRes->fetch_assoc() : null;
if ($diffRes) {
    $diffRes->free();
}
echo 'established_20_diff: ' . ($diffRow['diff'] ?? '?') . "\n";

echo "Done. Screenshot the counts above for Dagh.\n";
mysqli_close($con);
