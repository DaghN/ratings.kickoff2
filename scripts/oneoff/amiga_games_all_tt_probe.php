<?php
declare(strict_types=1);
require __DIR__ . '/../../site/public_html/includes/amiga_lb_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_games_hub_helpers.php';
require __DIR__ . '/../../site/public_html/includes/amiga_realm_games_all.php';
require __DIR__ . '/../../site/public_html/includes/amiga_realm_games_filter_facets.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

function ms(float $t0): float { return round((microtime(true) - $t0) * 1000, 1); }

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');

foreach (['present', 'year:2024', 'month:2014-07'] as $as) {
    if ($as === 'present') {
        amiga_snapshot_context_reset();
        $ctx = AmigaSnapshotContext::present();
    } else {
        $_GET['as'] = $as;
        amiga_snapshot_context_reset();
        $ctx = amiga_snapshot_context_from_request($con);
    }
    echo "=== {$as} ===\n";
    $state = amiga_realm_games_all_request_state();
    amiga_realm_games_all_sanitize_filters($con, $state, $ctx);

    $t0 = microtime(true);
    amiga_realm_games_all_fetch_players($con);
    echo '  fetch_players: ' . ms($t0) . " ms\n";

    $t0 = microtime(true);
    amiga_realm_games_facet_host_country_rows($con, $state, $ctx);
    echo '  host_country_rows: ' . ms($t0) . " ms\n";

    $t0 = microtime(true);
    amiga_realm_games_load_score_line_filter_facets($con, $state, $ctx);
    echo '  score_line_facets: ' . ms($t0) . " ms\n";

    $t0 = microtime(true);
    amiga_realm_games_all_fetch_years($con, $ctx);
    echo '  fetch_years: ' . ms($t0) . " ms\n";

    $t0 = microtime(true);
    amiga_realm_games_all_count($con, $state, $ctx);
    echo '  count: ' . ms($t0) . " ms\n";

    $t0 = microtime(true);
    amiga_realm_games_all_fetch_page($con, $state, $ctx, 250);
    echo '  fetch_page: ' . ms($t0) . " ms\n";

    $t0 = microtime(true);
    amiga_games_hub_status_counts($con, $ctx);
    echo '  hub_status_counts: ' . ms($t0) . " ms\n";
}
$con->close();