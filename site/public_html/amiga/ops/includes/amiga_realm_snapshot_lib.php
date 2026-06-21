<?php
/**
 * Realm snapshot compute + persist (mirrors scripts/amiga/server_records.py + realm_persist.py).
 *
 * @see docs/amiga-realm-snapshot-policy.md
 */
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/lb_player_filters.php';

const AMIGA_REALM_ESTABLISHED_MIN_GAMES = 20;

/**
 * @return array{event_date: string, chrono: float|int, tournament_id: int, tournament_name: string}
 */
function amiga_realm_load_cutoff(mysqli $con, int $tournamentId): array
{
    $stmt = $con->prepare(
        'SELECT id, event_date, chrono, name FROM tournaments WHERE id = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare realm cutoff: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute realm cutoff: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : false;
    $stmt->close();
    if ($row === false) {
        throw new RuntimeException("tournament_id={$tournamentId} not found");
    }

    return [
        'event_date' => (string) $row['event_date'],
        'chrono' => $row['chrono'],
        'tournament_id' => (int) $row['id'],
        'tournament_name' => (string) $row['name'],
    ];
}

function amiga_realm_game_cutoff_sql(string $alias = 't'): string
{
    return "({$alias}.event_date, {$alias}.chrono, {$alias}.id) <= (?, ?, ?)";
}

function amiga_realm_latest_player_snapshots_sql(): string
{
    $cutoff = amiga_realm_game_cutoff_sql('t_cut');

    return "
        SELECT s.*, p.name AS player_name
        FROM (
            SELECT s_inner.*,
                   ROW_NUMBER() OVER (
                       PARTITION BY s_inner.player_id
                       ORDER BY s_inner.event_date DESC, s_inner.event_chrono DESC,
                                s_inner.tournament_id DESC
                   ) AS rn
            FROM amiga_player_event_snapshots s_inner
            INNER JOIN tournaments t_cut ON t_cut.id = s_inner.tournament_id
            WHERE {$cutoff}
        ) s
        INNER JOIN amiga_players p ON p.id = s.player_id
        WHERE s.rn = 1
    ";
}

/**
 * @param array{event_date: string, chrono: float|int, tournament_id: int} $cutoff
 * @return array<string, mixed>
 */
function amiga_realm_compute_server_aggregates(mysqli $con, array $cutoff): array
{
    $cutoffWhere = amiga_realm_game_cutoff_sql('t');
    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tournamentId = (int) $cutoff['tournament_id'];

    $sql = "
        SELECT COUNT(*) AS games,
               SUM(CASE WHEN r.actual_score = 0.5 THEN 1 ELSE 0 END) AS draws,
               COALESCE(SUM(r.sum_of_goals), 0) AS goals,
               COALESCE(SUM(r.dd_player_a + r.dd_player_b), 0) AS dd,
               COALESCE(SUM(r.cs_player_a + r.cs_player_b), 0) AS cs
        FROM amiga_games g
        INNER JOIN amiga_game_ratings r ON r.game_id = g.id
        INNER JOIN tournaments t ON t.id = g.tournament_id
        WHERE {$cutoffWhere}
    ";
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare realm aggregates: ' . $con->error);
    }
    $stmt->bind_param('sdi', $eventDate, $chrono, $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute realm aggregates: ' . $stmt->error);
    }
    $agg = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $games = (int) ($agg['games'] ?? 0);
    $draws = (int) ($agg['draws'] ?? 0);
    $decided = $games - $draws;
    $goals = (int) ($agg['goals'] ?? 0);
    $dd = (int) ($agg['dd'] ?? 0);
    $cs = (int) ($agg['cs'] ?? 0);

    $latestSql = amiga_realm_latest_player_snapshots_sql();
    $countSql = "SELECT COUNT(*) AS n FROM ({$latestSql}) lp WHERE lp.NumberGames >= 1";
    $stmt = $con->prepare($countSql);
    if ($stmt === false) {
        throw new RuntimeException('prepare realm player count: ' . $con->error);
    }
    $stmt->bind_param('sdi', $eventDate, $chrono, $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute realm player count: ' . $stmt->error);
    }
    $numPlayers = (int) $stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();

    $avgSql = "SELECT AVG(lp.DifferentOpponents) AS a FROM ({$latestSql}) lp WHERE lp.DifferentOpponents >= 1";
    $stmt = $con->prepare($avgSql);
    if ($stmt === false) {
        throw new RuntimeException('prepare realm diff opp avg: ' . $con->error);
    }
    $stmt->bind_param('sdi', $eventDate, $chrono, $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute realm diff opp avg: ' . $stmt->error);
    }
    $diffOppAvg = $stmt->get_result()->fetch_assoc()['a'] ?? null;
    $stmt->close();

    return amiga_realm_aggregate_patch(
        $games,
        $draws,
        $decided,
        $goals,
        $dd,
        $cs,
        $numPlayers,
        $diffOppAvg
    );
}

