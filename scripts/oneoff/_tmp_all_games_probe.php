<?php
declare(strict_types=1);
$_SERVER['DOCUMENT_ROOT'] = 'C:/Users/daghn/Desktop/Online and Amiga 500 ELO/site/public_html';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2amiga_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_realm_games_all.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$ctx = amiga_lb_context($con);

function probe(array $get, mysqli $con, AmigaSnapshotContext $ctx): void {
    $_GET = $get;
    $state = amiga_realm_games_all_request_state();
    $total = amiga_realm_games_all_count($con, $state, $ctx);
    $page = amiga_realm_games_all_fetch_page($con, $state, $ctx, 250);
    echo json_encode($get) . " => total=$total page=" . count($page) . "\n";
}

probe([], $con, $ctx);
probe(['country' => 'Germany', 'rival' => 'England'], $con, $ctx);
probe(['country' => 'Unknown', 'rival' => 'Germany'], $con, $ctx);
mysqli_close($con);