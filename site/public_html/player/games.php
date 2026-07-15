<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; ?>
<script type="text/javascript" src="/js/k2-archive-listbox.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-archive-listbox.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/individual3-filters.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/individual3-filters.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-search.js" defer="defer"></script>

</head>

<body class="k2-site k2-player-wing">

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';

$playerId = k2_positive_int_param('id', 'Invalid player id.');

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_player_game_row.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_player_display_names.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_archive_listbox.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_ratedresults_games_filters.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_player_games_filter_facets.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_games_from.php';

function individual3_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function individual3_valid_result(string $value): string
{
    return k2_ratedresults_games_valid_result($value);
}

function individual3_valid_direction(string $value): string
{
    return strtolower($value) === 'asc' ? 'asc' : 'desc';
}

function individual3_build_url(array $params): string
{
    return '/player/games.php?' . http_build_query($params);
}

function individual3_query_all(mysqli $con, string $sql, string $types = '', array $params = []): array
{
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        error_log('DB player games prepare failed: ' . mysqli_error($con));
        k2_public_error('Could not load ratings data.');
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
    if ($result === false) {
        error_log('DB player games query failed: ' . mysqli_error($con));
        k2_public_error('Could not load ratings data.');
    }

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    mysqli_stmt_close($stmt);

    if ($rows !== [] && array_key_exists('idA', $rows[0]) && array_key_exists('NameA', $rows[0])) {
        $nameMap = k2_player_display_names_for_rated_rows($con, $rows);
        $rows = k2_rated_games_apply_display_names($rows, $nameMap);
    }

    return $rows;
}

function individual3_valid_day(string $value): string
{
    return k2_ratedresults_games_valid_day($value);
}

function individual3_format_utc_day_title(string $ymd): string
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $ymd, new DateTimeZone('UTC'));
    if ($dt === false) {
        return $ymd;
    }

    return $dt->format('l, M j, Y');
}

function individual3_format_utc_day_banner_html(string $ymd): string
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $ymd, new DateTimeZone('UTC'));
    if ($dt === false) {
        return individual3_h($ymd);
    }

    return individual3_h($dt->format('l')) . ', '
        . '<span class="k2-link-star k2-player-games-day-banner__date">' . individual3_h($dt->format('M j')) . '</span>, '
        . individual3_h($dt->format('Y'));
}

function individual3_has_games_filters_beyond_day(
    string $resultFilter,
    int $opponentFilter,
    int $goalsScoredFilter,
    int $goalsConcededFilter,
    int $goalsSumFilter,
    ?int $heroGoalDiffFilter = null
): bool {
    return $resultFilter !== 'all'
        || $opponentFilter > 0
        || $goalsScoredFilter >= 0
        || $goalsConcededFilter >= 0
        || $goalsSumFilter >= 0
        || $heroGoalDiffFilter !== null;
}

/**
 * @return array<string, int|string>
 */
function individual3_games_filter_params(array $state): array
{
    $params = [
        'id' => $state['player_id'],
        'sort' => $state['sort'],
        'dir' => $state['dir'],
    ];
    if (!empty($state['day'])) {
        $params['day'] = $state['day'];
    }
    if (!empty($state['period']) && !empty($state['anchor'])) {
        $params['period'] = $state['period'];
        $params['anchor'] = $state['anchor'];
    }
    if (!empty($state['from_game']) && !empty($state['to_game'])) {
        $params['from_game'] = $state['from_game'];
        $params['to_game'] = $state['to_game'];
        if (!empty($state['streak'])) {
            $params['streak'] = $state['streak'];
        }
    }
    $params = k2_player_games_with_from_param($params, (string) ($state['from'] ?? 'played-days'));
    if ($state['result'] !== 'all') {
        $params['result'] = $state['result'];
    }
    if ($state['opponent'] > 0) {
        $params['opponent'] = $state['opponent'];
    }
    if (($state['gf'] ?? -1) >= 0) {
        $params['gf'] = $state['gf'];
    }
    if (($state['ga'] ?? -1) >= 0) {
        $params['ga'] = $state['ga'];
    }
    if (($state['gs'] ?? -1) >= 0) {
        $params['gs'] = $state['gs'];
    }
    if (array_key_exists('gd', $state) && $state['gd'] !== null) {
        $params['gd'] = $state['gd'];
    }

    return $params;
}

function individual3_has_period_range_filter(array $state): bool
{
    $period = (string) ($state['period'] ?? '');

    return in_array($period, ['week', 'month', 'year'], true) && ($state['anchor'] ?? '') !== '';
}

