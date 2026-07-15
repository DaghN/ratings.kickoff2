<?php
/**
 * Amiga leaderboard — snapshot-at-cutoff reads (time travel).
 *
 * @see docs/amiga-time-travel-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/amiga_player_current_lib.php';
require_once __DIR__ . '/amiga_lb_lib.php';
require_once __DIR__ . '/amiga_tournament_lib.php';

/**
 * FROM + JOIN latest snapshot row per player on or before cutoff (alias ``s``).
 *
 * Window scan is narrow (id + chrono tuple only), then joins back to the wide
 * snapshot row by PRIMARY KEY. ROW_NUMBER over ``snap.*`` materialized all 174
 * snapshot columns into a temp table (~0.5–2 s); this shape is ~50 ms with
 * byte-identical rows (F6 Phase 0 audit, 2026-07-04).
 */
function amiga_lb_snapshot_from_sql(string $alias = 's'): string
{
    return "FROM amiga_players p\nINNER JOIN (\n"
        . "    SELECT x.player_id, x.tournament_id FROM (\n"
        . "        SELECT snap.player_id, snap.tournament_id,\n"
        . "            ROW_NUMBER() OVER (\n"
        . "                PARTITION BY snap.player_id\n"
        . "                ORDER BY snap.event_date DESC, snap.event_chrono DESC, snap.tournament_id DESC\n"
        . "            ) AS rn\n"
        . "        FROM amiga_player_event_snapshots snap\n"
        . "        WHERE (snap.event_date, snap.event_chrono, snap.tournament_id) <= (?, ?, ?)\n"
        . "    ) x\n"
        . "    WHERE x.rn = 1\n"
        . ") {$alias}_latest ON {$alias}_latest.player_id = p.id\n"
        . "INNER JOIN amiga_player_event_snapshots {$alias}\n"
        . "    ON {$alias}.player_id = {$alias}_latest.player_id AND {$alias}.tournament_id = {$alias}_latest.tournament_id";
}

/**
 * JOIN best imperfect perf event per player (alias ``part``) — narrow window + PK join-back.
 *
 * @param bool $atCutoff When true, inner scan is limited to rows on or before cutoff (3 bind params).
 */
function amiga_lb_best_perf_event_join_sql(string $alias = 'part', bool $atCutoff = true): string
{
    $visibility = amiga_tournament_public_visibility_where('t');
    $cutoffSql = $atCutoff
        ? "          AND (snap.event_date, snap.event_chrono, snap.tournament_id) <= (?, ?, ?)\n"
        : '';

    return "INNER JOIN (\n"
        . "    SELECT x.player_id, x.tournament_id FROM (\n"
        . "        SELECT snap.player_id, snap.tournament_id,\n"
        . "            ROW_NUMBER() OVER (\n"
        . "                PARTITION BY snap.player_id\n"
        . "                ORDER BY snap.performance_rating DESC,\n"
        . "                         snap.games DESC,\n"
        . "                         snap.tournament_id DESC\n"
        . "            ) AS rn\n"
        . "        FROM amiga_player_event_snapshots snap\n"
        . "        INNER JOIN tournaments t ON t.id = snap.tournament_id\n"
        . "        WHERE snap.performance_rating IS NOT NULL\n"
        . "          AND snap.games >= 2\n"
        . $cutoffSql
        . "          AND {$visibility}\n"
        . "    ) x\n"
        . "    WHERE x.rn = 1\n"
        . ") {$alias}_best ON {$alias}_best.player_id = p.id\n"
        . "INNER JOIN amiga_player_event_snapshots {$alias}\n"
        . "    ON {$alias}.player_id = {$alias}_best.player_id\n"
        . "   AND {$alias}.tournament_id = {$alias}_best.tournament_id";
}

/**
 * Career leaderboard query — present or snapshot at cutoff.
 *
 * @return mysqli_result
 */
