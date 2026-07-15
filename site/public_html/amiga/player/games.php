<?php
declare(strict_types=1);

$playerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($playerId < 1) {
    http_response_code(404);
    exit('Player not found.');
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_games_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_games_filter_facets.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_snapshot_context.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_performance_rating.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_game_row.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_archive_listbox.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_table_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_ratedresults_games_filters.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_amiga_routes.php';

include __DIR__ . '/../../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");

try {
    $pm = amiga_player_load($con, $playerId);
} catch (RuntimeException $e) {
    mysqli_close($con);
    http_response_code(404);
    exit('Player not found.');
}

$ctx = amiga_snapshot_context_peek();

amiga_player_publish_hero_context($pm, $con);
$name = $Name;

$gameFilters = amiga_player_games_filters_from_request($con, $playerId, $_GET, $ctx);
amiga_player_games_validate_filters_career_wide($con, $playerId, $gameFilters, $ctx);
$resultFilter = $gameFilters['result'];
$opponentFilter = $gameFilters['opponent'];
$tournamentFilter = $gameFilters['tournament'];
$eventFilter = $gameFilters['event'];
$countryFilter = $gameFilters['country'];
$oppCountryFilter = $gameFilters['opp_country'];
$utcDayFilter = $gameFilters['day'];
$sinceYearFilter = $gameFilters['since'];
$untilYearFilter = $gameFilters['until'];
$yearFilter = $gameFilters['year'];
$goalsScoredFilter = $gameFilters['gf'];
$goalsConcededFilter = $gameFilters['ga'];
$goalsSumFilter = $gameFilters['gs'];
$heroGoalDiffFilter = $gameFilters['gd'];
$heroGfMinFilter = $gameFilters['gf_min'];
$heroGfMaxFilter = $gameFilters['gf_max'];
$heroGaMinFilter = $gameFilters['ga_min'];
$heroGaMaxFilter = $gameFilters['ga_max'];
$filterContext = amiga_player_games_filter_context($gameFilters);
$filterFacets = amiga_player_games_load_filter_facets($con, $playerId, $filterContext, $ctx);
$filterChoices = amiga_player_games_facet_listbox_choices($con, $filterFacets, $filterContext);
$showHostCountryFilter = count($filterChoices['country']) > 1 || $countryFilter !== '';
$showOppCountryFilter = count($filterChoices['opp_country']) > 1 || $oppCountryFilter !== '';
$showYearFilters = count($filterChoices['year']) > 1 || $yearFilter > 0 || $sinceYearFilter > 0 || $untilYearFilter > 0;
$sortKey = (string) ($_GET['sort'] ?? AMIGA_PLAYER_GAMES_DEFAULT_SORT);
if ($sortKey === 'for') {
    $sortKey = 'goals_for';
}
$sortDirection = amiga_games_valid_direction((string) ($_GET['dir'] ?? AMIGA_PLAYER_GAMES_DEFAULT_DIR));
$limit = K2_PLAYER_GAMES_PAGE_SIZE;
$offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
$playerIdSql = (int) $playerId;

$sortMap = [
    'id' => 'r.id',
    'date' => 'r.`Date`',
    'team_a' => 'r.NameA',
    'team_b' => 'r.NameB',
    'tournament' => 'r.tournament_name',
    'phase' => 'r.phase',
    'result' => "CASE WHEN ((r.idA = $playerIdSql AND ABS(r.ActualScore - 1.0) < 0.001) OR (r.idB = $playerIdSql AND ABS(r.ActualScore) < 0.001)) THEN 2 WHEN ABS(r.ActualScore - 0.5) < 0.001 THEN 1 ELSE 0 END",
    'goals_for' => "CASE WHEN r.idA = $playerIdSql THEN r.GoalsA ELSE r.GoalsB END",
    'against' => "CASE WHEN r.idA = $playerIdSql THEN r.GoalsB ELSE r.GoalsA END",
    'diff' => "CASE WHEN r.idA = $playerIdSql THEN r.GoalsA - r.GoalsB ELSE r.GoalsB - r.GoalsA END",
    'sum' => 'r.SumOfGoals',
    'rating_a' => 'r.RatingA',
    'rating_b' => 'r.RatingB',
    'opp_rating' => "CASE WHEN r.idA = $playerIdSql THEN r.RatingB ELSE r.RatingA END",
    'es' => "CASE WHEN r.idA = $playerIdSql THEN r.ExpectedScoreA ELSE r.ExpectedScoreB END",
    'adjustment' => "CASE WHEN r.idA = $playerIdSql THEN r.AdjustmentA ELSE r.AdjustmentB END",
];
if (!isset($sortMap[$sortKey])) {
    $sortKey = AMIGA_PLAYER_GAMES_DEFAULT_SORT;
}

$fromSql = amiga_rated_games_from_sql($playerId);

$whereTypes = '';
$whereParams = [];
$whereSql = amiga_games_where_clause(
    $playerId,
    $resultFilter,
    $opponentFilter,
    $tournamentFilter,
    $eventFilter,
    $countryFilter,
    $oppCountryFilter,
    $utcDayFilter,
    $sinceYearFilter,
    $untilYearFilter,
    $yearFilter,
    $goalsScoredFilter,
    $goalsConcededFilter,
    $goalsSumFilter,
    $heroGoalDiffFilter,
    $whereTypes,
    $whereParams,
    $ctx,
    $heroGfMinFilter,
    $heroGfMaxFilter,
    $heroGaMinFilter,
    $heroGaMaxFilter
);

$countRows = amiga_games_query_all(
    $con,
    'SELECT COUNT(*) AS c ' . $fromSql . ' WHERE ' . $whereSql,
    $whereTypes,
    $whereParams
);
$totalMatches = (int) ($countRows[0]['c'] ?? 0);
if ($offset >= $totalMatches) {
    $offset = 0;
}

$games = amiga_games_query_all(
    $con,
    'SELECT r.id, r.Date, r.idA, r.NameA, r.idB, r.NameB, r.RatingA, r.RatingB, r.GoalsA, r.GoalsB, '
        . 'r.ExpectedScoreA, r.ExpectedScoreB, r.ActualScore, r.AdjustmentA, r.AdjustmentB, r.SumOfGoals, r.GoalDifference, '
        . 'r.phase, r.tournament_id, r.tournament_name, r.country_a, r.country_b, r.tournament_country '
        . $fromSql . ' WHERE ' . $whereSql
        . ' ORDER BY ' . $sortMap[$sortKey] . ' ' . strtoupper($sortDirection) . ', r.id DESC'
        . ' LIMIT ' . $limit . ' OFFSET ' . $offset,
    $whereTypes,
    $whereParams
);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga player games</title>
<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="/stylesheets/player-feast.css" rel="stylesheet" type="text/css" />
<link href="/stylesheets/amiga-tournament.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/amiga-tournament.css'); ?>" rel="stylesheet" type="text/css" />
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; ?>
<script type="text/javascript" src="/js/k2-archive-listbox.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-archive-listbox.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/individual3-filters.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/individual3-filters.js'); ?>" defer="defer"></script>
</head>
<body class="k2-site k2-player-wing player-feast-body">

<?php
$k2AmigaPlayerTabActive = 'games';
$k2AmigaPlayerTabWiredAtCutoff = true;
include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php';
?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_wing_hub_nav.inc.php'; ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_hero.php'; ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_nav.php'; ?>

<?php
$sortState = [
    'player_id' => $playerId,
    'sort' => $sortKey,
    'dir' => $sortDirection,
    'result' => $resultFilter,
    'opponent' => $opponentFilter,
    'tournament' => $tournamentFilter,
    'event' => $eventFilter,
    'country' => $countryFilter,
    'opp_country' => $oppCountryFilter,
    'day' => $utcDayFilter,
    'since' => $sinceYearFilter,
    'until' => $untilYearFilter,
    'year' => $yearFilter,
    'gf' => $goalsScoredFilter,
    'ga' => $goalsConcededFilter,
    'gs' => $goalsSumFilter,
    'gd' => $heroGoalDiffFilter,
    'gf_min' => $heroGfMinFilter,
    'gf_max' => $heroGfMaxFilter,
    'ga_min' => $heroGaMinFilter,
    'ga_max' => $heroGaMaxFilter,
];
$gamesUrlState = $sortState;
$shownCount = count($games);
$firstShown = $totalMatches > 0 ? $offset + 1 : 0;
$lastShown = $offset + $shownCount;
$pagerParams = amiga_games_active_url_params($sortState);

$resultChoices = $filterChoices['result'];
$opponentChoices = $filterChoices['opponent'];
$tournamentChoices = $filterChoices['tournament'];
$countryChoices = $filterChoices['country'];
$oppCountryChoices = $filterChoices['opp_country'];
$yearChoices = $filterChoices['year'];
$sinceChoices = $filterChoices['since'];
$untilChoices = $filterChoices['until'];
$goalsScoredChoices = $filterChoices['gf'];
$goalsConcededChoices = $filterChoices['ga'];
$goalsSumChoices = $filterChoices['gs'];
$heroGoalDiffChoices = $filterChoices['gd'];
$gdListboxValue = $heroGoalDiffFilter !== null ? (string) $heroGoalDiffFilter : '';
?>

<div id="<?php echo k2_h(K2_PLAYER_GAMES_FILTERS_ANCHOR); ?>" class="k2-player-games-filters-anchor" tabindex="-1"></div>
<div class="k2-chrome-tabs k2-amiga-player-games-scope-tabs">
    <nav class="k2-chrome-tabs__bar" data-k2-carry-scroll role="tablist" aria-label="Game scope">
        <a href="<?php echo amiga_games_h(amiga_games_event_filter_url($gamesUrlState, 'all')); ?>" class="k2-chrome-tabs__tab<?php echo $eventFilter === 'all' ? ' is-active' : ''; ?>"<?php echo $eventFilter === 'all' ? ' aria-current="true"' : ''; ?>>All games</a>
        <a href="<?php echo amiga_games_h(amiga_games_event_filter_url($gamesUrlState, 'world-cup')); ?>" class="k2-chrome-tabs__tab<?php echo $eventFilter === 'world-cup' ? ' is-active' : ''; ?>"<?php echo $eventFilter === 'world-cup' ? ' aria-current="true"' : ''; ?>>World Cup</a>
    </nav>
</div>
<div class="k2-player-games-filters">
    <form class="k2-player-games-controls" method="get" action="<?php echo amiga_games_h(k2_amiga_route('amiga-player-games')); ?>" data-k2-carry-scroll>
        <div class="k2-player-games-controls__meta">
            <input type="hidden" name="id" value="<?php echo $playerId; ?>" />
            <input type="hidden" name="sort" value="<?php echo amiga_games_h($sortKey); ?>" />
            <input type="hidden" name="dir" value="<?php echo amiga_games_h($sortDirection); ?>" />
            <?php if ($eventFilter !== 'all') { ?>
            <input type="hidden" name="filter" value="<?php echo amiga_games_h($eventFilter); ?>" />
            <?php } ?>
            <?php if ($utcDayFilter !== '') { ?>
            <input type="hidden" name="day" value="<?php echo amiga_games_h($utcDayFilter); ?>" />
            <?php } ?>
            <?php if ($heroGfMinFilter >= 0) { ?>
            <input type="hidden" name="gf_min" value="<?php echo (int) $heroGfMinFilter; ?>" />
            <?php } ?>
            <?php if ($heroGfMaxFilter >= 0) { ?>
            <input type="hidden" name="gf_max" value="<?php echo (int) $heroGfMaxFilter; ?>" />
            <?php } ?>
            <?php if ($heroGaMinFilter >= 0) { ?>
            <input type="hidden" name="ga_min" value="<?php echo (int) $heroGaMinFilter; ?>" />
            <?php } ?>
            <?php if ($heroGaMaxFilter >= 0) { ?>
            <input type="hidden" name="ga_max" value="<?php echo (int) $heroGaMaxFilter; ?>" />
            <?php } ?>
        </div>
        <div class="k2-amiga-player-games-filter-rows">
            <div class="k2-player-games-controls__fields k2-amiga-player-games-filter-row">
                <div class="k2-player-games-controls__field">
                    <span class="server-period-activity-leaderboard__picker-label">Opponent</span>
                    <?php k2_archive_listbox_render('opponent', 'k2-player-games-opponent', (string) $opponentFilter, $opponentChoices, 'Filter by opponent', '', '', false, '0'); ?>
                </div>
                <div class="k2-player-games-controls__field">
                    <span class="server-period-activity-leaderboard__picker-label">Tournament</span>
                    <?php k2_archive_listbox_render('tournament', 'k2-player-games-tournament', (string) $tournamentFilter, $tournamentChoices, 'Filter by tournament', '', '', false, '0'); ?>
                </div>
                <?php if ($showHostCountryFilter) { ?>
                <div class="k2-player-games-controls__field">
                    <span class="server-period-activity-leaderboard__picker-label">Host country</span>
                    <?php k2_archive_listbox_render('country', 'k2-player-games-country', $countryFilter, $countryChoices, 'Filter by host country', '', '', false, ''); ?>
                </div>
                <?php } ?>
                <?php if ($showOppCountryFilter) { ?>
                <div class="k2-player-games-controls__field">
                    <span class="server-period-activity-leaderboard__picker-label">Opponent country</span>
                    <?php k2_archive_listbox_render('opp_country', 'k2-player-games-opp-country', $oppCountryFilter, $oppCountryChoices, 'Filter by opponent country', '', '', false, ''); ?>
                </div>
                <?php } ?>
            </div>
            <?php if ($showYearFilters) { ?>
            <div class="k2-player-games-controls__fields k2-amiga-player-games-filter-row">
                <div class="k2-player-games-controls__field">
                    <span class="server-period-activity-leaderboard__picker-label">Year</span>
                    <?php k2_archive_listbox_render('year', 'k2-player-games-year', (string) $yearFilter, $yearChoices, 'Games from this calendar year', '', '', false, '0'); ?>
                </div>
                <div class="k2-player-games-controls__field">
                    <span class="server-period-activity-leaderboard__picker-label">Since</span>
                    <?php k2_archive_listbox_render('since', 'k2-player-games-since', (string) $sinceYearFilter, $sinceChoices, 'Games from this year onward', '', '', false, '0'); ?>
                </div>
                <div class="k2-player-games-controls__field">
                    <span class="server-period-activity-leaderboard__picker-label">Until</span>
                    <?php k2_archive_listbox_render('until', 'k2-player-games-until', (string) $untilYearFilter, $untilChoices, 'Games through this calendar year', '', '', false, '0'); ?>
                </div>
            </div>
            <?php } ?>
            <div class="k2-player-games-controls__fields k2-amiga-player-games-filter-row">
                <div class="k2-player-games-controls__field">
                    <span class="server-period-activity-leaderboard__picker-label">Result</span>
                    <?php k2_archive_listbox_render('result', 'k2-player-games-result', $resultFilter, $resultChoices, 'Filter by result', '', '', false, 'all'); ?>
                </div>
                <div class="k2-player-games-controls__field">
                    <span class="server-period-activity-leaderboard__picker-label">GF</span>
                    <?php k2_archive_listbox_render('gf', 'k2-player-games-gf', (string) $goalsScoredFilter, $goalsScoredChoices, 'Filter by goals for', '', '', false, '-1'); ?>
                </div>
                <div class="k2-player-games-controls__field">
                    <span class="server-period-activity-leaderboard__picker-label">GA</span>
                    <?php k2_archive_listbox_render('ga', 'k2-player-games-ga', (string) $goalsConcededFilter, $goalsConcededChoices, 'Filter by goals against', '', '', false, '-1'); ?>
                </div>
                <div class="k2-player-games-controls__field">
                    <span class="server-period-activity-leaderboard__picker-label">GD</span>
                    <?php k2_archive_listbox_render('gd', 'k2-player-games-gd', $gdListboxValue, $heroGoalDiffChoices, 'Filter by goal difference', '', '', false, ''); ?>
                </div>
                <div class="k2-player-games-controls__field">
                    <span class="server-period-activity-leaderboard__picker-label">Sum</span>
                    <?php k2_archive_listbox_render('gs', 'k2-player-games-gs', (string) $goalsSumFilter, $goalsSumChoices, 'Filter by total goals', '', '', false, '-1'); ?>
                </div>
            </div>
        </div>
    </form>
</div>

<div id="<?php echo k2_h(K2_PLAYER_MATCHING_GAMES_ANCHOR); ?>" class="k2-player-games-day-anchor" tabindex="-1"></div>

<div class="k2-player-games-status" data-k2-carry-scroll>
    <?php if ($utcDayFilter !== '') { ?>
    Rated games on <strong><?php echo amiga_games_h($utcDayFilter); ?></strong> UTC
    (<a href="<?php echo amiga_games_h(amiga_games_build_url(amiga_games_active_url_params(array_merge($gamesUrlState, ['day' => ''])))); ?>">clear day filter</a>).
    <?php } ?>
    <?php
    $gamesListFiltered = $resultFilter !== 'all'
        || $opponentFilter > 0
        || $tournamentFilter > 0
        || $eventFilter !== 'all'
        || $countryFilter !== ''
        || $oppCountryFilter !== ''
        || $utcDayFilter !== ''
        || $sinceYearFilter > 0
        || $untilYearFilter > 0
        || $yearFilter > 0
        || $goalsScoredFilter >= 0
        || $goalsConcededFilter >= 0
        || $goalsSumFilter >= 0
        || $heroGoalDiffFilter !== null
        || $heroGfMinFilter >= 0
        || $heroGfMaxFilter >= 0
        || $heroGaMinFilter >= 0
        || $heroGaMaxFilter >= 0;
    $gamesCountWord = $gamesListFiltered
        ? ('matching game' . ($totalMatches === 1 ? '' : 's'))
        : ('official game' . ($totalMatches === 1 ? '' : 's'));
    ?>
    <div class="k2-realm-games-all__status-range">
        <span class="k2-realm-games-all__status-text">
            Showing <?php echo (int) $firstShown; ?>–<?php echo (int) $lastShown; ?> of <?php echo number_format($totalMatches); ?> <?php echo $gamesCountWord; ?>.
        </span>
        <?php amiga_games_render_page_nav($offset, $limit, $totalMatches, $pagerParams); ?>
    </div>
    <span class="k2-player-games-status__perf" data-k2-help="<?php echo amiga_games_h(amiga_perf_rating_games_list_help()); ?>" data-k2-tooltip-label="<?php echo amiga_games_h(amiga_perf_rating_column_label()); ?>" tabindex="0">· Performance rating <span class="k2-player-games-status__perf-value">…</span></span><span class="k2-player-games-status__reset-sep" aria-hidden="true"> · </span><a class="k2-player-games-reset" href="<?php echo amiga_games_h(k2_amiga_route('amiga-player-games', ['id' => $playerId])); ?>">Reset filters</a>
</div>

<?php k2_table_wrap_open(true); ?>

<table class="<?php echo k2_h(k2_table_ranked_sortable_class('k2-table--player-games k2-table--tournament-games')); ?>" data-k2-anchor-col="0">

<thead>
<tr>
    <?php echo amiga_games_sort_header('id', 'ID', 'left', $sortState, 'Rated game ID.'); ?>
    <?php echo amiga_games_sort_header('date', 'Date', 'left', $sortState, '', '', 'k2-table-cell--pad-left-xs k2-amiga-player-games-date'); ?>
    <?php echo amiga_games_sort_header('tournament', 'Tournament', 'left', $sortState, 'Offline tournament or event.', 'Tournament'); ?>
    <?php echo amiga_games_sort_header('phase', 'Phase', 'left', $sortState, 'Bracket phase when recorded (group, final, etc.).', 'Phase'); ?>
    <?php echo amiga_games_sort_header('team_a', 'Team A', 'right', $sortState, ''); ?>
    <th></th>
    <th></th>
    <?php echo amiga_games_sort_header('team_b', 'Team B', 'left', $sortState, ''); ?>
    <?php echo amiga_games_sort_header('goals_for', 'GF', 'right', $sortState, 'Goals scored by ' . $name . '.', 'Goals for', 'k2-table-cell--pad-left-md'); ?>
    <?php echo amiga_games_sort_header('against', 'GA', 'right', $sortState, 'Goals conceded by ' . $name . '.', 'Goals against'); ?>
    <?php echo amiga_games_sort_header('diff', 'GD', 'right', $sortState, 'Goal difference from ' . $name . '\'s perspective.', 'GD'); ?>
    <?php echo amiga_games_sort_header('sum', 'Sum', 'right', $sortState, 'Total goals scored by both players in the game.'); ?>
    <?php echo amiga_games_sort_header('rating_a', 'Rating A', 'right', $sortState, 'Player A\'s Elo rating before this game.', '', 'k2-table-cell--pad-left-md'); ?>
    <?php echo amiga_games_sort_header('rating_b', 'Rating B', 'right', $sortState, 'Player B\'s Elo rating before this game.'); ?>
    <?php echo amiga_games_sort_header('es', 'ES ' . amiga_games_h($name), 'right', $sortState, 'Expected score for ' . $name . ' before the game, based on the rating difference.', 'Expected score'); ?>
    <?php echo amiga_games_sort_header('result', 'Result', 'left', $sortState, 'Result from ' . $name . '\'s perspective: win, draw, or loss.', 'Result'); ?>
    <?php echo amiga_games_sort_header('adjustment', 'Adjustment', 'right', $sortState, 'Rating points gained or lost by ' . $name . ' after the game.'); ?>
</tr>
</thead>

<tbody>
    <?php if ($games === []) { ?>
    <tr>
        <td colspan="17" class="k2-table-cell--left k2-games-day__empty">No games match these filters.</td>
    </tr>
    <?php } ?>
    <?php foreach ($games as $game) { ?>
    <?php echo amiga_player_game_row_html($game, $playerId, amiga_player_game_row_sorted_col_index($sortKey, $playerId, $game), $con); ?>
    <?php } ?>
</tbody>

</table>

<?php k2_table_wrap_close(); ?><!-- .k2-table-wrap -->

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_site_end.inc.php'; ?>
<?php mysqli_close($con); ?>
<script type="text/javascript" src="/js/amiga-player-games-perf.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/amiga-player-games-perf.js'); ?>"></script>
</body>
</html>
