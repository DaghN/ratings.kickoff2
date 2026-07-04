<?php
declare(strict_types=1);
require_once __DIR__ . '/../../site/public_html/includes/amiga_db.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_tournament_videos_lib.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_player_tournament_lib.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_tournament_lib.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_tournament_step_catalog.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_snapshot_context.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

function ms(float $t0): float { return round((microtime(true) - $t0) * 1000, 1); }

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

echo "=== Track H lib probes ===\n";
$t0 = microtime(true);
amiga_rated_game_load($con, 27418);
echo 'game_load: ' . ms($t0) . " ms\n";

$rows = amiga_tournament_videos_for_id(589);
[$matchRows, $extrasRows] = amiga_tournament_videos_partition($rows);
$t0 = microtime(true);
amiga_tournament_videos_wc_game_index($con, 589, $matchRows);
echo 'wc_game_index x1: ' . ms($t0) . " ms\n";
$t0 = microtime(true);
amiga_tournament_videos_wc_game_index($con, 589, $matchRows);
echo 'wc_game_index x2: ' . ms($t0) . " ms\n";

$t0 = microtime(true);
amiga_player_tournament_participation_all($con, 382);
echo 'player_tournaments present: ' . ms($t0) . " ms\n";

foreach (['year:2024', 'month:2014-07'] as $as) {
    $_GET['as'] = $as;
    amiga_snapshot_context_reset();
    amiga_snapshot_context_from_request($con);
    $t0 = microtime(true);
    amiga_player_tournament_participation_all($con, 382);
    echo "player_tournaments {$as}: " . ms($t0) . " ms\n";
}

function bootstrap_videos(mysqli $con, int $id): float {
    $tAll = microtime(true);
    amiga_tournament_load($con, $id);
    amiga_tournament_list_league_labeled_scopes($con, $id);
    amiga_tournament_list_scopes($con, $id, 'knockout');
    amiga_tournament_standings_rows($con, $id, 'league', '');
    amiga_tournament_standings_rows($con, $id, 'league', '');
    amiga_tournament_participation_rows($con, $id);
    amiga_tournament_game_count($con, $id);
    amiga_tournament_winner($con, $id);
    $rows = amiga_tournament_videos_for_id($id);
    [$m, $e] = amiga_tournament_videos_partition($rows);
    amiga_tournament_videos_wc_game_index($con, $id, $m);
    amiga_tournament_videos_wc_game_index($con, $id, $m);
    amiga_tournament_step_catalog($con);
    amiga_tournament_step_player_choices($con);
    return ms($tAll);
}

echo "\n=== videos bootstrap 589 (simulates duplicate wc_game_index) ===\n";
foreach (['present' => '', 'year:2024' => 'year:2024'] as $label => $as) {
    amiga_snapshot_context_reset();
    if ($as !== '') { $_GET['as'] = $as; amiga_snapshot_context_from_request($con); }
    echo "  {$label}: " . bootstrap_videos($con, 589) . " ms\n";
}

$con->close();
require_once __DIR__ . '/../../site/public_html/includes/amiga_participation_step_lib.php';

echo "\n=== step nav helpers ===\n";
$_GET = []; amiga_snapshot_context_reset();
$t0 = microtime(true);
amiga_participation_eligible_players($con);
echo 'eligible_players present: ' . ms($t0) . " ms\n";

$_GET['as'] = 'year:2024';
amiga_snapshot_context_reset();
amiga_snapshot_context_from_request($con);
$t0 = microtime(true);
amiga_tournament_step_catalog($con);
echo 'step_catalog year:2024: ' . ms($t0) . " ms\n";
$t0 = microtime(true);
amiga_tournament_step_player_choices($con);
echo 'step_player_choices year:2024: ' . ms($t0) . " ms\n";