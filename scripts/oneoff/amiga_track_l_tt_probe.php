<?php
declare(strict_types=1);
/** Track L — lib ms + curl sweep for Games hub + four LB wings. */
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../site/public_html') ?: '';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_snapshot_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_games_hub_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_realm_games_hub_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_games_highlights_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_realm_games_all.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_realm_games_filter_facets.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_tournament_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

function ms(float $t0): float { return round((microtime(true) - $t0) * 1000, 1); }

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

foreach (['present', 'month:2002-06', 'month:2014-07', 'year:2024'] as $as) {
    if ($as === 'present') {
        unset($_GET['as']);
        amiga_snapshot_context_reset();
        $ctx = AmigaSnapshotContext::present();
    } else {
        $_GET['as'] = $as;
        amiga_snapshot_context_reset();
        $ctx = amiga_snapshot_context_from_request($con);
    }
    echo "=== games/recent lib @ {$as} ===\n";
    $t0 = microtime(true);
    $recentTournaments = amiga_games_hub_recent_tournaments($con, $ctx);
    $gamesByTournament = amiga_games_hub_recent_games_by_tournament($con, $recentTournaments, $ctx);
    amiga_games_hub_status_counts($con, $ctx, null, $gamesByTournament);
    echo '  blocking: ' . ms($t0) . " ms\n";

    echo "=== games/highlights lib @ {$as} ===\n";
    $t0 = microtime(true);
    amiga_games_highlights_fetch($con, 'most_goals', $ctx);
    echo '  most_goals: ' . ms($t0) . " ms\n";

    echo "=== games/all lib @ {$as} ===\n";
    $state = amiga_realm_games_all_request_state();
    amiga_realm_games_all_sanitize_filters($con, $state, $ctx);
    $t0 = microtime(true);
    amiga_realm_games_load_score_line_filter_facets($con, $state, $ctx);
    amiga_realm_games_all_count($con, $state, $ctx);
    amiga_realm_games_all_fetch_page($con, $state, $ctx, 250);
    echo '  facets+page: ' . ms($t0) . " ms\n";

    echo "=== LB lib @ {$as} ===\n";
    $t0 = microtime(true);
    $res = amiga_lb_query_peak_rating($con, $ctx);
    while ($res->fetch_assoc()) {}
    amiga_lb_chapter_lede_html_for_request($con, $ctx);
    echo '  peak+lede: ' . ms($t0) . " ms\n";
    $t0 = microtime(true);
    $res = amiga_lb_query_career($con, $ctx, 'SELECT p.id AS ID ', 'ORDER BY s.Rating DESC');
    while ($res->fetch_assoc()) {}
    echo '  career: ' . ms($t0) . " ms\n";
    $t0 = microtime(true);
    amiga_lb_performance_rating_rows($con, $ctx);
    echo '  perf best: ' . ms($t0) . " ms\n";
    $t0 = microtime(true);
    amiga_tournament_honours_leaderboard_rows($con, $ctx);
    echo '  honours: ' . ms($t0) . " ms\n";
}
$con->close();

$base = getenv('K2_CURL_BASE') ?: 'http://ratingskickoff.test';
$pages = [
    '/amiga/games/recent.php' => 'year:2024',
    '/amiga/games/highlights.php' => 'present',
    '/amiga/games/all.php' => 'year:2024',
    '/amiga/leaderboards/peak-rating.php' => 'present',
    '/amiga/leaderboards/rating.php' => 'present',
    '/amiga/leaderboards/performance-rating/best.php' => 'year:2024',
    '/amiga/leaderboards/tournament-honours.php' => 'present',
];
echo "\n=== curl sweep (warm) ===\n";
foreach ($pages as $path => $worstAs) {
    foreach (['present', 'month:2002-06', 'month:2014-07', 'year:2024'] as $as) {
        $q = $as === 'present' ? '' : ('?as=' . rawurlencode($as));
        $url = $base . $path . $q;
        @file_get_contents($url);
    }
}
foreach ($pages as $path => $worstAs) {
    $worst = 0.0;
    foreach (['present', 'month:2002-06', 'month:2014-07', 'year:2024'] as $as) {
        $q = $as === 'present' ? '' : ('?as=' . rawurlencode($as));
        $url = $base . $path . $q;
        $t0 = microtime(true);
        $body = @file_get_contents($url);
        $elapsed = microtime(true) - $t0;
        if ($elapsed > $worst) {
            $worst = $elapsed;
        }
        if ($body !== false && preg_match('/Warning:|Fatal error|Deprecated:/', $body)) {
            echo "  ERROR body on {$path} @ {$as}\n";
        }
    }
    echo sprintf("  %-50s worst=%.3fs @ %s\n", $path, $worst, $worstAs);
}