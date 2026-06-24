<?php
/**
 * Amiga time travel phase 1 — CLI smoke (mirrors browser checklist in implementation plan slice 6).
 *
 * Run: php scripts/oneoff/amiga_time_travel_smoke.php
 * Full context probe: php scripts/oneoff/amiga_snapshot_context_probe.php
 */
declare(strict_types=1);

require __DIR__ . '/../../site/public_html/includes/amiga_snapshot_context.php';
require __DIR__ . '/../../site/public_html/includes/amiga_snapshot_url.php';
require __DIR__ . '/../../site/public_html/includes/amiga_lb_snapshot_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_realm_snapshot_read_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

$fail = static function (string $step, string $message): void {
    fwrite(STDERR, "FAIL step {$step}: {$message}\n");
    exit(1);
};

$pass = static function (string $step, string $detail = ''): void {
    echo 'PASS step ' . $step . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
};

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    $fail('0', "connect: {$con->connect_error}");
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

// Step 1 — LB rating present (no as=).
amiga_snapshot_context_reset();
$_GET = [];
$ctxPresent = amiga_snapshot_context_from_request($con);
if ($ctxPresent->isActive()) {
    $fail('1', 'present mode should be inactive');
}
$lbPresent = amiga_lb_query_career(
    $con,
    $ctxPresent,
    'SELECT p.id AS player_id, s.Rating ',
    'ORDER BY s.Rating DESC'
);
$presentLbCount = $lbPresent->num_rows;
mysqli_free_result($lbPresent);
if ($presentLbCount < 1) {
    $fail('1', 'rating LB empty in present mode');
}
$presentHof = amiga_hof_records_load($con, $ctxPresent);
if ($presentHof === null) {
    $fail('1', 'HoF present row missing');
}
$pass('1', "rating LB rows={$presentLbCount}");

// Step 2 — Enter time travel as=year:2003 (ribbon / context active).
$_GET['as'] = 'year:2003';
amiga_snapshot_context_reset();
$ctx2003 = amiga_snapshot_context_from_request($con);
if (!$ctx2003->isActive() || $ctx2003->wing() !== 'year' || $ctx2003->key() !== '2003') {
    $fail('2', 'year:2003 context not active');
}
$pass('2', 'context active · ' . ($ctx2003->label() ?? '2003'));

// Step 3 — HoF holders at cutoff differ from present (when records moved since).
$hof2003 = amiga_hof_records_load($con, $ctx2003);
if ($hof2003 === null) {
    $fail('3', 'HoF snapshot row missing at year:2003');
}
$hofChanged = (int) ($presentHof['MostGamesPlayed'] ?? 0) !== (int) ($hof2003['MostGamesPlayed'] ?? 0)
    || (int) ($presentHof['MostGamesPlayedID'] ?? 0) !== (int) ($hof2003['MostGamesPlayedID'] ?? 0);
if (!$hofChanged) {
    $fail('3', 'expected MostGamesPlayed holder to differ at year:2003 vs present');
}
$hofHref = amiga_url_with_context('/amiga/hall-of-fame.php');
if (!str_contains($hofHref, 'as=year%3A2003') && !str_contains($hofHref, 'as=year:2003')) {
    $fail('3', "HoF link lost as=: {$hofHref}");
}
$pass('3', 'HoF snapshot differs · link carries as=');

// Step 4 — LB wings reflect cutoff; wing nav keeps as=.
$goals2003 = amiga_lb_query_career(
    $con,
    $ctx2003,
    'SELECT p.id AS player_id ',
    'ORDER BY s.GoalsFor DESC'
);
$goals2003Count = $goals2003->num_rows;
mysqli_free_result($goals2003);
$geo2003 = amiga_lb_calendar_geo_rows_at_cutoff($con, $ctx2003);
if ($goals2003Count < 1 || $geo2003 === []) {
    $fail('4', 'goals or calendar-geo empty at year:2003');
}
if ($goals2003Count >= $presentLbCount) {
    $fail('4', 'year:2003 goals count should be less than present roster');
}
$goalsHref = amiga_url_with_context('/amiga/leaderboards/goals.php');
if (!str_contains($goalsHref, 'as=year%3A2003') && !str_contains($goalsHref, 'as=year:2003')) {
    $fail('4', "goals wing link lost as=: {$goalsHref}");
}
$pass('4', "goals={$goals2003Count} geo=" . count($geo2003));

// Step 5 — Player link carries as=; profile unwired (URL only — no page fetch).
$GLOBALS['_amiga_snapshot_context'] = $ctx2003;
$playerHref = amiga_url_with_context('/amiga/player/profile.php', ['id' => 42]);
if (!str_contains($playerHref, 'as=year%3A2003') && !str_contains($playerHref, 'as=year:2003')) {
    $fail('5', "player link lost as=: {$playerHref}");
}
if (!str_contains($playerHref, 'id=42')) {
    $fail('5', 'player id missing from link');
}
$pass('5', 'profile URL carries as= (page stays present-only in phase 1)');

// Step 6 — amiga_url_present() strips as= on same path (toggle exit → News is T19; tested in context probe).
$exitRating = amiga_url_present('/amiga/leaderboards/rating.php');
if (str_contains($exitRating, 'as=')) {
    $fail('6', "exit URL still has as=: {$exitRating}");
}
amiga_snapshot_context_reset();
$_GET = [];
$ctxAfterExit = amiga_snapshot_context_from_request($con);
$lbAfterExit = amiga_lb_query_career(
    $con,
    $ctxAfterExit,
    'SELECT p.id AS player_id ',
    'ORDER BY s.Rating DESC'
);
$afterCount = $lbAfterExit->num_rows;
mysqli_free_result($lbAfterExit);
if ($afterCount !== $presentLbCount) {
    $fail('6', "present LB row count changed ({$presentLbCount} vs {$afterCount})");
}
$pass('6', 'exit URL clean · present LB row count unchanged');

$con->close();
echo PHP_EOL . 'All six smoke steps passed (CLI). Browser spot-check on ratingskickoff.test still recommended.' . PHP_EOL;
