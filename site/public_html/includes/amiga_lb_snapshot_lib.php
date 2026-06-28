<?php
/**
 * Amiga leaderboard — snapshot-at-cutoff reads (time travel).
 *
 * @see docs/amiga-time-travel-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/amiga_player_current_lib.php';

/**
 * FROM + JOIN latest snapshot row per player on or before cutoff (alias ``s``).
 */
function amiga_lb_snapshot_from_sql(string $alias = 's'): string
{
    return "FROM amiga_players p\nINNER JOIN (\n"
        . "    SELECT x.* FROM (\n"
        . "        SELECT snap.*,\n"
        . "            ROW_NUMBER() OVER (\n"
        . "                PARTITION BY snap.player_id\n"
        . "                ORDER BY snap.event_date DESC, snap.event_chrono DESC, snap.tournament_id DESC\n"
        . "            ) AS rn\n"
        . "        FROM amiga_player_event_snapshots snap\n"
        . "        WHERE (snap.event_date, snap.event_chrono, snap.tournament_id) <= (?, ?, ?)\n"
        . "    ) x\n"
        . "    WHERE x.rn = 1\n"
        . ") {$alias} ON {$alias}.player_id = p.id";
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
    if (!$ctx->isActive()) {
        $gcRes = mysqli_query($con, 'SELECT COUNT(*) AS n FROM amiga_games');
        if (!$gcRes) {
            return 0;
        }
        $gcRow = mysqli_fetch_assoc($gcRes);
        mysqli_free_result($gcRes);

        return (int) ($gcRow['n'] ?? 0);
    }

    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return 0;
    }

    $sql = 'SELECT COUNT(*) AS n FROM amiga_games g '
        . 'INNER JOIN tournaments t ON t.id = g.tournament_id '
        . 'WHERE (t.event_date, t.chrono, t.id) <= (?, ?, ?)';
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
function amiga_lb_performance_rating_rows_at_cutoff(mysqli $con, AmigaSnapshotContext $ctx): array
{
    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return [];
    }

    $visibility = amiga_tournament_public_visibility_where('t');
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
                   ranked.event_wins,
                   ranked.event_draws,
                   ranked.event_losses,
                   ranked.performance_rating,
                   ranked.host_country
            FROM (
                SELECT pl.id AS player_id,
                       pl.name AS player_name,
                       s.Rating,
                       pl.country AS country,
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
                       part.performance_rating,
                       ROW_NUMBER() OVER (
                           PARTITION BY part.player_id
                           ORDER BY part.performance_rating DESC,
                                    part.games DESC,
                                    part.tournament_id DESC
                       ) AS rn
                FROM amiga_player_event_snapshots part
                INNER JOIN amiga_players pl ON pl.id = part.player_id
                INNER JOIN (
                    SELECT x.player_id, x.Rating, x.NumberGames FROM (
                        SELECT snap.player_id, snap.Rating, snap.NumberGames,
                            ROW_NUMBER() OVER (
                                PARTITION BY snap.player_id
                                ORDER BY snap.event_date DESC, snap.event_chrono DESC, snap.tournament_id DESC
                            ) AS rn
                        FROM amiga_player_event_snapshots snap
                        WHERE (snap.event_date, snap.event_chrono, snap.tournament_id) <= (?, ?, ?)
                    ) x WHERE x.rn = 1
                ) s ON s.player_id = part.player_id
                INNER JOIN tournaments t ON t.id = part.tournament_id
                WHERE (part.event_date, part.event_chrono, part.tournament_id) <= (?, ?, ?)
                  AND part.performance_rating IS NOT NULL
                  AND part.games >= 2
                  AND s.NumberGames > 0
                  AND ' . $visibility . '
            ) ranked
            WHERE ranked.rn = 1
            ORDER BY ranked.performance_rating DESC,
                     ranked.event_games DESC,
                     ranked.Rating DESC,
                     ranked.player_id ASC';

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
function amiga_lb_performance_rating_top_rows_at_cutoff(mysqli $con, AmigaSnapshotContext $ctx): array
{
    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return [];
    }

    $visibility = amiga_tournament_public_visibility_where('t');
    $sql = 'SELECT pl.id AS player_id,
                   pl.name AS player_name,
                   s.Rating,
                   pl.country AS country,
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
                   t.country AS host_country
            FROM amiga_player_event_snapshots part
            INNER JOIN amiga_players pl ON pl.id = part.player_id
            INNER JOIN (
                SELECT x.player_id, x.Rating, x.NumberGames FROM (
                    SELECT snap.player_id, snap.Rating, snap.NumberGames,
                        ROW_NUMBER() OVER (
                            PARTITION BY snap.player_id
                            ORDER BY snap.event_date DESC, snap.event_chrono DESC, snap.tournament_id DESC
                        ) AS rn
                    FROM amiga_player_event_snapshots snap
                    WHERE (snap.event_date, snap.event_chrono, snap.tournament_id) <= (?, ?, ?)
                ) x WHERE x.rn = 1
            ) s ON s.player_id = part.player_id
            INNER JOIN tournaments t ON t.id = part.tournament_id
            WHERE (part.event_date, part.event_chrono, part.tournament_id) <= (?, ?, ?)
              AND part.performance_rating IS NOT NULL
              AND part.games >= 2
              AND s.NumberGames > 0
              AND ' . $visibility . '
            ORDER BY part.performance_rating DESC,
                     part.games DESC,
                     part.tournament_id DESC,
                     part.player_id ASC
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
function amiga_lb_performance_rating_perfect_rows_at_cutoff(mysqli $con, AmigaSnapshotContext $ctx): array
{
    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return [];
    }

    $visibility = amiga_tournament_public_visibility_where('t');
    $sql = 'SELECT pl.id AS player_id,
                   pl.name AS player_name,
                   s.Rating,
                   pl.country AS country,
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
                   t.country AS host_country
            FROM amiga_player_event_snapshots part
            INNER JOIN amiga_players pl ON pl.id = part.player_id
            INNER JOIN (
                SELECT x.player_id, x.Rating, x.NumberGames FROM (
                    SELECT snap.player_id, snap.Rating, snap.NumberGames,
                        ROW_NUMBER() OVER (
                            PARTITION BY snap.player_id
                            ORDER BY snap.event_date DESC, snap.event_chrono DESC, snap.tournament_id DESC
                        ) AS rn
                    FROM amiga_player_event_snapshots snap
                    WHERE (snap.event_date, snap.event_chrono, snap.tournament_id) <= (?, ?, ?)
                ) x WHERE x.rn = 1
            ) s ON s.player_id = part.player_id
            INNER JOIN tournaments t ON t.id = part.tournament_id
            WHERE (part.event_date, part.event_chrono, part.tournament_id) <= (?, ?, ?)
              AND part.is_perfect_event = 1
              AND s.NumberGames > 0
              AND ' . $visibility . '
            ORDER BY part.event_date DESC,
                     part.event_chrono DESC,
                     part.tournament_id DESC,
                     part.player_id ASC';

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
function amiga_lb_honours_rows_at_cutoff(mysqli $con, AmigaSnapshotContext $ctx): array
{
    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return [];
    }

    $sql = 'SELECT t.player_id,
                   p.name AS player_name,
                   p.country,
                   COALESCE(t.Rating, 0) AS rating,
                   t.tournaments_played,
                   t.event_gold,
                   t.event_silver,
                   t.event_bronze,
                   t.event_podiums,
                   t.perfect_events
            FROM (
                SELECT x.* FROM (
                    SELECT snap.*,
                        ROW_NUMBER() OVER (
                            PARTITION BY snap.player_id
                            ORDER BY snap.event_date DESC, snap.event_chrono DESC, snap.tournament_id DESC
                        ) AS rn
                    FROM amiga_player_event_snapshots snap
                    WHERE (snap.event_date, snap.event_chrono, snap.tournament_id) <= (?, ?, ?)
                ) x
                WHERE x.rn = 1 AND x.tournaments_played > 0
            ) t
            INNER JOIN amiga_players p ON p.id = t.player_id
            ORDER BY t.tournaments_played DESC,
                     t.event_gold DESC,
                     t.event_podiums DESC,
                     t.player_id ASC';

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

