<?php
/**
 * Local CLI: REP-016 — rebuild player_result_streaks from chronological ratedresults.
 *
 *   php scripts/rebuild_player_result_streaks.php
 *   php scripts/rebuild_player_result_streaks.php --oracle-only
 *
 * Prerequisites: SCH-026 applied; ratedresults with derived ActualScore (prod-shaped).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$oracleOnly = in_array('--oracle-only', $argv ?? [], true);

$repoRoot = dirname(__DIR__);
$_SERVER['DOCUMENT_ROOT'] = $repoRoot . '/site/public_html';

$configPath = dirname($_SERVER['DOCUMENT_ROOT']) . '/config/ko2unitydb_config.php';
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

$res = $con->query("SHOW TABLES LIKE 'player_result_streaks'");
if ($res === false || $res->num_rows === 0) {
    if ($res) {
        $res->free();
    }
    fwrite(STDERR, "Apply ops/sql/migrations/026_player_result_streaks.sql first.\n");
    exit(1);
}
$res->free();

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_result_streaks.php';

if (!$oracleOnly) {
    echo "Rebuilding player_result_streaks (chronological match-result runs)...\n";
    $written = k2_result_streak_rebuild_all($con);
    echo "Rows written (player × type with best_streak > 0): {$written}\n";

    $check = $con->query(
        'SELECT `streak_type`, MAX(`best_streak`) AS mx, COUNT(*) AS n '
        . 'FROM `player_result_streaks` GROUP BY `streak_type`'
    );
    if ($check) {
        while ($row = $check->fetch_assoc()) {
            echo "  {$row['streak_type']}: rows={$row['n']} max_best={$row['mx']}\n";
        }
        $check->free();
    }
}

echo "Oracle check...\n";
$mismatches = k2_result_streak_oracle_mismatches($con);
if ($mismatches === []) {
    echo "Oracle: PASS (0 mismatches)\n";
} else {
    fwrite(STDERR, 'Oracle: FAIL — ' . count($mismatches) . " mismatch(es)\n");
    foreach (array_slice($mismatches, 0, 20) as $line) {
        fwrite(STDERR, "  {$line}\n");
    }
    if (count($mismatches) > 20) {
        fwrite(STDERR, '  ... and ' . (count($mismatches) - 20) . " more\n");
    }
    mysqli_close($con);
    exit(1);
}

mysqli_close($con);
echo "Done.\n";
