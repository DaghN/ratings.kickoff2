<?php
declare(strict_types=1);

require __DIR__ . '/../../site/public_html/includes/amiga_lb_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_player_opponents_load.php';
require __DIR__ . '/../../site/public_html/includes/amiga_player_opponents_h2h.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) { fwrite(STDERR, "connect fail\n"); exit(1); }
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

function ms(callable $fn): float
{
    $t0 = hrtime(true);
    $fn();
    return (hrtime(true) - $t0) / 1e6;
}

$playerId = 382;
$opponentId = 398;
$cutoffs = [
    'present' => '',
    'event:22' => 'event:22',
    'event:589' => 'event:589',
    'month:2014-07' => 'month:2014-07',
    'month:2025-09' => 'month:2025-09',
    'year:2024' => 'year:2024',
];

echo "player=$playerId opponent=$opponentId\n";
foreach ($cutoffs as $label => $as) {
    if ($as !== '') { $_GET['as'] = $as; } else { unset($_GET['as']); }
    $GLOBALS['_amiga_snapshot_context'] = null;
    $ctx = amiga_lb_context($con);

    $matchup = ms(static function () use ($con, $playerId, $ctx): void {
        amiga_player_opponents_matchup_rows($con, $playerId, $ctx);
    });
    $matchup2 = ms(static function () use ($con, $playerId, $ctx): void {
        amiga_player_opponents_matchup_rows($con, $playerId, $ctx);
    });
    $directed = ms(static function () use ($con, $playerId, $opponentId, $ctx): void {
        amiga_player_matchup_directed_opponent_row($con, $playerId, $opponentId, $ctx);
    });
    $h2hPanel = ms(static function () use ($con, $playerId, $opponentId, $ctx): void {
        $rows = amiga_player_opponents_matchup_rows($con, $playerId, $ctx);
        $pair = amiga_player_opponents_h2h_resolve_opponent_from_rows($con, $playerId, $opponentId, $rows);
        if ($pair !== null) {
            $pairRow = amiga_player_opponents_matchup_row_from_rows($rows, $opponentId);
            if ($pairRow !== null) {
                amiga_player_opponents_h2h_pair_record_from_row($pairRow);
                amiga_player_opponents_h2h_pair_detail_from_row($con, $pairRow, $playerId, $opponentId, $ctx);
                amiga_player_h2h_pair_games_rows($con, $playerId, $opponentId, $ctx);
            }
        }
    });
    $games = ms(static function () use ($con, $playerId, $opponentId, $ctx): void {
        amiga_player_h2h_pair_games_rows($con, $playerId, $opponentId, $ctx);
    });
    $chartPayload = ms(static function () use ($con, $playerId, $opponentId, $ctx): void {
        amiga_player_h2h_cumulative_payload($con, $playerId, $opponentId, $ctx);
        amiga_player_h2h_goals_scored_buckets($con, $playerId, $opponentId, $ctx);
        amiga_player_h2h_total_goals_buckets($con, $playerId, $opponentId, $ctx);
        amiga_player_h2h_scoreline_heatmap_payload($con, $playerId, $opponentId, $ctx);
    });

    printf("%-14s matchup=%6.1f x2=%6.1f directed=%6.1f h2h=%6.1f games=%6.1f charts=%6.1f\n",
        $label, $matchup, $matchup2, $directed, $h2hPanel, $games, $chartPayload);
}