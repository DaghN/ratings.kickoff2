<?php
declare(strict_types=1);
chdir(dirname(__DIR__, 2) . '/site/public_html');
$_SERVER['DOCUMENT_ROOT'] = getcwd();
require_once 'includes/amiga_tournament_videos_lib.php';
require_once 'includes/amiga_db.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';
require_once __DIR__ . '/../../site/public_html/includes/k2_safety.php';
$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$tid = isset($argv[1]) ? (int) $argv[1] : 25;
$wings = amiga_tournament_videos_wings_for_id($con, $tid);
$entries = $wings['game_entries'];
echo 'tid=' . $tid . ' games=' . count($entries) . ' extras=' . count($wings['extras_rows'])
    . ' games_wing=' . ($wings['has_games_wing'] ? 'Y' : 'N') . PHP_EOL;
foreach ($entries as $x) {
    echo $x['sort_bucket'] . ' game=' . $x['game_id'] . ' yt=' . $x['youtube_id'] . PHP_EOL;
}
$spot = amiga_tournament_videos_wc_default_game_spotlight($entries);
echo 'default game=' . ($spot['game_id'] ?? '?') . PHP_EOL;
mysqli_close($con);