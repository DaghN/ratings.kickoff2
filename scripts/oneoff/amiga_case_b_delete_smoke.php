<?php
declare(strict_types=1);
/**
 * L5 slice 4 smoke — Case B refuse gates + dry-run + project-present-at idempotency.
 * Does NOT delete the real tip unless --apply is passed (then seals after; restore from prior seal yourself).
 *
 * CLI: php scripts/oneoff/amiga_case_b_delete_smoke.php
 *      php scripts/oneoff/amiga_case_b_delete_smoke.php --apply
 */
$root = dirname(__DIR__, 2);
require_once $root . '/site/public_html/amiga/ops/modules/delete_unfinalized_tournament.php';
require_once $root . '/site/public_html/amiga/ops/modules/delete_last_finalized_tournament.php';
require_once $root . '/site/public_html/amiga/includes/amiga_backup_seal_lib.php';
require $root . '/site/config/ko2amiga_config.php';

$apply = in_array('--apply', $argv, true);

$dbName = (string) ($database ?? '');
if ($dbName !== 'ko2amiga_db' && $dbName !== 'ko2amiga_work') {
    fwrite(STDERR, "Expected ko2amiga_db or ko2amiga_work, got {$dbName}\n");
    exit(1);
}

mysqli_report(MYSQLI_REPORT_OFF);
$con = new mysqli($dbhost, $username, $password, $database, (int) $dbportnum);
if ($con->connect_errno) {
    fwrite(STDERR, 'connect failed: ' . $con->connect_error . "\n");
    exit(1);
}
$con->set_charset('utf8mb4');

echo "DB={$database} apply=" . ($apply ? '1' : '0') . "\n";

$ctx = amiga_case_b_tip_context($con);
if ($ctx['tip'] === null) {
    fwrite(STDERR, "FAIL: no finalized tip\n");
    exit(1);
}
$tipId = (int) $ctx['tip']['id'];
$priorId = $ctx['prior'] !== null ? (int) $ctx['prior']['id'] : 0;
echo "Tip #{$tipId} " . $ctx['tip']['name'] . " prior=" . ($priorId > 0 ? "#{$priorId}" : 'none') . "\n";

// Refuse Case B on unfinalized (create tiny draft)
$stamp = gmdate('Ymd-His');
$name = 'L5 CaseB RefuseSmoke ' . $stamp;
$overrides = json_encode([
    'generated_by' => 'site.public_html.amiga.ops.fixtures',
    'smoke' => 'l5-s4',
], JSON_UNESCAPED_SLASHES);
$eventDate = gmdate('Y-m-d');
$lifecycle = 'draft';
$pc = 0;
$country = 'England';
$stmt = $con->prepare(
    'INSERT INTO tournaments '
    . '(source_id, name, chrono, event_date, is_cup, country, equal_teams, player_count, '
    . 'format_overrides, has_league, has_cup, is_world_cup, lifecycle_status, rating_finalized) '
    . 'VALUES (NULL, ?, NULL, ?, 0, ?, 0, ?, ?, 1, 0, 0, ?, 0)'
);
$stmt->bind_param('sssiss', $name, $eventDate, $country, $pc, $overrides, $lifecycle);
if (!$stmt->execute()) {
    fwrite(STDERR, "insert refuse kitchen failed: {$stmt->error}\n");
    exit(1);
}
$draftId = (int) $stmt->insert_id;
$stmt->close();
$refuseDraft = amiga_delete_last_finalized_tournament($con, $draftId, true);
if ($refuseDraft['ok']) {
    fwrite(STDERR, "FAIL: Case B accepted unfinalized #{$draftId}\n");
    exit(1);
}
if (!str_contains((string) $refuseDraft['error'], 'not rating_finalized')) {
    fwrite(STDERR, "FAIL: unexpected refuse for draft: {$refuseDraft['error']}\n");
    exit(1);
}
echo "OK refuse unfinalized #{$draftId}\n";
amiga_delete_unfinalized_tournament($con, $draftId, false);

// Refuse non-tip finalized (if prior exists)
if ($priorId > 0) {
    $refusePrior = amiga_delete_last_finalized_tournament($con, $priorId, true);
    if ($refusePrior['ok']) {
        fwrite(STDERR, "FAIL: Case B accepted non-tip #{$priorId}\n");
        exit(1);
    }
    if (!str_contains((string) $refusePrior['error'], 'not the chrono-last')) {
        fwrite(STDERR, "FAIL: unexpected refuse for prior: {$refusePrior['error']}\n");
        exit(1);
    }
    echo "OK refuse non-tip prior #{$priorId}\n";
} else {
    echo "WARN no prior tip — skip non-tip refuse\n";
}

