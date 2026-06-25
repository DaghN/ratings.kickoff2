<?php
/**
 * Faceted filter counts for realm All games score-line listboxes (GD / Sum / TS).
 *
 * @see docs/k2-table-and-games-plan.md — All games Contract
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_games_filter_facet_helpers.php';
require_once __DIR__ . '/k2_ratedresults_games_filters.php';

/**
 * @param array{
 *     player: int,
 *     opponent: int,
 *     gd: int,
 *     gs: int,
 *     ts: int,
 *     year: int,
 *     year_mode: string
 * } $state
 * @param-out string $types
 * @param-out list<int|string> $params
 */
function k2_realm_games_facet_where(array $state, string $omitFacet, string &$types, array &$params): string
{
	$gd = (int) $state['gd'];
	$gs = (int) $state['gs'];
	$ts = (int) $state['ts'];

	if ($omitFacet === 'gd') {
		$gd = -1;
	} elseif ($omitFacet === 'gs') {
		$gs = -1;
	} elseif ($omitFacet === 'ts') {
		$ts = -1;
	}

	return k2_ratedresults_games_where_clause(
		(int) $state['player'],
		'all',
		(int) $state['opponent'],
		-1,
		-1,
		$gs,
		'',
		$types,
		$params,
		$gd,
		$ts,
		(int) $state['year'],
		(string) $state['year_mode']
	);
}

/** @return array<int, int> */
function k2_realm_games_facet_numeric_counts(
	mysqli $con,
	array $state,
	string $omitFacet,
	string $valueExpr,
	string $orderBy
): array {
	$types = '';
	$params = [];
	$where = k2_realm_games_facet_where($state, $omitFacet, $types, $params);
	$sql = 'SELECT ' . $valueExpr . ' AS v, COUNT(*) AS games '
		. 'FROM ratedresults r WHERE ' . $where . ' GROUP BY v ORDER BY ' . $orderBy;

	$sparse = [];
	foreach (k2_games_facet_query_rows($con, $sql, $types, $params) as $row) {
		$sparse[(int) ($row['v'] ?? 0)] = (int) ($row['games'] ?? 0);
	}

	return k2_games_facet_expand_numeric_gaps($sparse);
}

/**
 * @param array{
 *     player: int,
 *     opponent: int,
 *     gd: int,
 *     gs: int,
 *     ts: int,
 *     year: int,
 *     year_mode: string
 * } $state
 * @return array{gd: array<int, int>, gs: array<int, int>, ts: array<int, int>}
 */
function k2_realm_games_load_score_line_filter_facets(mysqli $con, array $state): array
{
	return [
		'gd' => k2_realm_games_facet_numeric_counts($con, $state, 'gd', 'r.GoalDifference', 'v DESC'),
		'gs' => k2_realm_games_facet_numeric_counts($con, $state, 'gs', 'r.SumOfGoals', 'v ASC'),
		'ts' => k2_realm_games_facet_numeric_counts($con, $state, 'ts', 'GREATEST(r.GoalsA, r.GoalsB)', 'v ASC'),
	];
}

/**
 * @param array{gd: array<int, int>, gs: array<int, int>, ts: array<int, int>} $facets
 * @param array{gd: int, gs: int, ts: int} $state
 * @return array{
 *   gd: list<array{value: string, label: string, meta: string}>,
 *   gs: list<array{value: string, label: string, meta: string}>,
 *   ts: list<array{value: string, label: string, meta: string}>
 * }
 */
function k2_realm_games_score_line_facet_choices(array $facets, array $state): array
{
	$gdCounts = k2_games_facet_inject_selected_numeric($facets['gd'], (int) $state['gd']);
	$gsCounts = k2_games_facet_inject_selected_numeric($facets['gs'], (int) $state['gs']);
	$tsCounts = k2_games_facet_inject_selected_numeric($facets['ts'], (int) $state['ts']);

	return [
		'gd' => k2_games_facet_numeric_choices(
			$gdCounts,
			'-1',
			true,
			static fn(int $value): string => $value > 0 ? '+' . $value : (string) $value
		),
		'gs' => k2_games_facet_numeric_choices($gsCounts, '-1', false),
		'ts' => k2_games_facet_numeric_choices($tsCounts, '-1', false),
	];
}