/**
 * @return array<string, mixed>
 */
function amiga_realm_aggregate_patch(
    int $games,
    int $draws,
    int $decided,
    int $goals,
    int $dd,
    int $cs,
    int $numPlayers,
    mixed $diffOppAvg,
): array {
    return [
        'NumberOfPlayers' => $numPlayers,
        'DifferentOpponentsAverage' => $diffOppAvg,
        'GamesPlayed' => $games,
        'GamesPlayedAverage' => $numPlayers > 0 ? round(2 * $games / $numPlayers, 3) : null,
        'NumberOfDecidedGames' => $decided,
        'NumberOfDraws' => $draws,
        'DecidedGamesRatio' => $games > 0 ? round($decided / $games, 8) : null,
        'DrawsRatio' => $games > 0 ? round($draws / $games, 8) : null,
        'GoalsScored' => $goals,
        'GoalsPerGameAverage' => $games > 0 ? round($goals / $games, 7) : null,
        'DoubleDigits' => $dd,
        'CleanSheets' => $cs,
        'DoubleDigitsRatio' => $games > 0 ? round($dd / $games, 8) : null,
        'CleanSheetsRatio' => $games > 0 ? round($cs / $games, 8) : null,
    ];
}

/**
 * @param array{event_date: string, chrono: float|int, tournament_id: int} $cutoff
 * @return array<string, mixed>
 */
function amiga_realm_career_holder_patch(
    mysqli $con,
    array $cutoff,
    string $valueCol,
    string $prefix,
): array {
    $latestSql = amiga_realm_latest_player_snapshots_sql();
    $sql = "
        SELECT lp.player_id, lp.player_name AS name,
               lp.{$valueCol} AS record_value,
               COALESCE(DATE_FORMAT(t.event_date, '%Y-%m-%d'), DATE_FORMAT(g.game_date, '%Y-%m-%d')) AS record_date
        FROM ({$latestSql}) lp
        LEFT JOIN amiga_games g ON g.id = lp.LastGameGameID
        LEFT JOIN tournaments t ON t.id = g.tournament_id
        WHERE lp.{$valueCol} IS NOT NULL AND lp.{$valueCol} > 0
        ORDER BY lp.{$valueCol} DESC, lp.player_id ASC
        LIMIT 1
    ";
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare career holder: ' . $con->error);
    }
    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tournamentId = (int) $cutoff['tournament_id'];
    $stmt->bind_param('sdi', $eventDate, $chrono, $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute career holder: ' . $stmt->error);
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row === null) {
        return [];
    }

    return [
        $prefix => $row['record_value'],
        $prefix . 'ID' => (int) $row['player_id'],
        $prefix . 'Name' => (string) $row['name'],
        $prefix . 'Date' => $row['record_date'] !== null ? (string) $row['record_date'] : null,
    ];
}

function amiga_realm_game_event_date_sql(): string
{
    return "COALESCE(DATE_FORMAT(t.event_date, '%Y-%m-%d'), DATE_FORMAT(g.game_date, '%Y-%m-%d'))";
}

