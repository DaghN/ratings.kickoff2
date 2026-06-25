<?php
/**
 * Faceted filter counts for amiga/player/games.php listboxes.
 *
 * Each facet query applies all active filters except the dimension being edited.
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_games_filter_facet_helpers.php';
require_once __DIR__ . '/k2_ratedresults_games_filters.php';
require_once __DIR__ . '/amiga_db.php';
require_once __DIR__ . '/amiga_player_games_lib.php';

/**
 * @return array{
 *   result: string,
 *   opponent: int,
 *   tournament: int,
 *   event: string,
 *   country: string,
 *   opp_country: string,
 *   day: string,
 *   since: int,
 *   until: int,
 *   year: int,
 *   gf: int,
 *   ga: int,
 *   gs: int,
 *   gd: ?int
 * }
 */
function amiga_player_games_filter_context(array $filters): array
{
    return [
        'result' => (string) ($filters['result'] ?? 'all'),
        'opponent' => (int) ($filters['opponent'] ?? 0),
        'tournament' => (int) ($filters['tournament'] ?? 0),
        'event' => (string) ($filters['event'] ?? 'all'),
        'country' => (string) ($filters['country'] ?? ''),
        'opp_country' => (string) ($filters['opp_country'] ?? ''),
        'day' => (string) ($filters['day'] ?? ''),
        'since' => (int) ($filters['since'] ?? 0),
        'until' => (int) ($filters['until'] ?? 0),
        'year' => (int) ($filters['year'] ?? 0),
        'gf' => (int) ($filters['gf'] ?? -1),
        'ga' => (int) ($filters['ga'] ?? -1),
        'gs' => (int) ($filters['gs'] ?? -1),
        'gd' => array_key_exists('gd', $filters) ? $filters['gd'] : null,
    ];
}

/**
 * @param-out string $types
 * @param-out list<int|string> $params
 */
function amiga_player_games_facet_where(
    int $playerId,
    array $ctx,
    string $omitFacet,
    string &$types,
    array &$params,
    ?AmigaSnapshotContext $snapshotCtx = null,
): string {
    $result = (string) $ctx['result'];
    $opponent = (int) $ctx['opponent'];
    $tournament = (int) $ctx['tournament'];
    $event = (string) $ctx['event'];
    $country = (string) $ctx['country'];
    $oppCountry = (string) $ctx['opp_country'];
    $day = (string) $ctx['day'];
    $since = (int) $ctx['since'];
    $until = (int) $ctx['until'];
    $year = (int) $ctx['year'];
    $gf = (int) $ctx['gf'];
    $ga = (int) $ctx['ga'];
    $gs = (int) $ctx['gs'];
    $gd = $ctx['gd'];

    if ($omitFacet === 'result') {
        $result = 'all';
    } elseif ($omitFacet === 'opponent') {
        $opponent = 0;
    } elseif ($omitFacet === 'tournament') {
        $tournament = 0;
    } elseif ($omitFacet === 'country') {
        $country = '';
    } elseif ($omitFacet === 'opp_country') {
        $oppCountry = '';
    } elseif ($omitFacet === 'since') {
        $since = 0;
    } elseif ($omitFacet === 'until') {
        $until = 0;
    } elseif ($omitFacet === 'year') {
        $year = 0;
    } elseif ($omitFacet === 'gf') {
        $gf = -1;
    } elseif ($omitFacet === 'ga') {
        $ga = -1;
    } elseif ($omitFacet === 'gs') {
        $gs = -1;
    } elseif ($omitFacet === 'gd') {
        $gd = null;
    }

    return amiga_games_where_clause(
        $playerId,
        $result,
        $opponent,
        $tournament,
        $event,
        $country,
        $oppCountry,
        $day,
        $since,
        $until,
        $year,
        $gf,
        $ga,
        $gs,
        $gd,
        $types,
        $params,
        $snapshotCtx
    );
}

