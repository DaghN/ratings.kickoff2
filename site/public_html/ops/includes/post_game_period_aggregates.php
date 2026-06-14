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

function k2_post_game_matchup_summary_has_extension(mysqli $con): bool
{
    static $cache = [];

    $key = spl_object_id($con);
    if (!array_key_exists($key, $cache)) {
        $cache[$key] = k2_ops_column_exists($con, 'player_matchup_summary', 'max_goals_for');
    }

    return $cache[$key];
}

function k2_post_game_upsert_matchup_summary_core(
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

function k2_post_game_upsert_matchup_summary_extended(
    mysqli $con,
    int $playerId,
    int $opponentId,
    int $w,
    int $d,
    int $l,
    int $gf,
    int $ga,
    int $ddSubject,
    int $ddConceded,
    int $csSubject,
    int $csConceded
): void {
    $goalSum = $gf + $ga;
    $winMargin = $w > 0 ? $gf - $ga : null;
    $lossMargin = $l > 0 ? $ga - $gf : null;
    $drawGoals = $d > 0 ? $gf : null;

    $stmt = $con->prepare(
        'INSERT INTO player_matchup_summary ('
        . 'player_id, opponent_id, games, wins, draws, losses, goals_for, goals_against, '
        . 'max_goals_for, max_goals_against, min_goals_for, min_goals_against, '
        . 'max_win_margin, max_loss_margin, max_draw_goals, max_goal_sum, min_goal_sum, '
        . 'double_digits, double_digits_conceded, clean_sheets, clean_sheets_conceded'
        . ') VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) '
        . 'ON DUPLICATE KEY UPDATE '
        . 'games = games + 1, '
        . 'wins = wins + VALUES(wins), draws = draws + VALUES(draws), losses = losses + VALUES(losses), '
        . 'goals_for = goals_for + VALUES(goals_for), goals_against = goals_against + VALUES(goals_against), '
        . 'max_goals_for = GREATEST(max_goals_for, VALUES(max_goals_for)), '
        . 'max_goals_against = GREATEST(max_goals_against, VALUES(max_goals_against)), '
        . 'min_goals_for = LEAST(min_goals_for, VALUES(min_goals_for)), '
        . 'min_goals_against = LEAST(min_goals_against, VALUES(min_goals_against)), '
        . 'max_win_margin = IF(VALUES(wins) > 0, GREATEST(COALESCE(max_win_margin, 0), COALESCE(VALUES(max_win_margin), 0)), max_win_margin), '
        . 'max_loss_margin = IF(VALUES(losses) > 0, GREATEST(COALESCE(max_loss_margin, 0), COALESCE(VALUES(max_loss_margin), 0)), max_loss_margin), '
        . 'max_draw_goals = IF(VALUES(draws) > 0, GREATEST(COALESCE(max_draw_goals, VALUES(max_draw_goals)), VALUES(max_draw_goals)), max_draw_goals), '
        . 'max_goal_sum = GREATEST(max_goal_sum, VALUES(max_goal_sum)), '
        . 'min_goal_sum = LEAST(min_goal_sum, VALUES(min_goal_sum)), '
        . 'double_digits = double_digits + VALUES(double_digits), '
        . 'double_digits_conceded = double_digits_conceded + VALUES(double_digits_conceded), '
        . 'clean_sheets = clean_sheets + VALUES(clean_sheets), '
        . 'clean_sheets_conceded = clean_sheets_conceded + VALUES(clean_sheets_conceded)'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare player_matchup_summary: ' . $con->error);
    }
    $stmt->bind_param(
        'iiiiiiiiiiiiiiiiiiii',
        $playerId,
        $opponentId,
        $w,
        $d,
        $l,
        $gf,
        $ga,
        $gf,
        $ga,
        $gf,
        $ga,
        $winMargin,
        $lossMargin,
        $drawGoals,
        $goalSum,
        $goalSum,
        $ddSubject,
        $ddConceded,
        $csSubject,
        $csConceded
    );
    if (!$stmt->execute()) {
        throw new RuntimeException('execute player_matchup_summary: ' . $stmt->error);
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
    int $ga,
    int $ddSubject = 0,
    int $ddConceded = 0,
    int $csSubject = 0,
    int $csConceded = 0
): void {
    if (!k2_post_game_matchup_summary_has_extension($con)) {
        k2_post_game_upsert_matchup_summary_core($con, $playerId, $opponentId, $w, $d, $l, $gf, $ga);

        return;
    }

    k2_post_game_upsert_matchup_summary_extended(
        $con,
        $playerId,
        $opponentId,
        $w,
        $d,
        $l,
        $gf,
        $ga,
        $ddSubject,
        $ddConceded,
        $csSubject,
        $csConceded
    );
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

    $ddA = (int) $derived['DDPlayerA'];
    $ddB = (int) $derived['DDPlayerB'];
    $csA = (int) $derived['CSPlayerA'];
    $csB = (int) $derived['CSPlayerB'];

    k2_post_game_upsert_matchup_summary(
        $con,
        $idA,
        $idB,
        $outA['w'],
        $outA['d'],
        $outA['l'],
        $goalsA,
        $goalsB,
        $ddA,
        $ddB,
        $csA,
        $csB
    );
    k2_post_game_upsert_matchup_summary(
        $con,
        $idB,
        $idA,
        $outB['w'],
        $outB['d'],
        $outB['l'],
        $goalsB,
        $goalsA,
        $ddB,
        $ddA,
        $csB,
        $csA
    );
}
