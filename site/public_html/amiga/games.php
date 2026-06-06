<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga player games</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="/stylesheets/player-feast.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="/js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/k2-archive-listbox.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-archive-listbox.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/individual3-filters.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/individual3-filters.js'); ?>" defer="defer"></script>
</head>
<body class="k2-site player-feast-body">

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
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_game_row.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_archive_listbox.php';

include __DIR__ . '/../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");

try {
    $pm = amiga_player_load($con, $playerId);
} catch (RuntimeException $e) {
    mysqli_close($con);
    http_response_code(404);
    exit('Player not found.');
}

$id = $playerId;
$Name = $pm['name'];
$Rating = $pm['rating'];
$NumberGames = $pm['games'];
$Display = $pm['display'] ? 1 : 0;
$rank = $pm['rank'];
$Country = $pm['country'];
$name = $Name;

$resultFilter = amiga_games_valid_result((string) ($_GET['result'] ?? 'all'));
$opponentFilter = isset($_GET['opponent']) ? max(0, (int) $_GET['opponent']) : 0;
$utcDayFilter = amiga_games_valid_day((string) ($_GET['day'] ?? ''));
$sortKey = (string) ($_GET['sort'] ?? 'id');
$sortDirection = amiga_games_valid_direction((string) ($_GET['dir'] ?? 'desc'));
$limit = 100;
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
    'opponent' => "CASE WHEN r.idA = $playerIdSql THEN r.NameB ELSE r.NameA END",
    'for' => "CASE WHEN r.idA = $playerIdSql THEN r.GoalsA ELSE r.GoalsB END",
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

$opponentRows = amiga_games_query_all(
    $con,
    'SELECT opponent_id, opponent_name, COUNT(*) AS games FROM ('
        . 'SELECT g.player_b_id AS opponent_id, pb.name AS opponent_name FROM amiga_games g '
        . 'INNER JOIN amiga_players pb ON pb.id = g.player_b_id WHERE g.player_a_id = ? '
        . 'UNION ALL '
        . 'SELECT g.player_a_id AS opponent_id, pa.name AS opponent_name FROM amiga_games g '
        . 'INNER JOIN amiga_players pa ON pa.id = g.player_a_id WHERE g.player_b_id = ?'
        . ') AS opponents GROUP BY opponent_id, opponent_name ORDER BY games DESC, opponent_name ASC',
    'ii',
    [$playerId, $playerId]
);
$validOpponentIds = [];
foreach ($opponentRows as $opponentRow) {
    $validOpponentIds[(int) $opponentRow['opponent_id']] = true;
}
if ($opponentFilter > 0 && !isset($validOpponentIds[$opponentFilter])) {
    $opponentFilter = 0;
}

$whereTypes = '';
$whereParams = [];
$whereSql = amiga_games_where_clause(
    $playerId,
    $resultFilter,
    $opponentFilter,
    $utcDayFilter,
    $whereTypes,
    $whereParams
);

$countRows = amiga_games_query_all(
    $con,
    'SELECT COUNT(*) AS c ' . $fromSql . ' WHERE ' . $whereSql,
    $whereTypes,
    $whereParams
);
$totalMatches = (int) ($countRows[0]['c'] ?? 0);
if ($offset >= $totalMatches && $totalMatches > 0) {
    $offset = 0;
}

$games = amiga_games_query_all(
    $con,
    'SELECT r.id, r.Date, r.idA, r.NameA, r.idB, r.NameB, r.RatingA, r.RatingB, r.GoalsA, r.GoalsB, '
        . 'r.ExpectedScoreA, r.ExpectedScoreB, r.ActualScore, r.AdjustmentA, r.AdjustmentB, r.SumOfGoals, r.GoalDifference, '
        . 'r.phase, r.tournament_name '
        . $fromSql . ' WHERE ' . $whereSql
        . ' ORDER BY ' . $sortMap[$sortKey] . ' ' . strtoupper($sortDirection) . ', r.id DESC'
        . ' LIMIT ' . $limit . ' OFFSET ' . $offset,
    $whereTypes,
    $whereParams
);

mysqli_close($con);
?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<div class="k2-page-nav">

<p style="padding:0.75rem 1.25rem 0;margin:0">
	<a class="k2-link-star" href="/amiga/rating.php">← Amiga ladder</a>
</p>

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
    'day' => $utcDayFilter,
];
$shownCount = count($games);
$firstShown = $totalMatches > 0 ? $offset + 1 : 0;
$lastShown = $offset + $shownCount;
$pagerParams = [
    'id' => $playerId,
    'sort' => $sortKey,
    'dir' => $sortDirection,
];
if ($resultFilter !== 'all') {
    $pagerParams['result'] = $resultFilter;
}
if ($opponentFilter > 0) {
    $pagerParams['opponent'] = $opponentFilter;
}
if ($utcDayFilter !== '') {
    $pagerParams['day'] = $utcDayFilter;
}
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
?>

