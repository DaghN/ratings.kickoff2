<?php
/**
 * Faceted filter counts for online player/games.php listboxes.
 *
 * Each facet query applies all active filters except the dimension being edited.
 *
 * @see docs/k2-table-and-games-plan.md — Player Games Contract
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_games_filter_facet_helpers.php';
require_once __DIR__ . '/k2_ratedresults_games_filters.php';
require_once __DIR__ . '/k2_player_display_names.php';

/**
 * @return array{
 *   result: string,
 *   opponent: int,
 *   gf: int,
 *   ga: int,
 *   gs: int,
 *   gd: ?int,
 *   utcDay: string,
 *   periodType: string,
 *   periodAnchor: string,
 *   fromGameId: int,
 *   toGameId: int
 * }
 */
function k2_player_games_filter_context(
	string $resultFilter,
	int $opponentFilter,
	int $goalsScoredFilter,
	int $goalsConcededFilter,
	int $goalsSumFilter,
	?int $heroGoalDiffFilter,
	string $utcDayFilter,
	string $periodType,
	string $periodAnchor,
	int $fromGameFilter,
	int $toGameFilter
): array {
	return [
		'result' => $resultFilter,
		'opponent' => $opponentFilter,
		'gf' => $goalsScoredFilter,
		'ga' => $goalsConcededFilter,
		'gs' => $goalsSumFilter,
		'gd' => $heroGoalDiffFilter,
		'utcDay' => $utcDayFilter,
		'periodType' => $periodType,
		'periodAnchor' => $periodAnchor,
		'fromGameId' => $fromGameFilter,
		'toGameId' => $toGameFilter,
	];
}

/**
 * @param-out string $types
 * @param-out list<int|string> $params
 */
function k2_player_games_facet_where(
	int $playerId,
	array $ctx,
	string $omitFacet,
	string &$types,
	array &$params
): string {
	$result = (string) $ctx['result'];
	$opponent = (int) $ctx['opponent'];
	$gf = (int) $ctx['gf'];
	$ga = (int) $ctx['ga'];
	$gs = (int) $ctx['gs'];
	$gd = $ctx['gd'];

	if ($omitFacet === 'result') {
		$result = 'all';
	} elseif ($omitFacet === 'opponent') {
		$opponent = 0;
	} elseif ($omitFacet === 'gf') {
		$gf = -1;
	} elseif ($omitFacet === 'ga') {
		$ga = -1;
	} elseif ($omitFacet === 'gs') {
		$gs = -1;
	} elseif ($omitFacet === 'gd') {
		$gd = null;
	}

	return k2_ratedresults_games_where_clause(
		$playerId,
		$result,
		$opponent,
		$gf,
		$ga,
		$gs,
		(string) $ctx['utcDay'],
		$types,
		$params,
		-1,
		-1,
		0,
		'',
		(string) $ctx['periodType'],
		(string) $ctx['periodAnchor'],
		(int) $ctx['fromGameId'],
		(int) $ctx['toGameId'],
		$gd
	);
}

/**
 * @return array{
 *   result: string,
 *   opponent: int,
 *   gf: int,
 *   ga: int,
 *   gs: int,
 *   gd: ?int,
 *   utcDay: string,
 *   periodType: string,
 *   periodAnchor: string,
 *   fromGameId: int,
 *   toGameId: int
 * }
 */
function k2_player_games_career_wide_filter_context(): array
{
	return k2_player_games_filter_context('all', 0, -1, -1, -1, null, '', '', '', 0, 0);
}

/** @param array<string, mixed> $ctx */
function k2_player_games_filter_context_is_career_wide(array $ctx): bool
{
	return ($ctx['result'] ?? 'all') === 'all'
		&& (int) ($ctx['opponent'] ?? 0) === 0
		&& (string) ($ctx['utcDay'] ?? '') === ''
		&& (string) ($ctx['periodType'] ?? '') === ''
		&& (string) ($ctx['periodAnchor'] ?? '') === ''
		&& (int) ($ctx['fromGameId'] ?? 0) === 0
		&& (int) ($ctx['toGameId'] ?? 0) === 0
		&& (int) ($ctx['gf'] ?? -1) === -1
		&& (int) ($ctx['ga'] ?? -1) === -1
		&& (int) ($ctx['gs'] ?? -1) === -1
		&& ($ctx['gd'] ?? null) === null;
}

