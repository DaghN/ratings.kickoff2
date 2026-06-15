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
