<?php
declare(strict_types=1);

require __DIR__ . '/../../site/public_html/includes/amiga_snapshot_context.php';
require __DIR__ . '/../../site/public_html/includes/amiga_snapshot_url.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    fwrite(STDERR, "connect fail: {$con->connect_error}\n");
    exit(1);
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

amiga_snapshot_context_reset();
$_GET = [];

$present = amiga_snapshot_context_from_request($con);
if ($present->isActive()) {
    fwrite(STDERR, "expected present mode with no GET params\n");
    exit(1);
}
echo "present_ok\n";

$yearCatalog = amiga_rating_history_catalog_year($con);
if ($yearCatalog === []) {
    fwrite(STDERR, "no years in catalog\n");
    exit(1);
}
$defaultAs = amiga_snapshot_latest_as_param($con);
$expectedDefault = amiga_snapshot_format_as_param('year', (string) $yearCatalog[0]['key']);
if ($defaultAs !== $expectedDefault) {
    fwrite(STDERR, "default entry expected {$expectedDefault}, got {$defaultAs}\n");
    exit(1);
}
echo 'default_entry=' . $defaultAs . PHP_EOL;

$events = amiga_rating_history_catalog_event($con);
if ($events === []) {
    fwrite(STDERR, "no events in catalog\n");
    exit(1);
}
$lastEvent = $events[count($events) - 1];
$lastId = (string) $lastEvent['key'];

$_GET['as'] = 'event:' . $lastId;
amiga_snapshot_context_reset();
$ctxEvent = amiga_snapshot_context_from_request($con);
if (!$ctxEvent->isActive()) {
    fwrite(STDERR, "event context not active\n");
    exit(1);
}
$cutoff = $ctxEvent->cutoff();
if ($cutoff === null || $cutoff['tournament_id'] !== (int) $lastId) {
    fwrite(STDERR, "event cutoff mismatch\n");
    exit(1);
}
echo 'event=' . $cutoff['tournament_id'] . ' label=' . $ctxEvent->label() . PHP_EOL;

$historyView = amiga_rating_history_resolve_view($con, 'event', $lastId);
if ($historyView['entry'] === null
    || (int) ($historyView['entry']['cutoff_tournament_id'] ?? 0) !== (int) $lastId) {
    fwrite(STDERR, "history view parity fail for event\n");
    exit(1);
}
echo "history_event_parity_ok\n";

$_GET['as'] = 'year:2003';
amiga_snapshot_context_reset();
$ctxYear = amiga_snapshot_context_from_request($con);
if (!$ctxYear->isActive() || $ctxYear->wing() !== 'year' || $ctxYear->key() !== '2003') {
    fwrite(STDERR, "year context fail\n");
    exit(1);
}
$prev = $ctxYear->prevCutoff();
if ($ctxYear->prevKey() !== null && $prev === null) {
    fwrite(STDERR, "prevCutoff missing when prev_key set\n");
    exit(1);
}
echo 'year_2003_cutoff_tournament=' . ($ctxYear->cutoff()['tournament_id'] ?? 'null') . PHP_EOL;

$_GET = ['wing' => 'month', 'at' => '2003-11'];
amiga_snapshot_context_reset();
$ctxLegacy = amiga_snapshot_context_from_request($con);
if (!$ctxLegacy->isActive() || $ctxLegacy->wing() !== 'month' || $ctxLegacy->key() !== '2003-11') {
    fwrite(STDERR, "legacy wing/at fail\n");
    exit(1);
}
echo "legacy_wing_at_ok\n";

$_GET['as'] = 'event:' . $lastId;
amiga_snapshot_context_reset();
$GLOBALS['_amiga_snapshot_context'] = amiga_snapshot_context_from_request($con);
$url = amiga_url_with_context('/amiga/leaderboards/rating.php');
if (!str_contains($url, 'as=event%3A' . $lastId) && !str_contains($url, 'as=event:' . $lastId)) {
    fwrite(STDERR, "url with context fail: {$url}\n");
    exit(1);
}
$exitUrl = amiga_url_present('/amiga/leaderboards/rating.php?sort=rating');
if (str_contains($exitUrl, 'as=')) {
    fwrite(STDERR, "url present fail: {$exitUrl}\n");
    exit(1);
}
$enterUrl = amiga_url_with_as_param('/amiga/leaderboards/rating.php', 'event:' . $lastId);
if (!str_contains($enterUrl, 'as=event%3A' . $lastId) && !str_contains($enterUrl, 'as=event:' . $lastId)) {
    fwrite(STDERR, "url with as param fail: {$enterUrl}\n");
    exit(1);
}
$monthUrl = amiga_url_with_as('/amiga/leaderboards/rating.php', 'month', '2008-06');
if (!str_contains($monthUrl, 'as=month%3A2008-06') && !str_contains($monthUrl, 'as=month:2008-06')) {
    fwrite(STDERR, "url wing tab fail: {$monthUrl}\n");
    exit(1);
}
echo "url_helpers_ok\n";

