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
require __DIR__ . '/../../site/public_html/includes/amiga_tournament_lib.php';
$tournamentUrl = amiga_url_with_context(amiga_tournament_url((int) $lastId));
if (!str_contains($tournamentUrl, 'tournament.php')
    || (!str_contains($tournamentUrl, 'as=event%3A' . $lastId) && !str_contains($tournamentUrl, 'as=event:' . $lastId))) {
    fwrite(STDERR, "tournament url with context fail: {$tournamentUrl}\n");
    exit(1);
}
$exitUrl = amiga_url_present('/amiga/leaderboards/rating.php?sort=rating');
if (str_contains($exitUrl, 'as=')) {
    fwrite(STDERR, "url present fail: {$exitUrl}\n");
    exit(1);
}
$_SERVER['REQUEST_URI'] = '/amiga/player/opponents/wdl.php?id=386&as=year%3A2003&k2_sort=1&k2_dir=desc';
$_GET = ['id' => '386', 'as' => 'year:2003', 'k2_sort' => '1', 'k2_dir' => 'desc'];
$playerPresent = amiga_url_present('/amiga/player/opponents/wdl.php');
if (!str_contains($playerPresent, 'id=386')) {
    fwrite(STDERR, "player present url lost id: {$playerPresent}\n");
    exit(1);
}
if (str_contains($playerPresent, 'as=')) {
    fwrite(STDERR, "player present url kept as=: {$playerPresent}\n");
    exit(1);
}
if (!str_contains($playerPresent, 'k2_sort=1')) {
    fwrite(STDERR, "player present url lost sort: {$playerPresent}\n");
    exit(1);
}
$_SERVER['REQUEST_URI'] = '/amiga/leaderboards/rating.php';
$_GET = ['as' => 'event:' . $lastId];
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

