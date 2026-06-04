<?php
declare(strict_types=1);

/**
 * player_milestones incremental unlocks (P6) - game-triggered keys after P5.
 */

require_once __DIR__ . '/post_game_constants.php';
require_once __DIR__ . '/ops_bootstrap.php';

function k2_post_game_milestones_table_available(mysqli $con): bool
{
    return k2_ops_table_exists($con, 'player_milestones');
}

function k2_post_game_milestone_player_has(
    mysqli $con,
    int $playerId,
    string $key
): bool {
    if (!k2_post_game_milestones_table_available($con)) {
        return false;
    }
    $stmt = $con->prepare(
        'SELECT 1 FROM player_milestones WHERE player_id = ? AND milestone_key = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare milestone has: ' . $con->error);
    }
    $stmt->bind_param('is', $playerId, $key);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute milestone has: ' . $stmt->error);
    }
    $stmt->store_result();
    $has = $stmt->num_rows > 0;
    $stmt->close();
    return $has;
}

/**
 * True when a counter crosses a threshold on this game (not a later replay).
 */
function k2_post_game_milestone_crossed(int $before, int $after, int $threshold): bool
{
    return $before < $threshold && $after >= $threshold;
}

function k2_post_game_milestone_try_insert_game(
    mysqli $con,
    int $playerId,
    string $key,
    string $achievedAt,
    int $value,
    int $gameId
): void {
    $stmt = $con->prepare(
        'INSERT INTO player_milestones '
        . '(player_id, milestone_key, achieved_at, value, source_kind, source_game_id, '
        . 'source_league_kind, source_period_type, source_period_start) '
        . 'SELECT ?, ?, ?, ?, \'game\', ?, NULL, NULL, NULL FROM DUAL '
        . 'WHERE NOT EXISTS ('
        . 'SELECT 1 FROM player_milestones WHERE player_id = ? AND milestone_key = ? LIMIT 1'
        . ')'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare milestone insert: ' . $con->error);
    }
    $stmt->bind_param('issiiis', $playerId, $key, $achievedAt, $value, $gameId, $playerId, $key);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute milestone insert: ' . $stmt->error);
    }
    $stmt->close();
}

/**
 * @param array<string, mixed> $st player state after match
 */
function k2_post_game_milestones_nth_games(
    mysqli $con,
    int $playerId,
    array $st,
    string $gameDate,
    int $gameId
): void {
    $games = (int) $st['games'];
    $map = [
        1 => ['debut', 1],
        10 => ['persistence', 10],
        20 => ['established_20', 20],
        50 => ['half_century_50', 50],
        100 => ['centurion_100', 100],
        250 => ['marathoner_250', 250],
        500 => ['club_500', 500],
        1000 => ['millennium_merchant_1000', 1000],
        10000 => ['club_10000', 10000],
    ];
    if (!isset($map[$games])) {
        return;
    }
    [$key, $val] = $map[$games];
    k2_post_game_milestone_try_insert_game($con, $playerId, $key, $gameDate, $val, $gameId);
}

function k2_post_game_milestones_dd_merchant(
    mysqli $con,
    int $playerId,
    int $goalsFor,
    string $gameDate,
    int $gameId
): void {
    if ($goalsFor >= 10) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'dd_merchant_10', $gameDate, 10, $gameId);
    }
}

function k2_post_game_milestones_rating_clubs(
    mysqli $con,
    int $playerId,
    float $preGameRating,
    float $newRating,
    string $gameDate,
    int $gameId
): void {
    foreach ([1700, 1800, 2000, 2300] as $thresh) {
        if ($preGameRating < $thresh && $newRating >= $thresh) {
            k2_post_game_milestone_try_insert_game(
                $con,
                $playerId,
                'club_' . $thresh,
                $gameDate,
                $thresh,
                $gameId
            );
        }
    }
}

/**
 * @return array{w: float, gf: int, ga: int, r_pre: float, r_opp: float}
 */
