<?php
/**
 * Post-simul verification (local / work DB before staging sign-off).
 *
 * @see docs/coordination/ops-derived-data-registry.md § Verification
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/ops_bootstrap.php';

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

    return $checks;
}

function k2_ops_verify_scalar(mysqli $con, string $sql): int
{
    $res = $con->query($sql);
    if ($res === false) {
        return -1;
    }
    $row = $res->fetch_assoc();
    $res->free();

    if ($row === null) {
        return -1;
    }
    $val = $row['c'] ?? reset($row);

    return (int) round((float) $val);
}

function k2_ops_verify_table_count(mysqli $con, string $table): int
{
    if (!k2_ops_table_exists($con, $table)) {
        return 0;
    }

    return k2_ops_verify_scalar($con, "SELECT COUNT(*) AS c FROM `{$table}`");
}

/**
 * @return array{id: string, label: string, ok: bool, detail: string, severity: string}
 */
function k2_ops_verify_check(
    string $id,
    string $label,
    bool $ok,
    string $detail,
    string $severity = 'ok'
): array {
    return [
        'id' => $id,
        'label' => $label,
        'ok' => $ok,
        'detail' => $detail,
        'severity' => $severity,
    ];
}

/**
 * @param list<array{id: string, label: string, ok: bool, detail: string, severity: string}> $checks
 */
function k2_ops_verify_exit_code(array $checks): int
{
    foreach ($checks as $c) {
        if ($c['severity'] === 'fail' && !$c['ok']) {
            return 1;
        }
    }

    return 0;
}