function individual3_has_streak_run_filter(array $state): bool
{
    $from = (int) ($state['from_game'] ?? 0);
    $to = (int) ($state['to_game'] ?? 0);

    return $from > 0 && $to >= $from;
}

function individual3_games_filter_url(array $state, ?string $dayYmd, bool $withDayAnchor = true): string
{
    $params = individual3_games_filter_params($state);
    if ($dayYmd !== null && $dayYmd !== '') {
        $params['day'] = $dayYmd;
        unset($params['period'], $params['anchor']);
    }

    $url = individual3_build_url($params);
    if ($withDayAnchor && $dayYmd !== null && $dayYmd !== '') {
        $url .= '#day-games';
    }

    return $url;
}

/**
 * @return array{prev: ?string, next: ?string} Y-m-d UTC period anchors from player_period_games.
 */
function individual3_adjacent_played_periods(mysqli $con, int $playerId, string $periodType, string $anchorYmd): array
{
    if (!in_array($periodType, ['day', 'week', 'month', 'year'], true)) {
        return ['prev' => null, 'next' => null];
    }

    $prevRows = individual3_query_all(
        $con,
        'SELECT DATE_FORMAT(period_start, \'%Y-%m-%d\') AS d FROM player_period_games '
            . 'WHERE period_type = ? AND player_id = ? AND period_start < ? '
            . 'ORDER BY period_start DESC LIMIT 1',
        'sis',
        [$periodType, $playerId, $anchorYmd]
    );
    $nextRows = individual3_query_all(
        $con,
        'SELECT DATE_FORMAT(period_start, \'%Y-%m-%d\') AS d FROM player_period_games '
            . 'WHERE period_type = ? AND player_id = ? AND period_start > ? '
            . 'ORDER BY period_start ASC LIMIT 1',
        'sis',
        [$periodType, $playerId, $anchorYmd]
    );
    $prev = individual3_valid_day((string) ($prevRows[0]['d'] ?? ''));
    $next = individual3_valid_day((string) ($nextRows[0]['d'] ?? ''));

    return [
        'prev' => $prev !== '' ? $prev : null,
        'next' => $next !== '' ? $next : null,
    ];
}

function individual3_adjacent_played_days(mysqli $con, int $playerId, string $utcDay): array
{
    return individual3_adjacent_played_periods($con, $playerId, 'day', $utcDay);
}

function individual3_played_period_step_label(string $periodType, string $dir): string
{
    $noun = match ($periodType) {
        'week' => 'week',
        'month' => 'month',
        'year' => 'year',
        default => 'day',
    };

    return ($dir === 'prev' ? 'Previous' : 'Next') . ' played ' . $noun;
}

function individual3_played_period_step_href(array $state, string $periodType, string $anchorYmd): string
{
    if ($periodType === 'day') {
        return individual3_games_filter_url($state, $anchorYmd, false);
    }

    $params = individual3_games_filter_params($state);
    $params['period'] = $periodType;
    $params['anchor'] = $anchorYmd;
    unset($params['day']);

    return individual3_build_url($params);
}

function individual3_render_played_period_step(string $dir, ?string $anchorYmd, array $state, string $periodType): void
{
    $isPrev = $dir === 'prev';
    $label = individual3_played_period_step_label($periodType, $dir);
    $classes = 'k2-player-games-day-step k2-player-games-day-step--' . $dir;
    if ($anchorYmd === null || $anchorYmd === '') {
        echo '<span class="' . $classes . ' is-disabled" aria-disabled="true" aria-label="' . individual3_h($label) . '">';
        echo '<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span></span>';

        return;
    }
    $href = individual3_played_period_step_href($state, $periodType, $anchorYmd);
    echo '<a class="' . $classes . '" href="' . individual3_h($href) . '" aria-label="' . individual3_h($label) . '">';
    echo '<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span></a>';
}

function individual3_render_day_step(string $dir, ?string $dayYmd, array $state): void
{
    individual3_render_played_period_step($dir, $dayYmd, $state, 'day');
}