/**
 * @param array{event_date: string, chrono: float|int, tournament_id: int} $cutoff
 * @return array<string, mixed>
 */
function amiga_realm_most_goals_one_game_patch(mysqli $con, array $cutoff): array
{
    $dateExpr = amiga_realm_game_event_date_sql();
    $extra = ' AND ' . amiga_realm_game_cutoff_sql('tg');
    $sql = "
        SELECT game_id, player_id, player_name, goals, record_date
        FROM (
            SELECT g.id AS game_id, g.player_a_id AS player_id, pa.name AS player_name,
                   g.goals_a AS goals, {$dateExpr} AS record_date
            FROM amiga_games g
            INNER JOIN amiga_players pa ON pa.id = g.player_a_id
            LEFT JOIN tournaments t ON t.id = g.tournament_id
            INNER JOIN tournaments tg ON tg.id = g.tournament_id
            WHERE 1=1{$extra}
            UNION ALL
            SELECT g.id, g.player_b_id, pb.name, g.goals_b, {$dateExpr}
            FROM amiga_games g
            INNER JOIN amiga_players pb ON pb.id = g.player_b_id
            LEFT JOIN tournaments t ON t.id = g.tournament_id
            INNER JOIN tournaments tg ON tg.id = g.tournament_id
            WHERE 1=1{$extra}
        ) sides
        ORDER BY goals DESC, game_id ASC
        LIMIT 1
    ";
    return amiga_realm_fetch_single_game_record($con, $sql, $cutoff, 'MostGoalsScoredInOneGame');
}

/**
 * @param array{event_date: string, chrono: float|int, tournament_id: int} $cutoff
 * @return array<string, mixed>
 */
function amiga_realm_biggest_win_margin_patch(mysqli $con, array $cutoff): array
{
    $dateExpr = amiga_realm_game_event_date_sql();
    $extra = ' AND ' . amiga_realm_game_cutoff_sql('tg');
    $sql = "
        SELECT g.id AS game_id, r.goal_difference AS margin,
               CASE WHEN r.actual_score = 1.0 THEN g.player_a_id WHEN r.actual_score = 0.0 THEN g.player_b_id END AS player_id,
               CASE WHEN r.actual_score = 1.0 THEN pa.name WHEN r.actual_score = 0.0 THEN pb.name END AS player_name,
               {$dateExpr} AS record_date
        FROM amiga_games g
        INNER JOIN amiga_game_ratings r ON r.game_id = g.id
        INNER JOIN amiga_players pa ON pa.id = g.player_a_id
        INNER JOIN amiga_players pb ON pb.id = g.player_b_id
        LEFT JOIN tournaments t ON t.id = g.tournament_id
        INNER JOIN tournaments tg ON tg.id = g.tournament_id
        WHERE r.actual_score IN (0.0, 1.0) AND r.goal_difference IS NOT NULL{$extra}
        ORDER BY r.goal_difference DESC, g.id ASC
        LIMIT 1
    ";
    $row = amiga_realm_fetch_game_row($con, $sql, $cutoff);
    if ($row === null || $row['player_id'] === null) {
        return [];
    }

    return [
        'BiggestWinDifference' => (int) $row['margin'],
        'BiggestWinDifferenceID' => (int) $row['player_id'],
        'BiggestWinDifferenceName' => (string) $row['player_name'],
        'BiggestWinDifferenceDate' => $row['record_date'] !== null ? (string) $row['record_date'] : null,
        'BiggestWinDifferenceGameID' => (int) $row['game_id'],
    ];
}

/**
 * @param array{event_date: string, chrono: float|int, tournament_id: int} $cutoff
 * @return array<string, mixed>
 */
