<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga player games</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="/stylesheets/player-feast.css" rel="stylesheet" type="text/css" />
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; ?>
<script type="text/javascript" src="/js/k2-archive-listbox.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-archive-listbox.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/individual3-filters.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/individual3-filters.js'); ?>" defer="defer"></script>
</head>
<body class="k2-site k2-player-wing player-feast-body">

<?php
$playerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($playerId < 1) {
    http_response_code(404);
    exit('Player not found.');
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_games_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_snapshot_context.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_performance_rating.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_game_row.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_archive_listbox.php';
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

amiga_player_publish_hero_context($pm);
$name = $Name;

$gameFilters = amiga_player_games_filters_from_request($con, $playerId, $_GET, $ctx);
$resultFilter = $gameFilters['result'];
$opponentFilter = $gameFilters['opponent'];
$tournamentFilter = $gameFilters['tournament'];
$eventFilter = $gameFilters['event'];
$countryFilter = $gameFilters['country'];
$utcDayFilter = $gameFilters['day'];
$sinceYearFilter = $gameFilters['since'];
$yearFilter = $gameFilters['year'];
$yearOptions = amiga_player_games_year_options($con, $playerId, $ctx);
$sortKey = (string) ($_GET['sort'] ?? 'id');
if ($sortKey === 'for') {
    $sortKey = 'goals_for';
}
$sortDirection = amiga_games_valid_direction((string) ($_GET['dir'] ?? 'desc'));
$playerIdSql = (int) $playerId;

$sortMap = [
    'id' => 'r.id',
    'date' => 'r.`Date`',
    'team_a' => 'r.NameA',
    'team_b' => 'r.NameB',
    'tournament' => 'r.tournament_name',
    'phase' => 'r.phase',
    'result' => "CASE WHEN ((r.idA = $playerIdSql AND ABS(r.ActualScore - 1.0) < 0.001) OR (r.idB = $playerIdSql AND ABS(r.ActualScore) < 0.001)) THEN 2 WHEN ABS(r.ActualScore - 0.5) < 0.001 THEN 1 ELSE 0 END",
    'opponent' => "CASE WHEN r.idA = $playerIdSql THEN r.NameB ELSE r.NameA END",
    'goals_for' => "CASE WHEN r.idA = $playerIdSql THEN r.GoalsA ELSE r.GoalsB END",
    'against' => "CASE WHEN r.idA = $playerIdSql THEN r.GoalsB ELSE r.GoalsA END",
    'diff' => "CASE WHEN r.idA = $playerIdSql THEN r.GoalsA - r.GoalsB ELSE r.GoalsB - r.GoalsA END",
    'sum' => 'r.SumOfGoals',
    'player_rating' => "CASE WHEN r.idA = $playerIdSql THEN r.RatingA ELSE r.RatingB END",
    'opponent_rating' => "CASE WHEN r.idA = $playerIdSql THEN r.RatingB ELSE r.RatingA END",
    'es' => "CASE WHEN r.idA = $playerIdSql THEN r.ExpectedScoreA ELSE r.ExpectedScoreB END",
    'adjustment' => "CASE WHEN r.idA = $playerIdSql THEN r.AdjustmentA ELSE r.AdjustmentB END",
];
if (!isset($sortMap[$sortKey])) {
    $sortKey = 'id';
}

$fromSql = amiga_rated_games_from_sql();

$branchATypes = 'i';
$branchAParams = [$playerId];
$branchACutoffSql = amiga_snapshot_tournament_cutoff_and_sql($ctx, $branchATypes, $branchAParams);
$branchBTypes = 'i';
$branchBParams = [$playerId];
$branchBCutoffSql = amiga_snapshot_tournament_cutoff_and_sql($ctx, $branchBTypes, $branchBParams);
$opponentRows = amiga_games_query_all(
    $con,
    'SELECT opponent_id, opponent_name, COUNT(*) AS games FROM ('
        . 'SELECT g.player_b_id AS opponent_id, pb.name AS opponent_name FROM amiga_games g '
        . 'INNER JOIN amiga_players pb ON pb.id = g.player_b_id '
        . 'INNER JOIN tournaments t ON t.id = g.tournament_id '
        . 'WHERE g.player_a_id = ?' . $branchACutoffSql . ' '
        . 'UNION ALL '
        . 'SELECT g.player_a_id AS opponent_id, pa.name AS opponent_name FROM amiga_games g '
        . 'INNER JOIN amiga_players pa ON pa.id = g.player_a_id '
        . 'INNER JOIN tournaments t ON t.id = g.tournament_id '
        . 'WHERE g.player_b_id = ?' . $branchBCutoffSql
        . ') AS opponents GROUP BY opponent_id, opponent_name ORDER BY games DESC, opponent_name ASC',
    $branchATypes . $branchBTypes,
    array_merge($branchAParams, $branchBParams)
);
$countryMetaTypes = 'ii';
$countryMetaParams = [$playerId, $playerId];
$countryMetaSql = amiga_games_tournament_meta_and_sql($eventFilter, '', $countryMetaTypes, $countryMetaParams);
$countryCutoffSql = amiga_snapshot_tournament_cutoff_and_sql($ctx, $countryMetaTypes, $countryMetaParams);
$countryRowList = amiga_games_query_all(
    $con,
    'SELECT DISTINCT t.country AS country FROM amiga_games g '
        . 'INNER JOIN tournaments t ON t.id = g.tournament_id '
        . 'WHERE (g.player_a_id = ? OR g.player_b_id = ?) '
        . 'AND t.country IS NOT NULL AND TRIM(t.country) <> \'\''
        . $countryMetaSql
        . $countryCutoffSql
        . ' ORDER BY country ASC',
    $countryMetaTypes,
    $countryMetaParams
);
$countryOptions = [];
foreach ($countryRowList as $countryRow) {
    $countryOptions[] = (string) $countryRow['country'];
}

$tournamentMetaTypes = 'ii';
$tournamentMetaParams = [$playerId, $playerId];
$tournamentMetaSql = amiga_games_tournament_meta_and_sql(
    $eventFilter,
    $countryFilter,
    $tournamentMetaTypes,
    $tournamentMetaParams
);
$tournamentCutoffSql = amiga_snapshot_tournament_cutoff_and_sql($ctx, $tournamentMetaTypes, $tournamentMetaParams);
$tournamentRows = amiga_games_query_all(
    $con,
    'SELECT g.tournament_id, t.name AS tournament_name, COUNT(*) AS games FROM amiga_games g '
        . 'INNER JOIN tournaments t ON t.id = g.tournament_id '
        . 'WHERE g.player_a_id = ? OR g.player_b_id = ?'
        . $tournamentMetaSql
        . $tournamentCutoffSql
        . ' GROUP BY g.tournament_id, t.name, t.event_date, t.chrono '
        . 'ORDER BY COALESCE(t.chrono, 999999) DESC, COALESCE(t.event_date, \'1970-01-01\') DESC, t.name ASC',
    $tournamentMetaTypes,
    $tournamentMetaParams
);
$whereTypes = '';
$whereParams = [];
$whereSql = amiga_games_where_clause(
    $playerId,
    $resultFilter,
    $opponentFilter,
    $tournamentFilter,
    $eventFilter,
    $countryFilter,
    $utcDayFilter,
    $sinceYearFilter,
    $yearFilter,
    $whereTypes,
    $whereParams,
    $ctx
);

$countRows = amiga_games_query_all(
    $con,
    'SELECT COUNT(*) AS c ' . $fromSql . ' WHERE ' . $whereSql,
    $whereTypes,
    $whereParams
);
$totalMatches = (int) ($countRows[0]['c'] ?? 0);

$games = amiga_games_query_all(
    $con,
    'SELECT r.id, r.Date, r.idA, r.NameA, r.idB, r.NameB, r.RatingA, r.RatingB, r.GoalsA, r.GoalsB, '
        . 'r.ExpectedScoreA, r.ExpectedScoreB, r.ActualScore, r.AdjustmentA, r.AdjustmentB, r.SumOfGoals, r.GoalDifference, '
        . 'r.phase, r.tournament_id, r.tournament_name '
        . $fromSql . ' WHERE ' . $whereSql
        . ' ORDER BY ' . $sortMap[$sortKey] . ' ' . strtoupper($sortDirection) . ', r.id DESC',
    $whereTypes,
    $whereParams
);

?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_hero.php'; ?>

<?php
$k2AmigaPlayerTabActive = 'games';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_nav.php';
?>

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
    'day' => $utcDayFilter,
    'since' => $sinceYearFilter,
    'year' => $yearFilter,
];
$gamesUrlState = $sortState;
$sortedColIndex = amiga_player_game_sort_col_index($sortKey);

