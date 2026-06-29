<?php
/**
 * Amiga realm All games list — server sort + pagination (filters deferred).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_amiga_routes.php';
require_once __DIR__ . '/amiga_db.php';
require_once __DIR__ . '/amiga_countries_lib.php';
require_once __DIR__ . '/amiga_country_rivals_load.php';
require_once __DIR__ . '/amiga_country_rivals_h2h_games_lib.php';
require_once __DIR__ . '/amiga_realm_games_hub_lib.php';
require_once __DIR__ . '/amiga_snapshot_context.php';

const AMIGA_REALM_GAMES_ALL_PAGE_SIZE = 250;

function amiga_realm_games_all_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function amiga_realm_games_all_valid_direction(string $value): string
{
    return strtolower($value) === 'asc' ? 'asc' : 'desc';
}

/** @return array<string, string> */
function amiga_realm_games_all_sort_map(): array
{
    return [
        'id' => 'r.id',
        'date' => 'r.`Date`',
        'tournament' => 'r.tournament_name',
        'phase' => 'r.phase',
        'team_a' => 'r.NameA',
        'goals_a' => 'r.GoalsA',
        'goals_b' => 'r.GoalsB',
        'team_b' => 'r.NameB',
        'gd' => 'r.GoalDifference',
        'sum' => 'r.SumOfGoals',
        'top_score' => 'GREATEST(r.GoalsA, r.GoalsB)',
        'rating_a' => 'r.RatingA',
        'rating_b' => 'r.RatingB',
        'elo_diff' => 'r.RatingDifference',
        'fav_es' => 'GREATEST(r.ExpectedScoreA, r.ExpectedScoreB)',
        'adjustment' => 'GREATEST(ABS(r.AdjustmentA), ABS(r.AdjustmentB))',
    ];
}

function amiga_realm_games_all_valid_sort(string $sortKey): string
{
    $map = amiga_realm_games_all_sort_map();

    return isset($map[$sortKey]) ? $sortKey : 'id';
}

function amiga_realm_games_all_build_url(array $params): string
{
    return k2_amiga_route('amiga-games-all', $params);
}

/**
 * @return array{sort: string, dir: string, offset: int, country: string, rival: string}
 */
function amiga_realm_games_all_request_state(): array
{
    $sortKey = amiga_realm_games_all_valid_sort((string) ($_GET['sort'] ?? 'id'));
    $sortDirection = amiga_realm_games_all_valid_direction((string) ($_GET['dir'] ?? 'desc'));
    $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
    $country = amiga_country_rivals_normalize_token((string) ($_GET['country'] ?? ''));
    $rival = amiga_country_rivals_normalize_token((string) ($_GET['rival'] ?? ''));

    return [
        'sort' => $sortKey,
        'dir' => $sortDirection,
        'offset' => $offset,
        'country' => $country,
        'rival' => $rival,
    ];
}

function amiga_realm_games_all_where_sql(array $state, AmigaSnapshotContext $ctx, string &$types, array &$params): string
{
    $where = '1=1';
    $hero = (string) ($state['country'] ?? '');
    $rival = (string) ($state['rival'] ?? '');
    if ($hero !== '' && $rival !== '') {
        $tokenA = amiga_country_rivals_games_token_sql('r.country_a');
        $tokenB = amiga_country_rivals_games_token_sql('r.country_b');
        $where .= ' AND ((' . $tokenA . ' = ? AND ' . $tokenB . ' = ?) OR (' . $tokenB . ' = ? AND ' . $tokenA . ' = ?))';
        $types .= 'ssss';
        $params[] = $hero;
        $params[] = $rival;
        $params[] = $hero;
        $params[] = $rival;
    }
    $where .= amiga_snapshot_rated_game_cutoff_and_sql($ctx, $types, $params, 'r');

    return $where;
}

function amiga_realm_games_all_count(mysqli $con, array $state, AmigaSnapshotContext $ctx): int
{
    $types = '';
    $params = [];
    $whereSql = amiga_realm_games_all_where_sql($state, $ctx, $types, $params);
    $rows = amiga_realm_games_hub_query_all(
        $con,
        'SELECT COUNT(*) AS c ' . amiga_rated_games_from_sql() . ' WHERE ' . $whereSql,
        $types,
        $params,
    );

    return (int) ($rows[0]['c'] ?? 0);
}