function amiga_realm_biggest_draw_sum_patch(mysqli $con, array $cutoff): array
{
    $dateExpr = amiga_realm_game_event_date_sql();
    $extra = ' AND ' . amiga_realm_game_cutoff_sql('tg');
    $sql = "
        SELECT g.id AS game_id, (g.goals_a + g.goals_b) AS draw_sum,
               g.player_a_id, g.player_b_id, pa.name AS name_a, pb.name AS name_b,
               {$dateExpr} AS record_date
        FROM amiga_games g
        INNER JOIN amiga_game_ratings r ON r.game_id = g.id
        INNER JOIN amiga_players pa ON pa.id = g.player_a_id
        INNER JOIN amiga_players pb ON pb.id = g.player_b_id
        LEFT JOIN tournaments t ON t.id = g.tournament_id
        INNER JOIN tournaments tg ON tg.id = g.tournament_id
        WHERE r.actual_score = 0.5{$extra}
        ORDER BY draw_sum DESC, g.id ASC
        LIMIT 1
    ";
    $row = amiga_realm_fetch_game_row($con, $sql, $cutoff);
    if ($row === null) {
        return [];
    }

    return [
        'BiggestDrawSum' => (int) $row['draw_sum'],
        'BiggestDrawSumIDA' => (int) $row['player_a_id'],
        'BiggestDrawSumIDB' => (int) $row['player_b_id'],
        'BiggestDrawSumNameA' => (string) $row['name_a'],
        'BiggestDrawSumNameB' => (string) $row['name_b'],
        'BiggestDrawSumDate' => $row['record_date'] !== null ? (string) $row['record_date'] : null,
        'BiggestDrawSumGameID' => (int) $row['game_id'],
    ];
}

/**
 * @param array{event_date: string, chrono: float|int, tournament_id: int} $cutoff
 * @return array<string, mixed>
 */
function amiga_realm_biggest_sum_goals_patch(mysqli $con, array $cutoff): array
{
    $dateExpr = amiga_realm_game_event_date_sql();
    $extra = ' AND ' . amiga_realm_game_cutoff_sql('tg');
    $sql = "
        SELECT g.id AS game_id,
               COALESCE(r.sum_of_goals, g.goals_a + g.goals_b) AS goal_sum,
               g.player_a_id, g.player_b_id, pa.name AS name_a, pb.name AS name_b,
               {$dateExpr} AS record_date
        FROM amiga_games g
        INNER JOIN amiga_game_ratings r ON r.game_id = g.id
        INNER JOIN amiga_players pa ON pa.id = g.player_a_id
        INNER JOIN amiga_players pb ON pb.id = g.player_b_id
        LEFT JOIN tournaments t ON t.id = g.tournament_id
        INNER JOIN tournaments tg ON tg.id = g.tournament_id
        WHERE 1=1{$extra}
        ORDER BY goal_sum DESC, g.id ASC
        LIMIT 1
    ";
    $row = amiga_realm_fetch_game_row($con, $sql, $cutoff);
    if ($row === null) {
        return [];
    }

    return [
        'BiggestSumOfGoals' => (int) $row['goal_sum'],
        'BiggestSumOfGoalsIDA' => (int) $row['player_a_id'],
        'BiggestSumOfGoalsIDB' => (int) $row['player_b_id'],
        'BiggestSumOfGoalsNameA' => (string) $row['name_a'],
        'BiggestSumOfGoalsNameB' => (string) $row['name_b'],
        'BiggestSumOfGoalsDate' => $row['record_date'] !== null ? (string) $row['record_date'] : null,
        'BiggestSumOfGoalsGameID' => (int) $row['game_id'],
    ];
}

/**
 * @param array{event_date: string, chrono: float|int, tournament_id: int} $cutoff
 * @return array<string, mixed>
 */