$resultChoices = [
    ['value' => 'all', 'label' => 'All results'],
    ['value' => 'win', 'label' => 'Wins'],
    ['value' => 'draw', 'label' => 'Draws'],
    ['value' => 'loss', 'label' => 'Losses'],
];
$opponentChoices = [['value' => '0', 'label' => 'All opponents']];
foreach ($opponentRows as $opponentRow) {
    $opponentChoices[] = [
        'value' => (string) (int) $opponentRow['opponent_id'],
        'label' => (string) $opponentRow['opponent_name'] . ' (' . (int) $opponentRow['games'] . ')',
    ];
}
$tournamentChoices = [['value' => '0', 'label' => 'All tournaments']];
foreach ($tournamentRows as $tournamentRow) {
    $tournamentChoices[] = [
        'value' => (string) (int) $tournamentRow['tournament_id'],
        'label' => (string) $tournamentRow['tournament_name'] . ' (' . (int) $tournamentRow['games'] . ')',
    ];
}
$countryChoices = [['value' => '', 'label' => 'All countries']];
foreach ($countryOptions as $countryName) {
    $countryChoices[] = [
        'value' => $countryName,
        'label' => $countryName,
    ];
}
$sinceChoices = [['value' => '0', 'label' => 'Any time']];
$yearChoices = [['value' => '0', 'label' => 'All years']];
foreach ($yearOptions as $year) {
    $sinceChoices[] = [
        'value' => (string) $year,
        'label' => (string) $year,
    ];
    $yearChoices[] = [
        'value' => (string) $year,
        'label' => (string) $year,
    ];
}
?>