$dry = amiga_delete_last_finalized_tournament($con, $tipId, true);
if (!$dry['ok']) {
    fwrite(STDERR, "FAIL dry-run tip: {$dry['error']}\n");
    exit(1);
}
if ((int) $dry['prior_tournament_id'] !== $priorId && $priorId > 0) {
    fwrite(STDERR, "FAIL dry-run prior mismatch got={$dry['prior_tournament_id']} want={$priorId}\n");
    exit(1);
}
echo "OK dry-run tip #{$tipId} → prior #{$dry['prior_tournament_id']}\n";

// Idempotent project-present-at on current tip (no tip delete)
$beforeGames = (int) ($con->query('SELECT COUNT(*) AS n FROM amiga_games')->fetch_assoc()['n'] ?? 0);
$beforeCurrent = (int) ($con->query('SELECT COUNT(*) AS n FROM amiga_player_current')->fetch_assoc()['n'] ?? 0);
$proj = amiga_ops_project_present_at($con, $tipId);
$afterCurrent = (int) ($con->query('SELECT COUNT(*) AS n FROM amiga_player_current')->fetch_assoc()['n'] ?? 0);
$sumGames = (int) ($con->query('SELECT COALESCE(SUM(games),0) AS n FROM amiga_player_matchup_summary')->fetch_assoc()['n'] ?? 0);
if ($sumGames !== 2 * $beforeGames) {
    fwrite(STDERR, "FAIL matchup SUM(games)={$sumGames} expected " . (2 * $beforeGames) . "\n");
    exit(1);
}
if ($afterCurrent < 1) {
    fwrite(STDERR, "FAIL player_current empty after project\n");
    exit(1);
}
echo "OK project-present-at tip #{$tipId}: current={$proj['player_current']} "
    . "(was {$beforeCurrent}) matchups={$proj['matchup_summary']} SUM(games)={$sumGames}=2×{$beforeGames}\n";

if (!$apply) {
    echo "PASS (refuse + dry-run + project-present-at). Tip not deleted. Pass --apply for full Case B.\n";
    $con->close();
    exit(0);
}

if ($priorId <= 0) {
    fwrite(STDERR, "FAIL --apply needs a prior tip\n");
    exit(1);
}

echo "APPLY: sealing pre-delete, then Case B tip #{$tipId}…\n";
$preSeal = amiga_backup_seal_write_from_config($con, ['reason' => 'case_b_smoke_pre', 'reserve' => false]);
if (!$preSeal['ok']) {
    fwrite(STDERR, "FAIL pre-seal: {$preSeal['error']}\n");
    exit(1);
}
echo "OK pre-seal {$preSeal['seal_id']}\n";

$deleted = amiga_delete_last_finalized_tournament($con, $tipId, false);
if (!$deleted['ok']) {
    fwrite(STDERR, "FAIL Case B apply: {$deleted['error']}\n");
    exit(1);
}
$postSeal = amiga_backup_seal_write_from_config($con, ['reason' => 'case_b_delete', 'reserve' => false]);
if (!$postSeal['ok']) {
    fwrite(STDERR, "FAIL post-seal after Case B: {$postSeal['error']}\n");
    exit(1);
}

$newTip = amiga_case_b_find_tip($con);
if ($newTip === null || (int) $newTip['id'] !== $priorId) {
    $got = $newTip !== null ? (int) $newTip['id'] : 0;
    fwrite(STDERR, "FAIL new tip #{$got} expected prior #{$priorId}\n");
    exit(1);
}
$gone = $con->query("SELECT id FROM tournaments WHERE id = {$tipId} LIMIT 1");
if ($gone && $gone->fetch_assoc()) {
    fwrite(STDERR, "FAIL tip #{$tipId} still exists\n");
    exit(1);
}
echo "PASS Case B apply: deleted #{$tipId}; new tip #{$priorId}; post-seal {$postSeal['seal_id']}\n";
echo "NOTE: restore from pre-seal {$preSeal['seal_id']} via backup page if you need tip back.\n";
$con->close();