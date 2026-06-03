<?php
/**
 * @deprecated Superseded by site/public_html/ops/run_finalize_league.php (REP-012/013, PER-003).
 *
 * Staging one-shot (May 2026): REP-012 + REP-013. Do not re-run on staging — awards already applied.
 * For new work DB parity use: php ops/run_finalize_league.php rebuild-all --target local-work
 *
 * Historical CLI (public_html on staging after 008/009):
 *   php staging-scripts/run_league_awards_rebuild.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/league_standings.php';

$configPath = $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "Missing config: {$configPath}\n");
    exit(1);
}

include $configPath;

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum ?? 3306);
if ($con->connect_errno) {
    fwrite(STDERR, 'DB connect failed: ' . $con->connect_error . PHP_EOL);
    exit(1);
}

$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

if (!k2_league_table_exists($con, 'player_league_award')) {
    fwrite(STDERR, "Apply staging-sql/008_league_period_awards.sql first.\n");
    mysqli_close($con);
    exit(1);
}

echo "REP-012: full league awards rebuild on " . ($database ?? '?') . "...\n";
$t0 = microtime(true);
$result = k2_league_rebuild_all_awards($con);
echo 'Instances finalized: ' . ($result['instances'] ?? 0) . "\n";
echo 'Award rows: ' . ($result['awards'] ?? 0) . "\n";

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
