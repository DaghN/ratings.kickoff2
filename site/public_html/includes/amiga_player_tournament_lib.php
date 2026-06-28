<?php
/**
 * Amiga player tournament participation + career totals read path (snapshots + current).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_table_helpers.php';
require_once __DIR__ . '/amiga_tournament_lib.php';
require_once __DIR__ . '/amiga_player_load.php';
require_once __DIR__ . '/amiga_player_current_lib.php';
require_once __DIR__ . '/amiga_snapshot_context.php';

/**
 * Tournament participation rows for a player (canonical derived source).
 *
 * Rows are shaped for profile blocks: id, name, position (event_finish_position), event_points, games, knockout_ties.
 * Ordered newest event first. Optional $limit caps rows (profile recent list); omit for full history.
 *
 * @return list<array<string, mixed>>
 */
function amiga_player_tournament_participation_rows(
    mysqli $con,
    int $playerId,
    ?int $limit = null,
    ?AmigaSnapshotContext $ctx = null,
): array {
    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();

    $types = 'i';
    $params = [$playerId];
    $cutoffSql = '';
    if ($ctx->isActive()) {
        $cutoff = $ctx->cutoff();
        if ($cutoff !== null) {
            $cutoffSql = amiga_snapshot_event_tuple_cutoff_and_sql(
                $cutoff,
                $types,
                $params,
                'p.event_date',
                'p.event_chrono',
                'p.tournament_id',
            );
        }
    }

    $sql = 'SELECT p.tournament_id AS id,
                   p.tournament_name AS name,
                   p.event_date,
                   p.event_chrono,
                   p.is_cup,
                   p.has_league,
                   p.has_cup,
                   p.country,
                   p.event_finish_position AS position,
                   p.event_points,
                   p.games,
                   p.wins,
                   p.draws,
                   p.losses,
                   p.goals_for,
                   p.goals_against,
                   p.avg_goals_for,
                   p.avg_goals_against,
                   p.rating_before,
                   p.rating_delta,
                   p.rating_after,
                   p.performance_rating,
                   p.is_winner,
                   (SELECT COUNT(DISTINCT sk.scope_key)
                    FROM amiga_tournament_standings sk
                    WHERE sk.tournament_id = p.tournament_id
                      AND sk.scope_type = \'knockout\') AS knockout_ties
            FROM amiga_player_event_snapshots p
            INNER JOIN tournaments t ON t.id = p.tournament_id
            WHERE p.player_id = ?
              AND ' . amiga_tournament_public_visibility_where('t') . $cutoffSql . '
            ORDER BY COALESCE(p.event_chrono, 999999) DESC,
                     COALESCE(p.event_date, \'1970-01-01\') DESC,
                     p.tournament_id DESC';
    if ($limit !== null) {
        $limit = max(1, min(20, $limit));
        $sql .= ' LIMIT ' . (int) $limit;
    }
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return [];
    }
    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = &$params[$key];
    }
    mysqli_stmt_bind_param($stmt, $types, ...$refs);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * Recent tournament participation (profile snippet).
 *
 * @return list<array<string, mixed>>
 */
function amiga_player_tournament_participation_recent(mysqli $con, int $playerId, int $limit = 5): array
{
    return amiga_player_tournament_participation_rows($con, $playerId, $limit);
}

/**
 * Full tournament participation history (all events, no pagination).
 *
 * @return list<array<string, mixed>>
 */
function amiga_player_tournament_participation_all(mysqli $con, int $playerId): array
{
    return amiga_player_tournament_participation_rows($con, $playerId, null);
}

/**
 * Distinct event locations (country) from participation rows, sorted A–Z.
 *
 * @param list<array<string, mixed>> $rows
 * @return list<string>
 */
function amiga_player_tournament_participation_countries(array $rows): array
{
    $countries = [];
    foreach ($rows as $row) {
        $country = trim((string) ($row['country'] ?? ''));
        if ($country !== '') {
            $countries[$country] = true;
        }
    }
    $list = array_keys($countries);
    sort($list, SORT_STRING);

    return $list;
}

/**
 * @param list<array<string, mixed>> $rows
 * @param 'all'|'world-cup' $filter
 * @return list<array<string, mixed>>
 */
function amiga_player_tournament_participation_filter_events(
    array $rows,
    string $filter,
    string $country = '',
    int $year = 0
): array {
    $country = trim($country);
    if ($filter === 'all' && $country === '' && $year < 1) {
        return $rows;
    }

    return array_values(array_filter(
        $rows,
        static function (array $row) use ($filter, $country, $year): bool {
            if ($filter === 'world-cup' && !amiga_tournament_is_world_cup($row)) {
                return false;
            }
            if ($country !== '' && trim((string) ($row['country'] ?? '')) !== $country) {
                return false;
            }
            if ($year > 0 && amiga_tournament_index_event_year($row) !== $year) {
                return false;
            }

            return true;
        }
    ));
}

/**
 * Plain-language count line for the filtered tournament history list.
 */
function amiga_player_tournaments_list_summary(
    int $count,
    string $eventFilter,
    string $countryFilter,
    bool $hasAnyParticipation,
    int $yearFilter = 0,
): string {
    if ($count === 0) {
        if (!$hasAnyParticipation) {
            return 'No events on record yet.';
        }

        return 'No events match these filters.';
    }

    $word = $count === 1 ? 'event' : 'events';
    $n = number_format($count);
    $yearSuffix = $yearFilter > 0 ? ' in ' . $yearFilter : '';

    if ($eventFilter === 'world-cup' && $countryFilter !== '') {
        return $n . ' World Cup ' . $word . ' in ' . $countryFilter . $yearSuffix . '.';
    }
    if ($eventFilter === 'world-cup') {
        return $n . ' World Cup ' . $word . $yearSuffix . '.';
    }
    if ($countryFilter !== '') {
        return $n . ' ' . $word . ' in ' . $countryFilter . $yearSuffix . '.';
    }
    if ($yearFilter > 0) {
        return $n . ' ' . $word . ' in ' . $yearFilter . '.';
    }

    return $n . ' ' . $word . ' in total.';
}

/**
 * Filter pills URL for player tournament history.
 */
function amiga_player_tournaments_filter_url(int $playerId, string $filter = 'all', string $country = '', int $year = 0): string
{
    $params = ['id' => $playerId];
    if ($filter !== 'all') {
        $params['filter'] = $filter;
    }
    $country = trim($country);
    if ($country !== '') {
        $params['country'] = $country;
    }
    if ($year > 0) {
        $params['year'] = (string) $year;
    }

    return k2_amiga_route('amiga-player-tournaments', array_merge($params, k2_table_sort_query_params()));
}

/**
 * Event-wide participation roster for one tournament (tournament.php event stats).
 *
 * @return list<array<string, mixed>>
 */
function amiga_tournament_participation_rows(mysqli $con, int $tournamentId): array
{
    if ($tournamentId < 1) {
        return [];
    }

    $sql = 'SELECT p.player_id,
                   pl.name AS player_name,
                   pl.country AS player_country,
                   p.tournament_id,
                   p.tournament_name AS name,
                   p.is_cup,
                   p.has_league,
                   p.has_cup,
                   p.country,
                   p.event_finish_position AS position,
                   p.event_points,
                   p.games,
                   p.wins,
                   p.draws,
                   p.losses,
                   p.goals_for,
                   p.goals_against,
                   p.avg_goals_for,
                   p.avg_goals_against,
                   p.rating_before,
                   p.rating_delta,
                   p.rating_after,
                   p.performance_rating,
                   p.is_winner
            FROM amiga_player_event_snapshots p
            INNER JOIN amiga_players pl ON pl.id = p.player_id
            INNER JOIN tournaments t ON t.id = p.tournament_id
            WHERE p.tournament_id = ?
              AND ' . amiga_tournament_public_visibility_where('t') . '
            ORDER BY p.event_points DESC,
                     (p.goals_for - p.goals_against) DESC,
                     p.goals_for DESC,
                     pl.name ASC';
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'i', $tournamentId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * @return array<string, mixed>|null
 */
function amiga_player_perf_rating_row_fetch(
    mysqli $con,
    int $playerId,
    string $orderSql,
): ?array {
    if ($playerId < 1) {
        return null;
    }

    $sql = 'SELECT p.tournament_id,
                   p.tournament_name AS name,
                   p.games,
                   p.performance_rating
            FROM amiga_player_event_snapshots p
            INNER JOIN tournaments t ON t.id = p.tournament_id
            WHERE p.player_id = ?
              AND p.performance_rating IS NOT NULL
              AND p.games >= 2
              AND ' . amiga_tournament_public_visibility_where('t') . '
            ORDER BY ' . $orderSql . '
            LIMIT 1';
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $playerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : false;
    if ($res) {
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);

    return $row !== false ? $row : null;
}

/**
 * Profile discovery: best single-event perf and most recent perf with a value.
 *
 * @return array{best: ?array<string, mixed>, recent: ?array<string, mixed>}
 */
function amiga_player_perf_rating_highlight(mysqli $con, int $playerId): array
{
    $best = amiga_player_perf_rating_row_fetch(
        $con,
        $playerId,
        'p.performance_rating DESC, p.games DESC, p.tournament_id DESC'
    );
    $recent = amiga_player_perf_rating_row_fetch(
        $con,
        $playerId,
        'COALESCE(p.event_chrono, 0) DESC, COALESCE(p.event_date, \'1970-01-01\') DESC, p.tournament_id DESC'
    );

    return ['best' => $best, 'recent' => $recent];
}

/**
 * Best single-event performance rating per player (one row each).
 *
 * Tie-break when picking the event: perf DESC, event games DESC, tournament_id DESC.
 * Leaderboard order: perf DESC, event games DESC, ladder rating DESC, player_id ASC.
 *
 * @return list<array<string, mixed>>
 */
function amiga_lb_performance_rating_rows(mysqli $con, ?AmigaSnapshotContext $ctx = null): array
{
    if ($ctx !== null && $ctx->isActive()) {
        require_once __DIR__ . '/amiga_lb_snapshot_lib.php';

        return amiga_lb_performance_rating_rows_at_cutoff($con, $ctx);
    }

    $sql = 'SELECT ranked.player_id,
                   ranked.player_name,
                   ranked.Rating,
                   ranked.country,
                   ranked.NumberGames,
                   ranked.tournament_id,
                   ranked.tournament_name,
                   ranked.event_date,
                   ranked.event_chrono,
                   ranked.event_games,
                   ranked.performance_rating
            FROM (
                SELECT pl.id AS player_id,
                       pl.name AS player_name,
                       s.Rating,
                       pl.country AS country,
                       s.NumberGames,
                       part.tournament_id,
                       part.tournament_name,
                       part.event_date,
                       part.event_chrono,
                       part.games AS event_games,
                       part.performance_rating,
                       ROW_NUMBER() OVER (
                           PARTITION BY part.player_id
                           ORDER BY part.performance_rating DESC,
                                    part.games DESC,
                                    part.tournament_id DESC
                       ) AS rn
                FROM amiga_player_event_snapshots part
                INNER JOIN amiga_players pl ON pl.id = part.player_id
                ' . amiga_player_career_join_sql($con, 'part.player_id') . '
                INNER JOIN tournaments t ON t.id = part.tournament_id
                WHERE part.performance_rating IS NOT NULL
                  AND part.games >= 2
                  AND s.NumberGames > 0
                  AND ' . amiga_tournament_public_visibility_where('t') . '
            ) ranked
            WHERE ranked.rn = 1
            ORDER BY ranked.performance_rating DESC,
                     ranked.event_games DESC,
                     ranked.Rating DESC,
                     ranked.player_id ASC';
    $result = mysqli_query($con, $sql);
    if (!$result) {
        return [];
    }
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_free_result($result);

    return $rows;
}

/**
 * Career tournament honours rollups for one player (profile, honours LB).
 *
 * Reads honours columns from amiga_player_current (name kept for call-site stability).
 *
 * @return array<string, mixed>|null
 */
function amiga_player_tournament_totals_row(mysqli $con, int $playerId): ?array
{
    require_once __DIR__ . '/amiga_player_slice_lib.php';

    $sql = 'SELECT t.player_id,
                   t.tournaments_played,
                   t.tournaments_won,
                   t.event_gold,
                   t.event_silver,
                   t.event_bronze,
                   t.event_podiums,
                   ' . amiga_slice_wc_lb_select_sql('wcs') . ',
                   t.last_event_date,
                   t.last_tournament_id
            FROM amiga_player_current t
            ' . amiga_slice_present_join_sql('t.player_id') . '
            WHERE t.player_id = ?
            LIMIT 1';
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $playerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : false;
    if ($res) {
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);

    return $row !== false ? $row : null;
}

/**
 * Tournament honours leaderboard rows (all-events honours + Elo).
 *
 * Default SQL order: tournaments_played, event_gold, event_podiums.
 *
 * @return list<array<string, mixed>>
 */
function amiga_tournament_honours_leaderboard_rows(mysqli $con, ?AmigaSnapshotContext $ctx = null): array
{
    if ($ctx !== null && $ctx->isActive()) {
        require_once __DIR__ . '/amiga_lb_snapshot_lib.php';

        return amiga_lb_honours_rows_at_cutoff($con, $ctx);
    }

    $sql = 'SELECT t.player_id,
                   p.name AS player_name,
                   p.country,
                   COALESCE(t.Rating, 0) AS rating,
                   t.tournaments_played,
                   t.event_gold,
                   t.event_silver,
                   t.event_bronze,
                   t.event_podiums
            FROM amiga_player_current t
            INNER JOIN amiga_players p ON p.id = t.player_id
            WHERE t.tournaments_played > 0
            ORDER BY t.tournaments_played DESC,
                     t.event_gold DESC,
                     t.event_podiums DESC,
                     t.player_id ASC';
    $result = mysqli_query($con, $sql);
    if (!$result) {
        return [];
    }
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_free_result($result);

    return $rows;
}

/**
 * Calendar-year + geography leaderboard rows from amiga_player_current.
 *
 * @return list<array<string, mixed>>
 */
function amiga_calendar_geo_leaderboard_rows(mysqli $con, ?AmigaSnapshotContext $ctx = null): array
{
    if ($ctx !== null && $ctx->isActive()) {
        require_once __DIR__ . '/amiga_lb_snapshot_lib.php';

        return amiga_lb_calendar_geo_rows_at_cutoff($con, $ctx);
    }

    $sql = 'SELECT t.player_id,
                   p.name AS player_name,
                   p.country,
                   COALESCE(t.Rating, 0) AS rating,
                   t.peak_year_games,
                   t.peak_year_games_year,
                   t.peak_year_tournaments,
                   t.peak_year_tournaments_year,
                   t.countries_played_in,
                   t.opponent_countries_faced,
                   t.opponent_countries_beaten
            FROM amiga_player_current t
            INNER JOIN amiga_players p ON p.id = t.player_id
            WHERE t.NumberGames > 0
            ORDER BY t.peak_year_games DESC,
                     t.peak_year_games_year ASC,
                     t.player_id ASC';
    $result = mysqli_query($con, $sql);
    if (!$result) {
        return [];
    }
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_free_result($result);

    return $rows;
}
