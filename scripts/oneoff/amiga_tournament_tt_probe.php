<?php
declare(strict_types=1);
/**
 * Track D — tournament entity + catalog TT probe + curl census.
 * Usage: php scripts/oneoff/amiga_tournament_tt_probe.php [--base=http://ratingskickoff.test]
 */
$base = 'http://ratingskickoff.test';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--base=')) {
        $base = rtrim(substr($arg, 7), '/');
    }
}

require_once __DIR__ . '/../../site/public_html/includes/amiga_rating_history_lib.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_tournament_lib.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_player_tournament_lib.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_tournament_bracket.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_tournament_step_catalog.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_tournament_step_href.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

function ms(float $t0): float { return round((microtime(true) - $t0) * 1000, 1); }

function bootstrap_tournament(mysqli $con, int $id, string $pageView): float
{
    $tAll = microtime(true);
    $tournament = amiga_tournament_load($con, $id);
    if ($tournament === null) {
        return ms($tAll);
    }
    $scopeType = 'league';
    $scopeKey = '';
    $knockoutScopes = amiga_tournament_list_scopes($con, $id, 'knockout');
    amiga_tournament_list_league_labeled_scopes($con, $id);
    amiga_tournament_standings_rows($con, $id, 'league', '');
    amiga_tournament_standings_rows($con, $id, $scopeType, $scopeKey);
    $isKnockoutView = $scopeType === 'knockout';
    if ($knockoutScopes !== [] && $isKnockoutView) {
        amiga_tournament_knockout_bracket_data($con, $id, $knockoutScopes);
    }
    amiga_tournament_participation_rows($con, $id);
    amiga_tournament_game_count($con, $id);
    amiga_tournament_winner($con, $id);
    if ($pageView === 'games') {
        amiga_tournament_games_rows($con, $id, 0);
    }
    amiga_tournament_step_catalog($con);
    amiga_tournament_step_player_choices($con);
    amiga_tournament_step_country_choices($con);

    return ms($tAll);
}

$ordinaryId = 589;
$wcId = 603;
$cutoffs = ['present' => '', 'year:2024' => 'year:2024', 'month:2014-07' => 'month:2014-07'];
$libFixtures = [
    ['id' => $ordinaryId, 'view' => 'event-stats'],
    ['id' => $ordinaryId, 'view' => 'games'],
    ['id' => $ordinaryId, 'view' => 'stages'],
    ['id' => $wcId, 'view' => 'stages'],
];

echo "=== Lib bootstrap (shared tournament stack) ===\n";
$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");
foreach ($cutoffs as $label => $as) {
    echo "-- {$label} --\n";
    amiga_snapshot_context_reset();
    if ($as !== '') {
        $_GET['as'] = $as;
        amiga_snapshot_context_from_request($con);
    }
    foreach ($libFixtures as $fx) {
        amiga_snapshot_context_reset();
        if ($as !== '') {
            $_GET['as'] = $as;
            amiga_snapshot_context_from_request($con);
        }
        echo '  ' . $fx['view'] . ' id=' . $fx['id'] . ': ' . bootstrap_tournament($con, $fx['id'], $fx['view']) . " ms\n";
    }
    amiga_snapshot_context_reset();
    if ($as !== '') {
        $_GET['as'] = $as;
        amiga_snapshot_context_from_request($con);
    }
    $t0 = microtime(true);
    $rows = amiga_tournament_index_rows($con, 0, 0);
    echo '  catalog rows: ' . ms($t0) . ' ms (' . count($rows) . ")\n";
}
$con->close();

echo "\n=== Curl census (5 Track D URLs) ===\n";
$paths = [
    '/amiga/tournament/event-stats.php?id=' . $ordinaryId,
    '/amiga/tournament/games.php?id=' . $ordinaryId,
    '/amiga/tournament/stages.php?id=' . $ordinaryId,
    '/amiga/tournament/stages.php?id=' . $wcId,
    '/amiga/tournaments.php',
];
$flagThreshold = 0.8;
foreach ($paths as $path) {
    foreach ($cutoffs as $label => $as) {
        $url = $base . $path;
        if ($as !== '') {
            $url .= (str_contains($path, '?') ? '&' : '?') . 'as=' . rawurlencode($as);
        }
        $t0 = microtime(true);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $elapsed = round(microtime(true) - $t0, 3);
        $tr = is_string($body) ? substr_count($body, '<tr') : 0;
        $err = is_string($body) && preg_match('/\b(Warning|Fatal error|Deprecated):/i', $body) === 1;
        $flag = $elapsed > $flagThreshold ? ' FLAG' : '';
        echo sprintf("  %6.3fs %s tr=%4d %s%s\n", $elapsed, $label, $tr, $path, $flag);
        if ($status !== 200) {
            echo "    HTTP {$status}\n";
        }
        if ($err) {
            echo "    PHP error in body\n";
        }
    }
}