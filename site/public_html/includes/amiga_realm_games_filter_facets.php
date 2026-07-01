<?php
/**
 * Faceted filter counts for Amiga realm All games (`amiga/games/all.php`).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_games_filter_facet_helpers.php';
require_once __DIR__ . '/k2_ratedresults_games_filters.php';
require_once __DIR__ . '/amiga_realm_games_all.php';
require_once __DIR__ . '/amiga_tournament_videos_lib.php';

/**
 * @param array<string, mixed> $state
 * @param-out string $types
 * @param-out list<int|string> $params
 */
function amiga_realm_games_facet_where(
    array $state,
    AmigaSnapshotContext $ctx,
    string $omitFacet,
    string &$types,
    array &$params,
): string {
    $facetState = $state;
    if ($omitFacet === 'host') {
        $facetState['host'] = '';
    } elseif ($omitFacet === 'player') {
        $facetState['player'] = 0;
        $facetState['player_via'] = '';
        $facetState['opponent'] = 0;
        $facetState['opponent_via'] = '';
    } elseif ($omitFacet === 'opponent') {
        $facetState['opponent'] = 0;
        $facetState['opponent_via'] = '';
    } elseif ($omitFacet === 'gd') {
        $facetState['gd'] = -1;
    } elseif ($omitFacet === 'gs') {
        $facetState['gs'] = -1;
    } elseif ($omitFacet === 'ts') {
        $facetState['ts'] = -1;
    } elseif ($omitFacet === 'gf') {
        $facetState['gf'] = -1;
    } elseif ($omitFacet === 'ga') {
        $facetState['ga'] = -1;
    } elseif ($omitFacet === 'year') {
        $facetState['year'] = 0;
        $facetState['year_mode'] = 'in';
    }

    return amiga_realm_games_all_where_sql($facetState, $ctx, $types, $params);
}

/** @return array<int, int> */
function amiga_realm_games_facet_numeric_counts(
    mysqli $con,
    array $state,
    AmigaSnapshotContext $ctx,
    string $omitFacet,
    string $valueExpr,
    string $orderBy,
): array {
    $types = '';
    $params = [];
    $where = amiga_realm_games_facet_where($state, $ctx, $omitFacet, $types, $params);
    $sql = 'SELECT ' . $valueExpr . ' AS v, COUNT(*) AS games '
        . amiga_rated_games_from_sql() . ' WHERE ' . $where . ' GROUP BY v ORDER BY ' . $orderBy;

    $sparse = [];
    foreach (k2_games_facet_query_rows($con, $sql, $types, $params) as $row) {
        $sparse[(int) ($row['v'] ?? 0)] = (int) ($row['games'] ?? 0);
    }

    return k2_games_facet_expand_numeric_gaps($sparse);
}

/**
 * @return list<array{country: string, games: int}>
 */
function amiga_realm_games_facet_host_country_rows(
    mysqli $con,
    array $state,
    AmigaSnapshotContext $ctx,
): array {
    $types = '';
    $params = [];
    $where = amiga_realm_games_facet_where($state, $ctx, 'host', $types, $params);
    $sql = 'SELECT TRIM(r.tournament_country) AS country, COUNT(*) AS games '
        . amiga_rated_games_from_sql()
        . ' WHERE ' . $where . " AND TRIM(r.tournament_country) <> '' "
        . 'GROUP BY country ORDER BY games DESC, country ASC';

    $rows = [];
    foreach (k2_games_facet_query_rows($con, $sql, $types, $params) as $row) {
        $country = trim((string) ($row['country'] ?? ''));
        if ($country === '') {
            continue;
        }
        $rows[] = [
            'country' => $country,
            'games' => (int) ($row['games'] ?? 0),
        ];
    }

    return $rows;
}

/**
 * @param list<array{country: string, games: int}> $rows
 * @return list<array{country: string, games: int}>
 */
function amiga_realm_games_facet_inject_selected_host_country(array $rows, string $host): array
{
    $host = trim($host);
    if ($host === '') {
        return $rows;
    }
    foreach ($rows as $row) {
        if ((string) $row['country'] === $host) {
            return $rows;
        }
    }

    $rows[] = ['country' => $host, 'games' => 0];

    return $rows;
}

/**
 * @param list<array{country: string, games: int}> $rows
 * @return list<array{value: string, label: string, meta: string}>
 */
function amiga_realm_games_host_country_choices(array $rows): array
{
    $choices = [['value' => '', 'label' => '', 'meta' => '']];
    foreach ($rows as $row) {
        $choices[] = [
            'value' => (string) $row['country'],
            'label' => (string) $row['country'],
            'meta' => (string) (int) $row['games'],
        ];
    }

    return $choices;
}

/**
 * @param array<string, mixed> $state
 * @return array{gd: array<int, int>, gs: array<int, int>, ts: array<int, int>}
 */
function amiga_realm_games_load_score_line_filter_facets(
    mysqli $con,
    array $state,
    AmigaSnapshotContext $ctx,
): array {
    return [
        'gd' => amiga_realm_games_facet_numeric_counts($con, $state, $ctx, 'gd', 'r.GoalDifference', 'v DESC'),
        'gs' => amiga_realm_games_facet_numeric_counts($con, $state, $ctx, 'gs', 'r.SumOfGoals', 'v ASC'),
        'ts' => amiga_realm_games_facet_numeric_counts($con, $state, $ctx, 'ts', 'GREATEST(r.GoalsA, r.GoalsB)', 'v ASC'),
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
function amiga_realm_games_score_line_facet_choices(array $facets, array $state): array
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

/**
 * @return list<array{opponent_id: int, opponent_name: string, games: int}>
 */
function amiga_realm_games_all_opponent_rows(
    mysqli $con,
    int $playerId,
    array $state,
    AmigaSnapshotContext $ctx,
): array {
    if ($playerId <= 0) {
        return [];
    }

    $types = '';
    $params = [];
    $where = amiga_realm_games_facet_where($state, $ctx, 'opponent', $types, $params);
    $playerIdSql = (int) $playerId;
    $sql = 'SELECT s.opponent_id, s.opponent_name, COUNT(*) AS games '
        . 'FROM ('
        . "SELECT CASE WHEN r.idA = $playerIdSql THEN r.idB ELSE r.idA END AS opponent_id, "
        . "CASE WHEN r.idA = $playerIdSql THEN r.NameB ELSE r.NameA END AS opponent_name "
        . amiga_rated_games_from_sql()
        . ' WHERE ' . $where
        . ') AS s '
        . 'GROUP BY s.opponent_id, s.opponent_name '
        . 'ORDER BY games DESC, opponent_name ASC';

    $rows = [];
    foreach (k2_games_facet_query_rows($con, $sql, $types, $params) as $row) {
        $rows[] = [
            'opponent_id' => (int) ($row['opponent_id'] ?? 0),
            'opponent_name' => (string) ($row['opponent_name'] ?? ''),
            'games' => (int) ($row['games'] ?? 0),
        ];
    }

    return $rows;
}