/** @param array<string, mixed> $ctx */
function k2_player_games_facet_context_cache_key(int $playerId, array $ctx): string
{
	return (int) $playerId . '|'
		. (string) ($ctx['result'] ?? 'all') . '|' . (int) ($ctx['opponent'] ?? 0) . '|'
		. (string) ($ctx['utcDay'] ?? '') . '|' . (string) ($ctx['periodType'] ?? '') . '|' . (string) ($ctx['periodAnchor'] ?? '') . '|'
		. (int) ($ctx['fromGameId'] ?? 0) . '|' . (int) ($ctx['toGameId'] ?? 0) . '|'
		. (int) ($ctx['gf'] ?? -1) . '|' . (int) ($ctx['ga'] ?? -1) . '|' . (int) ($ctx['gs'] ?? -1) . '|'
		. ($ctx['gd'] === null ? '' : (string) (int) $ctx['gd']);
}

/** @return list<array<string, mixed>> */
function k2_player_games_facet_query_rows(mysqli $con, string $sql, string $types, array $params): array
{
	return k2_games_facet_query_rows($con, $sql, $types, $params);
}

/** @param array<int, int> $sparse @return array<int, int> */
function k2_player_games_facet_expand_numeric_gaps(array $sparse): array
{
	return k2_games_facet_expand_numeric_gaps($sparse);
}

/**
 * @param array<int, int> $counts
 * @return list<array{value: string, label: string, meta: string}>
 */
function k2_player_games_facet_numeric_choices(
	array $counts,
	string $idleValue,
	bool $desc,
	?callable $labelFn = null
): array {
	return k2_games_facet_numeric_choices($counts, $idleValue, $desc, $labelFn);
}

/** @return array{win: int, draw: int, loss: int} */
function k2_player_games_facet_result_counts(mysqli $con, int $playerId, array $ctx): array
{
	$types = '';
	$params = [];
	$where = k2_player_games_facet_where($playerId, $ctx, 'result', $types, $params);
	$playerIdSql = (int) $playerId;
	$sql = 'SELECT CASE '
		. "WHEN ((r.idA = $playerIdSql AND ABS(r.ActualScore - 1.0) < 0.001) OR (r.idB = $playerIdSql AND ABS(r.ActualScore) < 0.001)) THEN 'win' "
		. "WHEN ABS(r.ActualScore - 0.5) < 0.001 THEN 'draw' "
		. "ELSE 'loss' END AS bucket, COUNT(*) AS games "
		. 'FROM ratedresults r WHERE ' . $where . ' GROUP BY bucket';

	$counts = ['win' => 0, 'draw' => 0, 'loss' => 0];
	foreach (k2_player_games_facet_query_rows($con, $sql, $types, $params) as $row) {
		$bucket = (string) ($row['bucket'] ?? '');
		if (isset($counts[$bucket])) {
			$counts[$bucket] = (int) ($row['games'] ?? 0);
		}
	}

	return $counts;
}

/**
 * @return list<array{opponent_id: int, opponent_name: string, games: int}>
 */
function k2_player_games_facet_opponent_rows(mysqli $con, int $playerId, array $ctx): array
{
	$types = '';
	$params = [];
	$where = k2_player_games_facet_where($playerId, $ctx, 'opponent', $types, $params);
	$playerIdSql = (int) $playerId;
	$sql = 'SELECT s.opponent_id, COALESCE(p.Name, CONCAT(\'#\', s.opponent_id)) AS opponent_name, COUNT(*) AS games '
		. 'FROM ('
		. "SELECT CASE WHEN r.idA = $playerIdSql THEN r.idB ELSE r.idA END AS opponent_id "
		. 'FROM ratedresults r WHERE ' . $where
		. ') AS s '
		. 'LEFT JOIN playertable p ON p.ID = s.opponent_id '
		. 'GROUP BY s.opponent_id, opponent_name '
		. 'ORDER BY games DESC, opponent_name ASC';

	$rows = [];
	foreach (k2_player_games_facet_query_rows($con, $sql, $types, $params) as $row) {
		$rows[] = [
			'opponent_id' => (int) ($row['opponent_id'] ?? 0),
			'opponent_name' => (string) ($row['opponent_name'] ?? ''),
			'games' => (int) ($row['games'] ?? 0),
		];
	}

	return $rows;
}

