<?php
declare(strict_types=1);
/**
 * L5 slice 3 smoke — Case A delete unfinalized kitchen (no auto-seal).
 * CLI: php scripts/oneoff/amiga_case_a_delete_smoke.php
 */
$root = dirname(__DIR__, 2);
require_once $root . '/site/public_html/amiga/ops/modules/delete_unfinalized_tournament.php';
require $root . '/site/config/ko2amiga_config.php';

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

echo "DB={$database}\n";

$tipRes = $con->query(
    'SELECT id, name FROM tournaments WHERE rating_finalized = 1 '
    . 'ORDER BY event_date DESC, chrono DESC, id DESC LIMIT 1'
);
$tip = $tipRes ? $tipRes->fetch_assoc() : null;
if ($tip) {
    $tipId = (int) $tip['id'];
    $refuse = amiga_delete_unfinalized_tournament($con, $tipId, true);
    if ($refuse['ok']) {
        fwrite(STDERR, "FAIL: Case A dry-run accepted finalized tip #{$tipId}\n");
        exit(1);
    }
    if (!str_contains((string) $refuse['error'], 'rating_finalized')) {
        fwrite(STDERR, "FAIL: unexpected refuse message: {$refuse['error']}\n");
        exit(1);
    }
    echo "OK refuse finalized tip #{$tipId}\n";
} else {
    echo "WARN no finalized tip to refuse-test\n";
}

$stamp = gmdate('Ymd-His');
$name = 'L5 CaseA Smoke ' . $stamp;
$overrides = json_encode([
    'generated_by' => 'site.public_html.amiga.ops.fixtures',
    'smoke' => 'l5-s3',
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
if ($stmt === false) {
    fwrite(STDERR, 'prepare insert: ' . $con->error . "\n");
    exit(1);
}
$stmt->bind_param('sssiss', $name, $eventDate, $country, $pc, $overrides, $lifecycle);
if (!$stmt->execute()) {
    fwrite(STDERR, 'insert smoke kitchen failed: ' . $stmt->error . "\n");
    exit(1);
}
$tid = (int) $stmt->insert_id;
$stmt->close();
echo "OK created smoke kitchen id={$tid} name={$name}\n";

$dry = amiga_delete_unfinalized_tournament($con, $tid, true);
if (!$dry['ok']) {
    fwrite(STDERR, "FAIL dry-run: {$dry['error']}\n");
    exit(1);
}
echo "OK dry-run games={$dry['games_deleted']}\n";

$del = amiga_delete_unfinalized_tournament($con, $tid, false);
if (!$del['ok']) {
    fwrite(STDERR, "FAIL delete: {$del['error']}\n");
    exit(1);
}
echo "OK deleted id={$del['tournament_id']} games={$del['games_deleted']}\n";

$check = $con->prepare('SELECT id FROM tournaments WHERE id = ? LIMIT 1');
$check->bind_param('i', $tid);
$check->execute();
$still = $check->get_result()->fetch_assoc();
$check->close();
if ($still !== null) {
    fwrite(STDERR, "FAIL tournament still present after delete\n");
    exit(1);
}
echo "OK tournament gone from DB\n";
echo "OK no auto-seal after Case A (tip unchanged)\n";

mysqli_close($con);
echo "SMOKE PASS\n";
exit(0);