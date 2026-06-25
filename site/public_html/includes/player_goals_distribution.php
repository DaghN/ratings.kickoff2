<?php
/**
 * Per-game goals scored frequency for one player (ratedresults, subject side).
 */
declare(strict_types=1);

/**
 * Sparse rows: each distinct goals-for count that appears at least once.
 *
 * @return list<array{goals_for: int, games: int}>
 */
function player_goals_scored_per_game_rows(mysqli $con, int $playerId, ?int $opponentId = null): array
{
    $playerId = max(0, $playerId);
    if ($playerId < 1) {
        return [];
    }

    $opponentId = $opponentId !== null ? max(0, $opponentId) : 0;

    if ($opponentId > 0) {
        $sql = 'SELECT goals_for, COUNT(*) AS games FROM ('
            . 'SELECT GoalsA AS goals_for FROM ratedresults WHERE idA = ? AND idB = ? '
            . 'UNION ALL '
            . 'SELECT GoalsB AS goals_for FROM ratedresults WHERE idB = ? AND idA = ?'
            . ') AS goals GROUP BY goals_for ORDER BY goals_for ASC';
        $stmt = $con->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('iiii', $playerId, $opponentId, $playerId, $opponentId);
    } else {
        $sql = 'SELECT goals_for, COUNT(*) AS games FROM ('
            . 'SELECT GoalsA AS goals_for FROM ratedresults WHERE idA = ? '
            . 'UNION ALL '
            . 'SELECT GoalsB AS goals_for FROM ratedresults WHERE idB = ?'
            . ') AS goals GROUP BY goals_for ORDER BY goals_for ASC';
        $stmt = $con->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('ii', $playerId, $playerId);
    }

    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'goals_for' => (int) $row['goals_for'],
            'games' => (int) $row['games'],
        ];
    }

    $stmt->close();

    return $rows;
}

/**
 * Full histogram buckets from 0 through max goals scored (missing counts = 0).
 *
 * @return list<array{goals: int, games: int}>
 */
function player_goals_scored_distribution_buckets(mysqli $con, int $playerId, ?int $opponentId = null): array
{
    $rows = player_goals_scored_per_game_rows($con, $playerId, $opponentId);
    if ($rows === []) {
        return [];
    }

    $maxGoals = $rows[count($rows) - 1]['goals_for'];
    $counts = [];
    foreach ($rows as $row) {
        $counts[$row['goals_for']] = $row['games'];
    }

    $buckets = [];
    for ($g = 0; $g <= $maxGoals; $g++) {
        $buckets[] = [
            'goals' => $g,
            'games' => $counts[$g] ?? 0,
        ];
    }

    return $buckets;
}

/** @return int */
function player_goals_scored_distribution_total_games(array $buckets): int
{
    $total = 0;
    foreach ($buckets as $bucket) {
        $total += (int) $bucket['games'];
    }

    return $total;
}

/** @return float|null */
function player_goals_scored_distribution_avg_goals_per_game(array $buckets): ?float
{
    $totalGames = player_goals_scored_distribution_total_games($buckets);
    if ($totalGames < 1) {
        return null;
    }

    $goalSum = 0;
    foreach ($buckets as $bucket) {
        $goalSum += (int) $bucket['goals'] * (int) $bucket['games'];
    }

    return round($goalSum / $totalGames, 2);
}

/**
 * Sparse rows: total goals per game (SumOfGoals) for one head-to-head pairing.
 *
 * @return list<array{total_goals: int, games: int}>
 */
function player_h2h_total_goals_per_game_rows(mysqli $con, int $playerId, int $opponentId): array
{
    $playerId = max(0, $playerId);
    $opponentId = max(0, $opponentId);
    if ($playerId < 1 || $opponentId < 1 || $playerId === $opponentId) {
        return [];
    }

    $sql = 'SELECT SumOfGoals AS total_goals, COUNT(*) AS games FROM ratedresults '
        . 'WHERE (idA = ? AND idB = ?) OR (idA = ? AND idB = ?) '
        . 'GROUP BY SumOfGoals ORDER BY SumOfGoals ASC';

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('iiii', $playerId, $opponentId, $opponentId, $playerId);
    if (!$stmt->execute()) {
        $stmt->close();

        return [];
    }
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'total_goals' => (int) $row['total_goals'],
            'games' => (int) $row['games'],
        ];
    }
    if ($res) {
        $res->free();
    }
    $stmt->close();

    return $rows;
}