function individual3_render_page_nav(int $offset, int $limit, int $totalMatches, array $pagerParams): void
{
    echo '<nav class="k2-player-games-day-steps k2-realm-games-all__status-nav" aria-label="Page">';
    if ($offset > 0) {
        $prevParams = $pagerParams + ['offset' => max(0, $offset - $limit)];
        echo '<a class="k2-player-games-day-step k2-player-games-day-step--prev" href="'
            . individual3_h(individual3_build_url($prevParams))
            . '" aria-label="Previous page">';
        echo '<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span></a>';
    } else {
        echo '<span class="k2-player-games-day-step k2-player-games-day-step--prev is-disabled" aria-disabled="true" aria-label="Previous page">';
        echo '<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span></span>';
    }
    if ($offset + $limit < $totalMatches) {
        $nextParams = $pagerParams + ['offset' => $offset + $limit];
        echo '<a class="k2-player-games-day-step k2-player-games-day-step--next" href="'
            . individual3_h(individual3_build_url($nextParams))
            . '" aria-label="Next page">';
        echo '<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span></a>';
    } else {
        echo '<span class="k2-player-games-day-step k2-player-games-day-step--next is-disabled" aria-disabled="true" aria-label="Next page">';
        echo '<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span></span>';
    }
    echo '</nav>';
}

function individual3_where_clause(
    int $playerId,
    string $resultFilter,
    int $opponentId,
    int $goalsScoredFilter,
    int $goalsConcededFilter,
    int $goalsSumFilter,
    string $utcDay,
    string &$types,
    array &$params,
    string $periodType = '',
    string $periodAnchor = '',
    int $fromGameId = 0,
    int $toGameId = 0,
    ?int $heroGoalDiffFilter = null
): string {
    return k2_ratedresults_games_where_clause(
        $playerId,
        $resultFilter,
        $opponentId,
        $goalsScoredFilter,
        $goalsConcededFilter,
        $goalsSumFilter,
        $utcDay,
        $types,
        $params,
        -1,
        -1,
        0,
        '',
        $periodType,
        $periodAnchor,
        $fromGameId,
        $toGameId,
        $heroGoalDiffFilter
    );
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

    $params = individual3_games_filter_params($state);
    $params['sort'] = $key;
    $params['dir'] = $nextDir;
    unset($params['offset']);

    $aria = $isActive ? ($state['dir'] === 'desc' ? 'descending' : 'ascending') : 'none';
    $attrs = [
        'class="' . implode(' ', $classes) . '"',
        'aria-sort="' . $aria . '"',
    ];
    if ($help !== '') {
        $attrs[] = 'data-k2-help="' . individual3_h($help) . '"';
    }
    if ($tooltipLabel !== '') {
        $attrs[] = 'data-k2-tooltip-label="' . individual3_h($tooltipLabel) . '"';
    }

    return '<th ' . implode(' ', $attrs) . '>'
        . '<a href="' . individual3_h(individual3_build_url($params)) . '">' . $label . '</a>'
        . '</th>';
}
?>

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_wing_hub_nav.inc.php'; ?>

<?php 
include $_SERVER["DOCUMENT_ROOT"] . "/../config/ko2unitydb_config.php";
$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);

$id = $playerId;
include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_hero_vars.php";
if (empty($Name)) {
    k2_public_error('Player not found.', 404);
}
$name = $Name;

$resultFilter = individual3_valid_result((string) ($_GET['result'] ?? 'all'));
$opponentFilter = isset($_GET['opponent']) ? max(0, (int) $_GET['opponent']) : 0;
$goalsScoredFilter = isset($_GET['gf']) ? (int) $_GET['gf'] : -1;
$goalsConcededFilter = isset($_GET['ga']) ? (int) $_GET['ga'] : -1;
$goalsSumFilter = isset($_GET['gs']) ? (int) $_GET['gs'] : -1;
$heroGoalDiffFilter = isset($_GET['gd']) && $_GET['gd'] !== '' ? (int) $_GET['gd'] : null;
$gamesFrom = k2_player_games_valid_from((string) ($_GET['from'] ?? ''));
$utcDayFilter = individual3_valid_day((string) ($_GET['day'] ?? ''));
$periodType = k2_ratedresults_games_valid_period_type((string) ($_GET['period'] ?? ''));
$periodAnchor = individual3_valid_day((string) ($_GET['anchor'] ?? ''));
$fromGameFilter = k2_ratedresults_games_valid_game_id((int) ($_GET['from_game'] ?? 0));
$toGameFilter = k2_ratedresults_games_valid_game_id((int) ($_GET['to_game'] ?? 0));
$streakTypeFilter = k2_ratedresults_games_valid_streak_type((string) ($_GET['streak'] ?? ''));
$hasStreakRunFilter = false;
if (
    $fromGameFilter > 0
    && $toGameFilter >= $fromGameFilter
    && k2_ratedresults_games_valid_streak_run($con, $playerId, $fromGameFilter, $toGameFilter)
) {
    $hasStreakRunFilter = true;
    $utcDayFilter = '';
    $periodType = '';
    $periodAnchor = '';
} else {
    $fromGameFilter = 0;
    $toGameFilter = 0;
    $streakTypeFilter = '';
}
if ($utcDayFilter !== '') {
    $periodType = '';
    $periodAnchor = '';
    $fromGameFilter = 0;
    $toGameFilter = 0;
    $streakTypeFilter = '';
    $hasStreakRunFilter = false;
} elseif ($periodType === '' || $periodAnchor === '') {
    $periodType = '';
    $periodAnchor = '';
}
$hasPeriodRangeFilter = in_array($periodType, ['week', 'month', 'year'], true);
$hasPeriodGamesView = $utcDayFilter !== '' || $hasPeriodRangeFilter || $hasStreakRunFilter;
if (($utcDayFilter !== '' || $hasPeriodRangeFilter) && !isset($_GET['sort'])) {
    $sortKey = 'date';
    $sortDirection = 'desc';
} elseif ($hasStreakRunFilter && !isset($_GET['sort'])) {
    $sortKey = 'date';
    $sortDirection = 'desc';
} else {
    $sortKey = (string) ($_GET['sort'] ?? 'id');
    if ($sortKey === 'for') {
        $sortKey = 'goals_for';
    }
    $sortDirection = individual3_valid_direction((string) ($_GET['dir'] ?? 'desc'));
}
$limit = K2_PLAYER_GAMES_PAGE_SIZE;
$offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
$playerIdSql = (int) $playerId;

