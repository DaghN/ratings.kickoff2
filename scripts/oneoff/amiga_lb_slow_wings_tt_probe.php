<?php
declare(strict_types=1);
/** Probe: tournament-honours / calendar-geo / peak-rating TT wing queries — current vs narrow shapes. */
require __DIR__ . '/../../site/public_html/includes/amiga_rating_history_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_lb_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_lb_snapshot_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_player_tournament_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

function ms(float $t0): float { return round((microtime(true) - $t0) * 1000, 1); }

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

foreach (['year:2024', 'month:2014-07', 'event:589'] as $as) {
    $_GET['as'] = $as;
    amiga_snapshot_context_reset();
    $ctx = amiga_snapshot_context_from_request($con);
    echo "=== {$as} ===\n";

    $t0 = microtime(true);
    $rows = amiga_lb_honours_rows_at_cutoff($con, $ctx);
    echo '  honours rows: ' . ms($t0) . ' ms (' . count($rows) . " rows)\n";

    $t0 = microtime(true);
    $n = amiga_lb_honours_player_count($con, $ctx);
    echo '  honours player count (2nd run of same query): ' . ms($t0) . " ms (n={$n})\n";

    $t0 = microtime(true);
    $rows2 = amiga_lb_calendar_geo_rows_at_cutoff($con, $ctx);
    echo '  calendar-geo rows: ' . ms($t0) . ' ms (' . count($rows2) . " rows)\n";

    $t0 = microtime(true);
    $res = amiga_lb_query_peak_rating($con, $ctx);
    $c = 0; while ($r = $res->fetch_assoc()) { $c++; }
    echo '  peak-rating query: ' . ms($t0) . " ms ({$c} rows)\n";

    echo '  peak mem: ' . round(memory_get_peak_usage(true) / 1048576, 1) . " MB\n";
}
$con->close();