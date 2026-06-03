<?php
/**
 * @archived One-off (May 2026). Staging fix applied — do not re-run.
 *
 * Staging wave 2: diversity_merchant rule fix (per-game DD vs 5 opponents).
 *
 * Run after REP-008 wave 1 (full milestones + giant_slayer) succeeded.
 * Does not touch other milestone keys.
 *
 * CLI from public_html:
 *   php staging-scripts/run_player_milestones_diversity_merchant_fix.php
 *
 * Then reload catalog if tier changed:
 *   php staging-scripts/load_milestone_definitions.php
 */
declare(strict_types=1);

require_once __DIR__ . '/_staging_milestones_bootstrap.php';

$con = k2_staging_milestones_bootstrap();
$sqlPath = dirname(__DIR__) . '/staging-sql/milestones/player_milestones_rebuild_diversity_merchant.sql';

if (!k2_staging_table_exists($con, 'player_milestones')) {
    fwrite(STDERR, "Table player_milestones missing.\n");
    exit(1);
}
if (!is_file($sqlPath)) {
    fwrite(STDERR, "Missing {$sqlPath} — upload from repo scripts/ladder/sql/.\n");
    exit(1);
}

$dbRes = $con->query('SELECT DATABASE() AS db');
$dbName = ($dbRes && ($r = $dbRes->fetch_assoc())) ? (string) $r['db'] : '?';
if ($dbRes) {
    $dbRes->free();
}

$before = $con->query(
    "SELECT COUNT(*) AS n FROM player_milestones WHERE milestone_key = 'diversity_merchant'"
);
$beforeRow = $before ? $before->fetch_assoc() : null;
if ($before) {
    $before->free();
}
echo "diversity_merchant fix on {$dbName} (before: " . ($beforeRow['n'] ?? '?') . " rows)\n";

k2_staging_exec_sql_file($con, $sqlPath, 'diversity_merchant surgical');

$checks = [
    'diversity_merchant' => "SELECT COUNT(*) AS n FROM player_milestones WHERE milestone_key = 'diversity_merchant'",
    'total_rows' => 'SELECT COUNT(*) AS n FROM player_milestones',
    'giant_slayer' => "SELECT COUNT(*) AS n FROM player_milestones WHERE milestone_key = 'giant_slayer'",
];
foreach ($checks as $label => $sql) {
    $res = $con->query($sql);
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    echo "{$label}: " . ($row['n'] ?? '?') . "\n";
}

echo "Expected: diversity_merchant=25, giant_slayer=31 (unchanged). total_rows=6615 if wave 1 had 6658.\n";
echo "Done. Reload milestone_definitions if seed tier_band changed.\n";
mysqli_close($con);