/** @return array<int, int> */
function k2_player_games_facet_numeric_counts(
	mysqli $con,
	int $playerId,
	array $ctx,
	string $omitFacet,
	string $valueExpr,
	string $orderBy
): array {
	$types = '';
	$params = [];
	$where = k2_player_games_facet_where($playerId, $ctx, $omitFacet, $types, $params);
	$sql = 'SELECT ' . $valueExpr . ' AS v, COUNT(*) AS games '
		. 'FROM ratedresults r WHERE ' . $where . ' GROUP BY v ORDER BY ' . $orderBy;

	$sparse = [];
	foreach (k2_player_games_facet_query_rows($con, $sql, $types, $params) as $row) {
		$sparse[(int) ($row['v'] ?? 0)] = (int) ($row['games'] ?? 0);
	}

	return k2_player_games_facet_expand_numeric_gaps($sparse);
}

/**
 * @return array{gf: array<int, int>, ga: array<int, int>, gs: array<int, int>, gd: array<int, int>}
 */
function k2_player_games_facet_score_line_counts_single_pass(mysqli $con, int $playerId, array $ctx): array
{
	$playerIdSql = (int) $playerId;
	$types = '';
	$params = [];
	$where = k2_player_games_facet_where($playerId, $ctx, 'gf', $types, $params);
	$sql = 'SELECT '
		. "CASE WHEN r.idA = $playerIdSql THEN r.GoalsA ELSE r.GoalsB END AS gf, "
		. "CASE WHEN r.idA = $playerIdSql THEN r.GoalsB ELSE r.GoalsA END AS ga, "
		. 'r.SumOfGoals AS gs, '
		. "CASE WHEN r.idA = $playerIdSql THEN r.GoalsA - r.GoalsB ELSE r.GoalsB - r.GoalsA END AS gd "
		. 'FROM ratedresults r WHERE ' . $where;

	$gfSparse = [];
	$gaSparse = [];
	$gsSparse = [];
	$gdSparse = [];
	foreach (k2_player_games_facet_query_rows($con, $sql, $types, $params) as $row) {
		$gf = (int) ($row['gf'] ?? 0);
		$ga = (int) ($row['ga'] ?? 0);
		$gs = (int) ($row['gs'] ?? 0);
		$gd = (int) ($row['gd'] ?? 0);
		$gfSparse[$gf] = ($gfSparse[$gf] ?? 0) + 1;
		$gaSparse[$ga] = ($gaSparse[$ga] ?? 0) + 1;
		$gsSparse[$gs] = ($gsSparse[$gs] ?? 0) + 1;
		$gdSparse[$gd] = ($gdSparse[$gd] ?? 0) + 1;
	}

	return [
		'gf' => k2_player_games_facet_expand_numeric_gaps($gfSparse),
		'ga' => k2_player_games_facet_expand_numeric_gaps($gaSparse),
		'gs' => k2_player_games_facet_expand_numeric_gaps($gsSparse),
		'gd' => k2_player_games_facet_expand_numeric_gaps($gdSparse),
	];
}

/**
 * @return array{
 *   result: array{win: int, draw: int, loss: int},
 *   opponent: list<array{opponent_id: int, opponent_name: string, games: int}>,
 *   gf: array<int, int>,
 *   ga: array<int, int>,
 *   gs: array<int, int>,
 *   gd: array<int, int>
 * }
 */