$_GET['as'] = 'year:2010';
amiga_snapshot_context_reset();
$GLOBALS['_amiga_snapshot_context'] = null;
$hubHref = amiga_url_with_context('/amiga/hall-of-fame.php');
if (!str_contains($hubHref, 'as=year%3A2010') && !str_contains($hubHref, 'as=year:2010')) {
    fwrite(STDERR, "hub propagation fail: {$hubHref}\n");
    exit(1);
}
$playerHref = amiga_url_with_context('/amiga/player/profile.php', ['id' => 42]);
if (!str_contains($playerHref, 'as=year%3A2010') && !str_contains($playerHref, 'as=year:2010')) {
    fwrite(STDERR, "player propagation fail: {$playerHref}\n");
    exit(1);
}
if (!str_contains($playerHref, 'id=42')) {
    fwrite(STDERR, "player id missing: {$playerHref}\n");
    exit(1);
}
echo "link_propagation_ok\n";

$_GET['as'] = 'bogus:xyz';
amiga_snapshot_context_reset();
$ctxBad = amiga_snapshot_context_from_request($con);
if ($ctxBad->isActive()) {
    fwrite(STDERR, "invalid as should be present mode\n");
    exit(1);
}
echo "invalid_as_present_ok\n";

require __DIR__ . '/../../site/public_html/includes/amiga_lb_snapshot_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_realm_snapshot_read_lib.php';

amiga_snapshot_context_reset();
$_GET = [];
$ctxPresent = amiga_snapshot_context_from_request($con);
$lbPresent = amiga_lb_query_career(
    $con,
    $ctxPresent,
    'SELECT p.id AS player_id, s.Rating ',
    'ORDER BY s.Rating DESC'
);
$presentCount = $lbPresent->num_rows;
mysqli_free_result($lbPresent);
if ($presentCount < 1) {
    fwrite(STDERR, "lb present mode empty\n");
    exit(1);
}
echo "lb_present_rows={$presentCount}\n";

$_GET['as'] = 'event:' . $lastId;
amiga_snapshot_context_reset();
$ctxLbEvent = amiga_snapshot_context_from_request($con);
$lbEvent = amiga_lb_rating_rows_at_cutoff($con, $ctxLbEvent);
if ($lbEvent === []) {
    fwrite(STDERR, "lb event snapshot empty\n");
    exit(1);
}
$cutoff = $ctxLbEvent->cutoff();
$historyLadder = amiga_rating_history_ladder_at_cutoff(
    $con,
    $cutoff['event_date'] ?? null,
    $cutoff['chrono'] ?? null,
    $cutoff['tournament_id'] ?? null
);
$compareN = min(10, count($lbEvent), count($historyLadder));
for ($i = 0; $i < $compareN; $i++) {
    if ((int) $lbEvent[$i]['player_id'] !== (int) $historyLadder[$i]['player_id']) {
        fwrite(STDERR, "lb/history rank mismatch at " . ($i + 1) . "\n");
        exit(1);
    }
}
echo "lb_history_top{$compareN}_parity_ok\n";

$_GET['as'] = 'year:2008';
amiga_snapshot_context_reset();
$ctx2008 = amiga_snapshot_context_from_request($con);
$goals2008 = amiga_lb_query_career(
    $con,
    $ctx2008,
    'SELECT p.id AS player_id, s.GoalsFor ',
    'ORDER BY s.GoalsFor DESC'
);
$goals2008Count = $goals2008->num_rows;
mysqli_free_result($goals2008);
$geo2008 = amiga_lb_calendar_geo_rows_at_cutoff($con, $ctx2008);
if ($goals2008Count < 1 || $geo2008 === []) {
    fwrite(STDERR, "year 2008 snapshot wings empty\n");
    exit(1);
}
if ($goals2008Count >= $presentCount) {
    fwrite(STDERR, "year 2008 goals count should be <= present\n");
    exit(1);
}
echo "lb_year2008_goals={$goals2008Count} geo=" . count($geo2008) . "\n";

