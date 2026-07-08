<?php
/**
 * Query helpers for amiga/player/games.php (mirrors player/games.php filters).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_amiga_routes.php';
require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/k2_ratedresults_games_filters.php';

const AMIGA_PLAYER_GAMES_DEFAULT_SORT = 'id';
const AMIGA_PLAYER_GAMES_DEFAULT_DIR = 'desc';

function amiga_games_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function amiga_games_valid_result(string $value): string
{
    return in_array($value, ['all', 'win', 'draw', 'loss'], true) ? $value : 'all';
}

function amiga_games_valid_direction(string $value): string
{
    return strtolower($value) === 'asc' ? 'asc' : 'desc';
}

function amiga_games_build_url(array $params): string
{
    return k2_amiga_route('amiga-player-games', $params);
}

function amiga_games_query_all(mysqli $con, string $sql, string $types = '', array $params = []): array
{
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Query failed: ' . $con->error);
    }

    if ($types !== '') {
        $refs = [];
        foreach ($params as $key => $value) {
            $refs[$key] = &$params[$key];
        }
        $stmt->bind_param($types, ...$refs);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result) {
        $stmt->close();
        throw new RuntimeException('Query failed: ' . $con->error);
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    return $rows;
}

function amiga_games_valid_day(string $value): string
{
    $value = trim($value);
    if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
        return $value;
    }

    return '';
}

/** @return 'all'|'world-cup' */
function amiga_games_valid_event_filter(string $value): string
{
    return $value === 'world-cup' ? 'world-cup' : 'all';
}

/** SQL fragment: World Cup catalog filter on stored flag. */
function amiga_games_world_cup_flag_sql(string $column): string
{
    return '(' . $column . ' = 1)';
}

/**
 * @param list<string> $countryOptions
 */
function amiga_games_valid_country_filter(string $value, array $countryOptions): string
{
    $value = trim($value);
    if ($value === '' || !in_array($value, $countryOptions, true)) {
        return '';
    }

    return $value;
}

/** Hero-relative opponent country expression on rated-games alias `r`. */
function amiga_games_hero_opponent_country_sql(int $playerId): string
{
    $playerIdSql = (int) $playerId;

    return "CASE WHEN r.idA = $playerIdSql THEN r.country_b ELSE r.country_a END";
}

/** @return list<int> */
function amiga_player_games_year_options(mysqli $con, int $playerId, ?AmigaSnapshotContext $ctx = null): array
{
    $ctx ??= amiga_snapshot_context_peek();
    $cutoffTypes = 'ii';
    $cutoffParams = [$playerId, $playerId];
    $cutoffSql = amiga_snapshot_tournament_cutoff_and_sql($ctx, $cutoffTypes, $cutoffParams);
    $rows = amiga_games_query_all(
        $con,
        'SELECT DISTINCT YEAR(g.game_date) AS yr FROM amiga_games g '
            . 'INNER JOIN tournaments t ON t.id = g.tournament_id '
            . 'WHERE (g.player_a_id = ? OR g.player_b_id = ?) AND g.game_date IS NOT NULL'
            . $cutoffSql
            . ' ORDER BY yr ASC',
        $cutoffTypes,
        $cutoffParams
    );
    $years = [];
    foreach ($rows as $row) {
        $year = (int) ($row['yr'] ?? 0);
        if ($year > 0) {
            $years[] = $year;
        }
    }

    return $years;
}

/**
 * @param list<int> $yearOptions
 */
function amiga_games_valid_since_year(int $value, array $yearOptions): int
{
    if ($value < 1 || !in_array($value, $yearOptions, true)) {
        return 0;
    }

    return $value;
}

/**
 * @param list<int> $yearOptions
 */
function amiga_games_valid_until_year(int $value, array $yearOptions): int
{
    return amiga_games_valid_since_year($value, $yearOptions);
}

/**
 * Normalized games-tab filters from a GET array (games.php + perf API).
 *
 * @param array<string, mixed> $query
 * @return array{
 *     result: string,
 *     opponent: int,
 *     tournament: int,
 *     event: string,
 *     country: string,
 *     opp_country: string,
 *     day: string,
 *     since: int,
 *     until: int,
 *     year: int,
 *     gf: int,
 *     ga: int,
 *     gs: int,
 *     gd: ?int
 * }
 */
