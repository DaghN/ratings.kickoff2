<?php
declare(strict_types=1);

require __DIR__ . '/../../site/public_html/includes/amiga_snapshot_context.php';
require __DIR__ . '/../../site/public_html/includes/amiga_tournament_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_tournament_step_catalog.php';
require __DIR__ . '/../../site/public_html/includes/amiga_tournament_step_href.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_id_with_url.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_id_country_url.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_id_wc_url.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    fwrite(STDERR, "connect fail\n");
    exit(1);
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

$_GET = [];
$_SERVER['REQUEST_URI'] = '/amiga/tournament/event-stats.php?id=94';
amiga_snapshot_context_reset();

$bag = amiga_tournament_step_filter_bag_from_request($con);
$catalog = amiga_tournament_step_catalog($con);
if ($catalog === []) {
    fwrite(STDERR, "empty catalog\n");
    exit(1);
}
$steps = amiga_tournament_step_keys($con, $catalog, 94, $bag);
if (!$steps['in_base_catalog']) {
    fwrite(STDERR, "id 94 not in base catalog\n");
    exit(1);
}
if ($steps['prev_key'] === null && $steps['next_key'] === null && count($catalog) > 1) {
    fwrite(STDERR, "expected step keys for id 94\n");
    exit(1);
}
echo "realm_step_ok prev=" . ($steps['prev_key'] ?? 'null') . ' next=' . ($steps['next_key'] ?? 'null') . PHP_EOL;

$playerId = 73;
$participated = amiga_player_participated_event_keys($con, $playerId);
if (count($participated) < 2) {
    fwrite(STDERR, "player 73 needs 2+ events\n");
    exit(1);
}
$lookup = array_fill_keys($participated, true);
$testKey = null;
$expectedNext = null;
$realmNext = null;
foreach ($catalog as $i => $entry) {
    if ($i >= count($catalog) - 1) {
        break;
    }
    $currentKey = (string) $entry['key'];
    $rn = (string) $catalog[$i + 1]['key'];
    if (isset($lookup[$rn])) {
        continue;
    }
    $fn = null;
    for ($j = $i + 1; $j < count($catalog); $j++) {
        $c = (string) $catalog[$j]['key'];
        if (isset($lookup[$c])) {
            $fn = $c;
            break;
        }
    }
    if ($fn !== null && isset($lookup[$currentKey])) {
        $testKey = (int) $currentKey;
        $expectedNext = $fn;
        $realmNext = $rn;
        break;
    }
}
if ($testKey === null) {
    fwrite(STDERR, "no id_with fixture\n");
    exit(1);
}

$bagFiltered = ['player_id' => $playerId];
$stepsFiltered = amiga_tournament_step_keys($con, $catalog, $testKey, $bagFiltered);
if ($stepsFiltered['next_key'] !== $expectedNext) {
    fwrite(STDERR, "id_with next expected {$expectedNext}, got " . ($stepsFiltered['next_key'] ?? 'null') . "\n");
    exit(1);
}
$stepsRealm = amiga_tournament_step_keys($con, $catalog, $testKey, ['player_id' => null]);
if ($stepsRealm['next_key'] !== $realmNext) {
    fwrite(STDERR, "realm next at gap expected {$realmNext}\n");
    exit(1);
}

$offFilterId = null;
foreach ($catalog as $entry) {
    $key = (string) $entry['key'];
    if (!isset($lookup[$key])) {
        $offFilterId = (int) $key;
        break;
    }
}
if ($offFilterId === null) {
    fwrite(STDERR, "need a base-catalog tournament player 73 did not play\n");
    exit(1);
}
$stepsOffFilter = amiga_tournament_step_keys($con, $catalog, $offFilterId, $bagFiltered);
$eligible = amiga_tournament_step_eligible_key_set($con, $catalog, $bagFiltered);
$expectedOff = k2_participation_step_keys($catalog, (string) $offFilterId, $eligible);
if ($stepsOffFilter['next_key'] !== $expectedOff['next_key']
    || $stepsOffFilter['prev_key'] !== $expectedOff['prev_key']) {
    fwrite(STDERR, "off-filter tournament should step to nearest eligible neighbor\n");
    exit(1);
}
if ($stepsOffFilter['next_key'] === null && $stepsOffFilter['prev_key'] === null) {
    fwrite(STDERR, "off-filter tournament expected at least one chevron when eligible exists\n");
    exit(1);
}
$expectedSnap = $stepsOffFilter['prev_key'] ?? $stepsOffFilter['next_key'];
$snapTarget = amiga_tournament_step_snap_target_key($con, $catalog, $offFilterId, $bagFiltered);
if ($snapTarget !== $expectedSnap) {
    fwrite(STDERR, "snap expected {$expectedSnap}, got " . ($snapTarget ?? 'null') . "\n");
    exit(1);
}
$snapOnFilter = amiga_tournament_step_snap_target_key($con, $catalog, $testKey, $bagFiltered);
if ($snapOnFilter !== null) {
    fwrite(STDERR, "on-filter tournament should not snap\n");
    exit(1);
}
echo "id_with_filter_snap_ok id={$offFilterId} snap={$expectedSnap}\n";
echo "id_with_nearest_neighbor_ok id={$offFilterId}\n";

