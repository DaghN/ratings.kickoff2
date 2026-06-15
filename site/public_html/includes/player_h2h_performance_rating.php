<?php
/**
 * Performance rating for one player in an online head-to-head pairing (read-time).
 */
declare(strict_types=1);

require_once __DIR__ . '/performance_rating.php';

/**
 * @param list<array{idA: int, RatingA: mixed, RatingB: mixed, ActualScore: mixed}> $rows
 * @return list<array{opponent: float, score: float}>
 */
function player_h2h_performance_rating_pairs_for_player(array $rows, int $playerId): array
{
    $pairs = [];
    foreach ($rows as $row) {
        $idA = (int) ($row['idA'] ?? 0);
        $actualScore = (float) ($row['ActualScore'] ?? 0);
        if ($idA === $playerId) {
            $pairs[] = [
                'opponent' => (float) ($row['RatingB'] ?? 0),
                'score' => $actualScore,
            ];
        } else {
            $pairs[] = [
                'opponent' => (float) ($row['RatingA'] ?? 0),
                'score' => 1.0 - $actualScore,
            ];
        }
    }

    return $pairs;
}

/**
 * @return array{subject: ?int, opponent: ?int}
 */
function player_h2h_pair_performance_ratings(mysqli $con, int $playerId, int $opponentId): array
{
    $playerId = max(0, $playerId);
    $opponentId = max(0, $opponentId);
    if ($playerId < 1 || $opponentId < 1 || $playerId === $opponentId) {
        return ['subject' => null, 'opponent' => null];
    }

    $sql = 'SELECT idA, RatingA, RatingB, ActualScore FROM ratedresults WHERE idA = ? AND idB = ? '
        . 'UNION ALL '
        . 'SELECT idA, RatingA, RatingB, ActualScore FROM ratedresults WHERE idA = ? AND idB = ?';

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return ['subject' => null, 'opponent' => null];
    }
    $stmt->bind_param('iiii', $playerId, $opponentId, $opponentId, $playerId);
    if (!$stmt->execute()) {
        $stmt->close();

        return ['subject' => null, 'opponent' => null];
    }
    $res = $stmt->get_result();
    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->free();
    }
    $stmt->close();

    $subjectPerf = performance_rating_from_pairs(
        player_h2h_performance_rating_pairs_for_player($rows, $playerId)
    );
    $opponentPerf = performance_rating_from_pairs(
        player_h2h_performance_rating_pairs_for_player($rows, $opponentId)
    );

    return [
        'subject' => $subjectPerf !== null ? (int) round($subjectPerf) : null,
        'opponent' => $opponentPerf !== null ? (int) round($opponentPerf) : null,
    ];
}