function k2_player_games_load_filter_facets_uncached(mysqli $con, int $playerId, array $ctx): array
{
	$playerIdSql = (int) $playerId;
	$heroGoalsFor = "CASE WHEN r.idA = $playerIdSql THEN r.GoalsA ELSE r.GoalsB END";
	$heroGoalsAgainst = "CASE WHEN r.idA = $playerIdSql THEN r.GoalsB ELSE r.GoalsA END";
	$heroGd = "CASE WHEN r.idA = $playerIdSql THEN r.GoalsA - r.GoalsB ELSE r.GoalsB - r.GoalsA END";

	$gfActive = (int) ($ctx['gf'] ?? -1) >= 0;
	$gaActive = (int) ($ctx['ga'] ?? -1) >= 0;
	$gsActive = (int) ($ctx['gs'] ?? -1) >= 0;
	$gdActive = ($ctx['gd'] ?? null) !== null;

	if (!$gfActive && !$gaActive && !$gsActive && !$gdActive) {
		$scoreLine = k2_player_games_facet_score_line_counts_single_pass($con, $playerId, $ctx);
	} else {
		$scoreLine = [
			'gf' => k2_player_games_facet_numeric_counts($con, $playerId, $ctx, 'gf', $heroGoalsFor, 'v ASC'),
			'ga' => k2_player_games_facet_numeric_counts($con, $playerId, $ctx, 'ga', $heroGoalsAgainst, 'v ASC'),
			'gs' => k2_player_games_facet_numeric_counts($con, $playerId, $ctx, 'gs', 'r.SumOfGoals', 'v ASC'),
			'gd' => k2_player_games_facet_numeric_counts($con, $playerId, $ctx, 'gd', $heroGd, 'v DESC'),
		];
	}

	return [
		'result' => k2_player_games_facet_result_counts($con, $playerId, $ctx),
		'opponent' => k2_player_games_facet_opponent_rows($con, $playerId, $ctx),
		'gf' => $scoreLine['gf'],
		'ga' => $scoreLine['ga'],
		'gs' => $scoreLine['gs'],
		'gd' => $scoreLine['gd'],
	];
}

/**
 * Career-wide facet bundle — shared by validate + unfiltered listbox load.
 *
 * @return array{
 *   result: array{win: int, draw: int, loss: int},
 *   opponent: list<array{opponent_id: int, opponent_name: string, games: int}>,
 *   gf: array<int, int>,
 *   ga: array<int, int>,
 *   gs: array<int, int>,
 *   gd: array<int, int>
 * }
 */
function k2_player_games_career_wide_facets(mysqli $con, int $playerId): array
{
	static $cache = [];

	$ctx = k2_player_games_career_wide_filter_context();
	$cacheKey = k2_player_games_facet_context_cache_key($playerId, $ctx) . '|career';
	if (isset($cache[$cacheKey])) {
		return $cache[$cacheKey];
	}

	return $cache[$cacheKey] = k2_player_games_load_filter_facets_uncached($con, $playerId, $ctx);
}

/**
 * @return array{
 *   result: array{win: int, draw: int, loss: int},
 *   opponent: list<array{opponent_id: int, opponent_name: string, games: int}>,
 *   gf: array<int, int>,
 *   ga: array<int, int>,
 *   gs: array<int, int>,
 *   gd: array<int, int>
 * }
 */
function k2_player_games_load_filter_facets(mysqli $con, int $playerId, array $ctx): array
{
	static $cache = [];

	if (k2_player_games_filter_context_is_career_wide($ctx)) {
		return k2_player_games_career_wide_facets($con, $playerId);
	}

	$cacheKey = k2_player_games_facet_context_cache_key($playerId, $ctx);
	if (isset($cache[$cacheKey])) {
		return $cache[$cacheKey];
	}

	return $cache[$cacheKey] = k2_player_games_load_filter_facets_uncached($con, $playerId, $ctx);
}

/** @param array<int, int> $counts */
function k2_player_games_facet_inject_selected_numeric(array $counts, int $selected): array
{
	return k2_games_facet_inject_selected_numeric($counts, $selected);
}

/**
 * @param list<array{opponent_id: int, opponent_name: string, games: int}> $rows
 * @return list<array{opponent_id: int, opponent_name: string, games: int}>
 */
function k2_player_games_facet_inject_selected_opponent(mysqli $con, array $rows, int $opponentId): array
{
	if ($opponentId < 1) {
		return $rows;
	}

	foreach ($rows as $row) {
		if ((int) $row['opponent_id'] === $opponentId) {
			return $rows;
		}
	}

	$nameRows = k2_player_games_facet_query_rows(
		$con,
		'SELECT COALESCE(Name, CONCAT(\'#\', ID)) AS opponent_name FROM playertable WHERE ID = ?',
		'i',
		[$opponentId]
	);
	$rows[] = [
		'opponent_id' => $opponentId,
		'opponent_name' => (string) ($nameRows[0]['opponent_name'] ?? ('#' . $opponentId)),
		'games' => 0,
	];

	return $rows;
}