$stepsUnknown = amiga_tournament_step_keys($con, $catalog, 999999, $bagFiltered);
if ($stepsUnknown['prev_key'] !== null || $stepsUnknown['next_key'] !== null) {
    fwrite(STDERR, "unknown id should disable chevrons\n");
    exit(1);
}
echo "unknown_id_ok\n";

$_GET = ['id' => '94', 'id_with' => (string) $playerId];
$_SERVER['REQUEST_URI'] = '/amiga/tournament/event-stats.php?id=94&id_with=' . $playerId;
$href = amiga_tournament_href(amiga_tournament_games_url(95));
if (!str_contains($href, 'id_with=73')) {
    fwrite(STDERR, "id_with propagation fail: {$href}\n");
    exit(1);
}
if (!str_contains($href, 'id=95')) {
    fwrite(STDERR, "href missing target id: {$href}\n");
    exit(1);
}
echo "id_with_propagation_ok\n";

$rowsById = amiga_tournament_step_row_by_id($con);
$countryFixture = null;
$countryNextRealm = null;
$countryNextFiltered = null;
foreach ($catalog as $i => $entry) {
    if ($i >= count($catalog) - 1) {
        break;
    }
    $currentKey = (string) $entry['key'];
    $row = $rowsById[(int) $currentKey] ?? null;
    if ($row === null) {
        continue;
    }
    $hostCountry = trim((string) ($row['country'] ?? ''));
    if ($hostCountry === '') {
        continue;
    }
    $realmNext = (string) $catalog[$i + 1]['key'];
    $nextSameCountry = null;
    for ($j = $i + 1; $j < count($catalog); $j++) {
        $candidateKey = (string) $catalog[$j]['key'];
        $candidateRow = $rowsById[(int) $candidateKey] ?? null;
        if ($candidateRow !== null
            && amiga_tournament_index_matches_country_filter($candidateRow, $hostCountry)) {
            $nextSameCountry = $candidateKey;
            break;
        }
    }
    if ($nextSameCountry !== null && $nextSameCountry !== $realmNext) {
        $countryFixture = (int) $currentKey;
        $countryNextRealm = $realmNext;
        $countryNextFiltered = $nextSameCountry;
        break;
    }
}
if ($countryFixture === null) {
    fwrite(STDERR, "no id_country fixture\n");
    exit(1);
}
$countryBag = ['player_id' => null, 'country' => trim((string) ($rowsById[$countryFixture]['country'] ?? ''))];
$stepsCountry = amiga_tournament_step_keys($con, $catalog, $countryFixture, $countryBag);
if ($stepsCountry['next_key'] !== $countryNextFiltered) {
    fwrite(STDERR, "id_country next expected {$countryNextFiltered}, got " . ($stepsCountry['next_key'] ?? 'null') . "\n");
    exit(1);
}
$stepsCountryRealm = amiga_tournament_step_keys($con, $catalog, $countryFixture, ['player_id' => null, 'country' => null]);
if ($stepsCountryRealm['next_key'] !== $countryNextRealm) {
    fwrite(STDERR, "realm next at country gap expected {$countryNextRealm}\n");
    exit(1);
}
$countryOffFilterId = null;
foreach ($catalog as $entry) {
    $key = (int) $entry['key'];
    $row = $rowsById[$key] ?? null;
    if ($row === null) {
        continue;
    }
    if (!amiga_tournament_index_matches_country_filter($row, $countryBag['country'])) {
        $countryOffFilterId = $key;
        break;
    }
}
if ($countryOffFilterId === null) {
    fwrite(STDERR, "need off-filter country snap fixture\n");
    exit(1);
}
$stepsCountryOff = amiga_tournament_step_keys($con, $catalog, $countryOffFilterId, $countryBag);
$expectedCountrySnap = $stepsCountryOff['prev_key'] ?? $stepsCountryOff['next_key'];
$countrySnap = amiga_tournament_step_snap_target_key($con, $catalog, $countryOffFilterId, $countryBag);
if ($countrySnap !== $expectedCountrySnap) {
    fwrite(STDERR, "country snap expected {$expectedCountrySnap}, got " . ($countrySnap ?? 'null') . "\n");
    exit(1);
}
echo "id_country_filter_snap_ok id={$countryOffFilterId} snap={$expectedCountrySnap}\n";
echo "id_country_nearest_neighbor_ok id={$countryFixture}\n";

