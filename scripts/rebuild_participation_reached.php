<?php
/**
 * Backfill SCH-025 reached_at / reached_game_id on player_activity_participation.
 * Run after migrate 025 on a DB that already has participation counts (P4b / rebuild).
 *
 *   php scripts/rebuild_participation_reached.php
 */
declare(strict_types=1);

$repoRoot = dirname(__DIR__);
$_SERVER['DOCUMENT_ROOT'] = $repoRoot . '/site/public_html';

require $repoRoot . '/site/config/ko2unitydb_config.php';
require $repoRoot . '/site/public_html/includes/lb_activity_lib.php';

$con = new mysqli($dbhost, $username, $password, $database, (int) $dbportnum);
if ($con->connect_error) {
    fwrite(STDERR, "connect failed: {$con->connect_error}\n");
    exit(1);
}
$con->query("SET time_zone = '+00:00'");

if (!k2_lb_activity_participation_reached_columns_ready($con)) {
    fwrite(STDERR, "SCH-025 columns missing — run ops migration 025 first.\n");
    exit(1);
}

$updated = k2_lb_activity_participation_rebuild_reached_columns($con);
echo "rebuild_participation_reached: updated {$updated} player row(s) on {$database}\n";

$mismatches = k2_lb_activity_participation_reached_oracle_mismatches($con);
if ($mismatches !== []) {
    fwrite(STDERR, "oracle mismatches after rebuild: " . count($mismatches) . "\n");
    foreach (array_slice($mismatches, 0, 10) as $line) {
        fwrite(STDERR, "  {$line}\n");
    }
    exit(2);
}

echo "oracle parity: PASS\n";
