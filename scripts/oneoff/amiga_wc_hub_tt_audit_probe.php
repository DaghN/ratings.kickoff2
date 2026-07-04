<?php
declare(strict_types=1);
/** WC hub TT audit — per-phase timings for chronology.php shell + body. */

require __DIR__ . '/../../site/public_html/includes/amiga_rating_history_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_lb_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_lb_snapshot_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_world_cup_stats_read_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_wc_lb_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_wc_countries_lb_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

function ms(float $t0): float { return round((microtime(true) - $t0) * 1000, 1); }
function timed(callable $fn): array { $t0 = microtime(true); $r = $fn(); return [$r, ms($t0)]; }

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

foreach (['amiga_player_slice_at_event', 'amiga_country_slice_at_event'] as $t) {
    $res = $con->query("SELECT COUNT(*) n FROM {$t}");
    $n = $res->fetch_assoc()['n'];
    $res = $con->query("SELECT COUNT(*) n FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '{$t}'");
    $c = $res->fetch_assoc()['n'];
    echo "{$t}: {$n} rows x {$c} cols\n";
}

foreach (['present' => null, 'event:583' => 'event:583', 'month:2025-09' => 'month:2025-09'] as $label => $as) {
    if ($as === null) { unset($_GET['as']); } else { $_GET['as'] = $as; }
    amiga_snapshot_context_reset();
    $ctx = amiga_snapshot_context_from_request($con);
    echo "=== {$label} ===\n";
    [, $t] = timed(fn () => amiga_world_cup_stats_rows($con, $ctx));
    echo "  wc_stats_rows (chapter count + chronology table): {$t} ms\n";
    [, $t] = timed(fn () => amiga_wc_honours_player_count($con, $ctx));
    echo "  wc_honours_player_count (chapter): {$t} ms\n";
    [, $t] = timed(fn () => amiga_wc_country_count($con, $ctx));
    echo "  wc_country_count (chapter): {$t} ms\n";
}

$con->close();
echo "OK\n";