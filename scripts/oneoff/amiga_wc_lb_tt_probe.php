<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2) . '/site/public_html';
require $root . '/includes/k2_safety.php';
require $root . '/includes/amiga_lb_lib.php';
require $root . '/includes/amiga_wc_lb_lib.php';
include dirname(__DIR__, 2) . '/site/config/ko2amiga_config.php';

$asParam = $argv[1] ?? 'year:2024';
$_GET['as'] = $asParam;

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$ctx = amiga_lb_context($con);

echo "as=$asParam active=" . ($ctx->isActive() ? '1' : '0') . PHP_EOL;
$c = $ctx->cutoff();
echo 'cutoff=' . json_encode($c) . PHP_EOL;

$sliceN = count(amiga_lb_wc_slice_rows_at_cutoff($con, $ctx));
$honoursN = count(amiga_wc_honours_leaderboard_rows($con, $ctx));
echo "slice=$sliceN honours=$honoursN" . PHP_EOL;

if ($stmt = $con->prepare('SELECT COUNT(*) AS n FROM amiga_player_slice_at_event WHERE slice_key = ?')) {
    $k = 'world_cup';
    $stmt->bind_param('s', $k);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    echo 'at_event_total=' . ($row['n'] ?? '?') . PHP_EOL;
}

mysqli_close($con);
