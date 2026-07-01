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
$events = amiga_rating_history_catalog_event($con);
if ($events === []) {
    fwrite(STDERR, "no events in catalog\n");
    exit(1);
}
$defaultAs = amiga_snapshot_latest_as_param($con);
$expectedDefault = amiga_snapshot_format_as_param('event', (string) $events[0]['key']);
if ($defaultAs !== $expectedDefault) {
    fwrite(STDERR, "default entry expected {$expectedDefault}, got {$defaultAs}\n");
    exit(1);
}
echo 'default_entry=' . $defaultAs . PHP_EOL;

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
if (!str_contains($tournamentUrl, 'tournament/event-stats.php')
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
if (isset($travelTabs['news']) || isset($travelTabs['live-tournaments'])) {
    fwrite(STDERR, "time-travel hub should omit present-only tabs (news, live-tournaments)\n");
    exit(1);
}
if (!isset($travelTabs['leaderboards'], $travelTabs['tournaments'], $travelTabs['activity'], $travelTabs['hall-of-fame'])) {
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
if (!str_contains($ttFromPlayer, 'as=event%3A') && !str_contains($ttFromPlayer, 'as=event:')) {
    fwrite(STDERR, "TT entry should use first ladder event: {$ttFromPlayer}\n");
    exit(1);
}
if (str_contains($ttFromPlayer, 'id=')) {
    fwrite(STDERR, "TT entry should not carry page id: {$ttFromPlayer}\n");
    exit(1);
}
$_SERVER['REQUEST_URI'] = '/amiga/tournament/event-stats.php?id=5';
$_GET = ['id' => '5'];
amiga_snapshot_context_reset();
$tournamentTtHref = amiga_time_mode_nav_time_travel_href();
if (!str_contains($tournamentTtHref, '/amiga/leaderboards/rating.php')) {
    fwrite(STDERR, "TT entry from tournament should be rating LB: {$tournamentTtHref}\n");
    exit(1);
}
if (!str_contains($tournamentTtHref, 'as=event%3A') && !str_contains($tournamentTtHref, 'as=event:')) {
    fwrite(STDERR, "TT entry from tournament should use first ladder event: {$tournamentTtHref}\n");
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
    fwrite(STDERR, "present realm home should be news\n");
    exit(1);
}
$_GET = [];
amiga_snapshot_context_reset();
$_SERVER['REQUEST_URI'] = '/amiga/player/profile.php?id=73';
$presentFromPresent = amiga_time_mode_nav_present_href();
if ($presentFromPresent !== '/amiga/news.php') {
    fwrite(STDERR, "present toggle from present mode should be news: {$presentFromPresent}\n");
    exit(1);
}
$_GET['as'] = 'year:2003';
$_GET['as_with'] = '73';
$_GET['id'] = '73';
amiga_snapshot_context_reset();
$_SERVER['REQUEST_URI'] = '/amiga/player/profile.php?id=73';
$presentFromTt = amiga_time_mode_nav_present_href();
if ($presentFromTt !== '/amiga/player/profile.php?id=73') {
    fwrite(STDERR, "present toggle from TT should stay on same page without lens: {$presentFromTt}\n");
    exit(1);
}
if (str_contains($presentFromTt, 'as=') || str_contains($presentFromTt, 'as_with=')) {
    fwrite(STDERR, "present toggle from TT should strip lens params: {$presentFromTt}\n");
    exit(1);
}
echo "mode_toggle_hrefs_ok\n";
require_once __DIR__ . '/../../site/public_html/includes/amiga_time_travel_stamp.php';
$_GET['as'] = 'year:2001';
amiga_snapshot_context_reset();
$wingCtx = amiga_snapshot_context_from_request($con);
$wingCutoff = $wingCtx->cutoff();
$monthKey = amiga_snapshot_wing_key_from_cutoff($wingCutoff, 'month');
if ($monthKey === null) {
    fwrite(STDERR, "wing tab probe: month key missing\n");
    exit(1);
}
$monthWingHref = amiga_url_with_as('/amiga/leaderboards/rating.php', 'month', $monthKey, amiga_time_travel_stamp_wing_arrival_entry_query());
if (!str_contains($monthWingHref, 'k2_tt_entry=wing')) {
    fwrite(STDERR, "wing tab change should carry k2_tt_entry=wing: {$monthWingHref}\n");
    exit(1);
}
$yearKey = amiga_snapshot_wing_key_from_cutoff($wingCutoff, 'year');
$yearWingHref = amiga_url_with_as('/amiga/leaderboards/rating.php', 'year', (string) $yearKey, []);
if (str_contains($yearWingHref, 'k2_tt_entry=')) {
    fwrite(STDERR, "same-wing tab should not carry k2_tt_entry: {$yearWingHref}\n");
    exit(1);
}
echo "wing_tab_arrival_ok\n";
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

$realmEvents = amiga_rating_history_catalog_event($con);
if (count($realmEvents) < 2) {
    fwrite(STDERR, "need at least 2 events for stepping parity test\n");
    exit(1);
}
$testKey = (string) $realmEvents[5]['key'];
$realmPos = amiga_rating_history_catalog_position($realmEvents, $testKey);
$expectedNext = $realmPos['next_key'];
if ($expectedNext === null || $expectedNext === '') {
    fwrite(STDERR, "test event needs a realm next key\n");
    exit(1);
}
$_SERVER['REQUEST_URI'] = '/amiga/player/profile.php?id=73&as=event%3A' . $testKey;
$_GET = ['id' => '73', 'as' => 'event:' . $testKey];
amiga_snapshot_context_reset();
$ctxPlayerEvent = amiga_snapshot_context_from_request($con);
if ($ctxPlayerEvent->nextKey() !== $expectedNext) {
    fwrite(STDERR, "player path event next should match hub: expected {$expectedNext}, got "
        . ($ctxPlayerEvent->nextKey() ?? 'null') . "\n");
    exit(1);
}
$_SERVER['REQUEST_URI'] = '/amiga/leaderboards/rating.php?as=event%3A' . $testKey;
$_GET = ['as' => 'event:' . $testKey];
amiga_snapshot_context_reset();
$ctxHubEvent = amiga_snapshot_context_from_request($con);
if ($ctxHubEvent->nextKey() !== $expectedNext) {
    fwrite(STDERR, "hub event next mismatch\n");
    exit(1);
}
echo "player_path_event_stepping_parity_ok\n";

require __DIR__ . '/../../site/public_html/includes/amiga_participation_step_lib.php';

$asWithPlayerId = 73;
$participatedKeys = amiga_player_participated_event_keys($con, $asWithPlayerId);
if (count($participatedKeys) < 2) {
    fwrite(STDERR, "player 73 needs at least 2 participated events for as_with probe\n");
    exit(1);
}
$participatedLookup = array_fill_keys($participatedKeys, true);
$asWithTestKey = null;
$asWithExpectedNext = null;
$asWithRealmNext = null;
foreach ($realmEvents as $i => $eventRow) {
    if ($i >= count($realmEvents) - 1) {
        break;
    }
    $currentKey = (string) $eventRow['key'];
    $realmNextKey = (string) $realmEvents[$i + 1]['key'];
    if (isset($participatedLookup[$realmNextKey])) {
        continue;
    }
    $filteredNext = null;
    for ($j = $i + 1; $j < count($realmEvents); $j++) {
        $candidateKey = (string) $realmEvents[$j]['key'];
        if (isset($participatedLookup[$candidateKey])) {
            $filteredNext = $candidateKey;
            break;
        }
    }
    if ($filteredNext === null || !isset($participatedLookup[$currentKey])) {
        continue;
    }
    $asWithTestKey = $currentKey;
    $asWithExpectedNext = $filteredNext;
    $asWithRealmNext = $realmNextKey;
    break;
}
if ($asWithTestKey === null || $asWithExpectedNext === null || $asWithRealmNext === null) {
    fwrite(STDERR, "could not find as_with stepping fixture in event catalog\n");
    exit(1);
}
$_GET = ['as' => 'event:' . $asWithTestKey, 'as_with' => (string) $asWithPlayerId];
amiga_snapshot_context_reset();
$ctxAsWith = amiga_snapshot_context_from_request($con);
if ($ctxAsWith->nextKey() !== $asWithExpectedNext) {
    fwrite(STDERR, "as_with next expected {$asWithExpectedNext}, got " . ($ctxAsWith->nextKey() ?? 'null') . "\n");
    exit(1);
}
$_GET = ['as' => 'event:' . $asWithTestKey];
amiga_snapshot_context_reset();
$ctxRealmAtGap = amiga_snapshot_context_from_request($con);
if ($ctxRealmAtGap->nextKey() !== $asWithRealmNext) {
    fwrite(STDERR, "realm next at gap expected {$asWithRealmNext}, got " . ($ctxRealmAtGap->nextKey() ?? 'null') . "\n");
    exit(1);
}
$_GET = ['as' => 'event:' . $lastId, 'as_with' => (string) $asWithPlayerId];
amiga_snapshot_context_reset();
$ctxAsWithLast = amiga_snapshot_context_from_request($con);
if ($ctxAsWithLast->nextKey() !== null) {
    fwrite(STDERR, "as_with forward clamp at last event expected null next\n");
    exit(1);
}
echo "as_with_event_stepping_ok\n";

$asWithOffKey = null;
foreach ($realmEvents as $eventRow) {
    $key = (string) $eventRow['key'];
    if (!isset($participatedLookup[$key])) {
        $asWithOffKey = $key;
        break;
    }
}
if ($asWithOffKey === null) {
    fwrite(STDERR, "need off-filter event for as_with snap\n");
    exit(1);
}
$offSteps = k2_participation_step_keys($realmEvents, $asWithOffKey, $participatedLookup);
$expectedAsWithSnap = $offSteps['prev_key'] ?? $offSteps['next_key'];
$snapAsWith = k2_participation_snap_target_key($realmEvents, $asWithOffKey, $participatedLookup);
if ($snapAsWith !== $expectedAsWithSnap) {
    fwrite(STDERR, "as_with snap expected {$expectedAsWithSnap}, got " . ($snapAsWith ?? 'null') . "\n");
    exit(1);
}
$snapOnFilter = k2_participation_snap_target_key($realmEvents, $asWithTestKey, $participatedLookup);
if ($snapOnFilter !== null) {
    fwrite(STDERR, "on-filter event should not snap\n");
    exit(1);
}
echo "as_with_filter_snap_ok id={$asWithOffKey} snap={$expectedAsWithSnap}\n";

$yearCatalog = amiga_rating_history_catalog_year($con);
$yearParticipated = amiga_player_participated_wing_key_set($con, $asWithPlayerId, 'year');
$yearTestKey = null;
$yearExpectedNext = null;
foreach ($yearCatalog as $i => $yearRow) {
    if ($i >= count($yearCatalog) - 1) {
        break;
    }
    $currentYearKey = (string) $yearRow['key'];
    $realmNextYearKey = (string) $yearCatalog[$i + 1]['key'];
    if (isset($yearParticipated[$realmNextYearKey])) {
        continue;
    }
    $filteredYearNext = null;
    for ($j = $i + 1; $j < count($yearCatalog); $j++) {
        $candidateYearKey = (string) $yearCatalog[$j]['key'];
        if (isset($yearParticipated[$candidateYearKey])) {
            $filteredYearNext = $candidateYearKey;
            break;
        }
    }
    if ($filteredYearNext === null || !isset($yearParticipated[$currentYearKey])) {
        continue;
    }
    $yearTestKey = $currentYearKey;
    $yearExpectedNext = $filteredYearNext;
    break;
}
if ($yearTestKey === null || $yearExpectedNext === null) {
    fwrite(STDERR, "could not find as_with stepping fixture in year catalog\n");
    exit(1);
}
$_GET = ['as' => 'year:' . $yearTestKey, 'as_with' => (string) $asWithPlayerId];
amiga_snapshot_context_reset();
$ctxAsWithYear = amiga_snapshot_context_from_request($con);
if ($ctxAsWithYear->nextKey() !== $yearExpectedNext) {
    fwrite(STDERR, "as_with year next expected {$yearExpectedNext}, got " . ($ctxAsWithYear->nextKey() ?? 'null') . "\n");
    exit(1);
}
$yearOffKey = null;
foreach ($yearCatalog as $yearRow) {
    $key = (string) $yearRow['key'];
    if (!isset($yearParticipated[$key])) {
        $yearOffKey = $key;
        break;
    }
}
if ($yearOffKey === null) {
    fwrite(STDERR, "need off-filter year for as_with snap\n");
    exit(1);
}
$yearSnap = k2_participation_snap_target_key($yearCatalog, $yearOffKey, $yearParticipated);
if ($yearSnap === null) {
    fwrite(STDERR, "year snap target missing for off-filter year {$yearOffKey}\n");
    exit(1);
}
echo "as_with_year_stepping_ok key={$yearTestKey} next={$yearExpectedNext} snap={$yearSnap}\n";

$monthCatalog = amiga_rating_history_catalog_month($con);
$monthParticipated = amiga_player_participated_wing_key_set($con, $asWithPlayerId, 'month');
$monthTestKey = null;
$monthExpectedNext = null;
foreach ($monthCatalog as $i => $monthRow) {
    if ($i >= count($monthCatalog) - 1) {
        break;
    }
    $currentMonthKey = (string) $monthRow['key'];
    $realmNextMonthKey = (string) $monthCatalog[$i + 1]['key'];
    if (isset($monthParticipated[$realmNextMonthKey])) {
        continue;
    }
    $filteredMonthNext = null;
    for ($j = $i + 1; $j < count($monthCatalog); $j++) {
        $candidateMonthKey = (string) $monthCatalog[$j]['key'];
        if (isset($monthParticipated[$candidateMonthKey])) {
            $filteredMonthNext = $candidateMonthKey;
            break;
        }
    }
    if ($filteredMonthNext === null || !isset($monthParticipated[$currentMonthKey])) {
        continue;
    }
    $monthTestKey = $currentMonthKey;
    $monthExpectedNext = $filteredMonthNext;
    break;
}
if ($monthTestKey === null || $monthExpectedNext === null) {
    fwrite(STDERR, "could not find as_with stepping fixture in month catalog\n");
    exit(1);
}
$_GET = ['as' => 'month:' . $monthTestKey, 'as_with' => (string) $asWithPlayerId];
amiga_snapshot_context_reset();
$ctxAsWithMonth = amiga_snapshot_context_from_request($con);
if ($ctxAsWithMonth->nextKey() !== $monthExpectedNext) {
    fwrite(STDERR, "as_with month next expected {$monthExpectedNext}, got " . ($ctxAsWithMonth->nextKey() ?? 'null') . "\n");
    exit(1);
}
echo "as_with_month_stepping_ok key={$monthTestKey} next={$monthExpectedNext}\n";

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
$_GET['as_with'] = '73';
$asWithHref = amiga_url_with_context('/amiga/leaderboards/rating.php');
if (!str_contains($asWithHref, 'as_with=73')) {
    fwrite(STDERR, "as_with propagation fail: {$asWithHref}\n");
    exit(1);
}
$presentHref = amiga_url_present('/amiga/leaderboards/rating.php');
if (str_contains($presentHref, 'as_with=')) {
    fwrite(STDERR, "amiga_url_present should strip as_with: {$presentHref}\n");
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
