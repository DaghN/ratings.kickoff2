<?php
/**
 * Query helpers for amiga/games.php (mirrors player/games.php filters).
 */
declare(strict_types=1);

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
    return '/amiga/games.php?' . http_build_query($params);
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

/** SQL fragment: World Cup catalog names (`amiga_tournament_is_world_cup_by_name`). */
function amiga_games_world_cup_name_sql(string $tournamentNameColumn): string
{
    // No literal space before [[:space:]] — "World Cup IV…" has only one space after Cup.
    return $tournamentNameColumn . " REGEXP '^World Cup[[:space:]]+[^[:space:]]'";
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

/** @return list<int> */
function amiga_player_games_year_options(mysqli $con, int $playerId): array
{
    $rows = amiga_games_query_all(
        $con,
        'SELECT DISTINCT YEAR(g.game_date) AS yr FROM amiga_games g '
            . 'WHERE (g.player_a_id = ? OR g.player_b_id = ?) AND g.game_date IS NOT NULL '
            . 'ORDER BY yr ASC',
        'ii',
        [$playerId, $playerId]
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
 * Normalized games-tab filters from a GET array (games.php + perf API).
 *
 * @param array<string, mixed> $query
 * @return array{
 *     result: string,
 *     opponent: int,
 *     tournament: int,
 *     event: string,
 *     country: string,
 *     day: string,
 *     since: int,
 *     year: int
 * }
 */
function amiga_player_games_filters_from_request(mysqli $con, int $playerId, array $query): array
{
    $resultFilter = amiga_games_valid_result((string) ($query['result'] ?? 'all'));
    $opponentFilter = isset($query['opponent']) ? max(0, (int) $query['opponent']) : 0;
    $tournamentFilter = isset($query['tournament']) ? max(0, (int) $query['tournament']) : 0;
    $eventFilter = amiga_games_valid_event_filter((string) ($query['filter'] ?? 'all'));
    $utcDayFilter = amiga_games_valid_day((string) ($query['day'] ?? ''));
    $yearOptions = amiga_player_games_year_options($con, $playerId);
    $sinceYear = amiga_games_valid_since_year(
        isset($query['since']) ? (int) $query['since'] : 0,
        $yearOptions
    );
    $yearFilter = amiga_games_valid_since_year(
        isset($query['year']) ? (int) $query['year'] : 0,
        $yearOptions
    );

    if ($opponentFilter > 0) {
        $opponentRows = amiga_games_query_all(
            $con,
            'SELECT opponent_id FROM ('
                . 'SELECT g.player_b_id AS opponent_id FROM amiga_games g WHERE g.player_a_id = ? '
                . 'UNION ALL '
                . 'SELECT g.player_a_id AS opponent_id FROM amiga_games g WHERE g.player_b_id = ?'
                . ') AS opponents WHERE opponent_id = ? LIMIT 1',
            'iii',
            [$playerId, $playerId, $opponentFilter]
        );
        if ($opponentRows === []) {
            $opponentFilter = 0;
        }
    }

    $countryMetaTypes = 'ii';
    $countryMetaParams = [$playerId, $playerId];
    $countryMetaSql = amiga_games_tournament_meta_and_sql($eventFilter, '', $countryMetaTypes, $countryMetaParams);
    $countryRowList = amiga_games_query_all(
        $con,
        'SELECT DISTINCT t.country AS country FROM amiga_games g '
            . 'INNER JOIN tournaments t ON t.id = g.tournament_id '
            . 'WHERE (g.player_a_id = ? OR g.player_b_id = ?) '
            . 'AND t.country IS NOT NULL AND TRIM(t.country) <> \'\''
            . $countryMetaSql
            . ' ORDER BY country ASC',
        $countryMetaTypes,
        $countryMetaParams
    );
    $countryOptions = [];
    foreach ($countryRowList as $countryRow) {
        $countryOptions[] = (string) $countryRow['country'];
    }
    $countryFilter = amiga_games_valid_country_filter(
        isset($query['country']) && is_string($query['country']) ? $query['country'] : '',
        $countryOptions
    );

    if ($tournamentFilter > 0) {
        $checkTypes = 'iii';
        $checkParams = [$playerId, $playerId, $tournamentFilter];
        $checkMetaSql = amiga_games_tournament_meta_and_sql(
            $eventFilter,
            $countryFilter,
            $checkTypes,
            $checkParams
        );
        $tournamentRows = amiga_games_query_all(
            $con,
            'SELECT g.tournament_id FROM amiga_games g '
                . 'INNER JOIN tournaments t ON t.id = g.tournament_id '
                . 'WHERE (g.player_a_id = ? OR g.player_b_id = ?) AND g.tournament_id = ?'
                . $checkMetaSql
                . ' LIMIT 1',
            $checkTypes,
            $checkParams
        );
        if ($tournamentRows === []) {
            $tournamentFilter = 0;
        }
    }

    return [
        'result' => $resultFilter,
        'opponent' => $opponentFilter,
        'tournament' => $tournamentFilter,
        'event' => $eventFilter,
        'country' => $countryFilter,
        'day' => $utcDayFilter,
        'since' => $sinceYear,
        'year' => $yearFilter,
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
    if (!empty($state['day'])) {
        $params['day'] = (string) $state['day'];
    }
    if ((int) ($state['since'] ?? 0) > 0) {
        $params['since'] = (int) $state['since'];
    }
    if ((int) ($state['year'] ?? 0) > 0) {
        $params['year'] = (int) $state['year'];
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

function amiga_games_where_clause(
    int $playerId,
    string $resultFilter,
    int $opponentId,
    int $tournamentId,
    string $eventFilter,
    string $countryFilter,
    string $utcDay,
    int $sinceYear,
    int $yearFilter,
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

    if ($tournamentId > 0) {
        $where[] = 'r.tournament_id = ?';
        $types .= 'i';
        $params[] = $tournamentId;
    }

    if ($eventFilter === 'world-cup') {
        $where[] = amiga_games_world_cup_name_sql('r.tournament_name');
    }

    if ($countryFilter !== '') {
        $where[] = 'r.tournament_country = ?';
        $types .= 's';
        $params[] = $countryFilter;
    }

    if ($sinceYear > 0) {
        $where[] = 'YEAR(r.`Date`) >= ?';
        $types .= 'i';
        $params[] = $sinceYear;
    }

    if ($yearFilter > 0) {
        $where[] = 'YEAR(r.`Date`) = ?';
        $types .= 'i';
        $params[] = $yearFilter;
    }

    return implode(' AND ', $where);
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
        $parts[] = amiga_games_world_cup_name_sql('t.name');
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

    $aria = $isActive ? ($state['dir'] === 'desc' ? 'descending' : 'ascending') : 'none';
    $attrs = [
        'class="' . implode(' ', $classes) . '"',
        'aria-sort="' . $aria . '"',
        'data-k2-help="' . amiga_games_h($help) . '"',
    ];
    if ($tooltipLabel !== '') {
        $attrs[] = 'data-k2-tooltip-label="' . amiga_games_h($tooltipLabel) . '"';
    }

    return '<th ' . implode(' ', $attrs) . '>'
        . '<a href="' . amiga_games_h(amiga_games_build_url($params)) . '">' . $label . '</a>'
        . '</th>';
}
