<?php
/**
 * Post-simul verification (local / work DB before staging sign-off).
 *
 * @see docs/coordination/ops-derived-data-registry.md § Verification
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/ops_bootstrap.php';
require_once __DIR__ . '/../includes/ops_verify_helpers.php';
require_once __DIR__ . '/verify_activity_wing_parity.php';

/**
 * @return list<array{id: string, label: string, ok: bool, detail: string, severity: string}>
 */
function k2_ops_verify_sim_complete(mysqli $con): array
{
    $checks = [];

    $ratedGames = k2_ops_verify_scalar($con, 'SELECT COUNT(*) AS c FROM ratedresults');
    $processed = k2_ops_verify_scalar(
        $con,
        'SELECT COUNT(*) AS c FROM ratedresults WHERE NewRatingA IS NOT NULL'
    );
    $unprocessed = $ratedGames - $processed;

    $checks[] = k2_ops_verify_check(
        'rated_games',
        'Rated games (ground)',
        true,
        "total={$ratedGames} processed={$processed} unprocessed={$unprocessed}",
        $unprocessed > 0 ? 'warn' : 'ok'
    );

    if ($processed > 0) {
        $dayGamesHalf = k2_ops_verify_scalar(
            $con,
            "SELECT COALESCE(SUM(games), 0) / 2 AS c FROM player_period_games WHERE period_type = 'day'"
        );
        $leagueHalf = k2_ops_verify_scalar(
            $con,
            "SELECT COALESCE(SUM(played), 0) / 2 AS c FROM player_period_league WHERE period_type = 'day'"
        );
        $dailyActivity = k2_ops_verify_scalar(
            $con,
            'SELECT COALESCE(SUM(rated_games), 0) AS c FROM server_daily_activity'
        );
        $periodTotals = k2_ops_verify_scalar(
            $con,
            "SELECT COALESCE(SUM(rated_games), 0) AS c FROM server_period_game_totals WHERE period_type = 'day'"
        );
        $matchups = k2_ops_verify_scalar(
            $con,
            "SELECT COALESCE(SUM(games), 0) AS c FROM server_period_matchups WHERE period_type = 'day'"
        );

        $sixOk = ($dayGamesHalf === $processed)
            && ($leagueHalf === $processed)
            && ($dailyActivity === $processed)
            && ($periodTotals === $processed)
            && ($matchups === $processed);

        $checks[] = k2_ops_verify_check(
            'six_value',
            'Contract six-value totals (= processed games)',
            $sixOk,
            "processed={$processed} period_games/2={$dayGamesHalf} period_league/2={$leagueHalf} "
            . "daily_activity={$dailyActivity} period_totals={$periodTotals} matchups={$matchups}",
            $sixOk ? 'ok' : 'fail'
        );
    } else {
        $checks[] = k2_ops_verify_check(
            'six_value',
            'Contract six-value totals',
            false,
            'no processed games — run ops sim first',
            'fail'
        );
    }

    $awards = k2_ops_verify_table_count($con, 'player_league_award');
    $periodsFinalized = k2_ops_verify_scalar(
        $con,
        'SELECT COUNT(*) AS c FROM league_period WHERE finalized_at IS NOT NULL'
    );
    $checks[] = k2_ops_verify_check(
        'league_awards',
        'League awards (honours source)',
        $awards > 0,
        "player_league_award={$awards} league_period_finalized={$periodsFinalized}",
        $awards > 0 ? 'ok' : 'fail'
    );

    $leagueMilestoneKeys = k2_ops_verify_scalar(
        $con,
        "SELECT COUNT(DISTINCT milestone_key) AS c FROM player_milestones "
        . "WHERE milestone_key LIKE 'league_%' OR milestone_key IN ('moment_of_glory','activity_king')"
    );
    $checks[] = k2_ops_verify_check(
        'league_milestones',
        'League-related milestone keys present',
        $leagueMilestoneKeys >= 5,
        "distinct_keys={$leagueMilestoneKeys}",
        $leagueMilestoneKeys >= 5 ? 'ok' : 'warn'
    );

    $perfect = k2_ops_verify_scalar(
        $con,
        "SELECT COUNT(*) AS c FROM player_milestones WHERE milestone_key = 'perfect_day'"
    );
    $nightmare = k2_ops_verify_scalar(
        $con,
        "SELECT COUNT(*) AS c FROM player_milestones WHERE milestone_key = 'nightmare_day'"
    );
    $checks[] = k2_ops_verify_check(
        'day_close',
        'Day-close milestones (informational)',
        true,
        "perfect_day={$perfect} nightmare_day={$nightmare}",
        'ok'
    );

    $lobby = k2_ops_verify_scalar(
        $con,
        "SELECT COUNT(*) AS c FROM player_milestones WHERE milestone_key = 'entered_arena'"
    );
    $joinEligible = k2_ops_verify_scalar(
        $con,
        "SELECT COUNT(*) AS c FROM playertable WHERE JoinDate IS NOT NULL AND JoinDate > '1970-01-01'"
    );
    $lobbyOk = $lobby > 0 && ($joinEligible === 0 || $lobby >= (int) floor($joinEligible * 0.95));
    $checks[] = k2_ops_verify_check(
        'lobby_seed',
        'entered_arena from prepare (not simul)',
        $lobbyOk,
        "entered_arena={$lobby} playertable_join_dates={$joinEligible}",
        $lobbyOk ? 'ok' : 'warn'
    );

    $gameMilestones = k2_ops_verify_scalar(
        $con,
        "SELECT COUNT(*) AS c FROM player_milestones WHERE source_kind = 'game' OR source_game_id IS NOT NULL"
    );
    $checks[] = k2_ops_verify_check(
        'game_milestones',
        'Game-sourced milestone rows',
        $gameMilestones > 0,
        "rows≈{$gameMilestones}",
        $gameMilestones > 0 ? 'ok' : 'warn'
    );

    if (k2_ops_table_exists($con, 'player_milestone_totals')) {
        $mismatch = k2_ops_verify_scalar(
            $con,
            <<<'SQL'
SELECT COUNT(*) AS c FROM (
  SELECT pm.player_id
  FROM player_milestones pm
  INNER JOIN milestone_definitions md ON md.milestone_key = pm.milestone_key
  GROUP BY pm.player_id
  HAVING COUNT(*) <> COALESCE((
    SELECT t.total FROM player_milestone_totals t WHERE t.player_id = pm.player_id
  ), 0)
     OR COALESCE(SUM(md.tier_band = 'aspirational'), 0) <> COALESCE((
    SELECT t.aspirational FROM player_milestone_totals t WHERE t.player_id = pm.player_id
  ), 0)
     OR COALESCE(SUM(md.tier_band = 'veteran'), 0) <> COALESCE((
    SELECT t.dedicated FROM player_milestone_totals t WHERE t.player_id = pm.player_id
  ), 0)
     OR COALESCE(SUM(md.tier_band = 'key'), 0) <> COALESCE((
    SELECT t.accomplished FROM player_milestone_totals t WHERE t.player_id = pm.player_id
  ), 0)
     OR COALESCE(SUM(md.tier_band = 'legendary'), 0) <> COALESCE((
    SELECT t.legendary FROM player_milestone_totals t WHERE t.player_id = pm.player_id
  ), 0)
) AS x
SQL
        );
        $totalsPlayers = k2_ops_verify_scalar(
            $con,
            'SELECT COUNT(*) AS c FROM player_milestone_totals'
        );
        $checks[] = k2_ops_verify_check(
            'milestone_totals_parity',
            'player_milestone_totals matches unlock rows',
            $mismatch === 0,
            "mismatch_players={$mismatch} totals_rows={$totalsPlayers}",
            $mismatch === 0 ? 'ok' : 'fail'
        );
    }

    if (k2_ops_column_exists($con, 'milestone_definitions', 'holder_count')) {
        $holderMismatch = k2_ops_verify_scalar(
            $con,
            <<<'SQL'
SELECT COUNT(*) AS c FROM (
  SELECT d.milestone_key
  FROM milestone_definitions d
  LEFT JOIN (
    SELECT pm.milestone_key, COUNT(*) AS holders
    FROM player_milestones pm
    GROUP BY pm.milestone_key
  ) h ON h.milestone_key = d.milestone_key
  WHERE d.holder_count <> COALESCE(h.holders, 0)
) AS x
SQL
        );
        $checks[] = k2_ops_verify_check(
            'milestone_holder_count_parity',
            'milestone_definitions.holder_count matches unlock rows',
            $holderMismatch === 0,
            "mismatch_keys={$holderMismatch}",
            $holderMismatch === 0 ? 'ok' : 'fail'
        );
        if ($holderMismatch > 0) {
            $sampleRes = $con->query(
                <<<'SQL'
SELECT d.milestone_key, d.holder_count, COALESCE(h.holders, 0) AS actual_holders
FROM milestone_definitions d
LEFT JOIN (
  SELECT pm.milestone_key, COUNT(*) AS holders
  FROM player_milestones pm
  GROUP BY pm.milestone_key
) h ON h.milestone_key = d.milestone_key
WHERE d.holder_count <> COALESCE(h.holders, 0)
ORDER BY d.milestone_key
LIMIT 8
SQL
            );
            if ($sampleRes !== false) {
                while ($row = $sampleRes->fetch_assoc()) {
                    k2_ops_log(
                        '  holder_count drift key=' . $row['milestone_key']
                        . ' stored=' . $row['holder_count']
                        . ' actual=' . $row['actual_holders']
                    );
                }
                $sampleRes->free();
            }
        }
    }

    $checks = array_merge($checks, k2_ops_verify_activity_wing_parity($con));

    return $checks;
}