$_GET = ['id' => '94', 'id_country' => $countryBag['country']];
$_SERVER['REQUEST_URI'] = '/amiga/tournament/event-stats.php?id=94&id_country=' . rawurlencode((string) $countryBag['country']);
$hrefCountry = amiga_tournament_href(amiga_tournament_games_url(95));
if (!str_contains($hrefCountry, 'id_country=')) {
    fwrite(STDERR, "id_country propagation fail: {$hrefCountry}\n");
    exit(1);
}
echo "id_country_propagation_ok\n";

$wcFixture = null;
$wcNextRealm = null;
$wcNextFiltered = null;
foreach ($catalog as $i => $entry) {
    if ($i >= count($catalog) - 1) {
        break;
    }
    $currentKey = (string) $entry['key'];
    $row = $rowsById[(int) $currentKey] ?? null;
    if ($row === null || !amiga_tournament_is_world_cup($row)) {
        continue;
    }
    $realmNext = (string) $catalog[$i + 1]['key'];
    $nextWc = null;
    for ($j = $i + 1; $j < count($catalog); $j++) {
        $candidateKey = (string) $catalog[$j]['key'];
        $candidateRow = $rowsById[(int) $candidateKey] ?? null;
        if ($candidateRow !== null && amiga_tournament_is_world_cup($candidateRow)) {
            $nextWc = $candidateKey;
            break;
        }
    }
    if ($nextWc !== null && $nextWc !== $realmNext) {
        $wcFixture = (int) $currentKey;
        $wcNextRealm = $realmNext;
        $wcNextFiltered = $nextWc;
        break;
    }
}
if ($wcFixture === null) {
    fwrite(STDERR, "no id_wc fixture\n");
    exit(1);
}
$wcBag = ['player_id' => null, 'country' => null, 'wc_only' => true];
$stepsWc = amiga_tournament_step_keys($con, $catalog, $wcFixture, $wcBag);
if ($stepsWc['next_key'] !== $wcNextFiltered) {
    fwrite(STDERR, "id_wc next expected {$wcNextFiltered}, got " . ($stepsWc['next_key'] ?? 'null') . "\n");
    exit(1);
}
$stepsWcRealm = amiga_tournament_step_keys($con, $catalog, $wcFixture, ['player_id' => null, 'country' => null, 'wc_only' => false]);
if ($stepsWcRealm['next_key'] !== $wcNextRealm) {
    fwrite(STDERR, "realm next at wc gap expected {$wcNextRealm}\n");
    exit(1);
}
$wcOffFilterId = null;
foreach ($catalog as $entry) {
    $key = (int) $entry['key'];
    $row = $rowsById[$key] ?? null;
    if ($row === null) {
        continue;
    }
    if (!amiga_tournament_is_world_cup($row)) {
        $wcOffFilterId = $key;
        break;
    }
}
if ($wcOffFilterId === null) {
    fwrite(STDERR, "need off-filter wc snap fixture\n");
    exit(1);
}
$stepsWcOff = amiga_tournament_step_keys($con, $catalog, $wcOffFilterId, $wcBag);
$expectedWcSnap = $stepsWcOff['prev_key'] ?? $stepsWcOff['next_key'];
$wcSnap = amiga_tournament_step_snap_target_key($con, $catalog, $wcOffFilterId, $wcBag);
if ($wcSnap !== $expectedWcSnap) {
    fwrite(STDERR, "wc snap expected {$expectedWcSnap}, got " . ($wcSnap ?? 'null') . "\n");
    exit(1);
}
echo "id_wc_filter_snap_ok id={$wcOffFilterId} snap={$expectedWcSnap}\n";
echo "id_wc_nearest_neighbor_ok id={$wcFixture}\n";

$_GET = ['id' => '94', 'id_wc' => 'world-cup'];
$_SERVER['REQUEST_URI'] = '/amiga/tournament/event-stats.php?id=94&id_wc=world-cup';
$hrefWc = amiga_tournament_href(amiga_tournament_games_url(95));
if (!str_contains($hrefWc, 'id_wc=world-cup')) {
    fwrite(STDERR, "id_wc propagation fail: {$hrefWc}\n");
    exit(1);
}
echo "id_wc_propagation_ok\n";

foreach ($catalog as $entry) {
    $tid = (int) $entry['key'];
    if ($tid > 0 && !amiga_tournament_has_videos($tid)) {
        $intentVideos = amiga_tournament_step_nav_intent_from_request('league', '', 'videos', 'games');
        $resolved = amiga_tournament_step_target_url($con, $tid, $intentVideos);
        if (!str_contains($resolved, 'event-stats.php')) {
            fwrite(STDERR, "videos fallback expected event-stats for id {$tid}: {$resolved}\n");
            exit(1);
        }
        echo "videos_fallback_ok id={$tid}\n";
        break;
    }
}

echo "amiga_tournament_step_probe_ok\n";
