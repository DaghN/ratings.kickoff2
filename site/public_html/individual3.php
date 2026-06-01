<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/k2_head.php"; ?>
<script type="text/javascript" src="js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="js/k2-archive-listbox.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-archive-listbox.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="js/individual3-filters.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/individual3-filters.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>

</head>

<body class="k2-site">

<?php
$playerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($playerId < 1) {
    exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_player_game_row.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_archive_listbox.php';

function individual3_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function individual3_valid_result(string $value): string
{
    return in_array($value, ['all', 'win', 'draw', 'loss'], true) ? $value : 'all';
}

function individual3_valid_direction(string $value): string
{
    return strtolower($value) === 'asc' ? 'asc' : 'desc';
}

function individual3_build_url(array $params): string
{
    return 'individual3.php?' . http_build_query($params);
}

function individual3_query_all(mysqli $con, string $sql, string $types = '', array $params = []): array
{
    $stmt = mysqli_prepare($con, $sql);
    if (!$stmt) {
        die("SELECT Error: " . mysqli_error($con));
    }

    if ($types !== '') {
        $refs = [];
        foreach ($params as $key => $value) {
            $refs[$key] = &$params[$key];
        }
        mysqli_stmt_bind_param($stmt, $types, ...$refs);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if (!$result) {
        die("SELECT Error: " . mysqli_error($con));
    }

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    mysqli_stmt_close($stmt);

    return $rows;
}

function individual3_valid_day(string $value): string
{
    $value = trim($value);
    if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
        return $value;
    }

    return '';
}

function individual3_where_clause(
    int $playerId,
    string $resultFilter,
    int $opponentId,
    string $utcDay,
    string &$types,
    array &$params
): string {
    $where = ['(r.idA = ? OR r.idB = ?)'];
    $types = 'ii';
    $params = [$playerId, $playerId];

    if ($utcDay !== '') {
        $where[] = 'DATE(r.`Date`) = ?';
        $types .= 's';
        $params[] = $utcDay;
    }

    if ($resultFilter === 'win') {
        $where[] = '((r.idA = ? AND ABS(r.ActualScore - 1.0) < 0.001) OR (r.idB = ? AND ABS(r.ActualScore) < 0.001))';
        $types .= 'ii';
        $params[] = $playerId;
        $params[] = $playerId;
    } elseif ($resultFilter === 'draw') {
        $where[] = 'ABS(r.ActualScore - 0.5) < 0.001';
    } elseif ($resultFilter === 'loss') {
        $where[] = '((r.idA = ? AND ABS(r.ActualScore) < 0.001) OR (r.idB = ? AND ABS(r.ActualScore - 1.0) < 0.001))';
        $types .= 'ii';
        $params[] = $playerId;
        $params[] = $playerId;
    }

    if ($opponentId > 0) {
        $where[] = '((r.idA = ? AND r.idB = ?) OR (r.idB = ? AND r.idA = ?))';
        $types .= 'iiii';
        $params[] = $playerId;
        $params[] = $opponentId;
        $params[] = $playerId;
        $params[] = $opponentId;
    }

    return implode(' AND ', $where);
}

function individual3_sort_header(string $key, string $label, string $align, array $state, string $help, string $tooltipLabel = '', string $extraClass = ''): string
{
    $isActive = $state['sort'] === $key;
    $nextDir = $isActive && $state['dir'] === 'desc' ? 'asc' : 'desc';
    $classes = ['k2-table-sortable'];
    if ($align === 'left') {
        $classes[] = 'k2-table-cell--left';
    }
    if ($extraClass !== '') {
        $classes[] = $extraClass;
    }
    if ($isActive) {
        $classes[] = $state['dir'] === 'desc' ? 'k2-table-sorted-desc' : 'k2-table-sorted-asc';
    }

    $params = [
        'id' => $state['player_id'],
        'sort' => $key,
        'dir' => $nextDir,
    ];
    if ($state['result'] !== 'all') {
        $params['result'] = $state['result'];
    }
    if ($state['opponent'] > 0) {
        $params['opponent'] = $state['opponent'];
    }
    if (!empty($state['day'])) {
        $params['day'] = $state['day'];
    }

    $aria = $isActive ? ($state['dir'] === 'desc' ? 'descending' : 'ascending') : 'none';
    $attrs = [
        'class="' . implode(' ', $classes) . '"',
        'aria-sort="' . $aria . '"',
        'data-k2-help="' . individual3_h($help) . '"',
    ];
    if ($tooltipLabel !== '') {
        $attrs[] = 'data-k2-tooltip-label="' . individual3_h($tooltipLabel) . '"';
    }

    return '<th ' . implode(' ', $attrs) . '>'
        . '<a href="' . individual3_h(individual3_build_url($params)) . '">' . $label . '</a>'
        . '</th>';
}
?>

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>

<?php 
include $_SERVER["DOCUMENT_ROOT"] . "/../config/ko2unitydb_config.php";
//mysql_connect(localhost,$username,$password);
//@mysql_select_db($database) or die( "Unable to select database");
	$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
	if (mysqli_connect_errno())
  	{
  		die("Failed to connect to MySQL: " . mysqli_connect_error());
  	}
    $con->set_charset('utf8mb4');
    $con->query("SET time_zone = '+00:00'");
$con->query("SET time_zone = '+00:00'");

$id = $playerId;
include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_hero_vars.php";
$name = $Name ?? '';

$resultFilter = individual3_valid_result((string) ($_GET['result'] ?? 'all'));
$opponentFilter = isset($_GET['opponent']) ? max(0, (int) $_GET['opponent']) : 0;
$utcDayFilter = individual3_valid_day((string) ($_GET['day'] ?? ''));
$sortKey = (string) ($_GET['sort'] ?? 'id');
$sortDirection = individual3_valid_direction((string) ($_GET['dir'] ?? 'desc'));
$limit = 100;
$offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
$playerIdSql = (int) $playerId;

$sortMap = [
    'id' => 'r.id',
    'date' => 'r.`Date`',
    'team_a' => 'r.NameA',
    'team_b' => 'r.NameB',
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

$opponentRows = individual3_query_all(
    $con,
    'SELECT opponent_id, opponent_name, COUNT(*) AS games FROM ('
        . 'SELECT idB AS opponent_id, NameB AS opponent_name FROM ratedresults WHERE idA = ? '
        . 'UNION ALL '
        . 'SELECT idA AS opponent_id, NameA AS opponent_name FROM ratedresults WHERE idB = ?'
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
$whereSql = individual3_where_clause(
    $playerId,
    $resultFilter,
    $opponentFilter,
    $utcDayFilter,
    $whereTypes,
    $whereParams
);

$countRows = individual3_query_all(
    $con,
    'SELECT COUNT(*) AS c FROM ratedresults r WHERE ' . $whereSql,
    $whereTypes,
    $whereParams
);
$totalMatches = (int) ($countRows[0]['c'] ?? 0);
if ($offset >= $totalMatches) {
    $offset = 0;
}

$games = individual3_query_all(
    $con,
    'SELECT r.id, r.Date, r.idA, r.NameA, r.idB, r.NameB, r.RatingA, r.RatingB, r.GoalsA, r.GoalsB, r.ExpectedScoreA, r.ExpectedScoreB, r.ActualScore, r.AdjustmentA, r.AdjustmentB, r.SumOfGoals, r.GoalDifference '
        . 'FROM ratedresults r WHERE ' . $whereSql
        . ' ORDER BY ' . $sortMap[$sortKey] . ' ' . strtoupper($sortDirection) . ', r.id DESC'
        . ' LIMIT ' . $limit . ' OFFSET ' . $offset,
    $whereTypes,
    $whereParams
);

mysqli_close($con);
?>

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_hero.php"; ?>
<?php
$k2PlayerTabActive = 'games';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_nav.php";
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
$sortedColIndex = k2_player_game_sort_col_index($sortKey);

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

<form class="k2-player-games-controls" method="get" action="individual3.php">
    <input type="hidden" name="id" value="<?php echo $playerId; ?>" />
    <input type="hidden" name="sort" value="<?php echo individual3_h($sortKey); ?>" />
    <input type="hidden" name="dir" value="<?php echo individual3_h($sortDirection); ?>" />
    <?php if ($utcDayFilter !== '') { ?>
    <input type="hidden" name="day" value="<?php echo individual3_h($utcDayFilter); ?>" />
    <?php } ?>
    <div class="k2-player-games-controls__field">
        <span class="server-period-activity-leaderboard__picker-label">Result</span>
        <?php k2_archive_listbox_render('result', 'k2-player-games-result', $resultFilter, $resultChoices, 'Filter by result'); ?>
    </div>
    <div class="k2-player-games-controls__field">
        <span class="server-period-activity-leaderboard__picker-label">Opponent</span>
        <?php k2_archive_listbox_render('opponent', 'k2-player-games-opponent', (string) $opponentFilter, $opponentChoices, 'Filter by opponent'); ?>
    </div>
    <a class="k2-player-games-action" href="individual3.php?id=<?php echo $playerId; ?>">Reset</a>
</form>

<div class="k2-player-games-status">
    <?php if ($utcDayFilter !== '') { ?>
    Rated games on <strong><?php echo individual3_h($utcDayFilter); ?></strong> UTC
    (<a href="<?php echo individual3_h(individual3_build_url(['id' => $playerId, 'sort' => $sortKey, 'dir' => $sortDirection] + ($resultFilter !== 'all' ? ['result' => $resultFilter] : []) + ($opponentFilter > 0 ? ['opponent' => $opponentFilter] : []))); ?>">clear day filter</a>).
    <?php } ?>
    Showing <?php echo $firstShown; ?>-<?php echo $lastShown; ?> of <?php echo $totalMatches; ?> matching games.
    <?php if ($offset > 0) { ?>
    <?php $prevParams = $pagerParams + ['offset' => max(0, $offset - $limit)]; ?>
    <a class="k2-player-games-action" href="<?php echo individual3_h(individual3_build_url($prevParams)); ?>">Previous 100</a>
    <?php } ?>
    <?php if ($offset + $limit < $totalMatches) { ?>
    <?php $nextParams = $pagerParams + ['offset' => $offset + $limit]; ?>
    <a class="k2-player-games-action" href="<?php echo individual3_h(individual3_build_url($nextParams)); ?>">Next 100</a>
    <?php } ?>
</div>

<div class="k2-table-wrap">

<table class="k2-table k2-table--numeric-default k2-table--calm-stats k2-table--player-games">

<thead>
<tr>
    <?php echo individual3_sort_header('id', 'ID', 'left', $sortState, 'Rated game ID.'); ?>
    <?php echo individual3_sort_header('date', 'Date', 'left', $sortState, 'Date the rated game was played.', 'Date', 'k2-table-cell--pad-left-xs'); ?>
    <?php echo individual3_sort_header('team_a', 'Team A', 'right', $sortState, 'Player listed as Team A in the original game record.'); ?>
    <th></th>
    <th></th>
    <?php echo individual3_sort_header('team_b', 'Team B', 'left', $sortState, 'Player listed as Team B in the original game record.'); ?>
    <?php echo individual3_sort_header('result', 'Result', 'left', $sortState, 'Result from this player\'s perspective: win, draw, or loss.', 'Result', 'k2-table-cell--pad-left-xl'); ?>
    <?php echo individual3_sort_header('opponent', 'Opponent', 'left', $sortState, 'Opponent in this game.'); ?>
    <?php echo individual3_sort_header('for', 'F', 'right', $sortState, 'Goals scored by this player.', 'For', 'k2-table-cell--pad-left-md'); ?>
    <?php echo individual3_sort_header('against', 'A', 'right', $sortState, 'Goals conceded by this player.', 'Against'); ?>
    <?php echo individual3_sort_header('diff', 'Diff', 'right', $sortState, 'Goal difference from this player\'s perspective.'); ?>
    <?php echo individual3_sort_header('sum', 'Sum', 'right', $sortState, 'Total goals scored by both players in the game.'); ?>
    <?php echo individual3_sort_header('player_rating', individual3_h($name), 'right', $sortState, 'This player\'s Elo rating before the game.', $name . ' rating', 'k2-table-cell--pad-left-md'); ?>
    <?php echo individual3_sort_header('opponent_rating', 'Opponent', 'right', $sortState, 'Opponent Elo rating before the game.', 'Opponent rating'); ?>
    <?php echo individual3_sort_header('es', 'ES ' . individual3_h($name), 'right', $sortState, 'Expected score for this player before the game, based on the rating difference.', 'Expected score'); ?>
    <?php echo individual3_sort_header('adjustment', 'Adjustment', 'right', $sortState, 'Rating points gained or lost by this player after the game.'); ?>
    </tr>
</thead>

<tbody>
    <?php if ($games === []) { ?>
    <tr>
        <td colspan="16" class="k2-table-cell--left k2-games-day__empty">No games match these filters.</td>
    </tr>
    <?php } ?>
    <?php foreach ($games as $game) { ?>
    <?php echo k2_player_game_row_html($game, $playerId, $sortedColIndex); ?>
    <?php } ?>
</tbody>

</table>

</div><!-- .k2-table-wrap -->

</div><!-- .k2-page-nav -->
</body>
</html>