$sortMap = [
    'id' => 'r.id',
    'date' => 'r.`Date`',
    'team_a' => 'r.NameA',
    'team_b' => 'r.NameB',
    'result' => "CASE WHEN ((r.idA = $playerIdSql AND ABS(r.ActualScore - 1.0) < 0.001) OR (r.idB = $playerIdSql AND ABS(r.ActualScore) < 0.001)) THEN 2 WHEN ABS(r.ActualScore - 0.5) < 0.001 THEN 1 ELSE 0 END",
    'goals_for' => "CASE WHEN r.idA = $playerIdSql THEN r.GoalsA ELSE r.GoalsB END",
    'against' => "CASE WHEN r.idA = $playerIdSql THEN r.GoalsB ELSE r.GoalsA END",
    'diff' => "CASE WHEN r.idA = $playerIdSql THEN r.GoalsA - r.GoalsB ELSE r.GoalsB - r.GoalsA END",
    'sum' => 'r.SumOfGoals',
    'rating_a' => 'r.RatingA',
    'rating_b' => 'r.RatingB',
    'es' => "CASE WHEN r.idA = $playerIdSql THEN r.ExpectedScoreA ELSE r.ExpectedScoreB END",
    'adjustment' => "CASE WHEN r.idA = $playerIdSql THEN r.AdjustmentA ELSE r.AdjustmentB END",
];
if (!isset($sortMap[$sortKey])) {
    $sortKey = 'id';
}

$filterFacets = null;
$filterChoices = null;

k2_player_games_validate_filters_career_wide(
    $con,
    $playerId,
    $resultFilter,
    $opponentFilter,
    $goalsScoredFilter,
    $goalsConcededFilter,
    $goalsSumFilter,
    $heroGoalDiffFilter
);

$filterContext = k2_player_games_filter_context(
    $resultFilter,
    $opponentFilter,
    $goalsScoredFilter,
    $goalsConcededFilter,
    $goalsSumFilter,
    $heroGoalDiffFilter,
    $utcDayFilter,
    $periodType,
    $periodAnchor,
    $fromGameFilter,
    $toGameFilter
);

if (!$hasPeriodGamesView) {
    $filterFacets = k2_player_games_load_filter_facets($con, $playerId, $filterContext);
    $filterChoices = k2_player_games_facet_listbox_choices($con, $filterFacets, $filterContext);
}

$hasFilterLandingView = !$hasPeriodGamesView && individual3_has_games_filters_beyond_day(
    $resultFilter,
    $opponentFilter,
    $goalsScoredFilter,
    $goalsConcededFilter,
    $goalsSumFilter,
    $heroGoalDiffFilter
);