function amiga_lb_query_career(
    mysqli $con,
    AmigaSnapshotContext $ctx,
    string $selectSql,
    string $orderSql,
    string $whereSql = 's.NumberGames > 0'
): mysqli_result {
    if (!$ctx->isActive()) {
        $sql = $selectSql . amiga_player_base_from_sql($con) . ' WHERE ' . $whereSql . ' ' . $orderSql;

        return k2_query_or_public_error($con, $sql, 'amiga leaderboard');
    }

    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        throw new RuntimeException('Active time travel context missing cutoff.');
    }

    $sql = $selectSql . amiga_lb_snapshot_from_sql('s') . ' WHERE ' . $whereSql . ' ' . $orderSql;
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('prepare amiga lb snapshot: ' . $con->error);
    }

    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tournamentId = $cutoff['tournament_id'];
    $stmt->bind_param('sdi', $eventDate, $chrono, $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute amiga lb snapshot: ' . $stmt->error);
    }
    $result = $stmt->get_result();
    if ($result === false) {
        throw new RuntimeException('result amiga lb snapshot: ' . $stmt->error);
    }
    $stmt->close();

    return $result;
}

function amiga_lb_games_count(mysqli $con, AmigaSnapshotContext $ctx): int
{
    /* Request-scoped cache — rating.php footer and the hub chapter lede both
       need this count (F6 Phase 0: was computed twice per request). */
    static $cache = [];
    $cutoffForKey = $ctx->isActive() ? $ctx->cutoff() : null;
    $cacheKey = $cutoffForKey === null
        ? 'present'
        : $cutoffForKey['event_date'] . '|' . $cutoffForKey['chrono'] . '|' . $cutoffForKey['tournament_id'];
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    return $cache[$cacheKey] = amiga_lb_games_count_uncached($con, $ctx);
}

function amiga_lb_games_count_uncached(mysqli $con, AmigaSnapshotContext $ctx): int
{
    if (!$ctx->isActive()) {
        $gcRes = mysqli_query($con, 'SELECT COUNT(*) AS n FROM amiga_games');
        if (!$gcRes) {
            return 0;
        }
        $gcRow = mysqli_fetch_assoc($gcRes);
        mysqli_free_result($gcRes);

        return (int) ($gcRow['n'] ?? 0);
    }

    $types = '';
    $params = [];
    $cutoffSql = amiga_snapshot_tournament_cutoff_and_sql($ctx, $types, $params, 't.event_date', 't.chrono', 't.id');
    $sql = 'SELECT COALESCE(SUM(c.game_count), 0) AS n FROM tournaments t '
        . 'LEFT JOIN amiga_tournament_catalog_stats c ON c.tournament_id = t.id '
        . 'WHERE ' . amiga_tournament_public_visibility_where('t') . $cutoffSql;
    if ($types === '') {
        $res = mysqli_query($con, $sql);
        if ($res === false) {
            return 0;
        }
        $row = mysqli_fetch_assoc($res);
        mysqli_free_result($res);

        return (int) ($row['n'] ?? 0);
    }

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : false;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    return $row !== false ? (int) ($row['n'] ?? 0) : 0;
}

/** Country token for DISTINCT counts — keep in sync with amiga_countries_token_sql(). */
function amiga_lb_country_token_sql(string $playerAlias = 'p'): string
{
    return 'CASE WHEN TRIM(' . $playerAlias . '.country) IS NULL OR TRIM(' . $playerAlias . '.country) = \'\' '
        . 'THEN \'Unknown\' ELSE TRIM(' . $playerAlias . '.country) END';
}

