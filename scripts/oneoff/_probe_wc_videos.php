<?php
declare(strict_types=1);
chdir(dirname(__DIR__, 2) . '/site/public_html');
$_SERVER['DOCUMENT_ROOT'] = getcwd();
require_once 'includes/amiga_tournament_videos_lib.php';
require_once 'includes/amiga/config.php';
$cfg = load_amiga_db_config();
$con = mysqli_connect($cfg->host, $cfg->user, $cfg->password, $cfg->database, $cfg->port);
if (!$con) { fwrite(STDERR, "db fail\n"); exit(1); }
$rows = amiga_tournament_videos_for_id(25);
[$m, $e] = amiga_tournament_videos_partition($rows);
$entries = amiga_tournament_videos_wc_game_index($con, 25, $m);
echo 'games=' . count($entries) . ' extras=' . count($e) . PHP_EOL;
foreach ($entries as $x) {
    echo $x['sort_bucket'] . ' game=' . $x['game_id'] . ' yt=' . $x['youtube_id'] . PHP_EOL;
}
$spot = amiga_tournament_videos_wc_default_game_spotlight($entries);
echo 'default game=' . ($spot['game_id'] ?? '?') . PHP_EOL;
mysqli_close($con);