$whereTypes = '';
$whereParams = [];
$whereSql = individual3_where_clause(
    $playerId,
    $resultFilter,
    $opponentFilter,
    $goalsScoredFilter,
    $goalsConcededFilter,
    $goalsSumFilter,
    $utcDayFilter,
    $whereTypes,
    $whereParams,
    $periodType,
    $periodAnchor,
    $fromGameFilter,
    $toGameFilter,
    $heroGoalDiffFilter
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

$secondaryIdDir = ($sortKey === 'date' && $sortDirection === 'asc') ? 'ASC' : 'DESC';
$games = individual3_query_all(
    $con,
    'SELECT r.id, r.Date, r.idA, r.NameA, r.idB, r.NameB, r.RatingA, r.RatingB, r.GoalsA, r.GoalsB, r.ExpectedScoreA, r.ExpectedScoreB, r.ActualScore, r.AdjustmentA, r.AdjustmentB, r.SumOfGoals, r.GoalDifference, r.NewRatingA '
        . 'FROM ratedresults r WHERE ' . $whereSql
        . ' ORDER BY ' . $sortMap[$sortKey] . ' ' . strtoupper($sortDirection) . ', r.id ' . $secondaryIdDir
        . ' LIMIT ' . $limit . ' OFFSET ' . $offset,
    $whereTypes,
    $whereParams
);

$adjacentPlayedDays = ['prev' => null, 'next' => null];
$adjacentPlayedPeriod = ['prev' => null, 'next' => null];
if ($utcDayFilter !== '') {
    $adjacentPlayedDays = individual3_adjacent_played_periods($con, $playerId, 'day', $utcDayFilter);
} elseif ($hasPeriodRangeFilter) {
    $adjacentPlayedPeriod = individual3_adjacent_played_periods($con, $playerId, $periodType, $periodAnchor);
}

$streakRunStartAt = '';
$streakRunEndAt = '';
if ($hasStreakRunFilter) {
    $streakDateRows = individual3_query_all(
        $con,
        'SELECT `Date` FROM `ratedresults` WHERE `id` IN (?, ?) ORDER BY `id` ASC',
        'ii',
        [$fromGameFilter, $toGameFilter]
    );
    $streakRunStartAt = (string) ($streakDateRows[0]['Date'] ?? '');
    $streakRunEndAt = (string) ($streakDateRows[count($streakDateRows) - 1]['Date'] ?? '');
}

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
    'gf' => $goalsScoredFilter,
    'ga' => $goalsConcededFilter,
    'gs' => $goalsSumFilter,
    'gd' => $heroGoalDiffFilter,
    'day' => $utcDayFilter,
    'period' => $periodType,
    'anchor' => $periodAnchor,
    'from_game' => $fromGameFilter,
    'to_game' => $toGameFilter,
    'streak' => $streakTypeFilter,
    'from' => $gamesFrom,
];
$gamesBackLink = k2_player_games_from_back_link($gamesFrom, $playerId);
$shownCount = count($games);
$firstShown = $totalMatches > 0 ? $offset + 1 : 0;
$lastShown = $offset + $shownCount;
$pagerParams = individual3_games_filter_params($sortState);
$sortedColIndex = k2_player_game_sort_col_index($sortKey);
$showDrillDownPager = $hasPeriodGamesView && $totalMatches > $limit;
$showTableMeta = !$hasPeriodGamesView || $showDrillDownPager;

if ($filterChoices !== null) {
    $resultChoices = $filterChoices['result'];
    $opponentChoices = $filterChoices['opponent'];
    $goalsScoredChoices = $filterChoices['gf'];
    $goalsConcededChoices = $filterChoices['ga'];
    $goalsSumChoices = $filterChoices['gs'];
    $heroGoalDiffChoices = $filterChoices['gd'];
}
?>