function k2_post_game_milestone_side_stats(
    int $playerId,
    int $idA,
    int $idB,
    int $goalsA,
    int $goalsB,
    float $actualScore,
    float $ratingA,
    float $ratingB
): array {
    if ($playerId === $idA) {
        $sc = $actualScore;
        $gf = $goalsA;
        $ga = $goalsB;
        $rPre = $ratingA;
        $rOpp = $ratingB;
    } else {
        $sc = $actualScore === 0.5 ? 0.5 : 1.0 - $actualScore;
        $gf = $goalsB;
        $ga = $goalsA;
        $rPre = $ratingB;
        $rOpp = $ratingA;
    }

    return ['w' => $sc, 'gf' => $gf, 'ga' => $ga, 'r_pre' => $rPre, 'r_opp' => $rOpp];
}

function k2_post_game_milestones_exists_feats(
    mysqli $con,
    int $playerId,
    array $side,
    string $gameDate,
    int $gameId
): void {
    $sc = (float) $side['w'];
    $gf = (int) $side['gf'];
    $ga = (int) $side['ga'];
    $rPre = (float) $side['r_pre'];
    $rOpp = (float) $side['r_opp'];
    $sum = $gf + $ga;
    $checks = [
        'brace' => $gf >= 2 ? 2 : null,
        'hat_trick' => $gf >= 3 ? 3 : null,
        'five_goal_frenzy' => $gf >= 5 ? 5 : null,
        'eight_goal_storm' => $gf >= 8 ? 8 : null,
        'dozen_dash' => $gf >= 12 ? 12 : null,
        'filthy_fifteen' => $gf >= 15 ? 15 : null,
        'victim_of_commerce' => $ga >= 10 ? 10 : null,
        'minimalist' => ($sc === 1.0 && $gf === 1 && $ga === 0) ? 1 : null,
        'perfect_storm' => ($sc === 1.0 && $gf === 10 && $ga === 0) ? 10 : null,
        'battle_hardened' => ($sc === 0.5 && $sum >= 10 && $gf === $ga) ? 10 : null,
        'survivor' => ($sc === 1.0 && $ga >= 7) ? 7 : null,
        'goal_fest_draw' => ($sc === 0.5 && $sum >= 14) ? 14 : null,
        'comfortable' => ($sc === 1.0 && ($gf - $ga) >= 5) ? 5 : null,
        'ruthless' => ($sc === 1.0 && ($gf - $ga) >= 10) ? 10 : null,
        'hard_lesson' => ($sc === 0.0 && ($ga - $gf) >= 10) ? 10 : null,
        'twenty_goal_chaos' => $sum >= 20 ? 20 : null,
        'massive_upset' => ($sc === 1.0 && ($rOpp - $rPre) >= 500) ? 500 : null,
        'merchant_denied' => ($sc === 0.0 && $gf === 9 && $ga === 10) ? 9 : null,
        'merchant_trade_fair' => ($sc === 0.5 && $gf === 10 && $ga === 10) ? 10 : null,
        'leaky_merchant' => ($sc === 1.0 && $gf >= 10 && $ga === 9) ? 10 : null,
    ];
    foreach ($checks as $key => $val) {
        if ($val === null) {
            continue;
        }
        k2_post_game_milestone_try_insert_game($con, $playerId, $key, $gameDate, $val, $gameId);
    }
}

/**
 * @param array<string, mixed> $st
 */