function amiga_lb_rated_country_count(mysqli $con, AmigaSnapshotContext $ctx): int
{
    $whereSql = amiga_lb_player_where_sql();
    $tokenSql = amiga_lb_country_token_sql('p');
    if (!$ctx->isActive()) {
        $sql = 'SELECT COUNT(DISTINCT ' . $tokenSql . ') AS n '
            . amiga_player_base_from_sql($con) . ' WHERE ' . $whereSql;
        $res = mysqli_query($con, $sql);
        if ($res === false) {
            return 0;
        }
        $row = mysqli_fetch_assoc($res);
        mysqli_free_result($res);

        return (int) ($row['n'] ?? 0);
    }

    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return 0;
    }

    $sql = 'SELECT COUNT(DISTINCT ' . $tokenSql . ') AS n '
        . amiga_lb_snapshot_from_sql('s') . ' WHERE ' . $whereSql;
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tournamentId = $cutoff['tournament_id'];
    $stmt->bind_param('sdi', $eventDate, $chrono, $tournamentId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : false;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    return $row !== false ? (int) ($row['n'] ?? 0) : 0;
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_lb_performance_rating_rows_at_cutoff(mysqli $con, AmigaSnapshotContext $ctx, ?string $orderClause = null): array
{
    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return [];
    }

    $orderClause ??= amiga_lb_performance_rating_best_default_order_sql();

    $sql = 'SELECT p.id AS player_id,
                   p.name AS player_name,
                   s.Rating,
                   p.country AS country,
                   s.NumberGames,
                   part.tournament_id,
                   part.tournament_name,
                   t.country AS host_country,
                   part.event_date,
                   part.event_chrono,
                   part.games AS event_games,
                   part.wins AS event_wins,
                   part.draws AS event_draws,
                   part.losses AS event_losses,
                   part.performance_rating '
        . amiga_lb_snapshot_from_sql('s') . "\n"
        . amiga_lb_best_perf_event_join_sql('part')
        . ' INNER JOIN tournaments t ON t.id = part.tournament_id
            WHERE s.NumberGames > 0
            ORDER BY ' . $orderClause;

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tournamentId = $cutoff['tournament_id'];
    $stmt->bind_param(
        'sdisdi',
        $eventDate,
        $chrono,
        $tournamentId,
        $eventDate,
        $chrono,
        $tournamentId
    );
    if (!$stmt->execute()) {
        $stmt->close();

        return [];
    }
    $result = $stmt->get_result();
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }
    $stmt->close();

    return $rows;
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_lb_performance_rating_top_rows_at_cutoff(mysqli $con, AmigaSnapshotContext $ctx, ?string $orderClause = null): array
{
    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return [];
    }

    $orderClause ??= amiga_lb_performance_rating_top_default_order_sql();

    $visibility = amiga_tournament_public_visibility_where('t');
    $sql = 'SELECT p.id AS player_id,
                   p.name AS player_name,
                   s.Rating,
                   p.country AS country,
                   s.NumberGames,
                   part.tournament_id,
                   part.tournament_name,
                   part.event_date,
                   part.event_chrono,
                   part.games AS event_games,
                   part.wins AS event_wins,
                   part.draws AS event_draws,
                   part.losses AS event_losses,
                   part.performance_rating,
                   t.country AS host_country '
        . amiga_lb_snapshot_from_sql('s') . '
            INNER JOIN amiga_player_event_snapshots part ON part.player_id = p.id
            INNER JOIN tournaments t ON t.id = part.tournament_id
            WHERE (part.event_date, part.event_chrono, part.tournament_id) <= (?, ?, ?)
              AND part.performance_rating IS NOT NULL
              AND part.games >= 2
              AND s.NumberGames > 0
              AND ' . $visibility . '
            ORDER BY ' . $orderClause . '
            LIMIT 100';

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tournamentId = $cutoff['tournament_id'];
    $stmt->bind_param(
        'sdisdi',
        $eventDate,
        $chrono,
        $tournamentId,
        $eventDate,
        $chrono,
        $tournamentId
    );
    if (!$stmt->execute()) {
        $stmt->close();

        return [];
    }
    $result = $stmt->get_result();
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }
    $stmt->close();

    return $rows;
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_lb_performance_rating_perfect_rows_at_cutoff(mysqli $con, AmigaSnapshotContext $ctx, ?string $orderClause = null): array
{
    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return [];
    }

    $orderClause ??= amiga_lb_performance_rating_perfect_default_order_sql();

    $visibility = amiga_tournament_public_visibility_where('t');
    $sql = 'SELECT p.id AS player_id,
                   p.name AS player_name,
                   s.Rating,
                   p.country AS country,
                   s.NumberGames,
                   part.tournament_id,
                   part.tournament_name,
                   part.event_date,
                   part.event_chrono,
                   part.games AS event_games,
                   part.wins AS event_wins,
                   part.draws AS event_draws,
                   part.losses AS event_losses,
                   part.performance_rating,
                   t.country AS host_country '
        . amiga_lb_snapshot_from_sql('s') . '
            INNER JOIN amiga_player_event_snapshots part ON part.player_id = p.id
            INNER JOIN tournaments t ON t.id = part.tournament_id
            WHERE (part.event_date, part.event_chrono, part.tournament_id) <= (?, ?, ?)
              AND part.is_perfect_event = 1
              AND s.NumberGames > 0
              AND ' . $visibility . '
            ORDER BY ' . $orderClause;

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tournamentId = $cutoff['tournament_id'];
    $stmt->bind_param(
        'sdisdi',
        $eventDate,
        $chrono,
        $tournamentId,
        $eventDate,
        $chrono,
        $tournamentId
    );
    if (!$stmt->execute()) {
        $stmt->close();

        return [];
    }
    $result = $stmt->get_result();
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }
    $stmt->close();

    return $rows;
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_lb_honours_rows_at_cutoff(mysqli $con, AmigaSnapshotContext $ctx, ?string $orderClause = null): array
{
    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return [];
    }

    $orderClause ??= amiga_lb_tournament_honours_order_sql('s');

    /* Request-scoped cache — tournament-honours.php needs rows + player count and
       amiga_lb_honours_player_count() TT path counts these same rows. */
    static $cache = [];
    $cacheKey = $cutoff['event_date'] . '|' . $cutoff['chrono'] . '|' . $cutoff['tournament_id'] . '|' . $orderClause;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $sql = 'SELECT p.id AS player_id,
                   p.name AS player_name,
                   p.country,
                   COALESCE(s.Rating, 0) AS rating,
                   s.tournaments_played,
                   s.event_gold,
                   s.event_silver,
                   s.event_bronze,
                   s.event_podiums,
                   s.perfect_events
            ' . amiga_lb_snapshot_from_sql('s') . '
            WHERE s.tournaments_played > 0
            ORDER BY ' . $orderClause;

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tournamentId = $cutoff['tournament_id'];
    $stmt->bind_param(
        'sdi',
        $eventDate,
        $chrono,
        $tournamentId
    );
    if (!$stmt->execute()) {
        $stmt->close();

        return [];
    }
    $result = $stmt->get_result();
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }
    $stmt->close();

    return $cache[$cacheKey] = $rows;
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_lb_calendar_geo_rows_at_cutoff(mysqli $con, AmigaSnapshotContext $ctx, ?string $orderClause = null): array
{
    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return [];
    }

    $orderClause ??= amiga_lb_calendar_geo_default_order_sql('s');

    $sql = 'SELECT p.id AS player_id,
                   p.name AS player_name,
                   p.country,
                   COALESCE(s.Rating, 0) AS rating,
                   s.peak_year_games,
                   s.peak_year_games_year,
                   s.peak_year_tournaments,
                   s.peak_year_tournaments_year,
                   s.countries_played_in,
                   s.opponent_countries_faced,
                   s.opponent_countries_beaten,
                   s.opponent_countries_beaten_by
            ' . amiga_lb_snapshot_from_sql('s') . '
            WHERE s.NumberGames > 0
            ORDER BY ' . $orderClause;

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tournamentId = $cutoff['tournament_id'];
    $stmt->bind_param(
        'sdi',
        $eventDate,
        $chrono,
        $tournamentId
    );
    if (!$stmt->execute()) {
        $stmt->close();

        return [];
    }
    $result = $stmt->get_result();
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }
    $stmt->close();

    return $rows;
}

