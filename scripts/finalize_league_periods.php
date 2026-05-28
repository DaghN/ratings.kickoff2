<?php
/**
 * Finalize closed league periods (PER-003) or full awards rebuild (REP-012).
 *
 * Usage:
 *   php scripts/finalize_league_periods.php --full-rebuild
 *   php scripts/finalize_league_periods.php
 *   php scripts/finalize_league_periods.php --rebuild-aggregates
 *
 * Requires Laragon PHP + ko2unity_db with SCH-009+ applied.
 */

declare(strict_types=1);

$repoRoot = dirname(__DIR__);
$_SERVER['DOCUMENT_ROOT'] = $repoRoot . '/site/public_html';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/league_standings.php';

$configPath = $repoRoot . '/site/config/ko2unitydb_config.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "Missing config: {$configPath}\n");
    exit(1);
}

include $configPath;

$fullRebuild = in_array('--full-rebuild', $argv, true);
$rebuildAggregates = in_array('--rebuild-aggregates', $argv, true);

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum ?? 3306);
if ($con->connect_errno) {
    fwrite(STDERR, 'DB connect failed: ' . $con->connect_error . PHP_EOL);
    exit(1);
}

$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

if (!k2_league_table_exists($con, 'player_league_award')) {
    fwrite(STDERR, "Table player_league_award missing — apply schema/migrations/008_league_period_awards.sql\n");
    mysqli_close($con);
    exit(1);
}

$t0 = microtime(true);

if ($fullRebuild) {
    echo "REP-012: full league awards rebuild...\n";
    $result = k2_league_rebuild_all_awards($con);
    echo 'Instances finalized: ' . $result['instances'] . "\n";
    echo 'Award rows: ' . $result['awards'] . "\n";
} elseif ($rebuildAggregates) {
    echo "REP-013: rebuild player_league_totals + player_league_slice_totals...\n";
    k2_league_rebuild_player_aggregates($con);
} else {
    echo "PER-003: finalize due closed periods...\n";
    $result = k2_league_finalize_due_periods($con);
    echo 'New instances finalized: ' . ($result['finalized'] ?? 0) . "\n";
}

$totalsRes = mysqli_query($con, 'SELECT COUNT(*) AS players, COALESCE(SUM(wins),0) AS wins FROM player_league_totals');
if ($totalsRes !== false) {
    $t = mysqli_fetch_assoc($totalsRes);
    mysqli_free_result($totalsRes);
    echo 'Totals: ' . ($t['players'] ?? 0) . ' players, ' . ($t['wins'] ?? 0) . " career wins\n";
}

if (k2_league_table_exists($con, 'player_league_slice_totals')) {
    $sliceRes = mysqli_query($con, 'SELECT COUNT(*) AS slice_rows, COALESCE(SUM(gold),0) AS gold FROM player_league_slice_totals');
    if ($sliceRes !== false) {
        $s = mysqli_fetch_assoc($sliceRes);
        mysqli_free_result($sliceRes);
        echo 'Slice totals: ' . ($s['slice_rows'] ?? 0) . ' rows, ' . ($s['gold'] ?? 0) . " gold (sum across slices)\n";
    }
}

$ms = round((microtime(true) - $t0) * 1000);
echo "Done in {$ms} ms\n";

mysqli_close($con);