/**
 * Full histogram buckets from 0 through max total goals (missing counts = 0).
 *
 * @return list<array{goals: int, games: int}>
 */
function player_h2h_total_goals_distribution_buckets(mysqli $con, int $playerId, int $opponentId): array
{
    $rows = player_h2h_total_goals_per_game_rows($con, $playerId, $opponentId);
    if ($rows === []) {
        return [];
    }

    $maxGoals = $rows[count($rows) - 1]['total_goals'];
    $counts = [];
    foreach ($rows as $row) {
        $counts[$row['total_goals']] = $row['games'];
    }

    $buckets = [];
    for ($g = 0; $g <= $maxGoals; $g++) {
        $buckets[] = [
            'goals' => $g,
            'games' => $counts[$g] ?? 0,
        ];
    }

    return $buckets;
}

/**
 * Sparse scoreline counts for one head-to-head pairing (subject goals_for × goals_against).
 *
 * @return list<array{goals_for: int, goals_against: int, games: int}>
 */
function player_h2h_scoreline_rows(mysqli $con, int $playerId, int $opponentId): array
{
    $playerId = max(0, $playerId);
    $opponentId = max(0, $opponentId);
    if ($playerId < 1 || $opponentId < 1 || $playerId === $opponentId) {
        return [];
    }

    $sql = 'SELECT goals_for, goals_against, COUNT(*) AS games FROM ('
        . 'SELECT GoalsA AS goals_for, GoalsB AS goals_against FROM ratedresults WHERE idA = ? AND idB = ? '
        . 'UNION ALL '
        . 'SELECT GoalsB AS goals_for, GoalsA AS goals_against FROM ratedresults WHERE idB = ? AND idA = ?'
        . ') AS scores GROUP BY goals_for, goals_against ORDER BY goals_for ASC, goals_against ASC';

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('iiii', $playerId, $opponentId, $playerId, $opponentId);
    if (!$stmt->execute()) {
        $stmt->close();

        return [];
    }
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'goals_for' => (int) $row['goals_for'],
            'goals_against' => (int) $row['goals_against'],
            'games' => (int) $row['games'],
        ];
    }
    if ($res) {
        $res->free();
    }
    $stmt->close();

    return $rows;
}

/**
 * Scoreline heatmap axes: hero GF (rows) and rival GA (columns) each 0…pair max on that axis.
 *
 * @return array{max_goals_for: int, max_goals_against: int, grid_axis_max: int, cells: list<array{goals_for: int, goals_against: int, games: int, outcome: string}>}
 */
function player_h2h_scoreline_heatmap_payload(mysqli $con, int $playerId, int $opponentId): array
{
    $rows = player_h2h_scoreline_rows($con, $playerId, $opponentId);
    if ($rows === []) {
        return [
            'max_goals_for' => 0,
            'max_goals_against' => 0,
            'grid_axis_max' => 0,
            'cells' => [],
        ];
    }

    $pairMaxGoalsFor = 0;
    $pairMaxGoalsAgainst = 0;
    $cells = [];
    foreach ($rows as $row) {
        $goalsFor = (int) $row['goals_for'];
        $goalsAgainst = (int) $row['goals_against'];
        if ($goalsFor > $pairMaxGoalsFor) {
            $pairMaxGoalsFor = $goalsFor;
        }
        if ($goalsAgainst > $pairMaxGoalsAgainst) {
            $pairMaxGoalsAgainst = $goalsAgainst;
        }

        $outcome = 'draw';
        if ($goalsFor > $goalsAgainst) {
            $outcome = 'win';
        } elseif ($goalsFor < $goalsAgainst) {
            $outcome = 'loss';
        }

        $cells[] = [
            'goals_for' => $goalsFor,
            'goals_against' => $goalsAgainst,
            'games' => (int) $row['games'],
            'outcome' => $outcome,
        ];
    }

    return [
        'max_goals_for' => $pairMaxGoalsFor,
        'max_goals_against' => $pairMaxGoalsAgainst,
        'grid_axis_max' => max($pairMaxGoalsFor, $pairMaxGoalsAgainst),
        'cells' => $cells,
    ];
}
