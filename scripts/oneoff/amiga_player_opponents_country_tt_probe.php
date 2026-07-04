<?php
declare(strict_types=1);

require __DIR__ . '/../../site/public_html/includes/amiga_lb_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_player_opponents_country_load.php';
require __DIR__ . '/../../site/public_html/includes/amiga_player_opponents_country_h2h.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) { fwrite(STDERR, "connect fail\n"); exit(1); }
$con->set_charset('utf8mb4');

function ms(callable $fn): float
{
    $t0 = hrtime(true);
    $fn();
    return (hrtime(true) - $t0) / 1e6;
}

$playerId = 382;
$country = 'Italy';
$cutoffs = ['present' => '', 'event:22' => 'event:22', 'event:589' => 'event:589', 'month:2014-07' => 'month:2014-07', 'month:2025-09' => 'month:2025-09', 'year:2024' => 'year:2024'];

echo "player=$playerId country=$country\n";
foreach ($cutoffs as $label => $as) {
    if ($as !== '') { $_GET['as'] = $as; } else { unset($_GET['as']); }
    $GLOBALS['_amiga_snapshot_context'] = null;
    $ctx = amiga_lb_context($con);

    $matchup = ms(static function () use ($con, $playerId, $ctx): void {
        amiga_player_opponents_matchup_rows($con, $playerId, $ctx);
    });
    $rowsNoPerf = ms(static function () use ($con, $playerId, $ctx): void {
        amiga_player_opponents_country_rows($con, $playerId, $ctx, false);
    });
    $rowsPerf = ms(static function () use ($con, $playerId, $ctx): void {
        amiga_player_opponents_country_rows($con, $playerId, $ctx, true);
    });
    $h2hPanel = ms(static function () use ($con, $playerId, $country, $ctx): void {
        amiga_player_opponents_country_rows($con, $playerId, $ctx, false);
        $bucket = amiga_player_opponents_country_bucket_from_rows(
            amiga_player_opponents_country_rows($con, $playerId, $ctx, false),
            $country
        );
        if ($bucket !== null) {
            amiga_player_opponents_country_attach_perf_to_bucket($bucket, $con, $playerId, $ctx);
            amiga_player_h2h_country_games_rows($con, $playerId, $country, $ctx);
        }
    });
    $games = ms(static function () use ($con, $playerId, $country, $ctx): void {
        amiga_player_h2h_country_games_rows($con, $playerId, $country, $ctx);
    });

    printf("%-14s matchup=%6.1f rows=%6.1f wdl=%6.1f h2h=%6.1f games=%6.1f\n", $label, $matchup, $rowsNoPerf, $rowsPerf, $h2hPanel, $games);
}