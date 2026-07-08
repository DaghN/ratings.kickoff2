<?php
/**
 * Amiga realm read-path SQL — joins ground + derived tables into ratedresults-shaped rows.
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_player_current_lib.php';

/** ORDER BY fragment for game chronology walks (matches replay.py GAME_SELECT). */
function amiga_game_chronology_order_sql(string $direction = 'ASC'): string
{
    $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

    return "g.game_date {$dir}, g.id {$dir}";
}

/**
 * Subquery alias `r` with column names compatible with legacy ratedresults consumers.
 *
 * Push predicates into the inner scan when possible:
 * - $scopePlayerId → idx_amiga_games_player_a / player_b
 * - $scopeTournamentId → idx_amiga_games_tournament
 * - $scopeGameId → primary key on amiga_games
 */
function amiga_rated_games_from_sql(
    ?int $scopePlayerId = null,
    ?int $scopeTournamentId = null,
    ?int $scopeGameId = null,
    string $extraInnerWhereSql = '',
): string {
    $whereParts = [];
    if ($scopePlayerId !== null && $scopePlayerId > 0) {
        $pid = (int) $scopePlayerId;
        $whereParts[] = "(g.player_a_id = {$pid} OR g.player_b_id = {$pid})";
    }
    if ($scopeTournamentId !== null && $scopeTournamentId > 0) {
        $whereParts[] = 'g.tournament_id = ' . (int) $scopeTournamentId;
    }
    if ($scopeGameId !== null && $scopeGameId > 0) {
        $whereParts[] = 'g.id = ' . (int) $scopeGameId;
    }
    $extraInnerWhereSql = trim($extraInnerWhereSql);
    if ($extraInnerWhereSql !== '') {
        $whereParts[] = '(' . $extraInnerWhereSql . ')';
    }
    $playerWhere = $whereParts !== [] ? "\n    WHERE " . implode(' AND ', $whereParts) : '';

    return <<<SQL
FROM (
    SELECT
        g.id,
        g.game_date AS `Date`,
        g.player_a_id AS idA,
        pa.name AS NameA,
        g.player_b_id AS idB,
        pb.name AS NameB,
        g.tournament_id,
        g.phase,
        g.goals_a AS GoalsA,
        g.goals_b AS GoalsB,
        gr.rating_a AS RatingA,
        gr.rating_b AS RatingB,
        gr.rating_difference AS RatingDifference,
        gr.home_win AS HomeWin,
        gr.draw AS Draw,
        gr.away_win AS AwayWin,
        gr.dd_player_a AS DDPlayerA,
        gr.dd_player_b AS DDPlayerB,
        gr.cs_player_a AS CSPlayerA,
        gr.cs_player_b AS CSPlayerB,
        gr.expected_score_a AS ExpectedScoreA,
        gr.expected_score_b AS ExpectedScoreB,
        gr.actual_score AS ActualScore,
        gr.adjustment_a AS AdjustmentA,
        gr.adjustment_b AS AdjustmentB,
        gr.new_rating_a AS NewRatingA,
        gr.new_rating_b AS NewRatingB,
        gr.sum_of_goals AS SumOfGoals,
        gr.goal_difference AS GoalDifference,
        gr.winner_id AS WinnerID,
        t.name AS tournament_name,
        t.country AS tournament_country,
        t.event_date AS tournament_event_date,
        t.chrono AS tournament_chrono,
        t.is_world_cup,
        pa.country AS country_a,
        pb.country AS country_b
    FROM amiga_games g
    INNER JOIN amiga_game_ratings gr ON gr.game_id = g.id
    INNER JOIN amiga_players pa ON pa.id = g.player_a_id
    INNER JOIN amiga_players pb ON pb.id = g.player_b_id
    LEFT JOIN tournaments t ON t.id = g.tournament_id{$playerWhere}
) r
SQL;
}

/**
 * Single rated Amiga game row (ground + derived), or null when missing.
 *
 * @return ?array<string, mixed>
 */
function amiga_rated_game_load(mysqli $con, int $gameId): ?array
{
    if ($gameId < 1) {
        return null;
    }

    $sql = 'SELECT r.id, r.Date, r.idA, r.NameA, r.idB, r.NameB, r.RatingA, r.RatingB, r.RatingDifference, '
        . 'r.GoalsA, r.GoalsB, r.ExpectedScoreA, r.ExpectedScoreB, r.ActualScore, r.AdjustmentA, r.AdjustmentB, '
        . 'r.SumOfGoals, r.GoalDifference, r.phase, r.tournament_id, r.tournament_name, '
        . 'r.country_a, r.country_b, r.tournament_country '
        . amiga_rated_games_from_sql(null, null, $gameId)
        . ' WHERE r.id = ? LIMIT 1';
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Query failed: ' . $con->error);
    }
    $stmt->bind_param('i', $gameId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : false;
    if ($result) {
        $result->free();
    }
    $stmt->close();

    return is_array($row) ? $row : null;
}