amiga_snapshot_context_reset();
$_GET = [];
$ctxPresentHof = amiga_snapshot_context_from_request($con);
$presentHof = amiga_hof_records_load($con, $ctxPresentHof);
if ($presentHof === null) {
    fwrite(STDERR, "hof present row missing\n");
    exit(1);
}

$_GET['as'] = 'year:2003';
amiga_snapshot_context_reset();
$ctx2003 = amiga_snapshot_context_from_request($con);
$hof2003 = amiga_realm_generalstats_at_cutoff($con, $ctx2003);
if ($hof2003 === null) {
    fwrite(STDERR, "hof year 2003 snapshot missing\n");
    exit(1);
}
$cutoff2003 = $ctx2003->cutoff();
$tid2003 = (int) ($cutoff2003['tournament_id'] ?? 0);
$stmt = $con->prepare(
    'SELECT MostGamesPlayed, MostGamesPlayedID FROM amiga_realm_snapshots WHERE tournament_id = ? LIMIT 1'
);
if (!$stmt) {
    fwrite(STDERR, "hof oracle prepare fail\n");
    exit(1);
}
$stmt->bind_param('i', $tid2003);
$stmt->execute();
$res = $stmt->get_result();
$oracle = $res ? $res->fetch_assoc() : false;
$stmt->close();
if (!is_array($oracle)) {
    fwrite(STDERR, "hof oracle row missing for tournament {$tid2003}\n");
    exit(1);
}
if ((int) ($hof2003['MostGamesPlayed'] ?? -1) !== (int) ($oracle['MostGamesPlayed'] ?? -2)
    || (int) ($hof2003['MostGamesPlayedID'] ?? -1) !== (int) ($oracle['MostGamesPlayedID'] ?? -2)) {
    fwrite(STDERR, "hof cutoff row mismatch vs realm_snapshots\n");
    exit(1);
}
if ((int) ($presentHof['MostGamesPlayed'] ?? 0) === (int) ($hof2003['MostGamesPlayed'] ?? 0)
    && (int) ($presentHof['MostGamesPlayedID'] ?? 0) === (int) ($hof2003['MostGamesPlayedID'] ?? 0)
    && (int) ($presentHof['MostGamesPlayed'] ?? 0) > 0) {
    fwrite(STDERR, "hof year 2003 unexpectedly identical to present MostGamesPlayed\n");
    exit(1);
}
echo "hof_year2003_parity_ok\n";

$_GET['as'] = 'event:' . $lastId;
amiga_snapshot_context_reset();
$ctxHistEvent = amiga_snapshot_context_from_request($con);
$historyView = amiga_rating_history_resolve_from_context($con, $ctxHistEvent);
$lbEventRows = amiga_lb_rating_rows_at_cutoff($con, $ctxHistEvent);
$histCompareN = min(10, count($historyView['ladder']), count($lbEventRows));
for ($i = 0; $i < $histCompareN; $i++) {
    if ((int) $historyView['ladder'][$i]['player_id'] !== (int) $lbEventRows[$i]['player_id']) {
        fwrite(STDERR, "history/lb rank mismatch at " . ($i + 1) . "\n");
        exit(1);
    }
}
echo "history_lb_top{$histCompareN}_parity_ok\n";

$legacyUrl = amiga_rating_history_page_url('month', '2003-11');
if (!str_contains($legacyUrl, 'leaderboards/rating.php') || (!str_contains($legacyUrl, 'as=month%3A2003-11') && !str_contains($legacyUrl, 'as=month:2003-11'))) {
    fwrite(STDERR, "snapshot page url not canonical as=: {$legacyUrl}\n");
    exit(1);
}
echo "history_page_url_ok\n";

$_GET = ['wing' => 'month', 'at' => '2003-11'];
amiga_snapshot_context_reset();
$ctxLegacyHist = amiga_snapshot_context_from_request($con);
$legacyView = amiga_rating_history_resolve_from_context($con, $ctxLegacyHist);
if (!$ctxLegacyHist->isActive() || $ctxLegacyHist->wing() !== 'month' || $ctxLegacyHist->key() !== '2003-11') {
    fwrite(STDERR, "history legacy wing/at resolve fail\n");
    exit(1);
}
if ($legacyView['ladder'] === []) {
    fwrite(STDERR, "history legacy ladder empty\n");
    exit(1);
}
echo "history_legacy_alias_ok\n";

$con->close();
echo "OK\n";
