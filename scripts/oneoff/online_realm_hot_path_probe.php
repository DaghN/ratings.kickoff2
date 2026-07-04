<?php
declare(strict_types=1);
/**
 * Online realm hot-path SQL probe — lib-level ms for worst census paths.
 * Usage: php scripts/oneoff/online_realm_hot_path_probe.php
 */
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../site/public_html') ?: '';

function ms(float $t0): float { return round((microtime(true) - $t0) * 1000, 1); }
function bench(string $label, callable $fn): mixed {
    $t0 = microtime(true);
    $result = $fn();
    echo $label . ': ' . ms($t0) . " ms\n";
    return $result;
}
function probe_query_all(mysqli $con, string $sql, string $types = '', array $params = []): array
{
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        return [];
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

$busyPlayer = 537;
$periodDay = '2026-07-04';
$periodHeavyYear = '2021';

include __DIR__ . '/../../site/config/ko2unitydb_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_realm_games_all.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_realm_games_all_filters_ui.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_realm_games_filter_facets.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/games_highlights_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/games_hub_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_player_games_filter_facets.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_ratedresults_games_filters.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_games_from.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/status_queries.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/period_activity_leaderboard_query.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/records_ratio_leaders.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/records_activity_leaders.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/records_career_leaders.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/peak_month_leaderboard_query.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_feast_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_peak_rating_lib.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    fwrite(STDERR, "connect fail: {$con->connect_error}\n");
    exit(1);
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

echo "=== /games/all.php hot SQL ===\n";
$state = k2_realm_games_all_request_state();
$limit = defined('K2_REALM_GAMES_ALL_PAGE_SIZE') ? K2_REALM_GAMES_ALL_PAGE_SIZE : 250;
bench('sanitize_filters', fn () => k2_realm_games_all_sanitize_filters($con, $state));
bench('fetch_players', fn () => k2_realm_games_all_fetch_players($con));
bench('score_line_facets', fn () => k2_realm_games_load_score_line_filter_facets($con, $state));
bench('fetch_years', fn () => k2_realm_games_all_fetch_years($con));
$total = bench('count', fn () => k2_realm_games_all_count($con, $state));
echo "  total_matches={$total}\n";
$page = bench('fetch_page', fn () => k2_realm_games_all_fetch_page($con, $state, $limit));
echo '  page_rows=' . count($page) . "\n";
echo "  anti-pattern: facet waterfall + full-table COUNT on ratedresults\n";

echo "\n=== /player/games.php?id={$busyPlayer} hot SQL ===\n";
$resultFilter = 'all';
$opponentFilter = 0;
$goalsScoredFilter = -1;
$goalsConcededFilter = -1;
$goalsSumFilter = -1;
$heroGoalDiffFilter = null;
bench('validate_filters_career_wide', fn () => k2_player_games_validate_filters_career_wide(
    $con, $busyPlayer, $resultFilter, $opponentFilter, $goalsScoredFilter, $goalsConcededFilter, $goalsSumFilter, $heroGoalDiffFilter
));
$filterContext = k2_player_games_filter_context(
    $resultFilter, $opponentFilter, $goalsScoredFilter, $goalsConcededFilter, $goalsSumFilter, $heroGoalDiffFilter, '', '', '', 0, 0
);
bench('load_filter_facets', fn () => k2_player_games_load_filter_facets($con, $busyPlayer, $filterContext));
// Default unfiltered view — same shape as individual3_where_clause() with all filters open.
$whereSql = '(r.idA = ? OR r.idB = ?)';
$whereTypes = 'ii';
$whereParams = [$busyPlayer, $busyPlayer];
$countRows = bench('count_query', fn () => probe_query_all(
    $con, 'SELECT COUNT(*) AS c FROM ratedresults r WHERE ' . $whereSql, $whereTypes, $whereParams
));
$playerLimit = defined('K2_PLAYER_GAMES_PAGE_SIZE') ? K2_PLAYER_GAMES_PAGE_SIZE : 500;
$games = bench('fetch_page_query', fn () => probe_query_all(
    $con,
    'SELECT r.id, r.Date, r.idA, r.NameA, r.idB, r.NameB, r.RatingA, r.RatingB, r.GoalsA, r.GoalsB, r.ExpectedScoreA, r.ExpectedScoreB, r.ActualScore, r.AdjustmentA, r.AdjustmentB, r.SumOfGoals, r.GoalDifference, r.NewRatingA '
        . 'FROM ratedresults r WHERE ' . $whereSql . ' ORDER BY r.id DESC LIMIT ' . $playerLimit,
    $whereTypes,
    $whereParams
));
echo '  total=' . (int) ($countRows[0]['c'] ?? 0) . ' page_rows=' . count($games) . "\n";
echo "  anti-pattern: duplicate facet passes + COUNT + wide row fetch per request\n";

echo "\n=== /api/server_play_texture.php inline SQL ===\n";
$sql = <<<'SQL'
SELECT DATE_FORMAT(`Date`, '%Y-%m') AS ym,
       COUNT(*) AS games,
       SUM(GoalsA + GoalsB) AS total_goals,
       SUM(CASE WHEN GoalsA = GoalsB THEN 1 ELSE 0 END) AS draws,
       SUM(CASE WHEN GoalsA >= 10 OR GoalsB >= 10 THEN 1 ELSE 0 END) AS dd_games,
       SUM(CASE WHEN GoalsA = 0 OR GoalsB = 0 THEN 1 ELSE 0 END) AS cs_games
FROM ratedresults
GROUP BY ym
ORDER BY ym ASC
SQL;
$res = bench('full_table_group_by_month', function () use ($con, $sql) {
    $r = mysqli_query($con, $sql);
    $n = 0;
    while ($r && mysqli_fetch_assoc($r)) { $n++; }
    if ($r) { mysqli_free_result($r); }
    return $n;
});
echo "  month_buckets={$res}\n";
echo "  anti-pattern: live full ratedresults scan (stored-truth candidate — defer)\n";

echo "\n=== /games/highlights.php hot SQL ===\n";
$rows = bench('highlights_fetch', fn () => k2_games_highlights_fetch($con, 'most_goals'));
echo '  rows=' . count($rows) . "\n";
bench('hub_status_counts', fn () => k2_games_hub_status_counts($con));
echo "  pattern: inner narrow LIMIT subquery + join-back (O4)\n";

echo "\n=== /hall-of-fame.php hot SQL ===\n";
bench('generalstats_row', function () use ($con) {
    $r = mysqli_query($con, 'SELECT * FROM generalstatstable WHERE id = 1 LIMIT 1');
    $row = $r ? mysqli_fetch_assoc($r) : null;
    if ($r) { mysqli_free_result($r); }
    return $row !== null;
});
bench('records_load_ratio_leaders', fn () => records_load_ratio_leaders($con));
bench('records_load_participation_leaders', fn () => records_load_participation_leaders($con));
bench('records_load_career_celebration_leaders', fn () => records_load_career_celebration_leaders($con));
foreach (['year', 'month', 'week', 'day'] as $period) {
    bench('peak_period_' . $period, function () use ($con, $period) {
        $err = null;
        return k2_peak_period_leaderboard_entries($con, $period, 1, $err);
    });
}
echo "  anti-pattern: many sequential reads; peak period loops ratedresults\n";

echo "\n=== Status period APIs (heavy year {$periodHeavyYear}) ===\n";
$err = null;
$entries = bench('period_activity_leaderboard_year', fn () => k2_period_activity_leaderboard_entries($con, 'year', $periodHeavyYear, 0, $err));
echo '  players=' . count($entries) . ($err ? " err={$err}" : '') . "\n";
$err2 = null;
$league = bench('status_league_for_key_year', fn () => k2_status_league_for_key($con, 'year', $periodHeavyYear, null, $err2));
echo '  league_rows=' . count($league['rows'] ?? []) . ($err2 ? " err={$err2}" : '') . "\n";
echo "  anti-pattern: year-wide ratedresults aggregation per Status tab switch\n";

echo "\n=== /player/profile.php?id={$busyPlayer} hot SQL ===\n";
$pm = bench('player_feast_load_pm', fn () => player_feast_load_pm($con, $busyPlayer));
echo '  queries_in_pm: multiple playertable + player_period_games + story/bonanza sub-loads' . "\n";
echo "  anti-pattern: feast bundles many small queries; busy player amplifies story scans\n";

echo "\n=== /api/lb_peak_rating_context.php?id={$busyPlayer} ===\n";
$payload = bench('lb_peak_rating_context_payload', fn () => k2_lb_peak_rating_context_payload($con, $busyPlayer));
echo '  games=' . count($payload['games'] ?? []) . "\n";
echo "  anti-pattern: peak game window reads on ratedresults\n";

echo "\n=== /status.php hot SQL ===\n";
$roomErr = null;
$room = bench('k2_status_load_room', fn () => k2_status_load_room($con, $roomErr));
echo '  room_ok=' . ($room !== null ? 'yes' : 'no') . ($roomErr ? " err={$roomErr}" : '') . "\n";

$con->close();
echo "\nOK\n";