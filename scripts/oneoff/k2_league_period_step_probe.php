<?php
declare(strict_types=1);

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../site/public_html') ?: '';

require __DIR__ . '/../../site/public_html/includes/k2_league_period_page.php';
require_once __DIR__ . '/../../site/public_html/includes/k2_league_period_with_player.php';
require_once __DIR__ . '/../../site/public_html/includes/k2_start_with_url.php';
include __DIR__ . '/../../site/config/ko2unitydb_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    fwrite(STDERR, "connect fail\n");
    exit(1);
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

$clock = k2_status_server_clock($con);
$serverNow = $clock['now'];
$period = 'month';
$catalog = k2_league_period_step_catalog($con, $period, $serverNow);
if (count($catalog) < 2) {
    fwrite(STDERR, "need 2+ month periods in catalog\n");
    exit(1);
}
echo 'catalog_ok count=' . count($catalog) . PHP_EOL;

$playerId = 0;
$participated = [];
$res = $con->query(
    "SELECT player_id, COUNT(*) AS c FROM player_period_games "
    . "WHERE period_type = 'month' AND games > 0 GROUP BY player_id HAVING c >= 2 ORDER BY c DESC LIMIT 1"
);
if ($res) {
    $row = $res->fetch_assoc();
    if ($row) {
        $playerId = (int) ($row['player_id'] ?? 0);
    }
    $res->free();
}
if ($playerId < 1) {
    fwrite(STDERR, "need player with 2+ month periods\n");
    exit(1);
}
$participated = k2_league_period_player_participated_starts($con, $playerId, $period);
if (count($participated) < 2) {
    fwrite(STDERR, "player {$playerId} needs 2+ month periods\n");
    exit(1);
}

$lookup = array_fill_keys($participated, true);
$testStart = null;
$expectedNext = null;
$realmNext = null;
foreach ($catalog as $i => $entry) {
    if ($i >= count($catalog) - 1) {
        break;
    }
    $currentStart = (string) $entry['key'];
    $rn = (string) $catalog[$i + 1]['key'];
    if (isset($lookup[$rn])) {
        continue;
    }
    $fn = null;
    for ($j = $i + 1; $j < count($catalog); $j++) {
        $candidate = (string) $catalog[$j]['key'];
        if (isset($lookup[$candidate])) {
            $fn = $candidate;
            break;
        }
    }
    if ($fn !== null && isset($lookup[$currentStart])) {
        $testStart = $currentStart;
        $expectedNext = $fn;
        $realmNext = $rn;
        break;
    }
}
if ($testStart === null) {
    fwrite(STDERR, "no start_with fixture\n");
    exit(1);
}

$_GET = [
    'cup' => 'points',
    'period' => $period,
    'start' => $testStart,
    'start_with' => (string) $playerId,
];
$filtered = k2_league_period_with_player_adjacent_starts($con, $period, $testStart, $playerId, $serverNow);
if (($filtered['next'] ?? null) !== $expectedNext) {
    fwrite(STDERR, 'start_with next mismatch got=' . ($filtered['next'] ?? 'null') . ' want=' . $expectedNext . PHP_EOL);
    exit(1);
}
echo 'start_with_nearest_neighbor_ok start=' . $testStart . ' skip=' . $realmNext . ' next=' . $expectedNext . PHP_EOL;

$unfiltered = k2_league_period_adjacent_starts($con, $period, $testStart, $serverNow);
if (($unfiltered['next'] ?? null) !== $realmNext) {
    fwrite(STDERR, 'unfiltered next mismatch' . PHP_EOL);
    exit(1);
}

$params = k2_start_with_append_to_query([
    'cup' => 'points',
    'period' => $period,
    'start' => $testStart,
]);
if ((int) ($params['start_with'] ?? 0) !== $playerId) {
    fwrite(STDERR, "start_with append fail\n");
    exit(1);
}
echo "league_period_step_ok\n";