require __DIR__ . '/../../site/public_html/includes/amiga_hub_nav_lib.php';
$presentTabs = amiga_hub_tabs_for_nav(false);
$travelTabs = amiga_hub_tabs_for_nav(true);
if (!isset($presentTabs['news'], $presentTabs['live-tournaments'])) {
    fwrite(STDERR, "present hub missing news or live-tournaments\n");
    exit(1);
}
if (isset($travelTabs['news']) || isset($travelTabs['live-tournaments']) || isset($travelTabs['tournaments'])) {
    fwrite(STDERR, "time-travel hub should omit present-only / collection tabs\n");
    exit(1);
}
if (!isset($travelTabs['leaderboards'], $travelTabs['activity'], $travelTabs['hall-of-fame'])) {
    fwrite(STDERR, "time-travel hub missing snapshot tabs\n");
    exit(1);
}
if (count($travelTabs) !== count(K2_AMIGA_HUB_TIME_TRAVEL_TAB_IDS)) {
    fwrite(STDERR, "time-travel hub tab count mismatch\n");
    exit(1);
}
require __DIR__ . '/../../site/public_html/includes/amiga_time_mode_nav.php';
$_GET = [];
amiga_snapshot_context_reset();
$_SERVER['REQUEST_URI'] = '/amiga/player/profile.php?id=73';
$ttFromPlayer = amiga_time_mode_nav_time_travel_href();
if (!str_contains($ttFromPlayer, '/amiga/leaderboards/rating.php')) {
    fwrite(STDERR, "TT entry from player should be rating LB: {$ttFromPlayer}\n");
    exit(1);
}
if (!str_contains($ttFromPlayer, 'as=year%3A') && !str_contains($ttFromPlayer, 'as=year:')) {
    fwrite(STDERR, "TT entry should use first calendar year: {$ttFromPlayer}\n");
    exit(1);
}
if (str_contains($ttFromPlayer, 'id=')) {
    fwrite(STDERR, "TT entry should not carry page id: {$ttFromPlayer}\n");
    exit(1);
}
$_SERVER['REQUEST_URI'] = '/amiga/tournament.php?id=5&view=event-stats';
$_GET = ['id' => '5', 'view' => 'event-stats'];
amiga_snapshot_context_reset();
$tournamentTtHref = amiga_time_mode_nav_time_travel_href();
if (!str_contains($tournamentTtHref, '/amiga/leaderboards/rating.php')) {
    fwrite(STDERR, "TT entry from tournament should be rating LB: {$tournamentTtHref}\n");
    exit(1);
}
if (!str_contains($tournamentTtHref, 'as=year%3A') && !str_contains($tournamentTtHref, 'as=year:')) {
    fwrite(STDERR, "TT entry from tournament should use first calendar year: {$tournamentTtHref}\n");
    exit(1);
}
if (str_contains($tournamentTtHref, 'id=')) {
    fwrite(STDERR, "TT entry should not carry tournament id: {$tournamentTtHref}\n");
    exit(1);
}
if (!str_contains($tournamentTtHref, 'k2_tt_entry=1')) {
    fwrite(STDERR, "TT entry from present should carry k2_tt_entry=1: {$tournamentTtHref}\n");
    exit(1);
}
$_GET['as'] = 'event:' . $lastId;
amiga_snapshot_context_reset();
$ttInLens = amiga_time_mode_nav_time_travel_href();
if (!str_contains($ttInLens, '/amiga/leaderboards/rating.php')
    || (!str_contains($ttInLens, 'as=event%3A' . $lastId) && !str_contains($ttInLens, 'as=event:' . $lastId))) {
    fwrite(STDERR, "TT toggle in lens should be rating LB with active as=: {$ttInLens}\n");
    exit(1);
}
if (str_contains($ttInLens, 'k2_tt_entry=')) {
    fwrite(STDERR, "TT toggle in lens should not carry k2_tt_entry: {$ttInLens}\n");
    exit(1);
}
if (amiga_hub_present_entry_path() !== '/amiga/news.php') {
    fwrite(STDERR, "present toggle target should be news\n");
    exit(1);
}
echo "mode_toggle_hrefs_ok\n";
$presentKeys = array_keys($presentTabs);
if ($presentKeys[array_key_last($presentKeys)] !== 'live-tournaments') {
    fwrite(STDERR, "live-tournaments should be last present hub tab\n");
    exit(1);
}
$_GET['as'] = 'event:' . $lastId;
amiga_snapshot_context_reset();
$realmHomeTt = amiga_realm_home_href();
if (!str_contains($realmHomeTt, '/amiga/leaderboards/rating.php')
    || (!str_contains($realmHomeTt, 'as=event%3A' . $lastId) && !str_contains($realmHomeTt, 'as=event:' . $lastId))) {
    fwrite(STDERR, "realm home in TT should be rating LB with as=: {$realmHomeTt}\n");
    exit(1);
}
$_GET = [];
amiga_snapshot_context_reset();
if (amiga_realm_home_href() !== '/amiga/news.php') {
    fwrite(STDERR, "realm home in present should be news\n");
    exit(1);
}
echo "realm_home_href_ok\n";
echo "hub_nav_ia_ok\n";

require __DIR__ . '/../../site/public_html/includes/amiga_snapshot_chrome.php';
$_GET['as'] = 'event:' . $lastId;
amiga_snapshot_context_reset();
$GLOBALS['_amiga_snapshot_chrome_rendered'] = false;
ob_start();
amiga_snapshot_chrome_render();
$eventChromeOut = ob_get_clean();
if (!str_contains($eventChromeOut, 'k2-amiga-time-travel--event-wing')
    || substr_count($eventChromeOut, '<form') !== substr_count($eventChromeOut, '</form>')
    || !str_contains($eventChromeOut, 'k2-archive-listbox')
    || !str_contains($eventChromeOut, 'k2-amiga-history__stepper--fixed-label')) {
    fwrite(STDERR, "event wing chrome render incomplete\n");
    exit(1);
}
echo "event_wing_chrome_ok\n";