function k2_post_game_milestones_streak_keys(
    mysqli $con,
    int $playerId,
    array $st,
    array $side,
    string $gameDate,
    int $gameId
): void {
    $ws = (int) $st['winning_streak'];
    $ls = (int) $st['losing_streak'];
    $ns = (int) $st['non_win_streak'];
    $ds = (int) $st['drawing_streak'];
    $wins = (int) $st['wins'];
    $sc = (float) $side['w'];
    $won = $sc === 1.0;
    $drew = $sc === 0.5;
    $lost = $sc === 0.0;

    if ($won && $ws === 3) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'win_hat_trick', $gameDate, 3, $gameId);
    }
    if ($won && $ws === 10) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'ten_wins_straight', $gameDate, 10, $gameId);
    }
    if ($won && $ws === 15) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'rampage', $gameDate, 15, $gameId);
    }
    if ($won && $ws === 30) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'win_streak_30', $gameDate, 30, $gameId);
    }
    if ($lost && $ls === 5) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'cold_streak', $gameDate, 5, $gameId);
    }
    if (!$won && $ns === 10) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'win_drought', $gameDate, 10, $gameId);
    }
    if ($drew && $ds === 3) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'peace_streak', $gameDate, 3, $gameId);
    }
    if ($drew && $ds === 5) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'united_nations', $gameDate, 5, $gameId);
    }
    if ($wins === 10) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'ten_wins', $gameDate, 10, $gameId);
    }

    if ((int) $st['score_streak'] === 10) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'on_the_scoresheet', $gameDate, 10, $gameId);
    }
    if ((int) $st['merchant_streak'] === 5) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'merchant_streak', $gameDate, 5, $gameId);
    }
    if ((int) $st['exact_ten_goal_streak'] === 3) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'minimalist_merchant', $gameDate, 3, $gameId);
    }
    if ($won && (int) $st['win_margin_one_streak'] === 5) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'knife_edge', $gameDate, 5, $gameId);
    }
    if ($lost && (int) $st['loss_margin_one_streak'] === 5) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'unlucky', $gameDate, 5, $gameId);
    }
}

/**
 * @param array<string, mixed> $st
 */
function k2_post_game_milestones_tail_keys(
    mysqli $con,
    int $playerId,
    int $opponentId,
    array $st,
    array $side,
    string $gameDate,
    int $gameId
): void {
    $sc = (float) $side['w'];
    $gf = (int) $side['gf'];
    $ga = (int) $side['ga'];
    $won = $sc === 1.0;
    $drew = $sc === 0.5;
    $lost = $sc === 0.0;

    if ($won && (int) $st['wins'] === 1) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'first_victory', $gameDate, 1, $gameId);
    }
    if ($drew && (int) $st['draws'] === 1) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'first_handshake', $gameDate, 1, $gameId);
    }
    if ($lost && (int) $st['losses'] === 1) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'welcome_to_the_ladder', $gameDate, 1, $gameId);
    }
    if ($gf >= 1 && (int) $st['goals_for'] - $gf === 0) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'first_goal', $gameDate, 1, $gameId);
    }
    if ($ga === 0 && (int) $st['clean_sheets'] === 1) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'first_shutout', $gameDate, 1, $gameId);
    }

    $tailN = [
        'century_of_wins' => [(int) $st['wins'], 100, 100],
        'ten_draws' => [(int) $st['draws'], 10, 10],
        'battle_scarred' => [(int) $st['losses'], 100, 100],
        'fortress_builder' => [(int) $st['clean_sheets'], 25, 25],
        'clean_sheet_artist' => [(int) $st['clean_sheets'], 50, 50],
        'ten_opponents' => [(int) $st['different_opponents'], 10, 10],
        'wide_net' => [(int) $st['different_opponents'], 25, 25],
        'fifty_faces' => [(int) $st['different_opponents'], 50, 50],
        'century_of_rivals' => [(int) $st['different_opponents'], 100, 100],
        'five_victims' => [(int) $st['different_victims'], 5, 5],
        'twenty_five_victims' => [(int) $st['different_victims'], 25, 25],
        'ten_culprits' => [(int) $st['different_culprits'], 10, 10],
    ];
    foreach ($tailN as $key => [$cur, $thresh, $val]) {
        if ($cur === $thresh) {
            k2_post_game_milestone_try_insert_game($con, $playerId, $key, $gameDate, $val, $gameId);
        }
    }

    $prevGf = (int) $st['goals_for'] - $gf;
    $newGf = (int) $st['goals_for'];
    if ($prevGf < 100 && $newGf >= 100) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'hundred_goals', $gameDate, 100, $gameId);
    }
    if ($prevGf < 1000 && $newGf >= 1000) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'thousand_goal_club', $gameDate, 1000, $gameId);
    }

    $flags = $st['game_flags'] ?? [];
    if ($gf >= 10 && !empty($flags['new_dd_victim'])) {
        $ddN = (int) ($st['double_digits_victims'] ?? 0);
        if ($ddN === 5) {
            k2_post_game_milestone_try_insert_game($con, $playerId, 'diversity_merchant', $gameDate, 5, $gameId);
        }
        if ($ddN === 10) {
            k2_post_game_milestone_try_insert_game($con, $playerId, 'travelling_salesman', $gameDate, 10, $gameId);
        }
    }
    if ($ga === 0 && !empty($flags['new_cs_victim'])) {
        $csN = (int) ($st['clean_sheets_victims'] ?? 0);
        if ($csN === 10) {
            k2_post_game_milestone_try_insert_game($con, $playerId, 'clean_sheet_spread', $gameDate, 10, $gameId);
        }
    }

    $pair = k2_post_game_milestone_matchup_counts($con, $playerId, $opponentId);
    if ($pair['games'] === 10) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'ten_match_saga', $gameDate, 10, $gameId);
    }
    if ($pair['games'] === 50) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'lifetime_rivalry', $gameDate, 50, $gameId);
    }
    if ($pair['wins'] === 10) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'regular_customer', $gameDate, 10, $gameId);
    }
    if ($pair['wins'] === 20) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'bogeyman', $gameDate, 20, $gameId);
    }
}