/** @return array{win: int, draw: int, loss: int} */
function amiga_player_games_facet_result_counts(
    mysqli $con,
    int $playerId,
    array $ctx,
    ?AmigaSnapshotContext $snapshotCtx = null,
): array {
    $types = '';
    $params = [];
    $where = amiga_player_games_facet_where($playerId, $ctx, 'result', $types, $params, $snapshotCtx);
    $playerIdSql = (int) $playerId;
    $sql = 'SELECT CASE '
        . "WHEN ((r.idA = $playerIdSql AND ABS(r.ActualScore - 1.0) < 0.001) OR (r.idB = $playerIdSql AND ABS(r.ActualScore) < 0.001)) THEN 'win' "
        . "WHEN ABS(r.ActualScore - 0.5) < 0.001 THEN 'draw' "
        . "ELSE 'loss' END AS bucket, COUNT(*) AS games "
        . amiga_rated_games_from_sql()
        . ' WHERE ' . $where . ' GROUP BY bucket';

    $counts = ['win' => 0, 'draw' => 0, 'loss' => 0];
    foreach (k2_games_facet_query_rows($con, $sql, $types, $params) as $row) {
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
function amiga_player_games_facet_opponent_rows(
    mysqli $con,
    int $playerId,
    array $ctx,
    ?AmigaSnapshotContext $snapshotCtx = null,
): array {
    $types = '';
    $params = [];
    $where = amiga_player_games_facet_where($playerId, $ctx, 'opponent', $types, $params, $snapshotCtx);
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

/**
 * @return list<array{tournament_id: int, tournament_name: string, games: int}>
 */
function amiga_player_games_facet_tournament_rows(
    mysqli $con,
    int $playerId,
    array $ctx,
    ?AmigaSnapshotContext $snapshotCtx = null,
): array {
    $types = '';
    $params = [];
    $where = amiga_player_games_facet_where($playerId, $ctx, 'tournament', $types, $params, $snapshotCtx);
    $sql = 'SELECT r.tournament_id, r.tournament_name, COUNT(*) AS games '
        . amiga_rated_games_from_sql()
        . ' WHERE ' . $where
        . ' GROUP BY r.tournament_id, r.tournament_name '
        . 'ORDER BY games DESC, r.tournament_name ASC';

    $rows = [];
    foreach (k2_games_facet_query_rows($con, $sql, $types, $params) as $row) {
        $rows[] = [
            'tournament_id' => (int) ($row['tournament_id'] ?? 0),
            'tournament_name' => (string) ($row['tournament_name'] ?? ''),
            'games' => (int) ($row['games'] ?? 0),
        ];
    }

    return $rows;
}

/**
 * @return list<array{country: string, games: int}>
 */
function amiga_player_games_facet_country_rows(
    mysqli $con,
    int $playerId,
    array $ctx,
    string $omitFacet,
    ?AmigaSnapshotContext $snapshotCtx = null,
): array {
    $types = '';
    $params = [];
    $where = amiga_player_games_facet_where($playerId, $ctx, $omitFacet, $types, $params, $snapshotCtx);
    if ($omitFacet === 'country') {
        $bucketExpr = 'r.tournament_country';
    } else {
        $bucketExpr = amiga_games_hero_opponent_country_sql($playerId);
    }

    $sql = 'SELECT ' . $bucketExpr . ' AS country, COUNT(*) AS games '
        . amiga_rated_games_from_sql()
        . ' WHERE ' . $where
        . ' AND ' . $bucketExpr . ' IS NOT NULL AND TRIM(' . $bucketExpr . ") <> ''"
        . ' GROUP BY country ORDER BY country ASC';

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

/** @return array<int, int> */
function amiga_player_games_facet_year_histogram(
    mysqli $con,
    int $playerId,
    array $ctx,
    string $omitFacet,
    ?AmigaSnapshotContext $snapshotCtx = null,
): array {
    $types = '';
    $params = [];
    $where = amiga_player_games_facet_where($playerId, $ctx, $omitFacet, $types, $params, $snapshotCtx);
    $sql = 'SELECT YEAR(r.`Date`) AS yr, COUNT(*) AS games '
        . amiga_rated_games_from_sql()
        . ' WHERE ' . $where . ' GROUP BY yr ORDER BY yr ASC';

    $sparse = [];
    foreach (k2_games_facet_query_rows($con, $sql, $types, $params) as $row) {
        $year = (int) ($row['yr'] ?? 0);
        if ($year > 0) {
            $sparse[$year] = (int) ($row['games'] ?? 0);
        }
    }

    return $sparse;
}

/** @param array<int, int> $histogram @return array<int, int> */
function amiga_player_games_facet_year_counts(array $histogram): array
{
    return $histogram;
}

/** @param array<int, int> $histogram @return array<int, int> */
function amiga_player_games_facet_since_counts(array $histogram): array
{
    if ($histogram === []) {
        return [];
    }

    $years = array_keys($histogram);
    sort($years, SORT_NUMERIC);
    $counts = [];
    $running = 0;
    for ($i = count($years) - 1; $i >= 0; $i--) {
        $year = $years[$i];
        $running += (int) ($histogram[$year] ?? 0);
        $counts[$year] = $running;
    }

    return $counts;
}

/** @param array<int, int> $histogram @return array<int, int> */
function amiga_player_games_facet_until_counts(array $histogram): array
{
    if ($histogram === []) {
        return [];
    }

    $years = array_keys($histogram);
    sort($years, SORT_NUMERIC);
    $counts = [];
    $running = 0;
    foreach ($years as $year) {
        $running += (int) ($histogram[$year] ?? 0);
        $counts[$year] = $running;
    }

    return $counts;
}

/** @return array<int, int> */
function amiga_player_games_facet_numeric_counts(
    mysqli $con,
    int $playerId,
    array $ctx,
    string $omitFacet,
    string $valueExpr,
    string $orderBy,
    ?AmigaSnapshotContext $snapshotCtx = null,
): array {
    $types = '';
    $params = [];
    $where = amiga_player_games_facet_where($playerId, $ctx, $omitFacet, $types, $params, $snapshotCtx);
    $sql = 'SELECT ' . $valueExpr . ' AS v, COUNT(*) AS games '
        . amiga_rated_games_from_sql()
        . ' WHERE ' . $where . ' GROUP BY v ORDER BY ' . $orderBy;

    $sparse = [];
    foreach (k2_games_facet_query_rows($con, $sql, $types, $params) as $row) {
        $sparse[(int) ($row['v'] ?? 0)] = (int) ($row['games'] ?? 0);
    }

    return k2_games_facet_expand_numeric_gaps($sparse);
}

/**
 * @return array{
 *   result: array{win: int, draw: int, loss: int},
 *   opponent: list<array{opponent_id: int, opponent_name: string, games: int}>,
 *   tournament: list<array{tournament_id: int, tournament_name: string, games: int}>,
 *   country: list<array{country: string, games: int}>,
 *   opp_country: list<array{country: string, games: int}>,
 *   year: array<int, int>,
 *   since: array<int, int>,
 *   until: array<int, int>,
 *   gf: array<int, int>,
 *   ga: array<int, int>,
 *   gs: array<int, int>,
 *   gd: array<int, int>
 * }
 */
function amiga_player_games_load_filter_facets(
    mysqli $con,
    int $playerId,
    array $ctx,
    ?AmigaSnapshotContext $snapshotCtx = null,
): array {
    $playerIdSql = (int) $playerId;
    $heroGoalsFor = "CASE WHEN r.idA = $playerIdSql THEN r.GoalsA ELSE r.GoalsB END";
    $heroGoalsAgainst = "CASE WHEN r.idA = $playerIdSql THEN r.GoalsB ELSE r.GoalsA END";
    $heroGd = "CASE WHEN r.idA = $playerIdSql THEN r.GoalsA - r.GoalsB ELSE r.GoalsB - r.GoalsA END";

    $yearHistogram = amiga_player_games_facet_year_histogram($con, $playerId, $ctx, 'year', $snapshotCtx);
    $sinceHistogram = amiga_player_games_facet_year_histogram($con, $playerId, $ctx, 'since', $snapshotCtx);
    $untilHistogram = amiga_player_games_facet_year_histogram($con, $playerId, $ctx, 'until', $snapshotCtx);

    return [
        'result' => amiga_player_games_facet_result_counts($con, $playerId, $ctx, $snapshotCtx),
        'opponent' => amiga_player_games_facet_opponent_rows($con, $playerId, $ctx, $snapshotCtx),
        'tournament' => amiga_player_games_facet_tournament_rows($con, $playerId, $ctx, $snapshotCtx),
        'country' => amiga_player_games_facet_country_rows($con, $playerId, $ctx, 'country', $snapshotCtx),
        'opp_country' => amiga_player_games_facet_country_rows($con, $playerId, $ctx, 'opp_country', $snapshotCtx),
        'year' => amiga_player_games_facet_year_counts($yearHistogram),
        'since' => amiga_player_games_facet_since_counts($sinceHistogram),
        'until' => amiga_player_games_facet_until_counts($untilHistogram),
        'gf' => amiga_player_games_facet_numeric_counts($con, $playerId, $ctx, 'gf', $heroGoalsFor, 'v ASC', $snapshotCtx),
        'ga' => amiga_player_games_facet_numeric_counts($con, $playerId, $ctx, 'ga', $heroGoalsAgainst, 'v ASC', $snapshotCtx),
        'gs' => amiga_player_games_facet_numeric_counts($con, $playerId, $ctx, 'gs', 'r.SumOfGoals', 'v ASC', $snapshotCtx),
        'gd' => amiga_player_games_facet_numeric_counts($con, $playerId, $ctx, 'gd', $heroGd, 'v DESC', $snapshotCtx),
    ];
}

/**
 * @param list<array{opponent_id: int, opponent_name: string, games: int}> $rows
 * @return list<array{opponent_id: int, opponent_name: string, games: int}>
 */
function amiga_player_games_facet_inject_selected_opponent(mysqli $con, array $rows, int $opponentId): array
{
    if ($opponentId < 1) {
        return $rows;
    }

    foreach ($rows as $row) {
        if ((int) $row['opponent_id'] === $opponentId) {
            return $rows;
        }
    }

    $nameRows = k2_games_facet_query_rows(
        $con,
        'SELECT COALESCE(name, CONCAT(\'#\', id)) AS opponent_name FROM amiga_players WHERE id = ?',
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
 * @param list<array{tournament_id: int, tournament_name: string, games: int}> $rows
 * @return list<array{tournament_id: int, tournament_name: string, games: int}>
 */
function amiga_player_games_facet_inject_selected_tournament(mysqli $con, array $rows, int $tournamentId): array
{
    if ($tournamentId < 1) {
        return $rows;
    }

    foreach ($rows as $row) {
        if ((int) $row['tournament_id'] === $tournamentId) {
            return $rows;
        }
    }

    $nameRows = k2_games_facet_query_rows(
        $con,
        'SELECT name AS tournament_name FROM tournaments WHERE id = ?',
        'i',
        [$tournamentId]
    );
    $rows[] = [
        'tournament_id' => $tournamentId,
        'tournament_name' => (string) ($nameRows[0]['tournament_name'] ?? ('#' . $tournamentId)),
        'games' => 0,
    ];

    return $rows;
}

/**
 * @param list<array{country: string, games: int}> $rows
 * @return list<array{country: string, games: int}>
 */
function amiga_player_games_facet_inject_selected_country(array $rows, string $country): array
{
    $country = trim($country);
    if ($country === '') {
        return $rows;
    }

    foreach ($rows as $row) {
        if ((string) $row['country'] === $country) {
            return $rows;
        }
    }

    $rows[] = [
        'country' => $country,
        'games' => 0,
    ];

    return $rows;
}

/** @param array<int, int> $counts */
function amiga_player_games_facet_inject_selected_year(array $counts, int $selected): array
{
    if ($selected > 0 && !array_key_exists($selected, $counts)) {
        $counts[$selected] = 0;
    }

    return $counts;
}

/**
 * @param array<int, int> $counts
 * @return list<array{value: string, label: string, meta: string}>
 */
function amiga_player_games_facet_year_choices(array $counts, string $idleValue): array
{
    $choices = [['value' => $idleValue, 'label' => '', 'meta' => '']];
    if ($counts === []) {
        return $choices;
    }

    $years = array_keys($counts);
    rsort($years, SORT_NUMERIC);
    foreach ($years as $year) {
        $choices[] = [
            'value' => (string) $year,
            'label' => (string) $year,
            'meta' => (string) (int) $counts[$year],
        ];
    }

    return $choices;
}

/**
 * @param array{
 *   result: array{win: int, draw: int, loss: int},
 *   opponent: list<array{opponent_id: int, opponent_name: string, games: int}>,
 *   tournament: list<array{tournament_id: int, tournament_name: string, games: int}>,
 *   country: list<array{country: string, games: int}>,
 *   opp_country: list<array{country: string, games: int}>,
 *   year: array<int, int>,
 *   since: array<int, int>,
 *   until: array<int, int>,
 *   gf: array<int, int>,
 *   ga: array<int, int>,
 *   gs: array<int, int>,
 *   gd: array<int, int>
 * } $facets
 * @param array{
 *   result: string,
 *   opponent: int,
 *   tournament: int,
 *   event: string,
 *   country: string,
 *   opp_country: string,
 *   day: string,
 *   since: int,
 *   until: int,
 *   year: int,
 *   gf: int,
 *   ga: int,
 *   gs: int,
 *   gd: ?int
 * } $ctx
 * @return array<string, list<array{value: string, label: string, meta: string}>>
 */
function amiga_player_games_facet_listbox_choices(mysqli $con, array $facets, array $ctx): array
{
    $resultChoices = [
        ['value' => 'all', 'label' => '', 'meta' => ''],
        ['value' => 'win', 'label' => 'Win', 'meta' => (string) (int) $facets['result']['win']],
        ['value' => 'draw', 'label' => 'Draw', 'meta' => (string) (int) $facets['result']['draw']],
        ['value' => 'loss', 'label' => 'Loss', 'meta' => (string) (int) $facets['result']['loss']],
    ];

    $opponentRows = amiga_player_games_facet_inject_selected_opponent($con, $facets['opponent'], (int) $ctx['opponent']);
    $opponentChoices = [['value' => '0', 'label' => '', 'meta' => '']];
    foreach ($opponentRows as $row) {
        $opponentChoices[] = [
            'value' => (string) (int) $row['opponent_id'],
            'label' => (string) $row['opponent_name'],
            'meta' => (string) (int) $row['games'],
        ];
    }

    $tournamentRows = amiga_player_games_facet_inject_selected_tournament($con, $facets['tournament'], (int) $ctx['tournament']);
    $tournamentChoices = [['value' => '0', 'label' => '', 'meta' => '']];
    foreach ($tournamentRows as $row) {
        $tournamentChoices[] = [
            'value' => (string) (int) $row['tournament_id'],
            'label' => (string) $row['tournament_name'],
            'meta' => (string) (int) $row['games'],
        ];
    }

    $countryRows = amiga_player_games_facet_inject_selected_country($facets['country'], (string) $ctx['country']);
    $countryChoices = [['value' => '', 'label' => '', 'meta' => '']];
    foreach ($countryRows as $row) {
        $countryChoices[] = [
            'value' => (string) $row['country'],
            'label' => (string) $row['country'],
            'meta' => (string) (int) $row['games'],
        ];
    }

    $oppCountryRows = amiga_player_games_facet_inject_selected_country($facets['opp_country'], (string) $ctx['opp_country']);
    $oppCountryChoices = [['value' => '', 'label' => '', 'meta' => '']];
    foreach ($oppCountryRows as $row) {
        $oppCountryChoices[] = [
            'value' => (string) $row['country'],
            'label' => (string) $row['country'],
            'meta' => (string) (int) $row['games'],
        ];
    }

    $yearCounts = amiga_player_games_facet_inject_selected_year($facets['year'], (int) $ctx['year']);
    $sinceCounts = amiga_player_games_facet_inject_selected_year($facets['since'], (int) $ctx['since']);
    $untilCounts = amiga_player_games_facet_inject_selected_year($facets['until'], (int) $ctx['until']);

    $gfCounts = k2_games_facet_inject_selected_numeric($facets['gf'], (int) $ctx['gf']);
    $gaCounts = k2_games_facet_inject_selected_numeric($facets['ga'], (int) $ctx['ga']);
    $gsCounts = k2_games_facet_inject_selected_numeric($facets['gs'], (int) $ctx['gs']);
    $gdCounts = $facets['gd'];
    if ($ctx['gd'] !== null) {
        $gdCounts = k2_games_facet_inject_selected_numeric($gdCounts, (int) $ctx['gd']);
    }

    return [
        'result' => $resultChoices,
        'opponent' => $opponentChoices,
        'tournament' => $tournamentChoices,
        'country' => $countryChoices,
        'opp_country' => $oppCountryChoices,
        'year' => amiga_player_games_facet_year_choices($yearCounts, '0'),
        'since' => amiga_player_games_facet_year_choices($sinceCounts, '0'),
        'until' => amiga_player_games_facet_year_choices($untilCounts, '0'),
        'gf' => k2_games_facet_numeric_choices($gfCounts, '-1', false),
        'ga' => k2_games_facet_numeric_choices($gaCounts, '-1', false),
        'gs' => k2_games_facet_numeric_choices($gsCounts, '-1', false),
        'gd' => k2_games_facet_numeric_choices(
            $gdCounts,
            '',
            true,
            static fn(int $value): string => k2_player_games_hero_gd_label($value)
        ),
    ];
}

/** Drop URL values that never appear in this player's career; keep valid empty intersections. */
function amiga_player_games_validate_filters_career_wide(
    mysqli $con,
    int $playerId,
    array &$filters,
    ?AmigaSnapshotContext $snapshotCtx = null,
): void {
    $snapshotCtx ??= amiga_snapshot_context_peek();
    $yearOptions = amiga_player_games_year_options($con, $playerId, $snapshotCtx);
    $filters['since'] = amiga_games_valid_since_year((int) $filters['since'], $yearOptions);
    $filters['until'] = amiga_games_valid_until_year((int) $filters['until'], $yearOptions);
    $filters['year'] = amiga_games_valid_since_year((int) $filters['year'], $yearOptions);

    $emptyCtx = amiga_player_games_filter_context([
        'result' => 'all',
        'opponent' => 0,
        'tournament' => 0,
        'event' => $filters['event'],
        'country' => '',
        'opp_country' => '',
        'day' => '',
        'since' => 0,
        'until' => 0,
        'year' => 0,
        'gf' => -1,
        'ga' => -1,
        'gs' => -1,
        'gd' => null,
    ]);

    $playerIdSql = (int) $playerId;
    $heroGoalsFor = "CASE WHEN r.idA = $playerIdSql THEN r.GoalsA ELSE r.GoalsB END";
    $heroGoalsAgainst = "CASE WHEN r.idA = $playerIdSql THEN r.GoalsB ELSE r.GoalsA END";
    $heroGd = "CASE WHEN r.idA = $playerIdSql THEN r.GoalsA - r.GoalsB ELSE r.GoalsB - r.GoalsA END";

    $validOpponents = [];
    foreach (amiga_player_games_facet_opponent_rows($con, $playerId, $emptyCtx, $snapshotCtx) as $row) {
        $validOpponents[(int) $row['opponent_id']] = true;
    }
    if ((int) $filters['opponent'] > 0 && !isset($validOpponents[(int) $filters['opponent']])) {
        $filters['opponent'] = 0;
    }

    $validTournaments = [];
    foreach (amiga_player_games_facet_tournament_rows($con, $playerId, $emptyCtx, $snapshotCtx) as $row) {
        $validTournaments[(int) $row['tournament_id']] = true;
    }
    if ((int) $filters['tournament'] > 0 && !isset($validTournaments[(int) $filters['tournament']])) {
        $filters['tournament'] = 0;
    }

    $validCountries = [];
    foreach (amiga_player_games_facet_country_rows($con, $playerId, $emptyCtx, 'country', $snapshotCtx) as $row) {
        $validCountries[(string) $row['country']] = true;
    }
    $filters['country'] = amiga_games_valid_country_filter((string) $filters['country'], array_keys($validCountries));

    $validOppCountries = [];
    foreach (amiga_player_games_facet_country_rows($con, $playerId, $emptyCtx, 'opp_country', $snapshotCtx) as $row) {
        $validOppCountries[(string) $row['country']] = true;
    }
    $filters['opp_country'] = amiga_games_valid_country_filter((string) $filters['opp_country'], array_keys($validOppCountries));

    $validGoalsScored = amiga_player_games_facet_numeric_counts($con, $playerId, $emptyCtx, 'gf', $heroGoalsFor, 'v ASC', $snapshotCtx);
    $validGoalsConceded = amiga_player_games_facet_numeric_counts($con, $playerId, $emptyCtx, 'ga', $heroGoalsAgainst, 'v ASC', $snapshotCtx);
    $validGoalsSum = amiga_player_games_facet_numeric_counts($con, $playerId, $emptyCtx, 'gs', 'r.SumOfGoals', 'v ASC', $snapshotCtx);
    $validHeroGoalDiff = amiga_player_games_facet_numeric_counts($con, $playerId, $emptyCtx, 'gd', $heroGd, 'v DESC', $snapshotCtx);

    $filters['gf'] = k2_ratedresults_games_valid_goals_filter((int) $filters['gf'], $validGoalsScored);
    $filters['ga'] = k2_ratedresults_games_valid_goals_filter((int) $filters['ga'], $validGoalsConceded);
    $filters['gs'] = k2_ratedresults_games_valid_goals_filter((int) $filters['gs'], $validGoalsSum);
    $filters['gd'] = k2_ratedresults_games_valid_hero_gd_filter($filters['gd'], $validHeroGoalDiff);
}