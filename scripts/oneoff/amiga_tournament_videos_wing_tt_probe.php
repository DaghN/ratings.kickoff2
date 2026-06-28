<?php
declare(strict_types=1);

$_GET = ['id' => '26', 'as' => 'year:2005'];
$_SERVER['REQUEST_URI'] = '/amiga/tournament/videos/games.php?id=26&as=year%3A2005';

require __DIR__ . '/../../site/config/ko2amiga_config.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_snapshot_context.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_tournament_lib.php';

$con = mysqli_connect($dbhost, $username, $password, $database, $dbportnum ?? 3306);
if (!$con instanceof mysqli) {
    fwrite(STDERR, "db connect failed\n");
    exit(1);
}
amiga_snapshot_context_from_request($con);

$atmosphereHref = amiga_tournament_href(amiga_tournament_videos_url(26, 'atmosphere'));
$gamesHref = amiga_tournament_href(amiga_tournament_videos_url(26, 'games'));
echo "atmosphere: {$atmosphereHref}\n";
echo "games: {$gamesHref}\n";

if (!str_contains($atmosphereHref, '/videos/atmosphere.php')) {
    fwrite(STDERR, "FAIL: atmosphere href missing folder path\n");
    exit(1);
}
if (str_contains($atmosphereHref, 'wing=')) {
    fwrite(STDERR, "FAIL: atmosphere href still uses wing query\n");
    exit(1);
}
if (!str_contains($atmosphereHref, 'as=year') && !str_contains($atmosphereHref, 'as%3Dyear')) {
    fwrite(STDERR, "FAIL: atmosphere href missing as=year\n");
    exit(1);
}
if (!str_contains($gamesHref, '/videos/games.php')) {
    fwrite(STDERR, "FAIL: games href missing folder path\n");
    exit(1);
}

$legacyMode = amiga_tournament_videos_mode_from_request('/amiga/tournament/videos/atmosphere.php');
if ($legacyMode !== 'atmosphere') {
    fwrite(STDERR, "FAIL: mode_from_request atmosphere\n");
    exit(1);
}

echo "videos_folder_modes_ok\n";