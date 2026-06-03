<?php
/**
 * P5 period aggregates — server_daily_activity, player_period_league,
 * player_matchup_summary, server_period_game_totals, server_period_matchups.
 */
declare(strict_types=1);

require_once __DIR__ . '/ops_bootstrap.php';
require_once __DIR__ . '/post_game_period_activity.php';

function k2_post_game_p5_tables_available(mysqli $con): bool
{
    foreach (
        [
            'server_daily_activity',
            'player_period_league',
            'player_matchup_summary',
            'server_period_game_totals',
            'server_period_matchups',
        ] as $table
    ) {
        if (!k2_ops_table_exists($con, $table)) {
            return false;
        }
    }

    return true;
}

function k2_post_game_update_server_daily_activity(
    mysqli $con,
    string $activityDay,
    bool $playerAFirstDay,
    bool $playerBFirstDay
): void {
    $activeDelta = ($playerAFirstDay ? 1 : 0) + ($playerBFirstDay ? 1 : 0);
    $stmt = $con->prepare(
        'INSERT INTO server_daily_activity (activity_day, rated_games, active_players) '
        . 'VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE '
        . 'rated_games = rated_games + 1, active_players = active_players + ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare server_daily_activity: ' . $con->error);
    }
    $stmt->bind_param('sii', $activityDay, $activeDelta, $activeDelta);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute server_daily_activity: ' . $stmt->error);
    }
    $stmt->close();
}

/**
 * @return array{w: int, d: int, l: int, pts: int}
 */
function k2_post_game_league_outcome_for_player(float $actualScore, bool $isPlayerA): array
{
    if ($actualScore === 0.5) {
        return ['w' => 0, 'd' => 1, 'l' => 0, 'pts' => 1];
    }
    if ($isPlayerA) {
        if ($actualScore === 1.0) {
            return ['w' => 1, 'd' => 0, 'l' => 0, 'pts' => 3];
        }

        return ['w' => 0, 'd' => 0, 'l' => 1, 'pts' => 0];
    }
    if ($actualScore === 0.0) {
        return ['w' => 1, 'd' => 0, 'l' => 0, 'pts' => 3];
    }

    return ['w' => 0, 'd' => 0, 'l' => 1, 'pts' => 0];
}

function k2_post_game_upsert_period_league(
    mysqli $con,
    string $periodType,
    string $periodStart,
    int $playerId,
    int $w,
    int $d,
    int $l,
    int $gf,
    int $ga,
    int $pts
): void {
    $stmt = $con->prepare(
        'INSERT INTO player_period_league '
        . '(period_type, period_start, player_id, played, wins, draws, losses, goals_for, goals_against, goal_difference, points) '
        . 'VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?) '
        . 'ON DUPLICATE KEY UPDATE '
        . 'played = played + 1, wins = wins + ?, draws = draws + ?, losses = losses + ?, '
        . 'goals_for = goals_for + ?, goals_against = goals_against + ?, '
        . 'goal_difference = goal_difference + ?, points = points + ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare player_period_league: ' . $con->error);
    }
    $gd = $gf - $ga;
    $stmt->bind_param(
        'ssiiiiiiiiiiiiiii',
        $periodType,
        $periodStart,
        $playerId,
        $w,
        $d,
        $l,
        $gf,
        $ga,
        $gd,
        $pts,
        $w,
        $d,
        $l,
        $gf,
        $ga,
        $gd,
        $pts
    );
    if (!$stmt->execute()) {
        throw new RuntimeException('execute player_period_league: ' . $stmt->error);
    }
    $stmt->close();
}

function k2_post_game_upsert_matchup_summary(
    mysqli $con,
    int $playerId,
    int $opponentId,
    int $w,
    int $d,
    int $l,
    int $gf,
    int $ga
): void {
    $stmt = $con->prepare(
        'INSERT INTO player_matchup_summary '
        . '(player_id, opponent_id, games, wins, draws, losses, goals_for, goals_against) '
        . 'VALUES (?, ?, 1, ?, ?, ?, ?, ?) '
        . 'ON DUPLICATE KEY UPDATE '
        . 'games = games + 1, wins = wins + ?, draws = draws + ?, losses = losses + ?, '
        . 'goals_for = goals_for + ?, goals_against = goals_against + ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare player_matchup_summary: ' . $con->error);
    }
    $stmt->bind_param(
        'iiiiiiiiiiii',
        $playerId,
        $opponentId,
        $w,
        $d,
        $l,
        $gf,
        $ga,
        $w,
        $d,
        $l,
        $gf,
        $ga
    );
    if (!$stmt->execute()) {
        throw new RuntimeException('execute player_matchup_summary: ' . $stmt->error);
    }
    $stmt->close();
}