function amiga_realm_biggest_peak_in_game_patch(mysqli $con, array $cutoff): array
{
    $dateExpr = amiga_realm_game_event_date_sql();
    $extra = ' AND ' . amiga_realm_game_cutoff_sql('tg');
    $sql = "
        SELECT game_id, player_id, player_name, peak_rating, record_date
        FROM (
            SELECT g.id AS game_id, g.player_a_id AS player_id, pa.name AS player_name,
                   COALESCE(r.new_rating_a, r.rating_a + r.adjustment_a) AS peak_rating,
                   {$dateExpr} AS record_date
            FROM amiga_games g
            INNER JOIN amiga_game_ratings r ON r.game_id = g.id
            INNER JOIN amiga_players pa ON pa.id = g.player_a_id
            LEFT JOIN tournaments t ON t.id = g.tournament_id
            INNER JOIN tournaments tg ON tg.id = g.tournament_id
            WHERE r.rating_a IS NOT NULL AND r.adjustment_a IS NOT NULL{$extra}
            UNION ALL
            SELECT g.id, g.player_b_id, pb.name,
                   COALESCE(r.new_rating_b, r.rating_b + r.adjustment_b), {$dateExpr}
            FROM amiga_games g
            INNER JOIN amiga_game_ratings r ON r.game_id = g.id
            INNER JOIN amiga_players pb ON pb.id = g.player_b_id
            LEFT JOIN tournaments t ON t.id = g.tournament_id
            INNER JOIN tournaments tg ON tg.id = g.tournament_id
            WHERE r.rating_b IS NOT NULL AND r.adjustment_b IS NOT NULL{$extra}
        ) peaks
        ORDER BY peak_rating DESC, game_id ASC
        LIMIT 1
    ";
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare peak in game: ' . $con->error);
    }
    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tournamentId = (int) $cutoff['tournament_id'];
    $stmt->bind_param('sdisdi', $eventDate, $chrono, $tournamentId, $eventDate, $chrono, $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute peak in game: ' . $stmt->error);
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row === null) {
        return [];
    }

    return [
        'BiggestPeakRating' => $row['peak_rating'],
        'BiggestPeakRatingID' => (int) $row['player_id'],
        'BiggestPeakRatingName' => (string) $row['player_name'],
        'BiggestPeakRatingDate' => $row['record_date'] !== null ? (string) $row['record_date'] : null,
    ];
}

/**
 * @param array{event_date: string, chrono: float|int, tournament_id: int} $cutoff
 * @return array<string, mixed>
 */
function amiga_realm_ratio_leader_patch(
    mysqli $con,
    array $cutoff,
    string $prefix,
    string $column,
    string $direction,
    string $extraWhere = '',
): array {
    $minGames = k2_established_min_games();
    $dirSql = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
    $latestSql = amiga_realm_latest_player_snapshots_sql();
    $extra = $extraWhere !== '' ? "AND ({$extraWhere})" : '';
    $sql = "
        SELECT lp.player_id, lp.player_name AS name, lp.`{$column}` AS metric_value
        FROM ({$latestSql}) lp
        WHERE lp.NumberGames >= ?
          AND lp.`{$column}` IS NOT NULL
          {$extra}
        ORDER BY lp.`{$column}` {$dirSql}, lp.player_id ASC
        LIMIT 1
    ";
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare ratio leader: ' . $con->error);
    }
    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tournamentId = (int) $cutoff['tournament_id'];
    $stmt->bind_param('sdis', $eventDate, $chrono, $tournamentId, $minGames);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute ratio leader: ' . $stmt->error);
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row === null) {
        return [];
    }

    return [
        $prefix => $row['metric_value'],
        $prefix . 'ID' => (int) $row['player_id'],
        $prefix . 'Name' => (string) $row['name'],
    ];
}

/**
 * Full-history rescan oracle (repair / parity checks).
 *
 * @return array<string, mixed>
 */