function amiga_player_games_filters_from_request(
    mysqli $con,
    int $playerId,
    array $query,
    ?AmigaSnapshotContext $ctx = null,
): array {
    $ctx ??= amiga_snapshot_context_peek();
    $resultFilter = amiga_games_valid_result((string) ($query['result'] ?? 'all'));
    $opponentFilter = isset($query['opponent']) ? max(0, (int) $query['opponent']) : 0;
    $tournamentFilter = isset($query['tournament']) ? max(0, (int) $query['tournament']) : 0;
    $eventFilter = amiga_games_valid_event_filter((string) ($query['filter'] ?? 'all'));
    $utcDayFilter = amiga_games_valid_day((string) ($query['day'] ?? ''));
    $yearOptions = amiga_player_games_year_options($con, $playerId, $ctx);
    $sinceYear = amiga_games_valid_since_year(
        isset($query['since']) ? (int) $query['since'] : 0,
        $yearOptions
    );
    $untilYear = amiga_games_valid_until_year(
        isset($query['until']) ? (int) $query['until'] : 0,
        $yearOptions
    );
    $yearFilter = amiga_games_valid_since_year(
        isset($query['year']) ? (int) $query['year'] : 0,
        $yearOptions
    );
    $goalsScoredFilter = isset($query['gf']) ? (int) $query['gf'] : -1;
    $goalsConcededFilter = isset($query['ga']) ? (int) $query['ga'] : -1;
    $goalsSumFilter = isset($query['gs']) ? (int) $query['gs'] : -1;
    $heroGoalDiffFilter = isset($query['gd']) && $query['gd'] !== '' ? (int) $query['gd'] : null;
    $countryFilter = isset($query['country']) && is_string($query['country']) ? trim($query['country']) : '';
    $oppCountryFilter = isset($query['opp_country']) && is_string($query['opp_country']) ? trim($query['opp_country']) : '';

    return [
        'result' => $resultFilter,
        'opponent' => $opponentFilter,
        'tournament' => $tournamentFilter,
        'event' => $eventFilter,
        'country' => $countryFilter,
        'opp_country' => $oppCountryFilter,
        'day' => $utcDayFilter,
        'since' => $sinceYear,
        'until' => $untilYear,
        'year' => $yearFilter,
        'gf' => $goalsScoredFilter,
        'ga' => $goalsConcededFilter,
        'gs' => $goalsSumFilter,
        'gd' => $heroGoalDiffFilter,
    ];
}

/**
 * Active GET params for games tab links (sort headers, event pills, clear day).
 *
 * @return array<string, int|string>
 */
function amiga_games_active_url_params(array $state): array
{
    $params = [
        'id' => (int) $state['player_id'],
        'sort' => (string) $state['sort'],
        'dir' => (string) $state['dir'],
    ];
    if (($state['result'] ?? 'all') !== 'all') {
        $params['result'] = (string) $state['result'];
    }
    if ((int) ($state['opponent'] ?? 0) > 0) {
        $params['opponent'] = (int) $state['opponent'];
    }
    if ((int) ($state['tournament'] ?? 0) > 0) {
        $params['tournament'] = (int) $state['tournament'];
    }
    if (($state['event'] ?? 'all') !== 'all') {
        $params['filter'] = (string) $state['event'];
    }
    if (!empty($state['country'])) {
        $params['country'] = (string) $state['country'];
    }
    if (!empty($state['opp_country'])) {
        $params['opp_country'] = (string) $state['opp_country'];
    }
    if (!empty($state['day'])) {
        $params['day'] = (string) $state['day'];
    }
    if ((int) ($state['since'] ?? 0) > 0) {
        $params['since'] = (int) $state['since'];
    }
    if ((int) ($state['until'] ?? 0) > 0) {
        $params['until'] = (int) $state['until'];
    }
    if ((int) ($state['year'] ?? 0) > 0) {
        $params['year'] = (int) $state['year'];
    }
    if ((int) ($state['gf'] ?? -1) >= 0) {
        $params['gf'] = (int) $state['gf'];
    }
    if ((int) ($state['ga'] ?? -1) >= 0) {
        $params['ga'] = (int) $state['ga'];
    }
    if ((int) ($state['gs'] ?? -1) >= 0) {
        $params['gs'] = (int) $state['gs'];
    }
    if (array_key_exists('gd', $state) && $state['gd'] !== null) {
        $params['gd'] = (int) $state['gd'];
    }

    return $params;
}

function amiga_games_event_filter_url(array $state, string $eventFilter): string
{
    $params = amiga_games_active_url_params($state);
    if ($eventFilter === 'all') {
        unset($params['filter']);
    } else {
        $params['filter'] = $eventFilter;
    }

    return amiga_games_build_url($params);
}

function amiga_games_render_page_nav(int $offset, int $limit, int $totalMatches, array $pagerParams): void
{
    echo '<nav class="k2-player-games-day-steps k2-realm-games-all__status-nav" aria-label="Page">';
    if ($offset > 0) {
        $prevParams = $pagerParams + ['offset' => max(0, $offset - $limit)];
        echo '<a class="k2-player-games-day-step k2-player-games-day-step--prev" href="'
            . amiga_games_h(amiga_games_build_url($prevParams))
            . '" aria-label="Previous page">';
        echo '<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span></a>';
    } else {
        echo '<span class="k2-player-games-day-step k2-player-games-day-step--prev is-disabled" aria-disabled="true" aria-label="Previous page">';
        echo '<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span></span>';
    }
    if ($offset + $limit < $totalMatches) {
        $nextParams = $pagerParams + ['offset' => $offset + $limit];
        echo '<a class="k2-player-games-day-step k2-player-games-day-step--next" href="'
            . amiga_games_h(amiga_games_build_url($nextParams))
            . '" aria-label="Next page">';
        echo '<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span></a>';
    } else {
        echo '<span class="k2-player-games-day-step k2-player-games-day-step--next is-disabled" aria-disabled="true" aria-label="Next page">';
        echo '<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span></span>';
    }
    echo '</nav>';
}

