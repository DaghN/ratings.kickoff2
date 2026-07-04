<?php
/**
 * Amiga realm All games list — server sort + pagination + filters.
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_amiga_routes.php';
require_once __DIR__ . '/amiga_db.php';
require_once __DIR__ . '/amiga_countries_lib.php';
require_once __DIR__ . '/amiga_country_rivals_load.php';
require_once __DIR__ . '/amiga_country_rivals_h2h_games_lib.php';
require_once __DIR__ . '/amiga_realm_games_hub_lib.php';
require_once __DIR__ . '/amiga_tournament_lib.php';
require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/amiga_player_games_lib.php';
require_once __DIR__ . '/amiga_player_current_lib.php';
require_once __DIR__ . '/k2_ratedresults_games_filters.php';
require_once __DIR__ . '/amiga_tournament_videos_lib.php';

const AMIGA_REALM_GAMES_ALL_PAGE_SIZE = 250;

function amiga_realm_games_all_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Directed nation-pair tokens when `country` + `rival` filters are active.
 *
 * @return array{hero: string, rival: string}|null
 */
function amiga_realm_games_all_nation_pair_tokens(array $state): ?array
{
    $heroRaw = trim((string) ($state['country'] ?? ''));
    $rivalRaw = trim((string) ($state['rival'] ?? ''));
    if ($heroRaw === '' || $rivalRaw === '') {
        return null;
    }

    $hero = amiga_country_rivals_normalize_token($heroRaw);
    $rival = amiga_country_rivals_normalize_token($rivalRaw);
    if ($hero === '' || $rival === '' || amiga_country_rivals_is_domestic_rival($hero, $rival)) {
        return null;
    }

    return ['hero' => $hero, 'rival' => $rival];
}

function amiga_realm_games_all_nation_pair_hero_goals_sql(): string
{
    $tokenA = amiga_country_rivals_games_token_sql('r.country_a');

    return 'CASE WHEN ' . $tokenA . ' = ? THEN r.GoalsA ELSE r.GoalsB END';
}

