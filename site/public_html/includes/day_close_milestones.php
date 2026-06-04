<?php
declare(strict_types=1);

/**
 * UTC day-close milestones: perfect_day, nightmare_day.
 *
 * @see docs/website-data-contract.md § player_milestones
 * @see scripts/ladder/milestone_sim.py _finalize_day
 */

function k2_day_close_try_insert_milestone(
    mysqli $con,
    int $playerId,
    string $key,
    string $achievedAt,
    int $lastGameId
): bool {
    $insertStmt = $con->prepare(
        'INSERT INTO player_milestones '
        . '(player_id, milestone_key, achieved_at, value, source_kind, source_game_id, '
        . 'source_league_kind, source_period_type, source_period_start) '
        . 'SELECT ?, ?, ?, 5, \'game\', ?, NULL, NULL, NULL FROM DUAL '
        . 'WHERE NOT EXISTS ('
        . 'SELECT 1 FROM player_milestones WHERE player_id = ? AND milestone_key = ? LIMIT 1'
        . ')'
    );
    if ($insertStmt === false) {
        throw new RuntimeException('prepare day-close insert: ' . $con->error);
    }
    $insertStmt->bind_param('issiis', $playerId, $key, $achievedAt, $lastGameId, $playerId, $key);
    if (!$insertStmt->execute()) {
        $err = $insertStmt->error;
        $insertStmt->close();
        throw new RuntimeException('day-close insert: ' . $err);
    }
    $inserted = $insertStmt->affected_rows > 0;
    $insertStmt->close();

    return $inserted;
}

/**
 * @return array{perfect_day: int, nightmare_day: int}
 */
function k2_day_close_finalize_utc_day(mysqli $con, string $closedUtcDayYmd): array
{
    $result = ['perfect_day' => 0, 'nightmare_day' => 0];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $closedUtcDayYmd)) {
        throw new InvalidArgumentException('closedUtcDayYmd must be YYYY-MM-DD');
    }

    $dayStart = $closedUtcDayYmd . ' 00:00:00';
    $dayEnd = (new DateTimeImmutable($closedUtcDayYmd . ' 00:00:00', new DateTimeZone('UTC')))
        ->modify('+1 day')
        ->format('Y-m-d H:i:s');
    $achievedAt = $dayEnd;

    $sql = <<<'SQL'
SELECT
  agg.player_id,
  agg.game_count,
  agg.win_count,
  agg.loss_count,
  agg.last_game_id
FROM (
  SELECT
    u.player_id,
    COUNT(*) AS game_count,
    SUM(CASE WHEN u.outcome = 'W' THEN 1 ELSE 0 END) AS win_count,
    SUM(CASE WHEN u.outcome = 'L' THEN 1 ELSE 0 END) AS loss_count,
    MAX(u.game_id) AS last_game_id
  FROM (
    SELECT
      r.id AS game_id,
      r.idA AS player_id,
      CASE
        WHEN r.ActualScore = 1 THEN 'W'
        WHEN r.ActualScore = 0.5 THEN 'D'
        ELSE 'L'
      END AS outcome
    FROM ratedresults r
    WHERE r.Date >= ? AND r.Date < ?
      AND r.NewRatingA IS NOT NULL
      AND r.idA > 0
    UNION ALL
    SELECT
      r.id,
      r.idB,
      CASE
        WHEN r.ActualScore = 0 THEN 'W'
        WHEN r.ActualScore = 0.5 THEN 'D'
        ELSE 'L'
      END
    FROM ratedresults r
    WHERE r.Date >= ? AND r.Date < ?
      AND r.NewRatingA IS NOT NULL
      AND r.idB > 0
  ) AS u
  GROUP BY u.player_id
) AS agg
WHERE agg.game_count >= 5
  AND (
    agg.win_count = agg.game_count
    OR agg.loss_count = agg.game_count
  )
SQL;

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare day-close: ' . $con->error);
    }
    $stmt->bind_param('ssss', $dayStart, $dayEnd, $dayStart, $dayEnd);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute day-close: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    if ($res === false) {
        $stmt->close();
        throw new RuntimeException('day-close result: ' . $con->error);
    }

    while ($row = $res->fetch_assoc()) {
        $playerId = (int) $row['player_id'];
        $gameCount = (int) $row['game_count'];
        $winCount = (int) $row['win_count'];
        $lossCount = (int) $row['loss_count'];
        $lastGameId = (int) $row['last_game_id'];
        if ($gameCount < 5) {
            continue;
        }
        $key = null;
        if ($winCount === $gameCount) {
            $key = 'perfect_day';
        } elseif ($lossCount === $gameCount) {
            $key = 'nightmare_day';
        }
        if ($key === null) {
            continue;
        }
        if (k2_day_close_try_insert_milestone($con, $playerId, $key, $achievedAt, $lastGameId)) {
            ++$result[$key];
        }
    }

    $res->free();
    $stmt->close();

    return $result;
}
