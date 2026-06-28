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
    $cutoffSql = amiga_snapshot_rated_game_cutoff_and_sql($ctx, $cutoffTypes, $cutoffParams);
    $types .= $cutoffTypes;
    $params = array_merge($params, $cutoffParams);

    $sql = amiga_realm_games_hub_select_sql()
        . amiga_rated_games_from_sql()
        . ' WHERE r.tournament_id = ?' . $cutoffSql
        . ' ORDER BY r.id ' . $dir;

    return amiga_realm_games_hub_query_all($con, $sql, $types, $params);
}
