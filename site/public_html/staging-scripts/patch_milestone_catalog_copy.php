<?php
/**
 * @archived One-off (May 2026). Do not run unless explicitly re-applying a copy patch batch.
 *
 * Staging one-shot: apply milestone catalog copy patches (no TRUNCATE).
 *
 * CLI only. After WinSCP upload:
 *   public_html/staging-data/milestone_catalog_copy_patches.json
 *   public_html/staging-scripts/patch_milestone_catalog_copy.php
 *
 *   cd /path/to/public_html
 *   php staging-scripts/patch_milestone_catalog_copy.php
 */
declare(strict_types=1);

require_once __DIR__ . '/_staging_milestones_bootstrap.php';

$con = k2_staging_milestones_bootstrap();

if (!k2_staging_table_exists($con, 'milestone_definitions')) {
    fwrite(STDERR, "Missing milestone_definitions table.\n");
    exit(1);
}

$patchPath = dirname(__DIR__) . '/staging-data/milestone_catalog_copy_patches.json';
if (!is_file($patchPath)) {
    fwrite(STDERR, "Missing patch file: {$patchPath}\n");
    exit(1);
}

$payload = json_decode(file_get_contents($patchPath), true, 512, JSON_THROW_ON_ERROR);
$patches = $payload['patches'] ?? null;
if (!is_array($patches) || $patches === []) {
    fwrite(STDERR, "Invalid patch file: empty patches array.\n");
    exit(1);
}

$dbRes = $con->query('SELECT DATABASE() AS db');
$dbName = ($dbRes && ($r = $dbRes->fetch_assoc())) ? (string) $r['db'] : '?';
if ($dbRes) {
    $dbRes->free();
}
echo "Patch milestone catalog copy on {$dbName} (" . count($patches) . " keys)...\n";

$stmtName = $con->prepare(
    'UPDATE milestone_definitions SET display_name = ? WHERE milestone_key = ?'
);
$stmtRule = $con->prepare(
    'UPDATE milestone_definitions SET rule_short = ? WHERE milestone_key = ?'
);
if ($stmtName === false || $stmtRule === false) {
    fwrite(STDERR, 'Prepare failed: ' . $con->error . PHP_EOL);
    exit(1);
}

foreach ($patches as $patch) {
    $key = (string) ($patch['milestone_key'] ?? '');
    if ($key === '') {
        fwrite(STDERR, "Patch missing milestone_key.\n");
        exit(1);
    }

    if (isset($patch['display_name'])) {
        $name = (string) $patch['display_name'];
        $stmtName->bind_param('ss', $name, $key);
        if (!$stmtName->execute() || $stmtName->affected_rows > 1) {
            fwrite(STDERR, "display_name update failed for {$key}: " . $con->error . PHP_EOL);
            exit(1);
        }
        echo "  display_name {$key}\n";
    }

    if (isset($patch['rule_short'])) {
        $rule = (string) $patch['rule_short'];
        $stmtRule->bind_param('ss', $rule, $key);
        if (!$stmtRule->execute() || $stmtRule->affected_rows > 1) {
            fwrite(STDERR, "rule_short update failed for {$key}: " . $con->error . PHP_EOL);
            exit(1);
        }
        echo "  rule_short {$key}\n";
    }
}

$stmtName->close();
$stmtRule->close();

$key = $con->real_escape_string('play_streak_100');
$res = $con->query(
    "SELECT display_name, rule_short FROM milestone_definitions WHERE milestone_key = '{$key}' LIMIT 1"
);
if ($res && ($row = $res->fetch_assoc())) {
    echo "Spot-check play_streak_100: {$row['display_name']} — {$row['rule_short']}\n";
    $res->free();
}

echo "Done.\n";
mysqli_close($con);