/**
 * @return array{games: int, wins: int}
 */
function k2_post_game_milestone_matchup_counts(mysqli $con, int $playerId, int $opponentId): array
{
    if (!k2_ops_table_exists($con, 'player_matchup_summary')) {
        return ['games' => 0, 'wins' => 0];
    }
    $stmt = $con->prepare(
        'SELECT games, wins FROM player_matchup_summary '
        . 'WHERE player_id = ? AND opponent_id = ? LIMIT 1'
    );
    if ($stmt === false) {
        return ['games' => 0, 'wins' => 0];
    }
    $stmt->bind_param('ii', $playerId, $opponentId);
    if (!$stmt->execute()) {
        $stmt->close();

        return ['games' => 0, 'wins' => 0];
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : false;
    $stmt->close();
    if ($row === false || $row === null) {
        return ['games' => 0, 'wins' => 0];
    }

    return ['games' => (int) $row['games'], 'wins' => (int) $row['wins']];
}

function k2_post_game_milestones_year_in_heaven(
    mysqli $con,
    int $playerId,
    int $weekGames,
    string $weekStart,
    int $gameId,
    string $gameDate
): void {
    if ($weekGames !== 1 || $weekStart === '') {
        return;
    }
    require_once dirname(__DIR__, 2) . '/includes/player_milestone_year_in_heaven.php';
    k2_milestone_maybe_unlock_year_in_heaven($con, $playerId, $weekStart, $gameId, $gameDate);
}

function k2_post_game_milestones_period_burst(
    mysqli $con,
    int $playerId,
    int $dayGames,
    int $monthGames,
    string $gameDate,
    int $gameId
): void {
    $dayMap = [
        5 => 'hot_day',
        10 => 'marathon_day',
        20 => 'absurd_day',
        30 => 'ultra_day_30',
    ];
    foreach ($dayMap as $n => $key) {
        if ($dayGames === $n) {
            k2_post_game_milestone_try_insert_game($con, $playerId, $key, $gameDate, $n, $gameId);
        }
    }
    if ($monthGames === 50) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'grind_month', $gameDate, 50, $gameId);
    }
}

function k2_post_game_milestones_monday_week_key(DateTimeImmutable $dt): string
{
    $monday = $dt->modify('-' . ((int) $dt->format('N') - 1) . ' days');

    return $monday->format('Y-m-d');
}

