<?php
/**
 * Realm-wide All games list (`games/all.php`) — server sort + pagination + filters.
 */

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_routes.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_player_display_names.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_ratedresults_games_filters.php';

const K2_REALM_GAMES_ALL_PAGE_SIZE = 250;

function k2_realm_games_all_h(string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function k2_realm_games_all_valid_direction(string $value): string
{
	return strtolower($value) === 'asc' ? 'asc' : 'desc';
}

/** @return array<string, string> */
function k2_realm_games_all_sort_map(): array
{
	return [
		'id' => 'r.id',
		'date' => 'r.`Date`',
		'team_a' => 'r.NameA',
		'goals_a' => 'r.GoalsA',
		'goals_b' => 'r.GoalsB',
		'team_b' => 'r.NameB',
		'gd' => 'r.GoalDifference',
		'sum' => 'r.SumOfGoals',
		'top_score' => 'GREATEST(r.GoalsA, r.GoalsB)',
		'winner' => "CASE WHEN ABS(r.ActualScore - 0.5) < 0.001 THEN 'Draw' "
			. "WHEN ABS(r.ActualScore - 1.0) < 0.001 THEN r.NameA ELSE r.NameB END",
		'rating_a' => 'r.RatingA',
		'rating_b' => 'r.RatingB',
		'elo_diff' => 'r.RatingDifference',
		'fav_es' => 'GREATEST(r.ExpectedScoreA, r.ExpectedScoreB)',
		'adjustment' => 'GREATEST(ABS(r.AdjustmentA), ABS(r.AdjustmentB))',
	];
}

function k2_realm_games_all_valid_sort(string $sortKey): string
{
	$map = k2_realm_games_all_sort_map();

	return isset($map[$sortKey]) ? $sortKey : 'id';
}

function k2_realm_games_all_build_url(array $params): string
{
	$url = k2_route('games-all');
	if ($params === []) {
		return $url;
	}

	return $url . '?' . http_build_query($params);
}

/**
 * @return array{
 *     sort: string,
 *     dir: string,
 *     offset: int,
 *     player: int,
 *     opponent: int,
 *     gd: int,
 *     gs: int,
 *     ts: int,
 *     year: int,
 *     year_mode: string,
 *     player_via: string,
 *     opponent_via: string
 * }
 */
function k2_realm_games_all_valid_player_via(string $via): string
{
	return in_array($via, ['search', 'rating', 'alpha'], true) ? $via : '';
}

function k2_realm_games_all_valid_opponent_via(string $via): string
{
	return in_array($via, ['search', 'games', 'alpha'], true) ? $via : '';
}

function k2_realm_games_all_request_state(): array
{
	$playerId = isset($_GET['player']) ? max(0, (int) $_GET['player']) : 0;
	$opponentFilter = $playerId > 0 && isset($_GET['opponent']) ? max(0, (int) $_GET['opponent']) : 0;
	$playerVia = k2_realm_games_all_valid_player_via((string) ($_GET['player_via'] ?? ''));
	$opponentVia = k2_realm_games_all_valid_opponent_via((string) ($_GET['opponent_via'] ?? ''));

	$goalDiffFilter = isset($_GET['gd']) ? (int) $_GET['gd'] : -1;
	$goalsSumFilter = isset($_GET['gs']) ? (int) $_GET['gs'] : -1;
	$topScoreFilter = isset($_GET['ts']) ? (int) $_GET['ts'] : -1;
	if ($goalDiffFilter < -1) {
		$goalDiffFilter = -1;
	}
	if ($goalsSumFilter < -1) {
		$goalsSumFilter = -1;
	}
	if ($topScoreFilter < -1) {
		$topScoreFilter = -1;
	}

	$year = isset($_GET['year']) ? max(0, (int) $_GET['year']) : 0;
	$yearMode = k2_ratedresults_games_valid_year_mode((string) ($_GET['year_mode'] ?? 'in'));

	$sortKey = k2_realm_games_all_valid_sort((string) ($_GET['sort'] ?? 'id'));
	$sortDirection = k2_realm_games_all_valid_direction((string) ($_GET['dir'] ?? 'desc'));
	$offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;

	return [
		'sort' => $sortKey,
		'dir' => $sortDirection,
		'offset' => $offset,
		'player' => $playerId,
		'opponent' => $opponentFilter,
		'gd' => $goalDiffFilter,
		'gs' => $goalsSumFilter,
		'ts' => $topScoreFilter,
		'year' => $year,
		'year_mode' => $yearMode,
		'player_via' => $playerVia,
		'opponent_via' => $opponentVia,
	];
}

function k2_realm_games_all_query_all(mysqli $con, string $sql, string $types = '', array $params = []): array
{
	$stmt = mysqli_prepare($con, $sql);
	if ($stmt === false) {
		error_log('DB realm games all prepare failed: ' . mysqli_error($con));
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
		error_log('DB realm games all query failed: ' . mysqli_error($con));
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

/**
 * @param array{
 *     sort: string,
 *     dir: string,
 *     offset: int,
 *     player: int,
 *     opponent: int,
 *     result: string,
 *     gf: int,
 *     ga: int,
 *     gs: int
 * } $state
 */
function k2_realm_games_all_sanitize_scalar_filter(
	mysqli $con,
	string $sql,
	string $types,
	array $params
): bool {
	$rows = k2_realm_games_all_query_all($con, $sql, $types, $params);

	return $rows !== [];
}

function k2_realm_games_all_sanitize_filters(mysqli $con, array &$state): void
{
	if ($state['player'] > 0) {
		$playerRows = k2_realm_games_all_query_all(
			$con,
			'SELECT id FROM playertable WHERE id = ? AND Display = 1 LIMIT 1',
			'i',
			[$state['player']]
		);
		if ($playerRows === []) {
			$state['player'] = 0;
			$state['opponent'] = 0;
			$state['player_via'] = '';
			$state['opponent_via'] = '';
		}
	} else {
		$state['player'] = 0;
		$state['opponent'] = 0;
		$state['player_via'] = '';
		$state['opponent_via'] = '';
	}

	if ($state['player'] <= 0) {
		$state['player_via'] = '';
	} else {
		$state['player_via'] = k2_realm_games_all_valid_player_via((string) ($state['player_via'] ?? ''));
	}

	$playerId = $state['player'];

	if ($playerId > 0 && $state['opponent'] > 0) {
		$validOpponent = k2_realm_games_all_sanitize_scalar_filter(
			$con,
			'SELECT opponent_id FROM ('
				. 'SELECT idB AS opponent_id FROM ratedresults WHERE idA = ? '
				. 'UNION '
				. 'SELECT idA AS opponent_id FROM ratedresults WHERE idB = ?'
				. ') AS opponents WHERE opponent_id = ? LIMIT 1',
			'iii',
			[$playerId, $playerId, $state['opponent']]
		);
		if (!$validOpponent) {
			$state['opponent'] = 0;
		}
	} else {
		$state['opponent'] = 0;
	}

	if ($state['opponent'] <= 0) {
		$state['opponent_via'] = '';
	} else {
		$state['opponent_via'] = k2_realm_games_all_valid_opponent_via((string) ($state['opponent_via'] ?? ''));
	}

	if ($state['gd'] >= 0 && !k2_realm_games_all_sanitize_scalar_filter(
		$con,
		'SELECT id FROM ratedresults WHERE GoalDifference = ? LIMIT 1',
		'i',
		[$state['gd']]
	)) {
		$state['gd'] = -1;
	}

	if ($state['gs'] >= 0 && !k2_realm_games_all_sanitize_scalar_filter(
		$con,
		'SELECT id FROM ratedresults WHERE SumOfGoals = ? LIMIT 1',
		'i',
		[$state['gs']]
	)) {
		$state['gs'] = -1;
	}

	if ($state['ts'] >= 0 && !k2_realm_games_all_sanitize_scalar_filter(
		$con,
		'SELECT id FROM ratedresults WHERE GREATEST(GoalsA, GoalsB) = ? LIMIT 1',
		'i',
		[$state['ts']]
	)) {
		$state['ts'] = -1;
	}

	if ($state['year'] > 0) {
		$validYear = k2_realm_games_all_sanitize_scalar_filter(
			$con,
			'SELECT id FROM ratedresults WHERE YEAR(`Date`) = ? LIMIT 1',
			'i',
			[$state['year']]
		);
		if (!$validYear) {
			$state['year'] = 0;
		}
	} else {
		$state['year'] = 0;
	}

	$state['year_mode'] = k2_ratedresults_games_valid_year_mode($state['year_mode']);
}

/**
 * @param array{
 *     sort: string,
 *     dir: string,
 *     offset: int,
 *     player: int,
 *     opponent: int,
 *     result: string,
 *     gf: int,
 *     ga: int,
 *     gs: int
 * } $state
 * @param-out string $types
 * @param-out list<int|string> $params
 */
function k2_realm_games_all_where_sql(array $state, string &$types, array &$params): string
{
	return k2_ratedresults_games_where_clause(
		$state['player'],
		'all',
		$state['opponent'],
		-1,
		-1,
		$state['gs'],
		'',
		$types,
		$params,
		$state['gd'],
		$state['ts'],
		$state['year'],
		$state['year_mode']
	);
}

/**
 * @param array{
 *     sort: string,
 *     dir: string,
 *     offset: int,
 *     player: int,
 *     opponent: int,
 *     result: string,
 *     gf: int,
 *     ga: int,
 *     gs: int
 * } $state
 */
function k2_realm_games_all_count(mysqli $con, array $state): int
{
	$whereTypes = '';
	$whereParams = [];
	$whereSql = k2_realm_games_all_where_sql($state, $whereTypes, $whereParams);

	$rows = k2_realm_games_all_query_all(
		$con,
		'SELECT COUNT(*) AS c FROM ratedresults r WHERE ' . $whereSql,
		$whereTypes,
		$whereParams
	);

	return (int) ($rows[0]['c'] ?? 0);
}

/**
 * @param array{
 *     sort: string,
 *     dir: string,
 *     offset: int,
 *     player: int,
 *     opponent: int,
 *     result: string,
 *     gf: int,
 *     ga: int,
 *     gs: int
 * } $state
 * @return list<array<string, mixed>>
 */
function k2_realm_games_all_fetch_page(mysqli $con, array $state, int $limit): array
{
	$sortMap = k2_realm_games_all_sort_map();
	$sortSql = $sortMap[$state['sort']];
	$dirSql = strtoupper($state['dir']) === 'ASC' ? 'ASC' : 'DESC';
	$limit = max(1, $limit);
	$offset = max(0, $state['offset']);

	$whereTypes = '';
	$whereParams = [];
	$whereSql = k2_realm_games_all_where_sql($state, $whereTypes, $whereParams);

	$sql = 'SELECT r.id, r.Date, r.idA, r.NameA, r.idB, r.NameB, r.RatingA, r.RatingB, r.RatingDifference, '
		. 'r.GoalsA, r.GoalsB, r.ExpectedScoreA, r.ExpectedScoreB, r.ActualScore, r.AdjustmentA, r.AdjustmentB, '
		. 'r.SumOfGoals, r.GoalDifference '
		. 'FROM ratedresults r WHERE ' . $whereSql . ' ORDER BY ' . $sortSql . ' ' . $dirSql . ', r.id DESC '
		. 'LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

	return k2_realm_games_all_query_all($con, $sql, $whereTypes, $whereParams);
}

/**
 * @param array{
 *     sort: string,
 *     dir: string,
 *     offset: int,
 *     player: int,
 *     opponent: int,
 *     result: string,
 *     gf: int,
 *     ga: int,
 *     gs: int
 * } $state
 */
function k2_realm_games_all_has_active_filters(array $state): bool
{
	return $state['player'] > 0
		|| $state['opponent'] > 0
		|| $state['gd'] >= 0
		|| $state['gs'] >= 0
		|| $state['ts'] >= 0
		|| $state['year'] > 0;
}

/**
 * @param array{
 *     sort: string,
 *     dir: string,
 *     offset: int,
 *     player: int,
 *     opponent: int,
 *     result: string,
 *     gf: int,
 *     ga: int,
 *     gs: int
 * } $state
 */
function k2_realm_games_all_query_params(array $state, bool $includeOffset = true): array
{
	$params = [];
	if ($state['player'] > 0) {
		$params['player'] = $state['player'];
		if ($state['player_via'] !== '') {
			$params['player_via'] = $state['player_via'];
		}
		if ($state['opponent'] > 0) {
			$params['opponent'] = $state['opponent'];
			if ($state['opponent_via'] !== '') {
				$params['opponent_via'] = $state['opponent_via'];
			}
		}
	}
	if ($state['gd'] >= 0) {
		$params['gd'] = $state['gd'];
	}
	if ($state['gs'] >= 0) {
		$params['gs'] = $state['gs'];
	}
	if ($state['ts'] >= 0) {
		$params['ts'] = $state['ts'];
	}
	if ($state['year'] > 0) {
		$params['year'] = $state['year'];
		if ($state['year_mode'] !== 'in') {
			$params['year_mode'] = $state['year_mode'];
		}
	}
	if ($state['sort'] !== 'id') {
		$params['sort'] = $state['sort'];
	}
	if ($state['dir'] !== 'desc') {
		$params['dir'] = $state['dir'];
	}
	if ($includeOffset && $state['offset'] > 0) {
		$params['offset'] = $state['offset'];
	}

	return $params;
}

function k2_realm_games_all_sort_header(
	string $key,
	string $label,
	string $align,
	array $state,
	string $help,
	string $tooltipLabel = '',
	string $extraClass = ''
): string {
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

	$params = k2_realm_games_all_query_params($state, false);
	$params['sort'] = $key;
	$params['dir'] = $nextDir;

	$aria = $isActive ? ($state['dir'] === 'desc' ? 'descending' : 'ascending') : 'none';
	$attrs = [
		'class="' . implode(' ', $classes) . '"',
		'aria-sort="' . $aria . '"',
		'data-k2-help="' . k2_realm_games_all_h($help) . '"',
	];
	if ($tooltipLabel !== '') {
		$attrs[] = 'data-k2-tooltip-label="' . k2_realm_games_all_h($tooltipLabel) . '"';
	}

	return '<th ' . implode(' ', $attrs) . '>'
		. '<a href="' . k2_realm_games_all_h(k2_realm_games_all_build_url($params)) . '">' . $label . '</a>'
		. '</th>';
}

/**
 * @param array{
 *     sort: string,
 *     dir: string,
 *     offset: int,
 *     player: int,
 *     opponent: int,
 *     result: string,
 *     gf: int,
 *     ga: int,
 *     gs: int
 * } $state
 */
function k2_realm_games_all_pager_params(array $state): array
{
	return k2_realm_games_all_query_params($state, false);
}

/**
 * Opponent list for All games when a player filter is active (phase 2 UI).
 *
 * @return list<array{opponent_id: int, opponent_name: string, games: int}>
 */
function k2_realm_games_all_opponent_rows(mysqli $con, int $playerId): array
{
	if ($playerId <= 0) {
		return [];
	}

	return k2_realm_games_all_query_all(
		$con,
		k2_player_opponents_grouped_from_ratedresults_sql(),
		'ii',
		[$playerId, $playerId]
	);
}

function k2_realm_games_all_player_name(mysqli $con, int $playerId): string
{
	if ($playerId <= 0) {
		return '';
	}

	$rows = k2_realm_games_all_query_all(
		$con,
		'SELECT Name FROM playertable WHERE id = ? LIMIT 1',
		'i',
		[$playerId]
	);

	return (string) ($rows[0]['Name'] ?? '');
}