<div class="k2-player-tournament-filters k2-player-games-filters">
    <div class="k2-player-tournament-filters__row">
        <span class="server-period-activity-leaderboard__picker-label">Event</span>
        <nav class="k2-player-tournament-filters__pills" data-k2-carry-scroll aria-label="Filter events">
            <a href="<?php echo amiga_games_h(amiga_games_event_filter_url($gamesUrlState, 'all')); ?>" class="k2-player-nav__btn<?php echo $eventFilter === 'all' ? ' is-active' : ''; ?>">All</a>
            <a href="<?php echo amiga_games_h(amiga_games_event_filter_url($gamesUrlState, 'world-cup')); ?>" class="k2-player-nav__btn<?php echo $eventFilter === 'world-cup' ? ' is-active' : ''; ?>">World Cups</a>
        </nav>
    </div>
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
        </div>
        <div class="k2-player-games-controls__fields">
            <div class="k2-player-games-controls__field">
                <span class="server-period-activity-leaderboard__picker-label">Result</span>
                <?php k2_archive_listbox_render('result', 'k2-player-games-result', $resultFilter, $resultChoices, 'Filter by result'); ?>
            </div>
            <div class="k2-player-games-controls__field">
                <span class="server-period-activity-leaderboard__picker-label">Opponent</span>
                <?php k2_archive_listbox_render('opponent', 'k2-player-games-opponent', (string) $opponentFilter, $opponentChoices, 'Filter by opponent'); ?>
            </div>
            <div class="k2-player-games-controls__field">
                <span class="server-period-activity-leaderboard__picker-label">Tournament</span>
                <?php k2_archive_listbox_render('tournament', 'k2-player-games-tournament', (string) $tournamentFilter, $tournamentChoices, 'Filter by tournament'); ?>
            </div>
            <?php if ($countryOptions !== []) { ?>
            <div class="k2-player-games-controls__field">
                <span class="server-period-activity-leaderboard__picker-label">Country</span>
                <?php k2_archive_listbox_render('country', 'k2-player-games-country', $countryFilter, $countryChoices, 'Filter by country'); ?>
            </div>
            <?php } ?>
            <?php if ($yearOptions !== []) { ?>
            <div class="k2-player-games-controls__field">
                <span class="server-period-activity-leaderboard__picker-label">Year</span>
                <?php k2_archive_listbox_render('year', 'k2-player-games-year', (string) $yearFilter, $yearChoices, 'Games from this calendar year'); ?>
            </div>
            <div class="k2-player-games-controls__field">
                <span class="server-period-activity-leaderboard__picker-label">Since</span>
                <?php k2_archive_listbox_render('since', 'k2-player-games-since', (string) $sinceYearFilter, $sinceChoices, 'Games from this year onward'); ?>
            </div>
            <?php } ?>
            <a class="k2-player-games-reset" href="<?php echo amiga_games_h(k2_amiga_route('amiga-player-games', ['id' => $playerId])); ?>">Reset</a>
        </div>
    </form>