function k2_post_game_upsert_server_period_totals(
    mysqli $con,
    string $periodType,
    string $periodStart,
    int $sumGoals,
    int $drawInc,
    int $ddInc,
    int $csInc
): void {
    $stmt = $con->prepare(
        'INSERT INTO server_period_game_totals '
        . '(period_type, period_start, rated_games, total_goals, draws, double_digit_games, clean_sheets) '
        . 'VALUES (?, ?, 1, ?, ?, ?, ?) '
        . 'ON DUPLICATE KEY UPDATE '
        . 'rated_games = rated_games + 1, total_goals = total_goals + ?, '
        . 'draws = draws + ?, double_digit_games = double_digit_games + ?, clean_sheets = clean_sheets + ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare server_period_game_totals: ' . $con->error);
    }
    $stmt->bind_param(
        'ssiiiiiiii',
        $periodType,
        $periodStart,
        $sumGoals,
        $drawInc,
        $ddInc,
        $csInc,
        $sumGoals,
        $drawInc,
        $ddInc,
        $csInc
    );
    if (!$stmt->execute()) {
        throw new RuntimeException('execute server_period_game_totals: ' . $stmt->error);
    }
    $stmt->close();
}

function k2_post_game_upsert_server_period_matchup(
    mysqli $con,
    string $periodType,
    string $periodStart,
    int $playerA,
    int $playerB
): void {
    $stmt = $con->prepare(
        'INSERT INTO server_period_matchups (period_type, period_start, player_a, player_b, games) '
        . 'VALUES (?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE games = games + 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare server_period_matchups: ' . $con->error);
    }
    $stmt->bind_param('ssii', $periodType, $periodStart, $playerA, $playerB);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute server_period_matchups: ' . $stmt->error);
    }
    $stmt->close();
}

/**
 * @param array<string, mixed> $game
 * @param array<string, mixed> $derived
 * @param array<string, string> $periodStarts
 */
function k2_post_game_update_period_aggregates_after_game(
    mysqli $con,
    array $game,
    array $derived,
    array $periodStarts,
    int $dayGamesA,
    int $dayGamesB
): void {
    if (!k2_post_game_p5_tables_available($con)) {
        return;
    }

    $idA = (int) $game['idA'];
    $idB = (int) $game['idB'];
    $goalsA = (int) $game['GoalsA'];
    $goalsB = (int) $game['GoalsB'];
    $actualScore = (float) $derived['ActualScore'];
    $sumGoals = (int) $derived['SumOfGoals'];
    $isDraw = $actualScore === 0.5 ? 1 : 0;
    $ddInc = $sumGoals >= 10 ? 1 : 0;
    $csInc = ($goalsA === 0 || $goalsB === 0) ? 1 : 0;

    k2_post_game_update_server_daily_activity(
        $con,
        $periodStarts['day'],
        $dayGamesA === 1,
        $dayGamesB === 1
    );

    $outA = k2_post_game_league_outcome_for_player($actualScore, true);
    $outB = k2_post_game_league_outcome_for_player($actualScore, false);

    $pairA = min($idA, $idB);
    $pairB = max($idA, $idB);

    foreach ($periodStarts as $periodType => $periodStart) {
        k2_post_game_upsert_period_league(
            $con,
            $periodType,
            $periodStart,
            $idA,
            $outA['w'],
            $outA['d'],
            $outA['l'],
            $goalsA,
            $goalsB,
            $outA['pts']
        );
        k2_post_game_upsert_period_league(
            $con,
            $periodType,
            $periodStart,
            $idB,
            $outB['w'],
            $outB['d'],
            $outB['l'],
            $goalsB,
            $goalsA,
            $outB['pts']
        );
        k2_post_game_upsert_server_period_totals($con, $periodType, $periodStart, $sumGoals, $isDraw, $ddInc, $csInc);
        k2_post_game_upsert_server_period_matchup($con, $periodType, $periodStart, $pairA, $pairB);
    }

    k2_post_game_upsert_matchup_summary(
        $con,
        $idA,
        $idB,
        $outA['w'],
        $outA['d'],
        $outA['l'],
        $goalsA,
        $goalsB
    );
    k2_post_game_upsert_matchup_summary(
        $con,
        $idB,
        $idA,
        $outB['w'],
        $outB['d'],
        $outB['l'],
        $goalsB,
        $goalsA
    );
}