<?php if (!$hasPeriodGamesView) { ?>
<div id="<?php echo K2_PLAYER_GAMES_FILTERS_ANCHOR; ?>" class="k2-player-games-filters-anchor" tabindex="-1"></div>
<form class="k2-player-games-controls" method="get" action="/player/games.php" data-k2-carry-scroll>
    <div class="k2-player-games-controls__meta">
        <input type="hidden" name="id" value="<?php echo $playerId; ?>" />
        <input type="hidden" name="sort" value="<?php echo individual3_h($sortKey); ?>" />
        <input type="hidden" name="dir" value="<?php echo individual3_h($sortDirection); ?>" />
        <?php if ($utcDayFilter !== '') { ?>
        <input type="hidden" name="day" value="<?php echo individual3_h($utcDayFilter); ?>" />
        <?php } elseif ($hasPeriodRangeFilter) { ?>
        <input type="hidden" name="period" value="<?php echo individual3_h($periodType); ?>" />
        <input type="hidden" name="anchor" value="<?php echo individual3_h($periodAnchor); ?>" />
        <?php } elseif ($hasStreakRunFilter) { ?>
        <input type="hidden" name="from_game" value="<?php echo (int) $fromGameFilter; ?>" />
        <input type="hidden" name="to_game" value="<?php echo (int) $toGameFilter; ?>" />
        <?php if ($streakTypeFilter !== '') { ?>
        <input type="hidden" name="streak" value="<?php echo individual3_h($streakTypeFilter); ?>" />
        <?php } ?>
        <?php } ?>
        <?php if ($gamesFrom !== 'played-days') { ?>
        <input type="hidden" name="from" value="<?php echo individual3_h($gamesFrom); ?>" />
        <?php } ?>
    </div>
    <div class="k2-player-games-controls__fields">
        <div class="k2-player-games-controls__field">
            <span class="server-period-activity-leaderboard__picker-label">Opponent</span>
            <?php k2_archive_listbox_render(
                'opponent',
                'k2-player-games-opponent',
                (string) $opponentFilter,
                $opponentChoices,
                'Filter by opponent',
                '',
                '',
                false,
                '0'
            ); ?>
        </div>
        <div class="k2-player-games-controls__field">
            <span class="server-period-activity-leaderboard__picker-label">Result</span>
            <?php k2_archive_listbox_render(
                'result',
                'k2-player-games-result',
                $resultFilter,
                $resultChoices,
                'Filter by result',
                '',
                '',
                false,
                'all'
            ); ?>
        </div>
        <div class="k2-player-games-controls__field">
            <span class="server-period-activity-leaderboard__picker-label">GF</span>
            <?php k2_archive_listbox_render(
                'gf',
                'k2-player-games-gf',
                (string) $goalsScoredFilter,
                $goalsScoredChoices,
                'Filter by goals for',
                '',
                '',
                false,
                '-1'
            ); ?>
        </div>
        <div class="k2-player-games-controls__field">
            <span class="server-period-activity-leaderboard__picker-label">GA</span>
            <?php k2_archive_listbox_render(
                'ga',
                'k2-player-games-ga',
                (string) $goalsConcededFilter,
                $goalsConcededChoices,
                'Filter by goals against',
                '',
                '',
                false,
                '-1'
            ); ?>
        </div>
        <div class="k2-player-games-controls__field">
            <span class="server-period-activity-leaderboard__picker-label">GD</span>
            <?php k2_archive_listbox_render(
                'gd',
                'k2-player-games-gd',
                $heroGoalDiffFilter !== null ? (string) $heroGoalDiffFilter : '',
                $heroGoalDiffChoices,
                'Filter by goal difference',
                '',
                '',
                false,
                ''
            ); ?>
        </div>
        <div class="k2-player-games-controls__field">
            <span class="server-period-activity-leaderboard__picker-label">SUM</span>
            <?php k2_archive_listbox_render(
                'gs',
                'k2-player-games-gs',
                (string) $goalsSumFilter,
                $goalsSumChoices,
                'Filter by goal sum',
                '',
                '',
                false,
                '-1'
            ); ?>
        </div>
    </div>
</form>
<?php } ?>

<?php if ($hasFilterLandingView) { ?>
<section class="k2-player-games-filter-view">
<?php } ?>

<?php if ($hasPeriodGamesView) { ?>
<section class="k2-player-games-day-view">
<div id="day-games" class="k2-player-games-day-anchor" tabindex="-1"></div>
<?php } ?>