</div>

<div id="matching-games" class="k2-player-games-day-anchor" tabindex="-1"></div>

<div class="k2-player-games-status">
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
        || $utcDayFilter !== ''
        || $sinceYearFilter > 0
        || $yearFilter > 0;
    if ($gamesListFiltered) {
        echo (int) $totalMatches . ' matching game' . ($totalMatches === 1 ? '' : 's');
    } else {
        echo (int) $totalMatches . ' official game' . ($totalMatches === 1 ? '' : 's');
    }
    ?>
    <span class="k2-player-games-status__perf" title="<?php echo amiga_games_h(amiga_perf_rating_games_list_help()); ?>">· Performance rating <span class="k2-player-games-status__perf-value">…</span></span>
</div>

<?php k2_table_wrap_open(true); ?>

<table class="k2-table k2-table--numeric-default k2-table--calm-stats k2-table--player-games">

<thead>
<tr>
    <?php echo amiga_games_sort_header('id', 'ID', 'left', $sortState, 'Rated game ID.'); ?>
    <?php echo amiga_games_sort_header('date', 'Date', 'left', $sortState, 'Synthetic event date (tournament day + order within event).', 'Date', 'k2-table-cell--pad-left-xs k2-amiga-player-games-date'); ?>
    <?php echo amiga_games_sort_header('team_a', 'Team A', 'right', $sortState, 'Player listed as Team A in the original game record.'); ?>
    <th></th>
    <th></th>
    <?php echo amiga_games_sort_header('team_b', 'Team B', 'left', $sortState, 'Player listed as Team B in the original game record.'); ?>
    <?php echo amiga_games_sort_header('tournament', 'Tournament', 'left', $sortState, 'Offline tournament or event.', 'Tournament'); ?>
    <?php echo amiga_games_sort_header('phase', 'Phase', 'left', $sortState, 'Bracket phase when recorded (group, final, etc.).', 'Phase'); ?>
    <?php echo amiga_games_sort_header('result', 'Result', 'left', $sortState, 'Result from this player\'s perspective: win, draw, or loss.', 'Result', 'k2-table-cell--pad-left-xl'); ?>
    <?php echo amiga_games_sort_header('opponent', 'Opponent', 'left', $sortState, 'Opponent in this game.'); ?>
    <?php echo amiga_games_sort_header('goals_for', 'GF', 'right', $sortState, 'Goals scored by this player.', 'Goals for', 'k2-table-cell--pad-left-md'); ?>
    <?php echo amiga_games_sort_header('against', 'GA', 'right', $sortState, 'Goals conceded by this player.', 'Goals against'); ?>
    <?php echo amiga_games_sort_header('diff', 'GD', 'right', $sortState, 'Goal difference from this player\'s perspective.', 'GD'); ?>
    <?php echo amiga_games_sort_header('sum', 'Sum', 'right', $sortState, 'Total goals scored by both players in the game.'); ?>
    <?php echo amiga_games_sort_header('player_rating', amiga_games_h($name), 'right', $sortState, 'This player\'s Elo rating before the game.', $name . ' rating', 'k2-table-cell--pad-left-md'); ?>
    <?php echo amiga_games_sort_header('opponent_rating', 'Opponent', 'right', $sortState, 'Opponent Elo rating before the game.', 'Opponent rating'); ?>
    <?php echo amiga_games_sort_header('es', 'ES ' . amiga_games_h($name), 'right', $sortState, 'Expected score for this player before the game, based on the rating difference.', 'Expected score'); ?>
    <?php echo amiga_games_sort_header('adjustment', 'Adjustment', 'right', $sortState, 'Rating points gained or lost by this player after the game.'); ?>
</tr>
</thead>

<tbody>
    <?php if ($games === []) { ?>
    <tr>
        <td colspan="18" class="k2-table-cell--left k2-games-day__empty">No games match these filters.</td>
    </tr>
    <?php } ?>
    <?php foreach ($games as $game) { ?>
    <?php echo amiga_player_game_row_html($game, $playerId, $sortedColIndex, $con); ?>
    <?php } ?>
</tbody>

</table>

<?php k2_table_wrap_close(); ?><!-- .k2-table-wrap -->

</div><!-- .k2-page-nav -->
<?php mysqli_close($con); ?>
<script type="text/javascript" src="/js/amiga-player-games-perf.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/amiga-player-games-perf.js'); ?>"></script>
</body>
</html>
