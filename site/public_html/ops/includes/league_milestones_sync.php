<?php
declare(strict_types=1);

/**
 * Incremental league-event milestone keys (first matching award per player).
 * Win-count keys (league_wins_*) use k2_league_sync_win_milestones() in league_standings.php.
 *
 * @see ops day tick / finalize (league wave); historical batch: docs/archive/batch-rebuild-sql-2026-05/
 * @see docs/coordination/ops-orchestration-adr.md
 */

require_once dirname(__DIR__, 2) . '/includes/league_standings.php';
require_once dirname(__DIR__, 2) . '/includes/milestone_unlock.php';

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
 * @return list<array{
 *   player_id: int,
 *   period_end: string,
 *   league_kind: string,
 *   period_type: string,
 *   period_start: string
 * }>
 */
function k2_league_event_milestone_candidates(mysqli $con, string $milestoneKey, string $where): array
{
    $sql = "
SELECT
  fa.player_id,
  fa.period_end,
  fa.league_kind,
  fa.period_type,
  fa.period_start
FROM (
  SELECT
    player_id,
    period_end,
    league_kind,
    period_type,
    period_start,
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
  AND m.player_id IS NULL";

    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 's', $milestoneKey);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);

        return [];
    }
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    if ($res !== false) {
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = [
                'player_id' => (int) $row['player_id'],
                'period_end' => (string) $row['period_end'],
                'league_kind' => (string) $row['league_kind'],
                'period_type' => (string) $row['period_type'],
                'period_start' => (string) $row['period_start'],
            ];
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * Idempotent insert for first award rows not yet in player_milestones.
 *
 * @return int rows inserted
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
        $candidates = k2_league_event_milestone_candidates($con, $key, $spec['where']);
        foreach ($candidates as $row) {
            if (k2_milestone_unlock_insert($con, [
                'player_id' => $row['player_id'],
                'milestone_key' => $key,
                'achieved_at' => $row['period_end'],
                'value' => $value,
                'source_kind' => 'league',
                'source_game_id' => null,
                'source_league_kind' => $row['league_kind'],
                'source_period_type' => $row['period_type'],
                'source_period_start' => $row['period_start'],
            ])) {
                ++$inserted;
            }
        }
    }

    return $inserted;
}