/**
 * @return list<string> period_start dates (Y-m-d) with games >= 1, ascending
 */
function k2_post_game_milestones_period_starts_with_games(
    mysqli $con,
    int $playerId,
    string $periodType,
    string $rangeStart,
    string $rangeEnd
): array {
    $stmt = $con->prepare(
        'SELECT period_start FROM player_period_games '
        . 'WHERE player_id = ? AND period_type = ? '
        . 'AND period_start >= ? AND period_start <= ? AND games >= 1 '
        . 'ORDER BY period_start ASC'
    );
    if ($stmt === false) {
        return [];
    }
    $stmt->bind_param('isss', $playerId, $periodType, $rangeStart, $rangeEnd);
    if (!$stmt->execute()) {
        $stmt->close();

        return [];
    }
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = (string) $row['period_start'];
    }
    mysqli_free_result($res);
    $stmt->close();

    return $out;
}

/**
 * @param list<string> $weekStarts Monday period_start values, ascending
 */
function k2_post_game_milestones_weekly_regular_qualifies(array $weekStarts, string $currentWeekStart): bool
{
    $n = count($weekStarts);
    if ($n < 13) {
        return false;
    }
    for ($i = 0; $i <= $n - 13; $i++) {
        if ($weekStarts[$i + 12] !== $currentWeekStart) {
            continue;
        }
        $ok = true;
        for ($j = 1; $j < 13; $j++) {
            $d0 = new DateTimeImmutable($weekStarts[$i + $j - 1], new DateTimeZone('UTC'));
            $d1 = new DateTimeImmutable($weekStarts[$i + $j], new DateTimeZone('UTC'));
            if ($d0->diff($d1)->days > 10) {
                $ok = false;
                break;
            }
        }
        if ($ok) {
            return true;
        }
    }

    return false;
}

function k2_post_game_milestones_maybe_weekly_regular(
    mysqli $con,
    int $playerId,
    DateTimeImmutable $dt,
    string $gameDate,
    int $gameId
): void {
    if (k2_post_game_milestone_player_has($con, $playerId, 'weekly_regular')) {
        return;
    }
    if (!k2_post_game_milestones_period_games_table_available($con)) {
        return;
    }
    $currentWeekStart = k2_post_game_milestones_monday_week_key($dt);
    $windowStart = (new DateTimeImmutable($currentWeekStart, new DateTimeZone('UTC')))
        ->modify('-12 weeks')
        ->format('Y-m-d');
    $weekStarts = k2_post_game_milestones_period_starts_with_games(
        $con,
        $playerId,
        'week',
        $windowStart,
        $currentWeekStart
    );
    if (!k2_post_game_milestones_weekly_regular_qualifies($weekStarts, $currentWeekStart)) {
        return;
    }
    k2_post_game_milestone_try_insert_game($con, $playerId, 'weekly_regular', $gameDate, 13, $gameId);
}

function k2_post_game_milestones_maybe_year_round(
    mysqli $con,
    int $playerId,
    DateTimeImmutable $dt,
    string $gameDate,
    int $gameId
): void {
    if (k2_post_game_milestone_player_has($con, $playerId, 'year_round')) {
        return;
    }
    if (!k2_post_game_milestones_period_games_table_available($con)) {
        return;
    }
    $monthStart = $dt->format('Y-m-01');
    $windowStart = (new DateTimeImmutable($monthStart, new DateTimeZone('UTC')))
        ->modify('-11 months')
        ->format('Y-m-01');
    $monthStarts = k2_post_game_milestones_period_starts_with_games(
        $con,
        $playerId,
        'month',
        $windowStart,
        $monthStart
    );
    $activeMonths = [];
    foreach ($monthStarts as $ps) {
        $activeMonths[substr($ps, 0, 7)] = true;
    }
    $cursor = new DateTimeImmutable($windowStart, new DateTimeZone('UTC'));
    for ($j = 0; $j < 12; $j++) {
        $key = $cursor->format('Y-m');
        if (!isset($activeMonths[$key])) {
            return;
        }
        $cursor = $cursor->modify('+1 month');
    }
    k2_post_game_milestone_try_insert_game($con, $playerId, 'year_round', $gameDate, 12, $gameId);
}