<div id="<?php echo K2_PLAYER_MATCHING_GAMES_ANCHOR; ?>" class="k2-player-games-day-anchor" tabindex="-1"></div>
<div class="k2-player-games-status-stack" data-k2-carry-scroll>
<?php if ($hasPeriodGamesView) { ?>
<div class="k2-player-games-context">
    <?php if ($utcDayFilter !== '') {
        $dayBannerStory = !individual3_has_games_filters_beyond_day(
            $resultFilter,
            $opponentFilter,
            $goalsScoredFilter,
            $goalsConcededFilter,
            $goalsSumFilter,
            $heroGoalDiffFilter
        );
        $dayBannerGamesWord = $totalMatches === 1 ? 'game' : 'games';
        ?>
    <div class="k2-player-games-day-banner">
        <a class="k2-link-star k2-player-games-day-banner__back" href="<?php echo individual3_h($gamesBackLink['href']); ?>"><?php echo individual3_h($gamesBackLink['label']); ?></a>
        <span class="k2-player-games-day-banner__sep" aria-hidden="true">·</span>
        <nav class="k2-player-games-day-steps" aria-label="Adjacent played days">
            <?php individual3_render_day_step('prev', $adjacentPlayedDays['prev'], $sortState); ?>
            <?php individual3_render_day_step('next', $adjacentPlayedDays['next'], $sortState); ?>
        </nav>
        <span class="k2-player-games-day-banner__sep" aria-hidden="true">·</span>
        <span class="k2-player-games-day-banner__lead">
            <?php if ($dayBannerStory) { ?>
            <?php echo k2_player_link($playerId, $name); ?> played <span class="k2-link-star k2-player-games-day-banner__count"><?php echo $totalMatches; ?></span>
            <?php echo $dayBannerGamesWord; ?> on
            <?php } else { ?>
            <span class="k2-link-star k2-player-games-day-banner__count"><?php echo $totalMatches; ?></span>
            matching <?php echo $dayBannerGamesWord; ?> on
            <?php } ?>
            <?php echo individual3_format_utc_day_banner_html($utcDayFilter); ?>.
        </span>
    </div>
    <?php } elseif ($hasPeriodRangeFilter) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_activity_lib.php';
        $periodBannerStory = !individual3_has_games_filters_beyond_day(
            $resultFilter,
            $opponentFilter,
            $goalsScoredFilter,
            $goalsConcededFilter,
            $goalsSumFilter,
            $heroGoalDiffFilter
        );
        $periodBannerGamesWord = $totalMatches === 1 ? 'game' : 'games';
        $periodBannerLabel = k2_lb_activity_peak_period_filter_label($periodType, $periodAnchor);
        ?>
    <div class="k2-player-games-day-banner">
        <a class="k2-link-star k2-player-games-day-banner__back" href="<?php echo individual3_h($gamesBackLink['href']); ?>"><?php echo individual3_h($gamesBackLink['label']); ?></a>
        <span class="k2-player-games-day-banner__sep" aria-hidden="true">·</span>
        <nav class="k2-player-games-day-steps" aria-label="Adjacent played <?php echo individual3_h($periodType); ?>s">
            <?php individual3_render_played_period_step('prev', $adjacentPlayedPeriod['prev'], $sortState, $periodType); ?>
            <?php individual3_render_played_period_step('next', $adjacentPlayedPeriod['next'], $sortState, $periodType); ?>
        </nav>
        <span class="k2-player-games-day-banner__sep" aria-hidden="true">·</span>
        <span class="k2-player-games-day-banner__lead">
            <?php if ($periodBannerStory) { ?>
            <?php echo k2_player_link($playerId, $name); ?> played <span class="k2-link-star k2-player-games-day-banner__count"><?php echo $totalMatches; ?></span>
            <?php echo $periodBannerGamesWord; ?> in
            <?php } else { ?>
            <span class="k2-link-star k2-player-games-day-banner__count"><?php echo $totalMatches; ?></span>
            matching <?php echo $periodBannerGamesWord; ?> in
            <?php } ?>
            <span class="k2-link-star"><?php echo individual3_h($periodBannerLabel); ?></span>.
        </span>
    </div>
    <?php } elseif ($hasStreakRunFilter) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_result_streaks_lib.php';
        $streakBannerStory = !individual3_has_games_filters_beyond_day(
            $resultFilter,
            $opponentFilter,
            $goalsScoredFilter,
            $goalsConcededFilter,
            $goalsSumFilter,
            $heroGoalDiffFilter
        );
        $streakBannerGamesWord = $totalMatches === 1 ? 'game' : 'games';
        $streakLabel = $streakTypeFilter !== ''
            ? k2_lb_result_streaks_run_label($streakTypeFilter)
            : 'streak';
        $streakDateHtml = k2_lb_result_streaks_format_span_html($streakRunStartAt, $streakRunEndAt);
        ?>
    <div class="k2-player-games-day-banner">
        <a class="k2-link-star k2-player-games-day-banner__back" href="<?php echo individual3_h($gamesBackLink['href']); ?>"><?php echo individual3_h($gamesBackLink['label']); ?></a>
        <span class="k2-player-games-day-banner__sep" aria-hidden="true">·</span>
        <span class="k2-player-games-day-banner__lead">
            <?php if ($streakBannerStory) { ?>
            <?php echo k2_player_link($playerId, $name); ?> played <span class="k2-link-star k2-player-games-day-banner__count"><?php echo $totalMatches; ?></span>
            <?php echo $streakBannerGamesWord; ?> in this <?php echo individual3_h($streakLabel); ?>
            <?php } else { ?>
            <span class="k2-link-star k2-player-games-day-banner__count"><?php echo $totalMatches; ?></span>
            matching <?php echo $streakBannerGamesWord; ?> in this <?php echo individual3_h($streakLabel); ?>
            <?php } ?>
            <?php if ($streakDateHtml !== '') { ?>
            (<span class="k2-player-games-day-banner__dates"><?php echo $streakDateHtml; ?></span>).
            <?php } else { ?>.
            <?php } ?>
        </span>
    </div>
    <?php } ?>