function amiga_realm_build_generalstats_payload_oracle(mysqli $con, int $tournamentId): array
{
    $cutoff = amiga_realm_load_cutoff($con, $tournamentId);
    $patch = amiga_realm_compute_server_aggregates($con, $cutoff);

    $careerHolders = [
        ['MostGamesPlayed', 'NumberGames', 'MostGamesPlayed'],
        ['MostWins', 'NumberWins', 'MostWins'],
        ['MostGoalsScored', 'GoalsFor', 'MostGoalsScored'],
        ['MostDoubleDigits', 'DoubleDigits', 'MostDoubleDigits'],
        ['MostCleanSheets', 'CleanSheets', 'MostCleanSheets'],
        ['MostDifferentOpponents', 'DifferentOpponents', 'MostDifferentOpponents'],
        ['MostDifferentVictims', 'DifferentVictims', 'MostDifferentVictims'],
        ['MostDoubleDigitsVictims', 'DoubleDigitsVictims', 'MostDoubleDigitsVictims'],
        ['MostCleanSheetsVictims', 'CleanSheetsVictims', 'MostCleanSheetsVictims'],
        ['BiggestRatingAscent', 'BiggestRatingAscent', 'BiggestRatingAscent'],
        ['peak_year_games', 'peak_year_games', 'MostGamesInOneYear'],
        ['peak_year_tournaments', 'peak_year_tournaments', 'MostTournamentsInOneYear'],
        ['tournaments_played', 'tournaments_played', 'MostTournamentsPlayed'],
        ['event_gold', 'event_gold', 'MostTournamentWins'],
        ['wc_played', 'wc_played', 'MostWcPlayed'],
        ['countries_played_in', 'countries_played_in', 'MostCountriesPlayedIn'],
        ['opponent_countries_faced', 'opponent_countries_faced', 'MostOpponentCountriesFaced'],
        ['opponent_countries_beaten', 'opponent_countries_beaten', 'MostOpponentCountriesBeaten'],
    ];
    foreach ($careerHolders as [$_, $valueCol, $prefix]) {
        $patch = array_merge(
            $patch,
            amiga_realm_career_holder_patch($con, $cutoff, $valueCol, $prefix)
        );
    }
    $patch = array_merge($patch, amiga_realm_biggest_peak_in_game_patch($con, $cutoff));
    $patch = array_merge($patch, amiga_realm_most_goals_one_game_patch($con, $cutoff));
    $patch = array_merge($patch, amiga_realm_biggest_win_margin_patch($con, $cutoff));
    $patch = array_merge($patch, amiga_realm_biggest_draw_sum_patch($con, $cutoff));
    $patch = array_merge($patch, amiga_realm_biggest_sum_goals_patch($con, $cutoff));

    $ratioLeaders = [
        ['BiggestWinRatio', 'WinRatio', 'DESC', ''],
        ['BiggestGoalsForAverage', 'AverageGoalsFor', 'DESC', ''],
        ['SmallestGoalsAgainstAverage', 'AverageGoalsAgainst', 'ASC', ''],
        ['BiggestGoalRatio', 'GoalRatio', 'DESC', 'lp.GoalRatio > -1'],
        ['BiggestDoubleDigitsRatio', 'DoubleDigitsRatio', 'DESC', ''],
        ['BiggestCleanSheetsRatio', 'CleanSheetsRatio', 'DESC', ''],
    ];
    foreach ($ratioLeaders as [$prefix, $column, $direction, $extra]) {
        $patch = array_merge(
            $patch,
            amiga_realm_ratio_leader_patch($con, $cutoff, $prefix, $column, $direction, $extra)
        );
    }

    return $patch;
}

/**
 * @return array<string, mixed>
 */
function amiga_realm_build_row(mysqli $con, int $tournamentId, string $finalizedAt): array
{
    require_once __DIR__ . '/amiga_realm_incremental_lib.php';
    $cutoff = amiga_realm_load_cutoff($con, $tournamentId);
    $payload = amiga_realm_build_generalstats_payload_incremental($con, $tournamentId);

    return array_merge([
        'tournament_id' => $tournamentId,
        'event_date' => $cutoff['event_date'],
        'event_chrono' => $cutoff['chrono'],
        'tournament_name' => $cutoff['tournament_name'],
        'finalized_at' => $finalizedAt,
    ], $payload);
}

/**
 * @param array<string, mixed> $row
 */
