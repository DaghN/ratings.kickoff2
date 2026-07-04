<?php
declare(strict_types=1);
/** Probe: Track F LB remainder — rating career + delta, peak, perf top, honours @ present + 3 TT cutoffs. */
require __DIR__ . '/../../site/public_html/includes/amiga_rating_history_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_lb_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_lb_snapshot_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_player_tournament_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';
function ms(float $t0): float { return round((microtime(true) - $t0) * 1000, 1); }
$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset("utf8mb4");
$con->query("SET time_zone = '+00:00'");
foreach (["present", "month:2002-06", "month:2014-07", "year:2024"] as $as) {
    if ($as === "present") { unset($_GET["as"]); } else { $_GET["as"] = $as; }
    amiga_snapshot_context_reset();
    $ctx = amiga_snapshot_context_from_request($con);
    echo "=== {$as} ===\n";
    $t0 = microtime(true);
    $res = amiga_lb_query_career($con, $ctx, "SELECT p.id AS ID ", "ORDER BY s.Rating DESC");
    $n = 0; while ($res->fetch_assoc()) { $n++; }
    echo "  career query: " . ms($t0) . " ms ({$n} rows)\n";
    $t0 = microtime(true);
    $gc = amiga_lb_games_count($con, $ctx);
    echo "  games count: " . ms($t0) . " ms (n={$gc})\n";
    if ($ctx->isActive()) {
        $t0 = microtime(true);
        amiga_lb_rating_delta_map($con, $ctx);
        echo "  delta map: " . ms($t0) . " ms\n";
    }
    $t0 = microtime(true);
    $top = amiga_lb_performance_rating_top_rows($con, $ctx);
    echo "  perf top rows: " . ms($t0) . " ms (" . count($top) . " rows)\n";
    $t0 = microtime(true);
    $hon = amiga_tournament_honours_leaderboard_rows($con, $ctx);
    echo "  honours rows: " . ms($t0) . " ms (" . count($hon) . " rows)\n";
    $t0 = microtime(true);
    $hc = amiga_lb_honours_player_count($con, $ctx);
    echo "  honours count (cached): " . ms($t0) . " ms (n={$hc})\n";
}
$con->close();