function amiga_lb_honours_player_count(mysqli $con, AmigaSnapshotContext $ctx): int
{
    if (!$ctx->isActive()) {
        $countRes = mysqli_query($con, 'SELECT COUNT(*) AS n FROM amiga_player_current WHERE tournaments_played > 0');
        if (!$countRes) {
            return 0;
        }
        $n = (int) (mysqli_fetch_assoc($countRes)['n'] ?? 0);
        mysqli_free_result($countRes);

        return $n;
    }

    return count(amiga_lb_honours_rows_at_cutoff($con, $ctx));
}

/**
 * Rating wing at cutoff — compare oracle for history parity (uses Rating like present LB).
 *
 * @return list<array{player_id: int, name: string, rating: float, rank: int}>
 */
function amiga_lb_rating_rows_at_cutoff(mysqli $con, AmigaSnapshotContext $ctx): array
{
    $result = amiga_lb_query_career(
        $con,
        $ctx,
        'SELECT p.id AS player_id, p.name AS Name, s.Rating ',
        'ORDER BY s.Rating DESC, p.id ASC'
    );
    $rows = [];
    $rank = 0;
    while ($row = mysqli_fetch_assoc($result)) {
        $rank++;
        $rows[] = [
            'player_id' => (int) $row['player_id'],
            'name' => (string) $row['Name'],
            'rating' => (float) ($row['Rating'] ?? 0),
            'rank' => $rank,
        ];
    }
    mysqli_free_result($result);

    return $rows;
}