/**
 * @param array<string, mixed> $st player state after this game
 */
function k2_post_game_milestones_maybe_rare_blank(
    mysqli $con,
    int $playerId,
    array $st,
    int $goalsFor,
    string $gameDate,
    int $gameId
): void {
    if ((int) $st['games'] >= 51 && $goalsFor === 0) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'rare_blank', $gameDate, 0, $gameId);
    }
}

/**
 * Debut game awards for the opponent (playertable NumberGames === 1 on this game).
 *
 * @param array<string, mixed> $st
 */
function k2_post_game_milestones_debut_opponent_awards(
    mysqli $con,
    int $debutPlayerId,
    int $opponentId,
    array $st,
    int $goalsFor,
    string $gameDate,
    int $gameId
): void {
    if ((int) $st['games'] !== 1) {
        return;
    }
    k2_post_game_milestone_try_insert_game($con, $opponentId, 'newbie_welcomer', $gameDate, 1, $gameId);
    if ($goalsFor >= 2) {
        k2_post_game_milestone_try_insert_game($con, $opponentId, 'generous', $gameDate, 2, $gameId);
    }
}

function k2_post_game_milestones_period_games_table_available(mysqli $con): bool
{
    return k2_ops_table_exists($con, 'player_period_games');
}

function k2_post_game_milestones_maybe_daily_habit(
    mysqli $con,
    int $playerId,
    DateTimeImmutable $dt,
    string $gameDate,
    int $gameId
): void {
    if (!k2_post_game_milestones_period_games_table_available($con)) {
        return;
    }
    $weekStart = k2_post_game_milestones_monday_week_key($dt);
    $weekEnd = (new DateTimeImmutable($weekStart, new DateTimeZone('UTC')))->modify('+7 days')->format('Y-m-d');
    $stmt = $con->prepare(
        'SELECT COUNT(*) AS c FROM player_period_games '
        . 'WHERE player_id = ? AND period_type = \'day\' '
        . 'AND period_start >= ? AND period_start < ? AND games >= 1'
    );
    if ($stmt === false) {
        return;
    }
    $stmt->bind_param('iss', $playerId, $weekStart, $weekEnd);
    if (!$stmt->execute()) {
        $stmt->close();

        return;
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : false;
    $stmt->close();
    if ($row !== false && (int) ($row['c'] ?? 0) === 7) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'daily_habit', $gameDate, 7, $gameId);
    }
}

function k2_post_game_milestones_maybe_monthly_regular(
    mysqli $con,
    int $playerId,
    DateTimeImmutable $dt,
    string $gameDate,
    int $gameId
): void {
    if (!k2_post_game_milestones_period_games_table_available($con)) {
        return;
    }
    $monthStart = $dt->format('Y-m-01');
    $monthEnd = (new DateTimeImmutable($monthStart, new DateTimeZone('UTC')))->modify('+1 month')->format('Y-m-d');
    $daysInMonth = (int) $dt->format('t');
    $stmt = $con->prepare(
        'SELECT COUNT(*) AS c FROM player_period_games '
        . 'WHERE player_id = ? AND period_type = \'day\' '
        . 'AND period_start >= ? AND period_start < ? AND games >= 1'
    );
    if ($stmt === false) {
        return;
    }
    $stmt->bind_param('iss', $playerId, $monthStart, $monthEnd);
    if (!$stmt->execute()) {
        $stmt->close();

        return;
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : false;
    $stmt->close();
    if ($row !== false && (int) ($row['c'] ?? 0) === $daysInMonth) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'monthly_regular', $gameDate, $daysInMonth, $gameId);
    }
}

