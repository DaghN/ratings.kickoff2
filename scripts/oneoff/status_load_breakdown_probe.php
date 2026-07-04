<?php
$_SERVER['DOCUMENT_ROOT'] = 'C:/Users/daghn/Desktop/Online and Amiga 500 ELO/site/public_html';
require $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';
require $_SERVER['DOCUMENT_ROOT'] . '/includes/status_queries.php';
require $_SERVER['DOCUMENT_ROOT'] . '/includes/period_activity_leaderboard_query.php';
$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');
function t(string $label, callable $fn): void {
    $s = microtime(true);
    $fn();
    echo sprintf("%-42s %7.1f ms\n", $label, (microtime(true) - $s) * 1000);
}
$clock = k2_status_server_clock($con);
$now = $clock['now'];
t('arc_ticker', fn() => k2_status_arc_ticker($con));
t('active_top_rated', fn() => k2_status_active_top_rated($con));
t('build_period_competitions', fn() => k2_status_build_period_competitions($con, $now));
t('online_players', fn() => k2_status_online_players($con));
t('live_games', fn() => k2_status_live_games($con, 10));
t('recent_logins', fn() => k2_status_recent_logins($con, 10));
t('recent_regs', fn() => k2_status_recent_registrations($con, 10));
t('recent_rated_games', fn() => k2_status_recent_rated_games($con, 10));
$r = mysqli_query($con, 'SELECT COUNT(*) c FROM playertable WHERE NumberGames>=1 AND LastGame >= DATE_SUB(NOW(), INTERVAL 12 MONTH)');
$row = mysqli_fetch_assoc($r);
echo 'active_top_row_count: ' . ($row['c'] ?? 0) . "\n";

echo "\n=== per-period breakdown ===\n";
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/league_standings.php';
foreach (['day', 'week', 'month', 'year'] as $period) {
    $bounds = k2_status_league_period_bounds($period, 0, $now);
    $key = $bounds !== null ? k2_status_period_activity_key_from_bounds($period, $bounds) : null;
    t("  {$period} points league", fn() => k2_status_league($con, $period, null, 0, $now));
    if ($key !== null) {
        t("  {$period} activity entries", fn() => k2_period_activity_leaderboard_entries($con, $period, $key, 0));
        t("  {$period} activity total_games", fn() => k2_period_activity_total_games($con, $period, $key));
    }
    if ($period === 'day' && $key !== null) {
        t("  day games list", fn() => k2_status_rated_games_for_calendar_day($con, $key));
    }
}
t('available_keys week', fn() => k2_period_activity_available_keys($con, 'week'));
t('available_keys month', fn() => k2_period_activity_available_keys($con, 'month'));
t('available_keys year', fn() => k2_period_activity_available_keys($con, 'year'));
if ($bounds = k2_status_league_period_bounds('week', 0, $now)) {
    t('first_games week window', fn() => k2_league_load_first_games($con, $bounds['start'], $bounds['end']));
}
foreach (['day', 'week', 'month', 'year'] as $p) {
    $b = k2_status_league_period_bounds($p, 0, $now);
    if ($b) {
        t("first_games {$p}", fn() => k2_league_load_first_games($con, $b['start'], $b['end']));
    }
}