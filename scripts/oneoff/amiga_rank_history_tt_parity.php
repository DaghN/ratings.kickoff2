<?php
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2) . '/site/public_html';
$_GET['as'] = 'year:2003';
require $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2amiga_config.php';
require $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_snapshot_context.php';
require $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_rank_history_lib.php';
require $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_load.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');
$ctx = amiga_snapshot_context_from_request($con);

foreach ([237, 109, 84] as $pid) {
    $payload = amiga_player_rank_history_payload($con, $pid, $ctx);
    $pm = amiga_player_load($con, $pid, $ctx);
    $heroRank = amiga_player_normalize_elo_rank($pm['rank'] ?? null);
    $points = $payload['points'] ?? [];
    $lastRank = $points !== [] ? (int) end($points)['eloRank'] : null;
    $match = ($heroRank === $lastRank) ? 'OK' : 'MISMATCH';
    echo "TT2003 player $pid points=".count($points)." hero=".($heroRank??'null')." last=".($lastRank??'null')." $match\n";
}

unset($_GET['as']);
$GLOBALS['_amiga_snapshot_context'] = null;
foreach ([109, 84] as $pid) {
    $ctxP = amiga_snapshot_context_from_request($con);
    $payload = amiga_player_rank_history_payload($con, $pid, $ctxP);
    $pm = amiga_player_load($con, $pid, $ctxP);
    $heroRank = amiga_player_normalize_elo_rank($pm['rank'] ?? null);
    $points = $payload['points'] ?? [];
    $lastRank = $points !== [] ? (int) end($points)['eloRank'] : null;
    $match = ($heroRank === $lastRank) ? 'OK' : 'MISMATCH';
    echo "present player $pid hero=$heroRank last=$lastRank $match\n";
}

mysqli_close($con);