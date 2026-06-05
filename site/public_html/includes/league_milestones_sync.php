<?php
declare(strict_types=1);

/**
 * Incremental league-event milestone keys (first matching award per player).
 * Win-count keys (league_wins_*) use k2_league_sync_win_milestones() in league_standings.php.
 *
 * @see ops day tick / finalize (league wave); historical batch: scripts/ladder/sql/archive/batch-2026-05/
 * @see docs/coordination/ops-orchestration-adr.md
 */

require_once __DIR__ . '/league_standings.php';

/**
 * @return list<array{key: string, value: int, where: string}>
 */
function k2_league_event_milestone_specs(): array
{
    return [
        ['key' => 'moment_of_glory', 'value' => 1, 'where' => "league_kind = 'points' AND period_type = 'day' AND is_winner = 1"],
        ['key' => 'activity_king', 'value' => 1, 'where' => "league_kind = 'activity' AND period_type = 'month' AND is_winner = 1"],
        ['key' => 'league_daily_points_medal', 'value' => 3, 'where' => "league_kind = 'points' AND period_type = 'day' AND finish_rank <= 3"],
        ['key' => 'league_daily_activity_medal', 'value' => 3, 'where' => "league_kind = 'activity' AND period_type = 'day' AND finish_rank <= 3"],
        ['key' => 'league_daily_activity_winner', 'value' => 1, 'where' => "league_kind = 'activity' AND period_type = 'day' AND is_winner = 1"],
        ['key' => 'league_weekly_points_medal', 'value' => 3, 'where' => "league_kind = 'points' AND period_type = 'week' AND finish_rank <= 3"],
        ['key' => 'league_weekly_points_winner', 'value' => 1, 'where' => "league_kind = 'points' AND period_type = 'week' AND is_winner = 1"],
        ['key' => 'league_weekly_activity_medal', 'value' => 3, 'where' => "league_kind = 'activity' AND period_type = 'week' AND finish_rank <= 3"],
        ['key' => 'league_weekly_activity_winner', 'value' => 1, 'where' => "league_kind = 'activity' AND period_type = 'week' AND is_winner = 1"],
        ['key' => 'league_monthly_points_medal', 'value' => 3, 'where' => "league_kind = 'points' AND period_type = 'month' AND finish_rank <= 3"],
        ['key' => 'league_monthly_points_winner', 'value' => 1, 'where' => "league_kind = 'points' AND period_type = 'month' AND is_winner = 1"],
        ['key' => 'league_monthly_activity_medal', 'value' => 3, 'where' => "league_kind = 'activity' AND period_type = 'month' AND finish_rank <= 3"],
        ['key' => 'league_yearly_points_medal', 'value' => 3, 'where' => "league_kind = 'points' AND period_type = 'year' AND finish_rank <= 3"],
        ['key' => 'league_yearly_points_winner', 'value' => 1, 'where' => "league_kind = 'points' AND period_type = 'year' AND is_winner = 1"],
        ['key' => 'league_yearly_activity_medal', 'value' => 3, 'where' => "league_kind = 'activity' AND period_type = 'year' AND finish_rank <= 3"],
        ['key' => 'league_yearly_activity_winner', 'value' => 1, 'where' => "league_kind = 'activity' AND period_type = 'year' AND is_winner = 1"],
    ];
}

/**
 * Idempotent insert for first award rows not yet in player_milestones.
 *
 * @return int rows inserted (sum of affected_rows)
 */
function k2_league_sync_event_milestones(mysqli $con): int
{
    if (!k2_league_table_exists($con, 'player_milestones')
        || !k2_league_table_exists($con, 'player_league_award')) {
        return 0;
    }

    $inserted = 0;
    foreach (k2_league_event_milestone_specs() as $spec) {
        $key = $spec['key'];
        $value = (int) $spec['value'];
        $where = $spec['where'];
        $sql = <<<SQL
INSERT INTO player_milestones (
  player_id, milestone_key, achieved_at, value,
  source_kind, source_game_id, source_league_kind, source_period_type, source_period_start
)
SELECT
  fa.player_id, ?, fa.period_end, ?,
  'league', NULL, fa.league_kind, fa.period_type, fa.period_start
FROM (
  SELECT player_id, period_end, league_kind, period_type, period_start,
         ROW_NUMBER() OVER (
           PARTITION BY player_id
           ORDER BY period_end ASC, league_kind ASC, period_type ASC, period_start ASC
         ) AS rn
  FROM player_league_award
  WHERE {$where}
) AS fa
LEFT JOIN player_milestones m
  ON m.player_id = fa.player_id AND m.milestone_key = ?
WHERE fa.rn = 1
  AND m.player_id IS NULL
SQL;
        $stmt = mysqli_prepare($con, $sql);
        if ($stmt === false) {
            continue;
        }
        mysqli_stmt_bind_param($stmt, 'sis', $key, $value, $key);
        mysqli_stmt_execute($stmt);
        $inserted += mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
    }

    return $inserted;
}
