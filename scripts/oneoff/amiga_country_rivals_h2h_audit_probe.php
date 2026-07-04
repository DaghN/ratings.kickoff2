<?php
declare(strict_types=1);

require __DIR__ . '/../../site/public_html/includes/amiga_lb_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_countries_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_country_rivals_load.php';
require __DIR__ . '/../../site/public_html/includes/amiga_country_rivals_h2h.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

function ms(float $start): float { return round((microtime(true) - $start) * 1000, 1); }
function bench(string $label, callable $fn): mixed {
    $t0 = microtime(true);
    $r = $fn();
    echo $label . ': ' . ms($t0) . " ms\n";
    return $r;
}

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) { fwrite(STDERR, "connect fail\n"); exit(1); }
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

$hero = 'England';
$rival = 'Italy';
$cutoffs = ['present' => '', 'event:22' => 'event:22', 'event:589' => 'event:589', 'month:2025-09' => 'month:2025-09', 'year:2024' => 'year:2024'];

foreach ($cutoffs as $label => $as) {
    echo "\n=== Cutoff: $label ===\n";
    if ($as !== '') { $_GET['as'] = $as; } else { unset($_GET['as']); }
    $GLOBALS['_amiga_snapshot_context'] = null;
    $ctx = amiga_lb_context($con);

    bench('country_summary', fn () => amiga_countries_query_country_summary($con, $ctx, $hero));
    bench('rivals_rows (played list + perf)', fn () => amiga_country_rivals_rows($con, $hero, $ctx));
    bench('rivals_bucket (dup full rows scan)', fn () => amiga_country_rivals_bucket($con, $hero, $rival, $ctx));
    bench('player_counts_by_token (all countries)', fn () => amiga_countries_player_counts_by_token($con, $ctx));
    bench('h2h_games_rows (pair games)', fn () => amiga_country_rivals_h2h_games_rows($con, $hero, $rival, $ctx));
    bench('h2h_cumulative_payload', fn () => amiga_country_rivals_h2h_cumulative_payload($con, $hero, $rival, $ctx));
    bench('h2h_goals_buckets subject', fn () => amiga_country_rivals_h2h_goals_buckets($con, $hero, $rival, $ctx, 'subject'));
    bench('h2h_scoreline_heatmap', fn () => amiga_country_rivals_h2h_scoreline_heatmap_payload($con, $hero, $rival, $ctx));

    echo "Simulated panel path (sequential as page — optimized H2H flow):\n";
    $tPanel = microtime(true);
    amiga_countries_query_country_summary($con, $ctx, $hero);
    $rivalsRows = amiga_country_rivals_rows($con, $hero, $ctx, false);
    $played = amiga_country_rivals_h2h_played_rivals_from_rows($rivalsRows);
    unset($played);
    $bucket = amiga_country_rivals_bucket_from_rows($rivalsRows, $rival);
    if ($bucket !== null && (int) ($bucket['games'] ?? 0) > 0) {
        $bucket = amiga_country_rivals_attach_perf_to_bucket($bucket, $con, $hero, $ctx);
    }
    unset($bucket);
    $rivalSummary = amiga_countries_query_country_summary($con, $ctx, $rival);
    unset($rivalSummary);
    amiga_country_rivals_h2h_games_rows($con, $hero, $rival, $ctx);
    echo 'panel_sequential_total: ' . ms($tPanel) . " ms\n";
}

$con->close();
echo "\nOK\n";