/**
 * Wing-step Elo change vs previous snapshot in the active wing (time travel rating LB).
 *
 * Slim path (F6 Phase 0): narrow rating maps at current + previous cutoff, same
 * delta rules as amiga_rating_history_ladder_with_deltas() — no full ladder
 * resolve (name/country/rank) on the LB hot path.
 *
 * @return array<int, float> player_id => rating_delta
 */
function amiga_lb_rating_delta_map(mysqli $con, AmigaSnapshotContext $ctx): array
{
    if (!$ctx->isActive()) {
        return [];
    }

    require_once __DIR__ . '/amiga_rating_history_lib.php';

    $entry = $ctx->entry();
    $cutoff = $ctx->cutoff();
    if ($entry === null || $cutoff === null) {
        return [];
    }

    $currentRatingByPlayer = amiga_rating_history_rating_map_at_cutoff(
        $con,
        $cutoff['event_date'],
        $cutoff['chrono'],
        $cutoff['tournament_id']
    );
    if ($currentRatingByPlayer === []) {
        return [];
    }

    /* Previous wing step from the UNFILTERED catalog — ctx prevKey() may be
       participation-filtered under as_with, but Δ semantics are wing-step only. */
    $position = amiga_rating_history_catalog_position($ctx->catalog(), $ctx->key());
    $prevKey = $position['prev_key'];
    $hasPrevWingSnapshot = $prevKey !== null && $prevKey !== '';
    $prevRatingByPlayer = [];
    if ($hasPrevWingSnapshot) {
        $prevEntry = amiga_rating_history_catalog_entry_by_key($ctx->catalog(), $prevKey);
        if ($prevEntry !== null && $prevEntry['cutoff_tournament_id'] !== null) {
            $prevRatingByPlayer = amiga_rating_history_rating_map_at_cutoff(
                $con,
                (string) $prevEntry['cutoff_event_date'],
                (float) $prevEntry['cutoff_chrono'],
                (int) $prevEntry['cutoff_tournament_id']
            );
        }
    }

    $eventParticipantIds = null;
    if ($ctx->wing() === 'event') {
        $eventParticipantIds = amiga_rating_history_event_participant_ids($con, $cutoff['tournament_id']);
    }

    $map = [];
    foreach ($currentRatingByPlayer as $playerId => $ratingAfter) {
        $map[$playerId] = amiga_rating_history_compute_rating_delta(
            $playerId,
            $ratingAfter,
            $hasPrevWingSnapshot,
            $prevRatingByPlayer,
            $eventParticipantIds
        );
    }

    return $map;
}

