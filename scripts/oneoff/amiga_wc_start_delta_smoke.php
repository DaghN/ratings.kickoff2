<?php
$root = dirname(__DIR__, 2);
require $root . '/site/public_html/includes/k2_safety.php';
require $root . '/site/public_html/includes/amiga_lb_snapshot_lib.php';
include $root . '/site/config/ko2amiga_config.php';
$con = new mysqli($dbhost, $username, $password, $database, (int) $dbportnum);
if ($con->connect_error) {
    fwrite(STDERR, $con->connect_error);
    exit(1);
}
require_once $root . '/site/public_html/includes/amiga_rating_history_lib.php';
$wc = amiga_rating_history_last_world_cup_tournament($con);
echo 'last WC: ' . ($wc['name'] ?? 'none') . PHP_EOL;
$map = amiga_lb_wc_start_rating_delta_map($con);
echo 'delta count: ' . count($map) . PHP_EOL;
$i = 0;
foreach ($map as $pid => $d) {
    echo "  pid $pid => $d" . PHP_EOL;
    if (++$i >= 5) {
        break;
    }
}
$nonZero = array_filter($map, static fn (float $d): bool => (int) round($d) !== 0);
echo 'non-zero deltas: ' . count($nonZero) . PHP_EOL;
if ($nonZero !== []) {
    arsort($nonZero);
    $top = array_slice($nonZero, 0, 3, true);
    foreach ($top as $pid => $d) {
        echo "  top pid $pid => $d" . PHP_EOL;
    }
}