require __DIR__ . '/../../site/public_html/includes/amiga_player_load.php';
$_GET = [];
amiga_snapshot_context_reset();
$heroPresent = amiga_player_load($con, 386);
$_GET['as'] = 'year:2003';
amiga_snapshot_context_reset();
$hero2003 = amiga_player_load($con, 386);
if ((int) ($heroPresent['rating'] ?? 0) === (int) ($hero2003['rating'] ?? 0)
    && (int) ($heroPresent['games'] ?? 0) === (int) ($hero2003['games'] ?? 0)) {
    fwrite(STDERR, "player hero snapshot should differ at year:2003 vs present\n");
    exit(1);
}
if ((int) ($hero2003['games'] ?? 0) < 1) {
    fwrite(STDERR, "player hero snapshot missing games at year:2003\n");
    exit(1);
}
echo 'player_hero_snapshot_ok rating_present=' . ($heroPresent['rating'] ?? 'null')
    . ' rating_2003=' . ($hero2003['rating'] ?? 'null') . PHP_EOL;

$_GET['as'] = 'year:2001';
amiga_snapshot_context_reset();
$preDebut = amiga_player_load($con, 73);
if (($preDebut['at_cutoff'] ?? true) !== false) {
    fwrite(STDERR, "player 73 at year:2001 should be pre-debut\n");
    exit(1);
}
if ($preDebut['rating'] !== null || $preDebut['games'] !== null || $preDebut['rank'] !== null) {
    fwrite(STDERR, "pre-debut hero stats should be null\n");
    exit(1);
}
echo "player_pre_debut_ok\n";

$firstAs = amiga_player_first_snapshot_as_param($con, 73);
if ($firstAs === null || !str_starts_with($firstAs, 'event:')) {
    fwrite(STDERR, "player 73 missing first snapshot as param\n");
    exit(1);
}
echo 'player_first_snapshot=' . $firstAs . PHP_EOL;

$accent73 = amiga_player_participated_event_key_set($con, 73);
if (count($accent73) < 2) {
    fwrite(STDERR, "player 73 needs picker accent keys for stepper test\n");
    exit(1);
}
echo 'player_picker_accent_count=' . count($accent73) . PHP_EOL;
$realmEvents = amiga_rating_history_catalog_event($con);
$played73 = amiga_player_participated_event_keys($con, 73);
if (count($played73) < 2) {
    fwrite(STDERR, "player 73 needs at least 2 played events for stepper test\n");
    exit(1);
}
$firstPlayed = $played73[0];
$secondPlayed = $played73[1];
$realmFirst = amiga_rating_history_catalog_position($realmEvents, $firstPlayed);
$playerSteps = amiga_player_event_wing_step_keys($con, 73, $realmEvents, $firstPlayed);
if ($playerSteps['next_key'] !== $secondPlayed) {
    fwrite(STDERR, "player stepper next expected {$secondPlayed}, got " . ($playerSteps['next_key'] ?? 'null') . "\n");
    exit(1);
}
if ($playerSteps['prev_key'] !== $realmFirst['prev_key']) {
    fwrite(STDERR, "player stepper prev at debut should be realm prev\n");
    exit(1);
}
$_SERVER['REQUEST_URI'] = '/amiga/player/profile.php?id=73&as=event%3A' . $firstPlayed;
$_GET = ['id' => '73', 'as' => 'event:' . $firstPlayed];
amiga_snapshot_context_reset();
$ctxPlayerEvent = amiga_snapshot_context_from_request($con);
if ($ctxPlayerEvent->nextKey() !== $secondPlayed) {
    fwrite(STDERR, "context player event next mismatch\n");
    exit(1);
}
$_SERVER['REQUEST_URI'] = '/amiga/leaderboards/rating.php?as=event%3A' . $firstPlayed;
$_GET = ['as' => 'event:' . $firstPlayed];
amiga_snapshot_context_reset();
$ctxHubEvent = amiga_snapshot_context_from_request($con);
if ($ctxHubEvent->nextKey() !== $realmFirst['next_key']) {
    fwrite(STDERR, "hub event next should stay realm-global\n");
    exit(1);
}
echo "player_event_stepper_ok\n";

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