/**
 * Kickoff active #1: highest playertable.Rating among players active for this match.
 *
 * Must run before k2_post_game_player_write() for idA/idB so Rating/LastGame are still pre-game.
 * Active = LastGame within 365 rolling UTC days before game Date, or idA/idB of this game.
 * Tie → highest playertable.ID.
 */
function k2_post_game_milestones_kickoff_active_top_player_id(
    mysqli $con,
    string $gameDate,
    int $idA,
    int $idB
): int {
    $at = new DateTimeImmutable($gameDate, new DateTimeZone('UTC'));
    $cutoff = $at->modify('-365 days')->format('Y-m-d H:i:s');
    $stmt = $con->prepare(
        'SELECT ID AS pid FROM playertable '
        . 'WHERE (LastGame >= ? OR ID IN (?, ?)) '
        . 'ORDER BY Rating DESC, ID DESC LIMIT 1'
    );
    if ($stmt === false) {
        return 0;
    }
    $stmt->bind_param('sii', $cutoff, $idA, $idB);
    if (!$stmt->execute()) {
        $stmt->close();

        return 0;
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : false;
    $stmt->close();

    return $row !== false ? (int) $row['pid'] : 0;
}

function k2_post_game_milestones_maybe_giant_slayer(
    mysqli $con,
    int $playerId,
    int $opponentId,
    bool $won,
    float $rSelfPre,
    float $rOppPre,
    string $gameDate,
    int $gameId,
    int $idA,
    int $idB
): void {
    if (!$won || $opponentId === $playerId || $rOppPre < $rSelfPre) {
        return;
    }
    $topId = k2_post_game_milestones_kickoff_active_top_player_id($con, $gameDate, $idA, $idB);
    if ($topId !== $opponentId) {
        return;
    }
    k2_post_game_milestone_try_insert_game($con, $playerId, 'giant_slayer', $gameDate, 1, $gameId);
}

/**
 * Giant slayer at kickoff — call before playertable write for this game (see process_completed_game).
 *
 * @param array<string, mixed> $game
 * @param array<string, mixed> $derived
 */
function k2_post_game_milestones_apply_giant_slayer_at_kickoff(
    mysqli $con,
    array $game,
    array $derived
): void {
    if (!k2_post_game_milestones_table_available($con)) {
        return;
    }

    $gameId = (int) $game['id'];
    $gameDate = (string) $game['Date'];
    $idA = (int) $game['idA'];
    $idB = (int) $game['idB'];
    $goalsA = (int) $game['GoalsA'];
    $goalsB = (int) $game['GoalsB'];
    $actualScore = (float) $derived['ActualScore'];

    foreach ([$idA, $idB] as $pid) {
        $opp = $pid === $idA ? $idB : $idA;
        $side = k2_post_game_milestone_side_stats(
            $pid,
            $idA,
            $idB,
            $goalsA,
            $goalsB,
            $actualScore,
            (float) $derived['RatingA'],
            (float) $derived['RatingB']
        );
        if ((float) $side['w'] !== 1.0) {
            continue;
        }
        k2_post_game_milestones_maybe_giant_slayer(
            $con,
            $pid,
            $opp,
            true,
            (float) $side['r_pre'],
            (float) $side['r_opp'],
            $gameDate,
            $gameId,
            $idA,
            $idB
        );
    }
}

/**
 * DB-backed milestone checks only (no chrono notebook, no ratedresults re-sim).
 *
 * @param array<int, array<string, mixed>> $players
 */
function k2_post_game_milestones_db_backed_after_game(
    mysqli $con,
    array $game,
    array $derived,
    array &$players,
    int $idA,
    int $idB,
    int $goalsA,
    int $goalsB,
    float $actualScore,
    string $gameDate,
    int $gameId
): void {
    $dt = new DateTimeImmutable($gameDate, new DateTimeZone('UTC'));

    foreach ([$idA, $idB] as $pid) {
        if (!isset($players[$pid])) {
            continue;
        }
        $st = $players[$pid];
        $opp = $pid === $idA ? $idB : $idA;
        $side = k2_post_game_milestone_side_stats(
            $pid,
            $idA,
            $idB,
            $goalsA,
            $goalsB,
            $actualScore,
            (float) $derived['RatingA'],
            (float) $derived['RatingB']
        );
        $won = (float) $side['w'] === 1.0;

        k2_post_game_milestones_maybe_rare_blank($con, $pid, $st, (int) $side['gf'], $gameDate, $gameId);
        k2_post_game_milestones_debut_opponent_awards($con, $pid, $opp, $st, (int) $side['gf'], $gameDate, $gameId);
        k2_post_game_milestones_maybe_daily_habit($con, $pid, $dt, $gameDate, $gameId);
        k2_post_game_milestones_maybe_monthly_regular($con, $pid, $dt, $gameDate, $gameId);
        k2_post_game_milestones_maybe_weekly_regular($con, $pid, $dt, $gameDate, $gameId);
        k2_post_game_milestones_maybe_year_round($con, $pid, $dt, $gameDate, $gameId);
    }
}

/**
 * @param array<string, mixed> $game
 * @param array<string, mixed> $derived
 * @param array<int, array<string, mixed>> $players
 */
function k2_post_game_update_milestones_after_game(
    mysqli $con,
    array $game,
    array $derived,
    array &$players,
    int $dayGamesA,
    int $dayGamesB,
    int $weekGamesA,
    int $weekGamesB,
    int $monthGamesA,
    int $monthGamesB,
    string $weekStart
): void {
    if (!k2_post_game_milestones_table_available($con)) {
        return;
    }

    $gameId = (int) $game['id'];
    $gameDate = (string) $game['Date'];
    $idA = (int) $game['idA'];
    $idB = (int) $game['idB'];
    $goalsA = (int) $game['GoalsA'];
    $goalsB = (int) $game['GoalsB'];
    $actualScore = (float) $derived['ActualScore'];

    foreach (
        [
            $idA => [$dayGamesA, $weekGamesA, $monthGamesA],
            $idB => [$dayGamesB, $weekGamesB, $monthGamesB],
        ] as $pid => [$dayG, $weekG, $monthG]
    ) {
        if (!isset($players[$pid])) {
            continue;
        }
        $st = &$players[$pid];
        $side = k2_post_game_milestone_side_stats(
            $pid,
            $idA,
            $idB,
            $goalsA,
            $goalsB,
            $actualScore,
            (float) $derived['RatingA'],
            (float) $derived['RatingB']
        );
        $opp = $pid === $idA ? $idB : $idA;

        k2_post_game_milestones_nth_games($con, $pid, $st, $gameDate, $gameId);
        k2_post_game_milestones_dd_merchant($con, $pid, (int) $side['gf'], $gameDate, $gameId);
        k2_post_game_milestones_rating_clubs(
            $con,
            $pid,
            (float) $side['r_pre'],
            $pid === $idA ? (float) $derived['NewRatingA'] : (float) $derived['NewRatingB'],
            $gameDate,
            $gameId
        );
        k2_post_game_milestones_exists_feats($con, $pid, $side, $gameDate, $gameId);
        k2_post_game_milestones_streak_keys($con, $pid, $st, $side, $gameDate, $gameId);
        k2_post_game_milestones_period_burst($con, $pid, $dayG, $monthG, $gameDate, $gameId);
        k2_post_game_milestones_year_in_heaven($con, $pid, $weekG, $weekStart, $gameId, $gameDate);
        k2_post_game_milestones_tail_keys($con, $pid, $opp, $st, $side, $gameDate, $gameId);
    }

    k2_post_game_milestones_db_backed_after_game(
        $con,
        $game,
        $derived,
        $players,
        $idA,
        $idB,
        $goalsA,
        $goalsB,
        $actualScore,
        $gameDate,
        $gameId
    );
}