<form class="k2-player-games-controls" method="get" action="/amiga/games.php">
    <input type="hidden" name="id" value="<?php echo $playerId; ?>" />
    <input type="hidden" name="sort" value="<?php echo amiga_games_h($sortKey); ?>" />
    <input type="hidden" name="dir" value="<?php echo amiga_games_h($sortDirection); ?>" />
    <?php if ($utcDayFilter !== '') { ?>
    <input type="hidden" name="day" value="<?php echo amiga_games_h($utcDayFilter); ?>" />
    <?php } ?>
    <div class="k2-player-games-controls__field">
        <span class="server-period-activity-leaderboard__picker-label">Result</span>
        <?php k2_archive_listbox_render('result', 'k2-player-games-result', $resultFilter, $resultChoices, 'Filter by result'); ?>
    </div>
    <div class="k2-player-games-controls__field">
        <span class="server-period-activity-leaderboard__picker-label">Opponent</span>
        <?php k2_archive_listbox_render('opponent', 'k2-player-games-opponent', (string) $opponentFilter, $opponentChoices, 'Filter by opponent'); ?>
    </div>
    <a class="k2-player-games-action" href="/amiga/games.php?id=<?php echo $playerId; ?>">Reset</a>
</form>

<div class="k2-player-games-status">
    <?php if ($utcDayFilter !== '') { ?>
    Rated games on <strong><?php echo amiga_games_h($utcDayFilter); ?></strong> UTC
    (<a href="<?php echo amiga_games_h(amiga_games_build_url(['id' => $playerId, 'sort' => $sortKey, 'dir' => $sortDirection] + ($resultFilter !== 'all' ? ['result' => $resultFilter] : []) + ($opponentFilter > 0 ? ['opponent' => $opponentFilter] : []))); ?>">clear day filter</a>).
    <?php } ?>
    Showing <?php echo $firstShown; ?>-<?php echo $lastShown; ?> of <?php echo $totalMatches; ?> matching games.
    <?php if ($offset > 0) { ?>
    <?php $prevParams = $pagerParams + ['offset' => max(0, $offset - $limit)]; ?>
    <a class="k2-player-games-action" href="<?php echo amiga_games_h(amiga_games_build_url($prevParams)); ?>">Previous 100</a>
    <?php } ?>
    <?php if ($offset + $limit < $totalMatches) { ?>
    <?php $nextParams = $pagerParams + ['offset' => $offset + $limit]; ?>
    <a class="k2-player-games-action" href="<?php echo amiga_games_h(amiga_games_build_url($nextParams)); ?>">Next 100</a>
    <?php } ?>
</div>

<div class="k2-table-wrap">

<table class="k2-table k2-table--numeric-default k2-table--calm-stats k2-table--player-games">

<thead>
<tr>
    <?php echo amiga_games_sort_header('id', 'ID', 'left', $sortState, 'Rated game ID.'); ?>
    <?php echo amiga_games_sort_header('date', 'Date', 'left', $sortState, 'Synthetic event date (tournament day + order within event).', 'Date', 'k2-table-cell--pad-left-xs'); ?>
    <?php echo amiga_games_sort_header('team_a', 'Team A', 'right', $sortState, 'Player listed as Team A in the original game record.'); ?>
    <th></th>
    <th></th>
    <?php echo amiga_games_sort_header('team_b', 'Team B', 'left', $sortState, 'Player listed as Team B in the original game record.'); ?>
    <?php echo amiga_games_sort_header('tournament', 'Tournament', 'left', $sortState, 'Offline tournament or event.', 'Tournament'); ?>
    <?php echo amiga_games_sort_header('phase', 'Phase', 'left', $sortState, 'Bracket phase when recorded (group, final, etc.).', 'Phase'); ?>
    <?php echo amiga_games_sort_header('result', 'Result', 'left', $sortState, 'Result from this player\'s perspective: win, draw, or loss.', 'Result', 'k2-table-cell--pad-left-xl'); ?>
    <?php echo amiga_games_sort_header('opponent', 'Opponent', 'left', $sortState, 'Opponent in this game.'); ?>
    <?php echo amiga_games_sort_header('for', 'F', 'right', $sortState, 'Goals scored by this player.', 'For', 'k2-table-cell--pad-left-md'); ?>
    <?php echo amiga_games_sort_header('against', 'A', 'right', $sortState, 'Goals conceded by this player.', 'Against'); ?>
    <?php echo amiga_games_sort_header('diff', 'Diff', 'right', $sortState, 'Goal difference from this player\'s perspective.'); ?>
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
    <?php echo amiga_player_game_row_html($game, $playerId, $sortedColIndex); ?>
    <?php } ?>
</tbody>

</table>

</div><!-- .k2-table-wrap -->

</div><!-- .k2-page-nav -->
</body>
</html>
