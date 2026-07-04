<?php
/**
 * Amiga realm Games hub — shared rated-game row queries.
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_db.php';
require_once __DIR__ . '/amiga_snapshot_context.php';

function amiga_realm_games_hub_select_sql(): string
{
    return 'SELECT r.id, r.`Date`, r.idA, r.NameA, r.idB, r.NameB, r.tournament_id, r.tournament_name, r.tournament_country, r.phase, '
        . 'r.GoalsA, r.GoalsB, r.RatingA, r.RatingB, r.RatingDifference, '
        . 'r.ExpectedScoreA, r.ExpectedScoreB, r.ActualScore, r.AdjustmentA, r.AdjustmentB, '
        . 'r.NewRatingA, r.NewRatingB, r.SumOfGoals, r.GoalDifference, r.country_a, r.country_b ';
}

function amiga_realm_games_hub_lean_select_sql(): string
{
    return 'SELECT g.id AS id, g.game_date AS `Date`, g.player_a_id AS idA, pa.name AS NameA, '
        . 'g.player_b_id AS idB, pb.name AS NameB, g.tournament_id AS tournament_id, t.name AS tournament_name, '
        . 't.country AS tournament_country, g.phase AS phase, g.goals_a AS GoalsA, g.goals_b AS GoalsB, '
        . 'gr.rating_a AS RatingA, gr.rating_b AS RatingB, gr.rating_difference AS RatingDifference, '
        . 'gr.expected_score_a AS ExpectedScoreA, gr.expected_score_b AS ExpectedScoreB, gr.actual_score AS ActualScore, '
        . 'gr.adjustment_a AS AdjustmentA, gr.adjustment_b AS AdjustmentB, gr.new_rating_a AS NewRatingA, '
        . 'gr.new_rating_b AS NewRatingB, gr.sum_of_goals AS SumOfGoals, gr.goal_difference AS GoalDifference, '
        . 'pa.country AS country_a, pb.country AS country_b ';
}

function amiga_realm_games_hub_lean_from_sql(): string
{
    return 'FROM amiga_games g '
        . 'INNER JOIN amiga_game_ratings gr ON gr.game_id = g.id '
        . 'INNER JOIN amiga_players pa ON pa.id = g.player_a_id '
        . 'INNER JOIN amiga_players pb ON pb.id = g.player_b_id '
        . 'LEFT JOIN tournaments t ON t.id = g.tournament_id ';
}

function amiga_realm_games_hub_query_all(mysqli $con, string $sql, string $types = '', array $params = []): array
{
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Query failed: ' . $con->error);
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->free();
    }
    $stmt->close();

    return $rows;
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_realm_games_hub_fetch_tournament_games(
    mysqli $con,
    int $tournamentId,
    AmigaSnapshotContext $ctx,
    string $direction = 'DESC',
): array {
    $dir = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
    $types = 'i';
    $params = [$tournamentId];
    $cutoffTypes = '';
    $cutoffParams = [];
    $cutoffSql = amiga_snapshot_tournament_cutoff_and_sql(
        $ctx,
        $cutoffTypes,
        $cutoffParams,
        't.event_date',
        't.chrono',
        't.id',
    );
    $types .= $cutoffTypes;
    $params = array_merge($params, $cutoffParams);

    $sql = amiga_realm_games_hub_lean_select_sql()
        . amiga_realm_games_hub_lean_from_sql()
        . 'WHERE g.tournament_id = ?' . $cutoffSql
        . ' ORDER BY g.id ' . $dir;

    return amiga_realm_games_hub_query_all($con, $sql, $types, $params);
}

/**
 * @param list<int> $tournamentIds
 * @return array<int, list<array<string, mixed>>>
 */
function amiga_realm_games_hub_fetch_games_by_tournaments(
    mysqli $con,
    array $tournamentIds,
    AmigaSnapshotContext $ctx,
): array {
    $ids = array_values(array_unique(array_filter(
        array_map(static fn (mixed $id): int => (int) $id, $tournamentIds),
        static fn (int $id): bool => $id > 0,
    )));
    if ($ids === []) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $params = $ids;
    $cutoffTypes = '';
    $cutoffParams = [];
    $cutoffSql = amiga_snapshot_tournament_cutoff_and_sql(
        $ctx,
        $cutoffTypes,
        $cutoffParams,
        't.event_date',
        't.chrono',
        't.id',
    );
    $types .= $cutoffTypes;
    $params = array_merge($params, $cutoffParams);

    $sql = amiga_realm_games_hub_lean_select_sql()
        . amiga_realm_games_hub_lean_from_sql()
        . 'WHERE g.tournament_id IN (' . $placeholders . ')' . $cutoffSql
        . ' ORDER BY g.tournament_id ASC, g.id DESC';

    $byTournament = [];
    foreach ($ids as $id) {
        $byTournament[$id] = [];
    }
    foreach (amiga_realm_games_hub_query_all($con, $sql, $types, $params) as $row) {
        $tid = (int) ($row['tournament_id'] ?? 0);
        if ($tid > 0 && isset($byTournament[$tid])) {
            $byTournament[$tid][] = $row;
        }
    }

    return $byTournament;
}