/**
 * @param array{sort: string, dir: string, offset: int, country: string, rival: string} $state
 * @return list<array<string, mixed>>
 */
function amiga_realm_games_all_fetch_page(mysqli $con, array $state, AmigaSnapshotContext $ctx, int $limit): array
{
    $sortMap = amiga_realm_games_all_sort_map();
    $sortSql = $sortMap[$state['sort']];
    $dirSql = strtoupper($state['dir']) === 'ASC' ? 'ASC' : 'DESC';
    $limit = max(1, $limit);
    $offset = max(0, $state['offset']);

    $types = '';
    $params = [];
    $whereSql = amiga_realm_games_all_where_sql($state, $ctx, $types, $params);

    $sql = amiga_realm_games_hub_select_sql()
        . amiga_rated_games_from_sql()
        . ' WHERE ' . $whereSql
        . ' ORDER BY ' . $sortSql . ' ' . $dirSql . ', r.id DESC '
        . 'LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

    return amiga_realm_games_hub_query_all($con, $sql, $types, $params);
}

/** @param array{sort: string, dir: string, offset: int, country: string, rival: string} $state */
function amiga_realm_games_all_query_params(array $state, bool $includeOffset = true): array
{
    $params = [];
    if (($state['country'] ?? '') !== '') {
        $params['country'] = (string) $state['country'];
    }
    if (($state['rival'] ?? '') !== '') {
        $params['rival'] = (string) $state['rival'];
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

function amiga_realm_games_all_sort_col_index(string $sortKey, bool $withRank = false): int
{
    $offset = $withRank ? 1 : 0;
    $map = [
        'id' => 0,
        'date' => 1,
        'tournament' => 2,
        'phase' => 3,
        'team_a' => 4,
        'goals_a' => 5,
        'goals_b' => 6,
        'team_b' => 7,
        'gd' => 8,
        'sum' => 9,
        'top_score' => 10,
        'rating_a' => 11,
        'rating_b' => 12,
        'elo_diff' => 13,
        'fav_es' => 14,
        'adjustment' => 15,
    ];
    $key = amiga_realm_games_all_valid_sort($sortKey);

    return ($map[$key] ?? 0) + $offset;
}

/** @param array{sort: string, dir: string, offset: int, country: string, rival: string} $state */
function amiga_realm_games_all_sort_header(
    string $key,
    string $label,
    string $align,
    array $state,
    string $help,
    string $tooltipLabel = '',
    string $extraClass = '',
    bool $withRank = false,
): string {
    $isActive = $state['sort'] === $key;
    $nextDir = $isActive && $state['dir'] === 'desc' ? 'asc' : 'desc';
    $classes = ['k2-table-sortable'];
    if ($align === 'left') {
        $classes[] = 'k2-table-cell--left';
    } elseif ($align === 'right') {
        $classes[] = 'k2-table-cell--right';
    }
    if ($extraClass !== '') {
        $classes[] = $extraClass;
    }
    if ($isActive) {
        $classes[] = $state['dir'] === 'desc' ? 'k2-table-sorted-desc' : 'k2-table-sorted-asc';
    }

    $params = amiga_realm_games_all_query_params($state, false);
    $params['sort'] = $key;
    $params['dir'] = $nextDir;

    $aria = $isActive ? ($state['dir'] === 'desc' ? 'descending' : 'ascending') : 'none';
    $attrs = [
        'class="' . implode(' ', $classes) . '"',
        'aria-sort="' . $aria . '"',
        'data-k2-help="' . amiga_realm_games_all_h($help) . '"',
    ];
    if ($tooltipLabel !== '') {
        $attrs[] = 'data-k2-tooltip-label="' . amiga_realm_games_all_h($tooltipLabel) . '"';
    }

    return '<th ' . implode(' ', $attrs) . '>'
        . '<a href="' . amiga_realm_games_all_h(amiga_realm_games_all_build_url($params)) . '">' . $label . '</a>'
        . '</th>';
}