function amiga_realm_persist_snapshot(mysqli $con, array $row): void
{
    $timeline = ['tournament_id', 'event_date', 'event_chrono', 'tournament_name', 'finalized_at'];
    $snapshotCols = array_merge($timeline, array_keys(array_diff_key($row, array_flip($timeline))));

    $insertCols = [];
    $placeholders = [];
    $values = [];
    $updates = [];
    foreach ($snapshotCols as $col) {
        if (!array_key_exists($col, $row)) {
            continue;
        }
        $insertCols[] = '`' . $col . '`';
        $placeholders[] = '?';
        $values[] = $row[$col];
        if ($col !== 'tournament_id') {
            $updates[] = '`' . $col . '` = VALUES(`' . $col . '`)';
        }
    }
    $sql = 'INSERT INTO amiga_realm_snapshots (' . implode(', ', $insertCols) . ') VALUES ('
        . implode(', ', $placeholders) . ') ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
    amiga_realm_execute_bound($con, $sql, $values);

    $payload = array_diff_key($row, array_flip($timeline));
    if ($payload === []) {
        return;
    }
    $sets = [];
    $gstValues = [];
    foreach ($payload as $col => $value) {
        $sets[] = '`' . $col . '` = ?';
        $gstValues[] = $value;
    }
    $gstSql = 'UPDATE amiga_generalstats SET ' . implode(', ', $sets) . ' WHERE id = 1';
    amiga_realm_execute_bound($con, $gstSql, $gstValues);
}

function amiga_realm_persist_snapshot_for_tournament(
    mysqli $con,
    int $tournamentId,
    string $finalizedAt,
): void {
    $row = amiga_realm_build_row($con, $tournamentId, $finalizedAt);
    amiga_realm_persist_snapshot($con, $row);
}

/**
 * @param array{event_date: string, chrono: float|int, tournament_id: int} $cutoff
 * @return array<string, mixed>|null
 */
function amiga_realm_fetch_game_row(mysqli $con, string $sql, array $cutoff): ?array
{
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare game record: ' . $con->error);
    }
    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tournamentId = (int) $cutoff['tournament_id'];
    $stmt->bind_param('sdi', $eventDate, $chrono, $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute game record: ' . $stmt->error);
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row === null ? null : $row;
}

/**
 * @param array{event_date: string, chrono: float|int, tournament_id: int} $cutoff
 * @return array<string, mixed>
 */
function amiga_realm_fetch_single_game_record(
    mysqli $con,
    string $sql,
    array $cutoff,
    string $prefix,
): array {
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare single game record: ' . $con->error);
    }
    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tournamentId = (int) $cutoff['tournament_id'];
    $stmt->bind_param('sdisdi', $eventDate, $chrono, $tournamentId, $eventDate, $chrono, $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute single game record: ' . $stmt->error);
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row === null) {
        return [];
    }

    return [
        $prefix => (int) $row['goals'],
        $prefix . 'ID' => (int) $row['player_id'],
        $prefix . 'Name' => (string) $row['player_name'],
        $prefix . 'Date' => $row['record_date'] !== null ? (string) $row['record_date'] : null,
        $prefix . 'GameID' => (int) $row['game_id'],
    ];
}

/**
 * @param list<mixed> $values
 */
function amiga_realm_execute_bound(mysqli $con, string $sql, array $values): void
{
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare bound query: ' . $con->error);
    }
    $types = '';
    $bindValues = [];
    foreach ($values as $value) {
        if (is_int($value)) {
            $types .= 'i';
            $bindValues[] = $value;
        } elseif (is_float($value)) {
            $types .= 'd';
            $bindValues[] = $value;
        } elseif ($value === null) {
            $types .= 's';
            $bindValues[] = null;
        } else {
            $types .= 's';
            $bindValues[] = (string) $value;
        }
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$bindValues);
    }
    if (!$stmt->execute()) {
        throw new RuntimeException('execute bound query: ' . $stmt->error);
    }
    $stmt->close();
}