/**
 * @return list<array<string, mixed>>
 */
function amiga_lb_calendar_geo_rows_at_cutoff(mysqli $con, AmigaSnapshotContext $ctx): array
{
    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return [];
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
            FROM (
                SELECT x.* FROM (
                    SELECT snap.*,
                        ROW_NUMBER() OVER (
                            PARTITION BY snap.player_id
                            ORDER BY snap.event_date DESC, snap.event_chrono DESC, snap.tournament_id DESC
                        ) AS rn
                    FROM amiga_player_event_snapshots snap
                    WHERE (snap.event_date, snap.event_chrono, snap.tournament_id) <= (?, ?, ?)
                ) x
                WHERE x.rn = 1 AND x.NumberGames > 0
            ) t
            INNER JOIN amiga_players p ON p.id = t.player_id
            ORDER BY t.peak_year_games DESC,
                     t.peak_year_games_year ASC,
                     t.player_id ASC';

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
 * @return array<int, float> player_id => rating_delta
 */
function amiga_lb_rating_delta_map(mysqli $con, AmigaSnapshotContext $ctx): array
{
    if (!$ctx->isActive()) {
        return [];
    }

    require_once __DIR__ . '/amiga_rating_history_lib.php';

    $view = amiga_rating_history_resolve_from_context($con, $ctx);
    $map = [];
    foreach ($view['ladder'] as $row) {
        $map[(int) $row['player_id']] = (float) $row['rating_delta'];
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

function amiga_lb_rating_delta_cell(?float $delta): string
{
    if ($delta === null) {
        return k2_fmt_dash();
    }

    $rounded = (int) round($delta);
    if ($rounded === 0) {
        return k2_fmt_dash();
    }

    if ($rounded > 0) {
        return '<span class="blue">+' . $rounded . '</span>';
    }

    return '<span class="red">' . $rounded . '</span>';
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
function amiga_lb_query_peak_rating(mysqli $con, AmigaSnapshotContext $ctx): mysqli_result
{
    $selectBase = 'SELECT p.id AS ID, p.name AS Name, s.Rating, p.country AS Country, s.NumberGames, '
        . 's.PeakRating, s.LowestRating, s.AverageOpponentRating, s.HighestRatedVictim, s.LowestRatedCulprit, ';

    if (!$ctx->isActive()) {
        $sql = $selectBase
            . 'tpr.event_date AS peak_rating_date, s.peak_elo_rank, tpke.event_date AS peak_elo_rank_date '
            . 'FROM amiga_players p '
            . 'INNER JOIN amiga_player_current s ON s.player_id = p.id '
            . 'LEFT JOIN tournaments tpr ON tpr.id = s.peak_rating_tournament_id '
            . 'LEFT JOIN tournaments tpke ON tpke.id = s.peak_elo_rank_tournament_id '
            . 'WHERE ' . amiga_lb_player_where_sql() . ' '
            . 'ORDER BY s.PeakRating DESC, s.Rating DESC';

        return k2_query_or_public_error($con, $sql, 'amiga peak rating leaderboard');
    }

    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        throw new RuntimeException('Active time travel context missing cutoff.');
    }

    $sql = $selectBase
        . 'tpr.event_date AS peak_rating_date, er.peak_elo_rank, tpke.event_date AS peak_elo_rank_date '
        . amiga_lb_snapshot_from_sql('s')
        . ' LEFT JOIN tournaments tpr ON tpr.id = s.peak_rating_tournament_id '
        . ' LEFT JOIN ('
        . '    SELECT x.player_id, x.peak_elo_rank, x.peak_elo_rank_tournament_id FROM ('
        . '        SELECT er.player_id, er.peak_elo_rank, er.peak_elo_rank_tournament_id,'
        . '            ROW_NUMBER() OVER ('
        . '                PARTITION BY er.player_id'
        . '                ORDER BY er.event_date DESC, er.event_chrono DESC, er.tournament_id DESC'
        . '            ) AS rn'
        . '        FROM amiga_player_elo_rank_at_event er'
        . '        WHERE (er.event_date, er.event_chrono, er.tournament_id) <= (?, ?, ?)'
        . '    ) x WHERE x.rn = 1'
        . ') er ON er.player_id = p.id '
        . 'LEFT JOIN tournaments tpke ON tpke.id = er.peak_elo_rank_tournament_id '
        . 'WHERE ' . amiga_lb_player_where_sql() . ' '
        . 'ORDER BY s.PeakRating DESC, s.Rating DESC';

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare amiga peak rating lb snapshot: ' . $con->error);
    }

    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tournamentId = $cutoff['tournament_id'];
    $stmt->bind_param('sdisdi', $eventDate, $chrono, $tournamentId, $eventDate, $chrono, $tournamentId);
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