/**
 * @param array{
 *   result: array{win: int, draw: int, loss: int},
 *   opponent: list<array{opponent_id: int, opponent_name: string, games: int}>,
 *   gf: array<int, int>,
 *   ga: array<int, int>,
 *   gs: array<int, int>,
 *   gd: array<int, int>
 * } $facets
 * @param array{
 *   result: string,
 *   opponent: int,
 *   gf: int,
 *   ga: int,
 *   gs: int,
 *   gd: ?int,
 *   utcDay: string,
 *   periodType: string,
 *   periodAnchor: string,
 *   fromGameId: int,
 *   toGameId: int
 * } $ctx
 * @return array{
 *   result: list<array{value: string, label: string, meta: string}>,
 *   opponent: list<array{value: string, label: string, meta: string}>,
 *   gf: list<array{value: string, label: string, meta: string}>,
 *   ga: list<array{value: string, label: string, meta: string}>,
 *   gs: list<array{value: string, label: string, meta: string}>,
 *   gd: list<array{value: string, label: string, meta: string}>
 * }
 */
function k2_player_games_facet_listbox_choices(mysqli $con, array $facets, array $ctx): array
{
	$resultChoices = [
		['value' => 'all', 'label' => '', 'meta' => ''],
		['value' => 'win', 'label' => 'Win', 'meta' => (string) (int) $facets['result']['win']],
		['value' => 'draw', 'label' => 'Draw', 'meta' => (string) (int) $facets['result']['draw']],
		['value' => 'loss', 'label' => 'Loss', 'meta' => (string) (int) $facets['result']['loss']],
	];

	$opponentRows = k2_player_games_facet_inject_selected_opponent($con, $facets['opponent'], (int) $ctx['opponent']);
	$opponentChoices = [['value' => '0', 'label' => '', 'meta' => '']];
	foreach ($opponentRows as $row) {
		$opponentChoices[] = [
			'value' => (string) (int) $row['opponent_id'],
			'label' => (string) $row['opponent_name'],
			'meta' => (string) (int) $row['games'],
		];
	}

	$gfCounts = k2_player_games_facet_inject_selected_numeric($facets['gf'], (int) $ctx['gf']);
	$gaCounts = k2_player_games_facet_inject_selected_numeric($facets['ga'], (int) $ctx['ga']);
	$gsCounts = k2_player_games_facet_inject_selected_numeric($facets['gs'], (int) $ctx['gs']);
	$gdCounts = $facets['gd'];
	if ($ctx['gd'] !== null) {
		$gdCounts = k2_player_games_facet_inject_selected_numeric($gdCounts, (int) $ctx['gd']);
	}

	return [
		'result' => $resultChoices,
		'opponent' => $opponentChoices,
		'gf' => k2_player_games_facet_numeric_choices($gfCounts, '-1', false),
		'ga' => k2_player_games_facet_numeric_choices($gaCounts, '-1', false),
		'gs' => k2_player_games_facet_numeric_choices($gsCounts, '-1', false),
		'gd' => k2_player_games_facet_numeric_choices(
			$gdCounts,
			'',
			true,
			static fn(int $value): string => k2_player_games_hero_gd_label($value)
		),
	];
}

/** Drop URL values that never appear in this player's career; keep valid empty intersections. */
function k2_player_games_validate_filters_career_wide(
	mysqli $con,
	int $playerId,
	string &$resultFilter,
	int &$opponentFilter,
	int &$goalsScoredFilter,
	int &$goalsConcededFilter,
	int &$goalsSumFilter,
	?int &$heroGoalDiffFilter
): void {
	$careerFacets = k2_player_games_career_wide_facets($con, $playerId);

	$validOpponentIds = [];
	foreach ($careerFacets['opponent'] as $opponentRow) {
		$validOpponentIds[(int) ($opponentRow['opponent_id'] ?? 0)] = true;
	}
	if ($opponentFilter > 0 && !isset($validOpponentIds[$opponentFilter])) {
		$opponentFilter = 0;
	}

	$goalsScoredFilter = k2_ratedresults_games_valid_goals_filter($goalsScoredFilter, $careerFacets['gf']);
	$goalsConcededFilter = k2_ratedresults_games_valid_goals_filter($goalsConcededFilter, $careerFacets['ga']);
	$goalsSumFilter = k2_ratedresults_games_valid_goals_filter($goalsSumFilter, $careerFacets['gs']);
	$heroGoalDiffFilter = k2_ratedresults_games_valid_hero_gd_filter($heroGoalDiffFilter, $careerFacets['gd']);
}