</div>
<?php } ?>
<?php if ($showTableMeta) { ?>
<div class="k2-player-games-table-meta k2-realm-games-all__status">
    <div class="k2-realm-games-all__status-range">
        <span class="k2-realm-games-all__status-text">
            Showing <?php echo (int) $firstShown; ?>–<?php echo (int) $lastShown; ?> of <?php echo number_format($totalMatches); ?> matching games.
        </span>
        <?php individual3_render_page_nav($offset, $limit, $totalMatches, $pagerParams); ?>
    </div>
    <?php if (!$hasPeriodGamesView) { ?>
    <a class="k2-player-games-reset" href="/player/games.php?id=<?php echo $playerId; ?>">Reset filters</a>
    <?php } ?>
</div>
<?php } ?>
</div>

<?php k2_table_wrap_open(true); ?>

<table class="k2-table k2-table--numeric-default k2-table--calm-stats k2-table--player-games">

<thead>
<tr>
    <?php echo individual3_sort_header('id', 'ID', 'left', $sortState, 'Rated game ID.'); ?>
    <?php echo individual3_sort_header(
        'date',
        $utcDayFilter !== '' ? 'Time' : 'Date',
        'left',
        $sortState,
        $utcDayFilter !== '' ? 'UTC time the rated game was played.' : 'UTC date and time the rated game was played.',
        $utcDayFilter !== '' ? 'Time' : 'Date',
        'k2-table-cell--pad-left-xs'
    ); ?>
    <?php echo individual3_sort_header('team_a', 'Team A', 'right', $sortState, ''); ?>
    <th></th>
    <th></th>
    <?php echo individual3_sort_header('team_b', 'Team B', 'left', $sortState, ''); ?>
    <?php echo individual3_sort_header('goals_for', 'GF', 'right', $sortState, 'Goals scored by ' . $name . '.', 'Goals for', 'k2-table-cell--pad-left-md'); ?>
    <?php echo individual3_sort_header('against', 'GA', 'right', $sortState, 'Goals conceded by ' . $name . '.', 'Goals against'); ?>
    <?php echo individual3_sort_header('diff', 'GD', 'right', $sortState, 'Goal difference from ' . $name . '\'s perspective.', 'GD'); ?>
    <?php echo individual3_sort_header('sum', 'Sum', 'right', $sortState, 'Total goals scored by both players in the game.'); ?>
    <?php echo individual3_sort_header('rating_a', 'Rating A', 'right', $sortState, 'Player A\'s Elo rating before this game.', '', 'k2-table-cell--pad-left-md'); ?>
    <?php echo individual3_sort_header('rating_b', 'Rating B', 'right', $sortState, 'Player B\'s Elo rating before this game.'); ?>
    <?php echo individual3_sort_header('es', 'ES ' . individual3_h($name), 'right', $sortState, 'Expected score for ' . $name . ' before the game, based on the rating difference.', 'Expected score'); ?>
    <?php echo individual3_sort_header('result', 'Result', 'left', $sortState, 'Result from ' . $name . '\'s perspective: win, draw, or loss.', 'Result'); ?>
    <?php echo individual3_sort_header('adjustment', 'Adjustment', 'right', $sortState, 'Rating points gained or lost by ' . $name . ' after the game.'); ?>
    </tr>
</thead>

<tbody>
    <?php if ($games === []) { ?>
    <tr>
        <td colspan="15" class="k2-table-cell--left k2-games-day__empty">No games match these filters.</td>
    </tr>
    <?php } ?>
    <?php foreach ($games as $game) { ?>
    <?php echo k2_player_game_row_html($game, $playerId, $sortedColIndex, $utcDayFilter !== ''); ?>
    <?php } ?>
</tbody>

</table>

<?php k2_table_wrap_close(); ?><!-- .k2-table-wrap -->

<?php if ($hasFilterLandingView) { ?>
<div class="k2-player-games-filter-scroll-pad" aria-hidden="true"></div>
</section>
<?php } ?>

<?php if ($hasPeriodGamesView) { ?>
<div class="k2-player-games-day-scroll-pad" aria-hidden="true"></div>
</section>
<?php } ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_site_end.inc.php'; ?>
</body>
</html>




