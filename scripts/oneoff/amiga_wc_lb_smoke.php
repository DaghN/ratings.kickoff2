<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2) . '/site/public_html';
require $root . '/includes/k2_safety.php';
require $root . '/includes/amiga_lb_lib.php';
require $root . '/includes/amiga_wc_lb_lib.php';
include dirname(__DIR__, 2) . '/site/config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$ctx = amiga_lb_context($con);
$rows = amiga_wc_honours_leaderboard_rows($con, $ctx);
$n = amiga_wc_honours_player_count($con, $ctx);
echo 'rows=' . count($rows) . ' count=' . $n . PHP_EOL;
if ($rows !== []) {
    $r = $rows[0];
    echo 'top=' . $r['player_name'] . ' gold=' . $r['wc_gold'] . ' wc_played=' . $r['wc_played'] . PHP_EOL;
}
mysqli_close($con);