function amiga_games_where_clause(
    int $playerId,
    string $resultFilter,
    int $opponentId,
    int $tournamentId,
    string $eventFilter,
    string $countryFilter,
    string $oppCountryFilter,
    string $utcDay,
    int $sinceYear,
    int $untilYear,
    int $yearFilter,
    int $goalsScoredFilter,
    int $goalsConcededFilter,
    int $goalsSumFilter,
    ?int $heroGoalDiffFilter,
    string &$types,
    array &$params,
    ?AmigaSnapshotContext $ctx = null,
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

    if ($tournamentId > 0) {
        $where[] = 'r.tournament_id = ?';
        $types .= 'i';
        $params[] = $tournamentId;
    }

    if ($eventFilter === 'world-cup') {
        $where[] = amiga_games_world_cup_flag_sql('r.is_world_cup');
    }

    if ($countryFilter !== '') {
        $where[] = 'r.tournament_country = ?';
        $types .= 's';
        $params[] = $countryFilter;
    }

    if ($oppCountryFilter !== '') {
        $where[] = amiga_games_hero_opponent_country_sql($playerId) . ' = ?';
        $types .= 's';
        $params[] = $oppCountryFilter;
    }

    if ($sinceYear > 0) {
        $where[] = 'YEAR(r.`Date`) >= ?';
        $types .= 'i';
        $params[] = $sinceYear;
    }

    if ($untilYear > 0) {
        $where[] = 'r.`Date` < ?';
        $types .= 's';
        $params[] = sprintf('%04d-01-01', $untilYear + 1);
    }

    if ($yearFilter > 0) {
        $where[] = 'YEAR(r.`Date`) = ?';
        $types .= 'i';
        $params[] = $yearFilter;
    }

    if ($goalsScoredFilter >= 0) {
        $where[] = '((r.idA = ? AND r.GoalsA = ?) OR (r.idB = ? AND r.GoalsB = ?))';
        $types .= 'iiii';
        $params[] = $playerId;
        $params[] = $goalsScoredFilter;
        $params[] = $playerId;
        $params[] = $goalsScoredFilter;
    }

    if ($goalsConcededFilter >= 0) {
        $where[] = '((r.idA = ? AND r.GoalsB = ?) OR (r.idB = ? AND r.GoalsA = ?))';
        $types .= 'iiii';
        $params[] = $playerId;
        $params[] = $goalsConcededFilter;
        $params[] = $playerId;
        $params[] = $goalsConcededFilter;
    }

    if ($heroGoalDiffFilter !== null) {
        $where[] = '((r.idA = ? AND (r.GoalsA - r.GoalsB) = ?) OR (r.idB = ? AND (r.GoalsB - r.GoalsA) = ?))';
        $types .= 'iiii';
        $params[] = $playerId;
        $params[] = $heroGoalDiffFilter;
        $params[] = $playerId;
        $params[] = $heroGoalDiffFilter;
    }

    if ($goalsSumFilter >= 0) {
        $where[] = 'r.SumOfGoals = ?';
        $types .= 'i';
        $params[] = $goalsSumFilter;
    }

    $whereSql = implode(' AND ', $where);
    $whereSql .= amiga_snapshot_rated_game_cutoff_and_sql($ctx, $types, $params);

    return $whereSql;
}

/**
 * Optional AND fragments for tournament metadata filters on `t` alias.
 */
function amiga_games_tournament_meta_and_sql(
    string $eventFilter,
    string $countryFilter,
    string &$types,
    array &$params
): string {
    $parts = [];
    if ($eventFilter === 'world-cup') {
        $parts[] = amiga_games_world_cup_flag_sql('t.is_world_cup');
    }
    if ($countryFilter !== '') {
        $parts[] = 't.country = ?';
        $types .= 's';
        $params[] = $countryFilter;
    }

    return $parts === [] ? '' : ' AND ' . implode(' AND ', $parts);
}

function amiga_games_sort_header(string $key, string $label, string $align, array $state, string $help, string $tooltipLabel = '', string $extraClass = ''): string
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

    $params = amiga_games_active_url_params($state);
    $params['sort'] = $key;
    $params['dir'] = $nextDir;
    unset($params['offset']);

    $aria = $isActive ? ($state['dir'] === 'desc' ? 'descending' : 'ascending') : 'none';
    $attrs = [
        'class="' . implode(' ', $classes) . '"',
        'aria-sort="' . $aria . '"',
    ];
    if ($help !== '') {
        $attrs[] = 'data-k2-help="' . amiga_games_h($help) . '"';
    }
    if ($tooltipLabel !== '') {
        $attrs[] = 'data-k2-tooltip-label="' . amiga_games_h($tooltipLabel) . '"';
    }

    return '<th ' . implode(' ', $attrs) . '>'
        . '<a href="' . amiga_games_h(amiga_games_build_url($params)) . '">' . $label . '</a>'
        . '</th>';
}