function amiga_realm_games_all_nation_pair_rival_goals_sql(): string
{
    $tokenA = amiga_country_rivals_games_token_sql('r.country_a');

    return 'CASE WHEN ' . $tokenA . ' = ? THEN r.GoalsB ELSE r.GoalsA END';
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

function amiga_realm_games_all_valid_player_via(string $via): string
{
    return in_array($via, ['search', 'rating', 'alpha'], true) ? $via : '';
}

function amiga_realm_games_all_valid_opponent_via(string $via): string
{
    return in_array($via, ['search', 'games', 'alpha'], true) ? $via : '';
}

function amiga_realm_games_all_build_url(array $params): string
{
    return k2_amiga_route('amiga-games-all', $params);
}

/** @return list<int> */
function amiga_realm_games_all_manifest_video_game_ids(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $ids = [];
    foreach (amiga_tournament_videos_manifest()['videos'] as $row) {
        if (!is_array($row) || ($row['kind'] ?? '') === 'excluded') {
            continue;
        }
        $gameIds = $row['game_ids'] ?? [];
        if (!is_array($gameIds)) {
            continue;
        }
        foreach ($gameIds as $gid) {
            $gid = (int) $gid;
            if ($gid > 0) {
                $ids[$gid] = true;
            }
        }
    }

    $cached = array_keys($ids);

    return $cached;
}

/**
 * @return array{
 *     sort: string,
 *     dir: string,
 *     offset: int,
 *     country: string,
 *     rival: string,
 *     host: string,
 *     event: string,
 *     videos: string,
 *     player: int,
 *     opponent: int,
 *     gd: int,
 *     gs: int,
 *     ts: int,
 *     gf: int,
 *     ga: int,
 *     year: int,
 *     year_mode: string,
 *     player_via: string,
 *     opponent_via: string
 * }
 */
function amiga_realm_games_all_request_state(): array
{
    $sortKey = amiga_realm_games_all_valid_sort((string) ($_GET['sort'] ?? 'id'));
    $sortDirection = amiga_realm_games_all_valid_direction((string) ($_GET['dir'] ?? 'desc'));
    $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
    $country = trim((string) ($_GET['country'] ?? ''));
    $rival = trim((string) ($_GET['rival'] ?? ''));
    $host = trim((string) ($_GET['host'] ?? ''));
    $event = amiga_games_valid_event_filter((string) ($_GET['filter'] ?? 'all'));
    $videos = isset($_GET['videos']) && (string) $_GET['videos'] === 'with-videos' ? 'with-videos' : '';

    $playerId = isset($_GET['player']) ? max(0, (int) $_GET['player']) : 0;
    $opponentFilter = $playerId > 0 && isset($_GET['opponent']) ? max(0, (int) $_GET['opponent']) : 0;
    $playerVia = amiga_realm_games_all_valid_player_via((string) ($_GET['player_via'] ?? ''));
    $opponentVia = amiga_realm_games_all_valid_opponent_via((string) ($_GET['opponent_via'] ?? ''));

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

    $goalsForFilter = isset($_GET['gf']) ? (int) $_GET['gf'] : -1;
    $goalsAgainstFilter = isset($_GET['ga']) ? (int) $_GET['ga'] : -1;
    if ($goalsForFilter < -1) {
        $goalsForFilter = -1;
    }
    if ($goalsAgainstFilter < -1) {
        $goalsAgainstFilter = -1;
    }

    $year = isset($_GET['year']) ? max(0, (int) $_GET['year']) : 0;
    $yearMode = $year > 0
        ? k2_ratedresults_games_valid_year_mode((string) ($_GET['year_mode'] ?? 'in'))
        : 'in';

    return [
        'sort' => $sortKey,
        'dir' => $sortDirection,
        'offset' => $offset,
        'country' => $country,
        'rival' => $rival,
        'host' => $host,
        'event' => $event,
        'videos' => $videos,
        'player' => $playerId,
        'opponent' => $opponentFilter,
        'gd' => $goalDiffFilter,
        'gs' => $goalsSumFilter,
        'ts' => $topScoreFilter,
        'gf' => $goalsForFilter,
        'ga' => $goalsAgainstFilter,
        'year' => $year,
        'year_mode' => $yearMode,
        'player_via' => $playerVia,
        'opponent_via' => $opponentVia,
    ];
}

function amiga_realm_games_all_sanitize_scalar_filter(
    mysqli $con,
    string $sql,
    string $types,
    array $params,
): bool {
    $rows = amiga_realm_games_hub_query_all($con, $sql, $types, $params);

    return $rows !== [];
}

function amiga_realm_games_all_sanitize_filters(mysqli $con, array &$state, AmigaSnapshotContext $ctx): void
{
    if ($state['player'] > 0) {
        $careerTable = amiga_player_career_table($con);
        $playerRows = amiga_realm_games_hub_query_all(
            $con,
            'SELECT p.id FROM amiga_players p INNER JOIN `' . $careerTable . '` s ON s.player_id = p.id '
                . 'WHERE p.id = ? AND s.NumberGames > 0 LIMIT 1',
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
        $state['player_via'] = amiga_realm_games_all_valid_player_via((string) ($state['player_via'] ?? ''));
    }

    $playerId = (int) $state['player'];
    if ($playerId > 0 && $state['opponent'] > 0) {
        $types = 'iiii';
        $params = [$playerId, $state['opponent'], $playerId, $state['opponent']];
        $cutoffTypes = '';
        $cutoffParams = [];
        $cutoffSql = amiga_snapshot_rated_game_cutoff_and_sql($ctx, $cutoffTypes, $cutoffParams);
        $types .= $cutoffTypes;
        $params = array_merge($params, $cutoffParams);
        $validOpponent = amiga_realm_games_all_sanitize_scalar_filter(
            $con,
            'SELECT r.id ' . amiga_rated_games_from_sql()
                . ' WHERE ((r.idA = ? AND r.idB = ?) OR (r.idB = ? AND r.idA = ?))' . $cutoffSql . ' LIMIT 1',
            $types,
            $params
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
        $state['opponent_via'] = amiga_realm_games_all_valid_opponent_via((string) ($state['opponent_via'] ?? ''));
    }

    if ($state['host'] !== '') {
        $types = '';
        $params = [];
        $cutoffSql = amiga_snapshot_rated_game_cutoff_and_sql($ctx, $types, $params);
        $validHost = amiga_realm_games_all_sanitize_scalar_filter(
            $con,
            'SELECT r.id ' . amiga_rated_games_from_sql()
                . ' WHERE r.tournament_country = ?' . $cutoffSql . ' LIMIT 1',
            's' . $types,
            array_merge([$state['host']], $params)
        );
        if (!$validHost) {
            $state['host'] = '';
        }
    }

    if ($state['gd'] >= 0 && !amiga_realm_games_all_sanitize_scalar_filter(
        $con,
        'SELECT r.id ' . amiga_rated_games_from_sql() . ' WHERE r.GoalDifference = ? LIMIT 1',
        'i',
        [$state['gd']]
    )) {
        $state['gd'] = -1;
    }

    if ($state['gs'] >= 0 && !amiga_realm_games_all_sanitize_scalar_filter(
        $con,
        'SELECT r.id ' . amiga_rated_games_from_sql() . ' WHERE r.SumOfGoals = ? LIMIT 1',
        'i',
        [$state['gs']]
    )) {
        $state['gs'] = -1;
    }

    if ($state['ts'] >= 0 && !amiga_realm_games_all_sanitize_scalar_filter(
        $con,
        'SELECT r.id ' . amiga_rated_games_from_sql() . ' WHERE GREATEST(r.GoalsA, r.GoalsB) = ? LIMIT 1',
        'i',
        [$state['ts']]
    )) {
        $state['ts'] = -1;
    }

    if ($state['year'] > 0) {
        $validYear = amiga_realm_games_all_sanitize_scalar_filter(
            $con,
            'SELECT r.id ' . amiga_rated_games_from_sql() . ' WHERE YEAR(r.`Date`) = ? LIMIT 1',
            'i',
            [$state['year']]
        );
        if (!$validYear) {
            $state['year'] = 0;
        }
    } else {
        $state['year'] = 0;
    }

    if ($state['year'] <= 0) {
        $state['year_mode'] = 'in';
    } else {
        $state['year_mode'] = k2_ratedresults_games_valid_year_mode($state['year_mode']);
    }

    if ($state['event'] !== 'world-cup') {
        $state['event'] = 'all';
    }

    if ($state['videos'] !== 'with-videos') {
        $state['videos'] = '';
    }

    $nationPair = amiga_realm_games_all_nation_pair_tokens($state);
    if ($nationPair === null) {
        $state['gf'] = -1;
        $state['ga'] = -1;
    } else {
        foreach (['gf', 'ga'] as $goalKey) {
            if ((int) ($state[$goalKey] ?? -1) < 0) {
                continue;
            }
            $types = '';
            $params = [];
            $whereSql = amiga_realm_games_all_where_sql($state, $ctx, $types, $params);
            if (!amiga_realm_games_all_sanitize_scalar_filter(
                $con,
                'SELECT r.id ' . amiga_rated_games_from_sql() . ' WHERE ' . $whereSql . ' LIMIT 1',
                $types,
                $params
            )) {
                $state[$goalKey] = -1;
            }
        }
    }
}

/**
 * @param array<string, mixed> $state
 * @param-out string $types
 * @param-out list<int|string> $params
 */
function amiga_realm_games_all_where_sql(array $state, AmigaSnapshotContext $ctx, string &$types, array &$params): string
{
    $where = ['1=1'];
    $types = '';
    $params = [];

    $heroRaw = trim((string) ($state['country'] ?? ''));
    $rivalRaw = trim((string) ($state['rival'] ?? ''));
    $nationPair = amiga_realm_games_all_nation_pair_tokens($state);
    if ($nationPair !== null) {
        $hero = $nationPair['hero'];
        $rival = $nationPair['rival'];
        $tokenA = amiga_country_rivals_games_token_sql('r.country_a');
        $tokenB = amiga_country_rivals_games_token_sql('r.country_b');
        $where[] = '((' . $tokenA . ' = ? AND ' . $tokenB . ' = ?) OR (' . $tokenB . ' = ? AND ' . $tokenA . ' = ?))';
        $types .= 'ssss';
        $params[] = $hero;
        $params[] = $rival;
        $params[] = $hero;
        $params[] = $rival;
    } elseif ($heroRaw !== '' && $rivalRaw !== '') {
        $where[] = '1=0';
    }

    if (($state['event'] ?? 'all') === 'world-cup') {
        $where[] = amiga_games_world_cup_name_sql('r.tournament_name');
    }

    if (($state['videos'] ?? '') === 'with-videos') {
        $videoGameIds = amiga_realm_games_all_manifest_video_game_ids();
        if ($videoGameIds === []) {
            $where[] = '1=0';
        } else {
            $placeholders = implode(',', array_fill(0, count($videoGameIds), '?'));
            $where[] = 'r.id IN (' . $placeholders . ')';
            $types .= str_repeat('i', count($videoGameIds));
            foreach ($videoGameIds as $gameId) {
                $params[] = (int) $gameId;
            }
        }
    }

    $host = trim((string) ($state['host'] ?? ''));
    if ($host !== '') {
        $where[] = 'r.tournament_country = ?';
        $types .= 's';
        $params[] = $host;
    }

    $playerId = (int) ($state['player'] ?? 0);
    if ($playerId > 0) {
        $where[] = '(r.idA = ? OR r.idB = ?)';
        $types .= 'ii';
        $params[] = $playerId;
        $params[] = $playerId;

        $opponentId = (int) ($state['opponent'] ?? 0);
        if ($opponentId > 0) {
            $where[] = '((r.idA = ? AND r.idB = ?) OR (r.idB = ? AND r.idA = ?))';
            $types .= 'iiii';
            $params[] = $playerId;
            $params[] = $opponentId;
            $params[] = $playerId;
            $params[] = $opponentId;
        }
    }

    $gd = (int) ($state['gd'] ?? -1);
    if ($gd >= 0) {
        $where[] = 'r.GoalDifference = ?';
        $types .= 'i';
        $params[] = $gd;
    }

    $gs = (int) ($state['gs'] ?? -1);
    if ($gs >= 0) {
        $where[] = 'r.SumOfGoals = ?';
        $types .= 'i';
        $params[] = $gs;
    }

    $ts = (int) ($state['ts'] ?? -1);
    if ($ts >= 0) {
        $where[] = 'GREATEST(r.GoalsA, r.GoalsB) = ?';
        $types .= 'i';
        $params[] = $ts;
    }

    $gf = (int) ($state['gf'] ?? -1);
    if ($nationPair !== null && $gf >= 0) {
        $where[] = '(' . amiga_realm_games_all_nation_pair_hero_goals_sql() . ' = ?)';
        $types .= 'si';
        $params[] = $nationPair['hero'];
        $params[] = $gf;
    }

    $ga = (int) ($state['ga'] ?? -1);
    if ($nationPair !== null && $ga >= 0) {
        $where[] = '(' . amiga_realm_games_all_nation_pair_rival_goals_sql() . ' = ?)';
        $types .= 'si';
        $params[] = $nationPair['hero'];
        $params[] = $ga;
    }

    $year = (int) ($state['year'] ?? 0);
    if ($year > 0) {
        $yearMode = k2_ratedresults_games_valid_year_mode((string) ($state['year_mode'] ?? 'in'));
        $yearStart = sprintf('%04d-01-01', $year);
        $yearEnd = sprintf('%04d-01-01', $year + 1);
        if ($yearMode === 'since') {
            $where[] = 'r.`Date` >= ?';
            $types .= 's';
            $params[] = $yearStart;
        } elseif ($yearMode === 'until') {
            $where[] = 'r.`Date` < ?';
            $types .= 's';
            $params[] = $yearEnd;
        } else {
            $where[] = 'r.`Date` >= ? AND r.`Date` < ?';
            $types .= 'ss';
            $params[] = $yearStart;
            $params[] = $yearEnd;
        }
    }

    $cutoffSql = amiga_snapshot_rated_game_cutoff_and_sql($ctx, $types, $params);
    if ($cutoffSql !== '') {
        $where[] = ltrim($cutoffSql, ' AND');
    }

    return implode(' AND ', $where);
}

/** Player-scoped inner scan when a hero filter is active. */
function amiga_realm_games_all_from_sql(array $state): string
{
    $playerId = (int) ($state['player'] ?? 0);

    return amiga_rated_games_from_sql($playerId > 0 ? $playerId : null);
}

/**
 * Lean tournament-indexed scan — skips the wide ratedresults subquery when filters are simple.
 */
function amiga_realm_games_all_lean_eligible(array $state): bool
{
    return ($state['country'] ?? '') === ''
        && ($state['rival'] ?? '') === ''
        && ($state['host'] ?? '') === ''
        && ($state['event'] ?? 'all') !== 'world-cup'
        && ($state['videos'] ?? '') !== 'with-videos'
        && (int) ($state['opponent'] ?? 0) === 0
        && (int) ($state['gd'] ?? -1) < 0
        && (int) ($state['gs'] ?? -1) < 0
        && (int) ($state['ts'] ?? -1) < 0
        && (int) ($state['gf'] ?? -1) < 0
        && (int) ($state['ga'] ?? -1) < 0
        && (int) ($state['year'] ?? 0) === 0;
}

/** No hero-player filter — tournament catalog aggregates match full-realm scans. */
function amiga_realm_games_all_catalog_eligible(array $state): bool
{
    return amiga_realm_games_all_lean_eligible($state)
        && (int) ($state['player'] ?? 0) === 0;
}

/**
 * @param-out string $types
 * @param-out list<int|string> $params
 */
function amiga_realm_games_all_lean_player_and_cutoff_sql(
    array $state,
    AmigaSnapshotContext $ctx,
    string &$types,
    array &$params,
): string {
    $where = '';
    $playerId = (int) ($state['player'] ?? 0);
    if ($playerId > 0) {
        $where .= ' AND (g.player_a_id = ' . $playerId . ' OR g.player_b_id = ' . $playerId . ')';
    }

    $cutoffSql = amiga_snapshot_tournament_cutoff_and_sql($ctx, $types, $params, 't.event_date', 't.chrono', 't.id');
    if ($cutoffSql !== '') {
        $where .= $cutoffSql;
    }

    return $where;
}

/**
 * @param-out string $types
 * @param-out list<int|string> $params
 */
function amiga_realm_games_all_lean_from_sql(
    array $state,
    AmigaSnapshotContext $ctx,
    string &$types,
    array &$params,
): string {
    $where = '1=1' . amiga_realm_games_all_lean_player_and_cutoff_sql($state, $ctx, $types, $params);

    return 'FROM amiga_games g '
        . 'INNER JOIN amiga_game_ratings gr ON gr.game_id = g.id '
        . 'INNER JOIN tournaments t ON t.id = g.tournament_id '
        . 'WHERE ' . $where;
}

/**
 * Tournament catalog aggregate count at cutoff (parity with game scan — catalog parity probe).
 */
function amiga_realm_games_all_catalog_count(mysqli $con, AmigaSnapshotContext $ctx): int
{
    $types = '';
    $params = [];
    $cutoffSql = amiga_snapshot_tournament_cutoff_and_sql($ctx, $types, $params, 't.event_date', 't.chrono', 't.id');
    $rows = amiga_realm_games_hub_query_all(
        $con,
        'SELECT COALESCE(SUM(c.game_count), 0) AS c FROM tournaments t '
            . 'LEFT JOIN amiga_tournament_catalog_stats c ON c.tournament_id = t.id '
            . 'WHERE ' . amiga_tournament_public_visibility_where('t') . $cutoffSql,
        $types,
        $params,
    );

    return (int) ($rows[0]['c'] ?? 0);
}

/** @return list<int> */
function amiga_realm_games_all_catalog_years(mysqli $con, AmigaSnapshotContext $ctx): array
{
    $types = '';
    $params = [];
    $cutoffSql = amiga_snapshot_tournament_cutoff_and_sql($ctx, $types, $params, 't.event_date', 't.chrono', 't.id');
    $rows = amiga_realm_games_hub_query_all(
        $con,
        'SELECT DISTINCT YEAR(t.event_date) AS y FROM tournaments t '
            . 'WHERE ' . amiga_tournament_public_visibility_where('t') . $cutoffSql
            . ' AND t.event_date IS NOT NULL ORDER BY y DESC',
        $types,
        $params,
    );
    $years = [];
    foreach ($rows as $row) {
        $years[] = (int) $row['y'];
    }

    return $years;
}

function amiga_realm_games_all_count(mysqli $con, array $state, AmigaSnapshotContext $ctx): int
{
    if (amiga_realm_games_all_lean_eligible($state)) {
        if ($ctx->isActive() || amiga_realm_games_all_catalog_eligible($state)) {
            return amiga_realm_games_all_catalog_count($con, $ctx);
        }

        $types = '';
        $params = [];
        $fromSql = amiga_realm_games_all_lean_from_sql($state, $ctx, $types, $params);
        $rows = amiga_realm_games_hub_query_all(
            $con,
            'SELECT COUNT(*) AS c ' . $fromSql,
            $types,
            $params,
        );

        return (int) ($rows[0]['c'] ?? 0);
    }

    $types = '';
    $params = [];
    $whereSql = amiga_realm_games_all_where_sql($state, $ctx, $types, $params);
    $rows = amiga_realm_games_hub_query_all(
        $con,
        'SELECT COUNT(*) AS c ' . amiga_realm_games_all_from_sql($state) . ' WHERE ' . $whereSql,
        $types,
        $params,
    );

    return (int) ($rows[0]['c'] ?? 0);
}

/**
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

    if (amiga_realm_games_all_lean_eligible($state)) {
        $leanSortMap = [
            'id' => 'g.id',
            'date' => 'g.game_date',
            'team_a' => 'pa.name',
            'team_b' => 'pb.name',
            'tournament' => 't.name',
            'phase' => 'g.phase',
            'result' => 'gr.actual_score',
            'goals_for' => 'g.goals_a',
            'against' => 'g.goals_b',
            'diff' => 'gr.goal_difference',
            'sum' => 'gr.sum_of_goals',
            'rating_a' => 'gr.rating_a',
            'rating_b' => 'gr.rating_b',
        ];
        $sortKey = $state['sort'];
        $leanSort = $leanSortMap[$sortKey] ?? 'g.id';
        $where = '1=1' . amiga_realm_games_all_lean_player_and_cutoff_sql($state, $ctx, $types, $params);
        $sql = 'SELECT g.id AS id, g.game_date AS `Date`, g.player_a_id AS idA, pa.name AS NameA, '
            . 'g.player_b_id AS idB, pb.name AS NameB, g.tournament_id AS tournament_id, t.name AS tournament_name, '
            . 't.country AS tournament_country, g.phase AS phase, g.goals_a AS GoalsA, g.goals_b AS GoalsB, '
            . 'gr.rating_a AS RatingA, gr.rating_b AS RatingB, gr.rating_difference AS RatingDifference, '
            . 'gr.expected_score_a AS ExpectedScoreA, gr.expected_score_b AS ExpectedScoreB, gr.actual_score AS ActualScore, '
            . 'gr.adjustment_a AS AdjustmentA, gr.adjustment_b AS AdjustmentB, gr.new_rating_a AS NewRatingA, '
            . 'gr.new_rating_b AS NewRatingB, gr.sum_of_goals AS SumOfGoals, gr.goal_difference AS GoalDifference, '
            . 'pa.country AS country_a, pb.country AS country_b '
            . 'FROM amiga_games g '
            . 'INNER JOIN amiga_game_ratings gr ON gr.game_id = g.id '
            . 'INNER JOIN amiga_players pa ON pa.id = g.player_a_id '
            . 'INNER JOIN amiga_players pb ON pb.id = g.player_b_id '
            . 'LEFT JOIN tournaments t ON t.id = g.tournament_id '
            . 'WHERE ' . $where
            . ' ORDER BY ' . $leanSort . ' ' . $dirSql . ', g.id DESC '
            . 'LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

        return amiga_realm_games_hub_query_all($con, $sql, $types, $params);
    }

    $whereSql = amiga_realm_games_all_where_sql($state, $ctx, $types, $params);

    $sql = amiga_realm_games_hub_select_sql()
        . amiga_realm_games_all_from_sql($state)
        . ' WHERE ' . $whereSql
        . ' ORDER BY ' . $sortSql . ' ' . $dirSql . ', r.id DESC '
        . 'LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

    return amiga_realm_games_hub_query_all($con, $sql, $types, $params);
}

/** @param array<string, mixed> $state */
function amiga_realm_games_all_has_active_filters(array $state): bool
{
    return ($state['country'] ?? '') !== ''
        || ($state['rival'] ?? '') !== ''
        || ($state['host'] ?? '') !== ''
        || ($state['event'] ?? 'all') === 'world-cup'
        || ($state['videos'] ?? '') === 'with-videos'
        || (int) ($state['player'] ?? 0) > 0
        || (int) ($state['opponent'] ?? 0) > 0
        || (int) ($state['gd'] ?? -1) >= 0
        || (int) ($state['gs'] ?? -1) >= 0
        || (int) ($state['ts'] ?? -1) >= 0
        || (int) ($state['gf'] ?? -1) >= 0
        || (int) ($state['ga'] ?? -1) >= 0
        || (int) ($state['year'] ?? 0) > 0;
}

/** @param array<string, mixed> $state */
function amiga_realm_games_all_query_params(array $state, bool $includeOffset = true): array
{
    $params = [];
    if (($state['country'] ?? '') !== '') {
        $params['country'] = (string) $state['country'];
    }
    if (($state['rival'] ?? '') !== '') {
        $params['rival'] = (string) $state['rival'];
    }
    if (($state['host'] ?? '') !== '') {
        $params['host'] = (string) $state['host'];
    }
    if (($state['event'] ?? 'all') === 'world-cup') {
        $params['filter'] = 'world-cup';
    }
    if (($state['videos'] ?? '') === 'with-videos') {
        $params['videos'] = 'with-videos';
    }
    if ((int) ($state['player'] ?? 0) > 0) {
        $params['player'] = (int) $state['player'];
        if (($state['player_via'] ?? '') !== '') {
            $params['player_via'] = (string) $state['player_via'];
        }
        if ((int) ($state['opponent'] ?? 0) > 0) {
            $params['opponent'] = (int) $state['opponent'];
            if (($state['opponent_via'] ?? '') !== '') {
                $params['opponent_via'] = (string) $state['opponent_via'];
            }
        }
    }
    if ((int) ($state['gd'] ?? -1) >= 0) {
        $params['gd'] = (int) $state['gd'];
    }
    if ((int) ($state['gs'] ?? -1) >= 0) {
        $params['gs'] = (int) $state['gs'];
    }
    if ((int) ($state['ts'] ?? -1) >= 0) {
        $params['ts'] = (int) $state['ts'];
    }
    if ((int) ($state['gf'] ?? -1) >= 0) {
        $params['gf'] = (int) $state['gf'];
    }
    if ((int) ($state['ga'] ?? -1) >= 0) {
        $params['ga'] = (int) $state['ga'];
    }
    if ((int) ($state['year'] ?? 0) > 0) {
        $params['year'] = (int) $state['year'];
        if (($state['year_mode'] ?? 'in') !== 'in') {
            $params['year_mode'] = (string) $state['year_mode'];
        }
    }
    if (($state['sort'] ?? 'id') !== 'id') {
        $params['sort'] = (string) $state['sort'];
    }
    if (($state['dir'] ?? 'desc') !== 'desc') {
        $params['dir'] = (string) $state['dir'];
    }
    if ($includeOffset && (int) ($state['offset'] ?? 0) > 0) {
        $params['offset'] = (int) $state['offset'];
    }

    return $params;
}

function amiga_realm_games_all_segment_url(array $state, string $event, string $videos): string
{
    $params = amiga_realm_games_all_query_params($state, false);
    if ($event === 'world-cup') {
        $params['filter'] = 'world-cup';
    } else {
        unset($params['filter']);
    }
    if ($videos === 'with-videos') {
        $params['videos'] = 'with-videos';
    } else {
        unset($params['videos']);
    }

    return amiga_realm_games_all_build_url($params);
}

/**
 * @return list<array{id: int, name: string, rating: int}>
 */
function amiga_realm_games_all_fetch_players(mysqli $con): array
{
    $careerTable = amiga_player_career_table($con);
    $rows = amiga_realm_games_hub_query_all(
        $con,
        'SELECT p.id AS id, p.name AS name, ROUND(s.Rating) AS rating '
            . 'FROM amiga_players p INNER JOIN `' . $careerTable . '` s ON s.player_id = p.id '
            . 'WHERE s.NumberGames > 0 AND p.name IS NOT NULL AND TRIM(p.name) <> \'\' '
            . 'ORDER BY p.name ASC, s.Rating DESC',
        '',
        []
    );

    $players = [];
    foreach ($rows as $row) {
        $players[] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'rating' => (int) $row['rating'],
        ];
    }

    return $players;
}

/** @return list<int> */
function amiga_realm_games_all_fetch_years(mysqli $con, AmigaSnapshotContext $ctx): array
{
    static $cache = [];
    $cutoffForKey = $ctx->isActive() ? $ctx->cutoff() : null;
    $cacheKey = $cutoffForKey === null
        ? 'present'
        : $cutoffForKey['event_date'] . '|' . $cutoffForKey['chrono'] . '|' . $cutoffForKey['tournament_id'];
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $state = amiga_realm_games_all_request_state();
    if (amiga_realm_games_all_lean_eligible($state)) {
        if ($ctx->isActive() || amiga_realm_games_all_catalog_eligible($state)) {
            return $cache[$cacheKey] = amiga_realm_games_all_catalog_years($con, $ctx);
        }

        $types = '';
        $params = [];
        $fromSql = amiga_realm_games_all_lean_from_sql($state, $ctx, $types, $params);
        $rows = amiga_realm_games_hub_query_all(
            $con,
            'SELECT DISTINCT YEAR(g.game_date) AS y ' . $fromSql . ' ORDER BY y DESC',
            $types,
            $params
        );
        $years = [];
        foreach ($rows as $row) {
            $years[] = (int) $row['y'];
        }

        return $cache[$cacheKey] = $years;
    }

    $types = '';
    $params = [];
    $cutoffSql = amiga_snapshot_rated_game_cutoff_and_sql($ctx, $types, $params);
    $rows = amiga_realm_games_hub_query_all(
        $con,
        'SELECT DISTINCT YEAR(r.`Date`) AS y ' . amiga_rated_games_from_sql()
            . ' WHERE 1=1' . $cutoffSql . ' ORDER BY y DESC',
        $types,
        $params
    );

    $years = [];
    foreach ($rows as $row) {
        $years[] = (int) $row['y'];
    }

    return $cache[$cacheKey] = $years;
}

function amiga_realm_games_all_gd_label(int $value): string
{
    if ($value > 0) {
        return '+' . $value;
    }

    return (string) $value;
}

/**
 * @param list<array{id: int, name: string, rating: int}> $players
 * @return list<array{value: string, label: string, meta: string}>
 */
function amiga_realm_games_all_player_rating_choices(array $players): array
{
    $byRating = $players;
    usort(
        $byRating,
        static function (array $a, array $b): int {
            $cmp = $b['rating'] <=> $a['rating'];
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcasecmp((string) $a['name'], (string) $b['name']);
        }
    );

    $choices = [['value' => '0', 'label' => '', 'meta' => '']];
    foreach ($byRating as $player) {
        $choices[] = [
            'value' => (string) $player['id'],
            'label' => $player['name'],
            'meta' => (string) $player['rating'],
        ];
    }

    return $choices;
}

/**
 * @param list<array{id: int, name: string, rating: int}> $players
 * @return list<array{value: string, label: string, meta: string}>
 */
function amiga_realm_games_all_player_alpha_choices(array $players): array
{
    $choices = [['value' => '0', 'label' => '', 'meta' => '']];
    foreach ($players as $player) {
        $choices[] = [
            'value' => (string) $player['id'],
            'label' => $player['name'],
            'meta' => (string) $player['rating'],
        ];
    }

    return $choices;
}

/**
 * @param list<array{opponent_id: int, opponent_name: string, games: int}> $rows
 * @return list<array{value: string, label: string, meta: string}>
 */
function amiga_realm_games_all_opponent_games_choices(array $rows): array
{
    $choices = [['value' => '0', 'label' => '', 'meta' => '']];
    foreach ($rows as $row) {
        $choices[] = [
            'value' => (string) (int) $row['opponent_id'],
            'label' => (string) $row['opponent_name'],
            'meta' => amiga_realm_games_all_games_meta_label((int) $row['games']),
        ];
    }

    return $choices;
}

/**
 * @param list<array{opponent_id: int, opponent_name: string, games: int}> $rows
 * @return list<array{value: string, label: string}>
 */
function amiga_realm_games_all_opponent_alpha_choices(array $rows): array
{
    $byAlpha = $rows;
    usort(
        $byAlpha,
        static function (array $a, array $b): int {
            return strcasecmp((string) $a['opponent_name'], (string) $b['opponent_name']);
        }
    );

    $choices = [['value' => '0', 'label' => '']];
    foreach ($byAlpha as $row) {
        $choices[] = [
            'value' => (string) (int) $row['opponent_id'],
            'label' => (string) $row['opponent_name'],
        ];
    }

    return $choices;
}

function amiga_realm_games_all_games_meta_label(int $games): string
{
    return $games . ' game' . ($games === 1 ? '' : 's');
}

/**
 * @param list<int> $years
 * @return list<array{value: string, label: string}>
 */
function amiga_realm_games_all_year_choices(array $years): array
{
    $choices = [['value' => '0', 'label' => '']];
    foreach ($years as $year) {
        $choices[] = [
            'value' => (string) $year,
            'label' => (string) $year,
        ];
    }

    return $choices;
}

/** @return list<array{value: string, label: string}> */
function amiga_realm_games_all_year_mode_choices(): array
{
    return [
        ['value' => 'in', 'label' => 'Just this year'],
        ['value' => 'since', 'label' => 'Since this year'],
        ['value' => 'until', 'label' => 'Until this year'],
    ];
}

function amiga_realm_games_all_name_from_players(int $playerId, array $players): string
{
    if ($playerId <= 0) {
        return '';
    }
    foreach ($players as $player) {
        if ((int) $player['id'] === $playerId) {
            return (string) $player['name'];
        }
    }

    return '';
}

function amiga_realm_games_all_name_from_opponents(int $opponentId, array $opponentRows, array $players): string
{
    if ($opponentId <= 0) {
        return '';
    }
    foreach ($opponentRows as $row) {
        if ((int) $row['opponent_id'] === $opponentId) {
            return (string) $row['opponent_name'];
        }
    }

    return amiga_realm_games_all_name_from_players($opponentId, $players);
}

function amiga_realm_games_all_listbox_selected_id(int $entityId, string $via, string $expectedVia): string
{
    return $entityId > 0 && $via === $expectedVia ? (string) $entityId : '0';
}

function amiga_realm_games_all_active_search_input_class(string $baseClass, bool $active): string
{
    return $active ? trim($baseClass . ' k2-link-star') : $baseClass;
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

/** @param array<string, mixed> $state */
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