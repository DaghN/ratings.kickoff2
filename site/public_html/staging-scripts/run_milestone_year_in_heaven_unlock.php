<?php
/**
 * @archived One-off handoff. Surgical unlock — use only when replaying that add-one slice.
 *
 * Surgical REP: add year_in_heaven unlock rows + refresh catalog (no full milestones truncate).
 *
 * Prerequisites: milestone_definitions loaded (112 keys); player_period_games week rows (REP-003).
 *
 * Run from public_html:
 *   php staging-scripts/run_milestone_year_in_heaven_unlock.php
 */
declare(strict_types=1);

require_once __DIR__ . '/_staging_milestones_bootstrap.php';

$con = k2_staging_milestones_bootstrap();
$sqlDir = dirname(__DIR__) . '/staging-sql/milestones';
$sqlPath = $sqlDir . '/player_milestones_rebuild_year_in_heaven.sql';
if (!k2_staging_table_exists($con, 'player_milestones')) {
    fwrite(STDERR, "player_milestones missing — apply SCH-008 first.\n");
    exit(1);
}

echo "Reload milestone_definitions from seed (112 keys)...\n";
$loadScript = __DIR__ . '/load_milestone_definitions.php';
if (!is_file($loadScript)) {
    fwrite(STDERR, "Missing staging-scripts/load_milestone_definitions.php\n");
    exit(1);
}
passthru('php ' . escapeshellarg($loadScript), $loadExit);
if ($loadExit !== 0) {
    fwrite(STDERR, "load_milestone_definitions failed.\n");
    exit(1);
}

if (!is_file($sqlPath)) {
    fwrite(STDERR, "Missing {$sqlPath}\n");
    exit(1);
}

$sql = file_get_contents($sqlPath);
if ($sql === false) {
    fwrite(STDERR, "Could not read SQL file.\n");
    exit(1);
}

$statements = k2_staging_split_sql_statements($sql);
echo 'Applying ' . count($statements) . " unlock INSERT(s) for year_in_heaven...\n";
foreach ($statements as $stmt) {
    if (!$con->query($stmt)) {
        fwrite(STDERR, 'SQL failed: ' . $con->error . "\n");
        exit(1);
    }
}

$res = $con->query('SELECT COUNT(*) AS n FROM milestone_definitions');
$defs = $res ? (int) ($res->fetch_assoc()['n'] ?? 0) : 0;
if ($res) {
    $res->free();
}
$res = $con->query("SELECT COUNT(*) AS n FROM player_milestones WHERE milestone_key = 'year_in_heaven'");
$unlocks = $res ? (int) ($res->fetch_assoc()['n'] ?? 0) : 0;
if ($res) {
    $res->free();
}
$res = $con->query('SELECT COUNT(DISTINCT milestone_key) AS n FROM player_milestones');
$keys = $res ? (int) ($res->fetch_assoc()['n'] ?? 0) : 0;
if ($res) {
    $res->free();
}

echo "milestone_definitions: {$defs} (expect 112)\n";
echo "year_in_heaven unlocks: {$unlocks}\n";
echo "distinct milestone_key in player_milestones: {$keys}\n";

mysqli_close($con);
echo "OK\n";