/**
 * Present-day rating LB: Elo change since start of the most recent World Cup.
 *
 * Baseline = rating after the tournament before that World Cup; players absent
 * from that ladder snapshot use AMIGA_RATING_HISTORY_START_RATING (1600).
 *
 * @return array<int, float> player_id => rating_delta
 */
function amiga_lb_wc_start_rating_delta_map(mysqli $con): array
{
    require_once __DIR__ . '/amiga_rating_history_lib.php';

    $lastWc = amiga_rating_history_last_world_cup_tournament($con);
    if ($lastWc === null) {
        return [];
    }

    $baselineByPlayer = amiga_rating_history_baseline_rating_before_tournament($con, $lastWc);

    $sql = 'SELECT p.id AS player_id, s.Rating AS rating '
        . amiga_player_base_from_sql($con)
        . ' WHERE s.NumberGames > 0';
    $result = k2_query_or_public_error($con, $sql, 'amiga wc start rating delta');

    $map = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $playerId = (int) $row['player_id'];
        $baseline = $baselineByPlayer[$playerId] ?? AMIGA_RATING_HISTORY_START_RATING;
        $map[$playerId] = (float) $row['rating'] - $baseline;
    }
    mysqli_free_result($result);

    return $map;
}

function amiga_lb_rating_delta_cell(?float $delta, ?int $linkTournamentId = null): string
{
    if ($delta === null) {
        return k2_fmt_dash();
    }

    $rounded = (int) round($delta);
    if ($rounded === 0) {
        return k2_fmt_dash();
    }

    if ($rounded > 0) {
        $display = '+' . $rounded;
        $toneClass = 'k2-lb-amiga-rating-delta-link--pos';
    } else {
        $display = (string) $rounded;
        $toneClass = 'k2-lb-amiga-rating-delta-link--neg';
    }

    if ($linkTournamentId === null || $linkTournamentId < 1) {
        if ($rounded > 0) {
            return '<span class="blue">' . $display . '</span>';
        }

        return '<span class="red">' . $display . '</span>';
    }

    require_once __DIR__ . '/amiga_tournament_lib.php';

    $href = amiga_tournament_href(amiga_tournament_event_stats_url($linkTournamentId))
        . '#' . AMIGA_TOURNAMENT_PAGE_FRAGMENT;

    return '<a class="k2-lb-amiga-rating-delta-link ' . $toneClass . '" href="' . k2_h($href) . '"'
        . ' aria-label="Open this tournament event stats">'
        . k2_h($display) . '</a>';
}

function amiga_lb_rating_delta_sort_value(?float $delta): string
{
    if ($delta === null) {
        return '';
    }

    $rounded = (int) round($delta);
    if ($rounded === 0) {
        return '';
    }

    return (string) $rounded;
}

/**
 * Peak-rating wing — rating stats from snapshot/current; peak rank from dense timeline (TT-safe).
 *
 * @return mysqli_result
 */
