<?php
/**
 * Orthogonal Activity wing parity (participation + play streaks + HoF GST).
 *
 * Parity SQL pack — compare stored truth to independent oracles on work:
 *
 *   -- Global participation sums vs period row counts
 *   SELECT SUM(active_days) FROM player_activity_participation;
 *   SELECT COUNT(*) FROM player_period_games WHERE period_type = 'day';
 *   -- repeat week / month / year
 *
 *   -- Per-player spot
 *   SELECT active_days FROM player_activity_participation WHERE player_id = ?;
 *   SELECT COUNT(*) FROM player_period_games WHERE player_id = ? AND period_type = 'day';
 *
 * Streak oracle: k2_play_streak_oracle_mismatches() walks sorted period_start lists.
 * HoF: MAX(best_streak) per streak_type vs generalstatstable Longest*PlayStreak*.
 *
 * @see docs/activity-wing-stored-truth-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/ops_bootstrap.php';
require_once __DIR__ . '/../includes/ops_verify_helpers.php';

/**
 * @return list<array{id: string, label: string, ok: bool, detail: string, severity: string}>
 */
function k2_ops_verify_activity_wing_parity(mysqli $con): array
{
    if (!k2_ops_table_exists($con, 'player_activity_participation')) {
        return [];
    }

    require_once dirname(__DIR__, 2) . '/includes/player_play_streaks.php';

    $checks = [];
    $con->query("SET time_zone = '+00:00'");

    $parityPairs = [
        ['active_days', 'day'],
        ['active_weeks', 'week'],
        ['active_months', 'month'],
        ['active_years', 'year'],
    ];

    foreach ($parityPairs as [$column, $periodType]) {
        $participation = k2_ops_verify_scalar(
            $con,
            "SELECT COALESCE(SUM(`{$column}`), 0) AS c FROM player_activity_participation"
        );
        $periodRows = k2_ops_verify_scalar(
            $con,
            "SELECT COUNT(*) AS c FROM player_period_games WHERE period_type = '{$periodType}'"
        );
        $ok = $participation === $periodRows;
        $checks[] = k2_ops_verify_check(
            "activity_participation_sum_{$periodType}",
            "Participation SUM({$column}) = period rows ({$periodType})",
            $ok,
            "participation={$participation} period_rows={$periodRows}",
            $ok ? 'ok' : 'fail'
        );
    }

    $perPlayerMismatch = k2_ops_verify_scalar(
        $con,
        <<<'SQL'
SELECT COUNT(*) AS c FROM player_activity_participation p WHERE
  p.active_days <> (SELECT COUNT(*) FROM player_period_games g WHERE g.player_id = p.player_id AND g.period_type = 'day')
  OR p.active_weeks <> (SELECT COUNT(*) FROM player_period_games g WHERE g.player_id = p.player_id AND g.period_type = 'week')
  OR p.active_months <> (SELECT COUNT(*) FROM player_period_games g WHERE g.player_id = p.player_id AND g.period_type = 'month')
  OR p.active_years <> (SELECT COUNT(*) FROM player_period_games g WHERE g.player_id = p.player_id AND g.period_type = 'year')
SQL
    );
    $checks[] = k2_ops_verify_check(
        'activity_participation_per_player',
        'Participation per-player counts match period rows',
        $perPlayerMismatch === 0,
        "mismatch_players={$perPlayerMismatch}",
        $perPlayerMismatch === 0 ? 'ok' : 'fail'
    );

    if (!k2_ops_table_exists($con, 'player_play_streaks')) {
        return $checks;
    }

    $streakMismatches = k2_play_streak_oracle_mismatches($con);
    $streakCount = count($streakMismatches);
    $detail = "oracle_mismatches={$streakCount}";
    if ($streakCount > 0) {
        $detail .= ' sample=' . implode('; ', array_slice($streakMismatches, 0, 3));
    }
    $checks[] = k2_ops_verify_check(
        'activity_play_streak_oracle',
        'Play streak best_streak matches period-list oracle',
        $streakCount === 0,
        $detail,
        $streakCount === 0 ? 'ok' : 'fail'
    );

    $hof = $con->query(
        'SELECT LongestDailyPlayStreak, LongestWeeklyPlayStreak, LongestMonthlyPlayStreak, LongestYearlyPlayStreak '
        . 'FROM generalstatstable WHERE id = 1'
    );
    $hofRow = $hof ? $hof->fetch_assoc() : null;
    if ($hof) {
        $hof->free();
    }

    $hofMap = [
        'day' => 'LongestDailyPlayStreak',
        'week' => 'LongestWeeklyPlayStreak',
        'month' => 'LongestMonthlyPlayStreak',
        'year' => 'LongestYearlyPlayStreak',
    ];
    foreach ($hofMap as $type => $col) {
        $tableMax = k2_ops_verify_scalar(
            $con,
            "SELECT COALESCE(MAX(best_streak), 0) AS c FROM player_play_streaks WHERE streak_type = '{$type}'"
        );
        $hofVal = (int) ($hofRow[$col] ?? 0);
        $ok = $hofVal === $tableMax;
        $checks[] = k2_ops_verify_check(
            "activity_hof_play_streak_{$type}",
            "HoF {$type} play streak = table MAX(best_streak)",
            $ok,
            "hof={$hofVal} table_max={$tableMax}",
            $ok ? 'ok' : 'fail'
        );
    }

    return $checks;
}
