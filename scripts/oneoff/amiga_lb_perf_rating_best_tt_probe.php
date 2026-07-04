<?php
declare(strict_types=1);
/** Probe: performance-rating Best wing TT query timing. */
require __DIR__ . '/../../site/public_html/includes/amiga_rating_history_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_lb_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_lb_snapshot_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_player_tournament_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

function ms(float $t0): float { return round((microtime(true) - $t0) * 1000, 1); }

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

foreach (['present', 'year:2001', 'year:2024', 'month:2014-07', 'event:589', 'month:2025-09'] as $as) {
    if ($as === 'present') {
        unset($_GET['as']);
    } else {
        $_GET['as'] = $as;
    }
    amiga_snapshot_context_reset();
    $ctx = amiga_snapshot_context_from_request($con);
    echo "=== {$as} ===\n";
    $t0 = microtime(true);
    $rows = amiga_lb_performance_rating_rows($con, $ctx);
    echo '  best rows: ' . ms($t0) . ' ms (' . count($rows) . " rows)\n";
    echo '  peak mem: ' . round(memory_get_peak_usage(true) / 1048576, 1) . " MB\n";
}
$con->close();