function amiga_lb_query_peak_rating(mysqli $con, AmigaSnapshotContext $ctx, ?string $orderClause = null): mysqli_result
{
    $orderClause ??= amiga_lb_peak_rating_default_order_sql();
    $selectBase = 'SELECT p.id AS ID, p.name AS Name, s.Rating, p.country AS Country, s.NumberGames, '
        . 's.PeakRating, s.LowestRating, s.HighestRatedVictim, s.LowestRatedCulprit, '
        . 's.peak_rating_tournament_id, tpr.name AS peak_rating_tournament_name, peak_snap.rating_delta AS peak_rating_delta, ';

    $joinPeakSnap = ' LEFT JOIN amiga_player_event_snapshots peak_snap '
        . 'ON peak_snap.player_id = p.id AND peak_snap.tournament_id = s.peak_rating_tournament_id ';

    $peakRankPlayedJoinPresent = ' LEFT JOIN amiga_player_event_snapshots pr_rank_snap '
        . 'ON pr_rank_snap.player_id = p.id AND pr_rank_snap.tournament_id = s.peak_elo_rank_tournament_id '
        . 'AND pr_rank_snap.NumberGames > 0 ';
    $peakRankPlayedJoinTt = ' LEFT JOIN amiga_player_event_snapshots pr_rank_snap '
        . 'ON pr_rank_snap.player_id = p.id AND pr_rank_snap.tournament_id = er.peak_elo_rank_tournament_id '
        . 'AND pr_rank_snap.NumberGames > 0 ';

    if (!$ctx->isActive()) {
        $sql = $selectBase
            . 'tpr.event_date AS peak_rating_date, s.peak_elo_rank, s.peak_elo_rank_tournament_id, '
            . 'tpke.name AS peak_elo_rank_tournament_name, tpke.event_date AS peak_elo_rank_date, '
            . '(pr_rank_snap.player_id IS NOT NULL) AS peak_elo_rank_played_in_event '
            . 'FROM amiga_players p '
            . 'INNER JOIN amiga_player_current s ON s.player_id = p.id '
            . 'LEFT JOIN tournaments tpr ON tpr.id = s.peak_rating_tournament_id '
            . $joinPeakSnap
            . 'LEFT JOIN tournaments tpke ON tpke.id = s.peak_elo_rank_tournament_id '
            . $peakRankPlayedJoinPresent
            . 'WHERE ' . amiga_lb_player_where_sql() . ' '
            . 'ORDER BY ' . $orderClause;

        return k2_query_or_public_error($con, $sql, 'amiga peak rating leaderboard');
    }

    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        throw new RuntimeException('Active time travel context missing cutoff.');
    }

    $sql = $selectBase
        . 'tpr.event_date AS peak_rating_date, er.peak_elo_rank, er.peak_elo_rank_tournament_id, '
        . 'tpke.name AS peak_elo_rank_tournament_name, tpke.event_date AS peak_elo_rank_date, '
        . '(pr_rank_snap.player_id IS NOT NULL) AS peak_elo_rank_played_in_event '
        . amiga_lb_snapshot_from_sql('s')
        . ' LEFT JOIN tournaments tpr ON tpr.id = s.peak_rating_tournament_id '
        . $joinPeakSnap
        /* amiga_player_elo_rank_at_event is DENSE: every finalize writes one row per
           debuted player (verified rows-per-event == cumulative debuts across all 605
           events), so "latest row per player <= cutoff" == "rows at the cutoff event".
           The previous ROW_NUMBER window over all rows <= cutoff scanned up to 173k
           rows (~1.7-3.4 s); this equality read is ~10-15 ms with identical rows. */
        . ' LEFT JOIN ('
        . '    SELECT er.player_id, er.peak_elo_rank, er.peak_elo_rank_tournament_id'
        . '    FROM amiga_player_elo_rank_at_event er'
        . '    WHERE er.tournament_id = ?'
        . ') er ON er.player_id = p.id '
        . 'LEFT JOIN tournaments tpke ON tpke.id = er.peak_elo_rank_tournament_id '
        . $peakRankPlayedJoinTt
        . 'WHERE ' . amiga_lb_player_where_sql() . ' '
        . 'ORDER BY ' . $orderClause;

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare amiga peak rating lb snapshot: ' . $con->error);
    }

    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tournamentId = $cutoff['tournament_id'];
    $stmt->bind_param('sdii', $eventDate, $chrono, $tournamentId, $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute amiga peak rating lb snapshot: ' . $stmt->error);
    }
    $result = $stmt->get_result();
    if ($result === false) {
        throw new RuntimeException('result amiga peak rating lb snapshot: ' . $stmt->error);
    }
    $stmt->close();

    return $result;
}
