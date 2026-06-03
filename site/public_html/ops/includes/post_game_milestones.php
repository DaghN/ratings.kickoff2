<?php
/**
 * player_milestones incremental unlocks (P6) — game-triggered keys after P5.
 */
declare(strict_types=1);

require_once __DIR__ . '/post_game_constants.php';
require_once __DIR__ . '/ops_bootstrap.php';

function k2_post_game_milestones_table_available(mysqli $con): bool
{
    return k2_ops_table_exists($con, 'player_milestones');
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
    global $k2_post_game_milestone_peak_rating;
    $prevPeak = $k2_post_game_milestone_peak_rating[$playerId] ?? $preGameRating;
    foreach ([1700, 1800, 2000, 2300] as $thresh) {
        if ($prevPeak < $thresh && $newRating >= $thresh) {
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
    $k2_post_game_milestone_peak_rating[$playerId] = max($prevPeak, $newRating);
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
    if ($wins === 10) {
        k2_post_game_milestone_try_insert_game($con, $playerId, 'ten_wins', $gameDate, 10, $gameId);
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
    if ($ga === 0 && $gf > 0 && !empty($flags['new_cs_victim'])) {
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

function k2_post_game_milestones_chrono_init_player(): array
{
    return [
        'games' => 0,
        'current_day' => null,
        'last_gid' => 0,
        'score_streak' => 0,
        'draw_streak' => 0,
        'win_margin_streak' => 0,
        'loss_margin_streak' => 0,
        'merchant_streak' => 0,
        'exact_ten_streak' => 0,
        'games_by_day' => [],
        'days_by_month' => [],
        'games_by_month' => [],
        'week_days' => [],
        'week_keys' => [],
        'done' => [],
    ];
}

function k2_post_game_milestones_monday_week_key(DateTimeImmutable $dt): string
{
    $monday = $dt->modify('-' . ((int) $dt->format('N') - 1) . ' days');

    return $monday->format('Y-m-d');
}

function k2_post_game_milestones_month_key(DateTimeImmutable $dt): string
{
    return $dt->format('Y-m');
}

/**
 * @param array<string, mixed> $c
 */
function k2_post_game_milestones_chrono_try_unlock(
    mysqli $con,
    int $playerId,
    string $key,
    array &$c,
    string $achievedAt,
    int $value,
    int $gameId
): void {
    if ($key === 'peace_streak' || isset($c['done'][$key])) {
        return;
    }
    $c['done'][$key] = true;
    k2_post_game_milestone_try_insert_game($con, $playerId, $key, $achievedAt, $value, $gameId);
}

/**
 * @param array<int, array<string, mixed>> $chronoState
 * @param array<int, float> $ratings
 * @param array<int, string> $lastGameDt
 */
function k2_post_game_milestones_chrono_for_player(
    mysqli $con,
    int $playerId,
    int $opponentId,
    array &$chronoState,
    array &$ratings,
    array &$lastGameDt,
    array $game,
    array $derived,
    array $side,
    array $st
): void {
    $gameId = (int) $game['id'];
    $gameDate = (string) $game['Date'];
    $dt = new DateTimeImmutable($gameDate, new DateTimeZone('UTC'));
    $dayKey = $dt->format('Y-m-d');
    $monthKey = k2_post_game_milestones_month_key($dt);
    $dayOfMonth = (int) $dt->format('j');

    if (!isset($chronoState[$playerId])) {
        $chronoState[$playerId] = k2_post_game_milestones_chrono_init_player();
    }
    $c = &$chronoState[$playerId];

    if ((int) $c['games'] === 0) {
        if (!isset($chronoState[$opponentId])) {
            $chronoState[$opponentId] = k2_post_game_milestones_chrono_init_player();
        }
        k2_post_game_milestones_chrono_try_unlock($con, $opponentId, 'newbie_welcomer', $chronoState[$opponentId], $gameDate, 1, $gameId);
        if ((int) $side['gf'] >= 2) {
            k2_post_game_milestones_chrono_try_unlock($con, $opponentId, 'generous', $chronoState[$opponentId], $gameDate, 2, $gameId);
        }
    }

    if ($c['current_day'] !== null && $c['current_day'] !== $dayKey) {
        k2_post_game_milestones_finalize_chrono_day($con, $playerId, $c, (int) $c['last_gid']);
    }
    $c['current_day'] = $dayKey;
    $c['last_gid'] = $gameId;

    $sc = (float) $side['w'];
    $gf = (int) $side['gf'];
    $ga = (int) $side['ga'];
    $won = $sc === 1.0;
    $drew = $sc === 0.5;
    $lost = $sc === 0.0;
    $margin = $won || $lost ? abs($gf - $ga) : 0;

    // rule_short “after 50+ career games” → first 0-goal game when NumberGames >= 51 (game 51+).
    if ((int) $st['games'] >= 51 && $gf === 0) {
        k2_post_game_milestones_chrono_try_unlock($con, $playerId, 'rare_blank', $c, $gameDate, 0, $gameId);
    }

    $outcome = $won ? 'W' : ($drew ? 'D' : 'L');
    if (!isset($c['games_by_day'][$dayKey])) {
        $c['games_by_day'][$dayKey] = [];
    }
    $c['games_by_day'][$dayKey][] = $outcome;

    if (!isset($c['days_by_month'][$monthKey])) {
        $c['days_by_month'][$monthKey] = [];
    }
    if (!in_array($dayOfMonth, $c['days_by_month'][$monthKey], true)) {
        $c['days_by_month'][$monthKey][] = $dayOfMonth;
    }
    if (!isset($c['games_by_month'][$monthKey])) {
        $c['games_by_month'][$monthKey] = 0;
    }
    $c['games_by_month'][$monthKey]++;

    if ($gf > 0) {
        $c['score_streak']++;
    } else {
        $c['score_streak'] = 0;
    }
    if ($c['score_streak'] === 10) {
        k2_post_game_milestones_chrono_try_unlock($con, $playerId, 'on_the_scoresheet', $c, $gameDate, 10, $gameId);
    }

    if ($drew) {
        $c['draw_streak']++;
    } else {
        $c['draw_streak'] = 0;
    }
    if ($c['draw_streak'] === 5) {
        k2_post_game_milestones_chrono_try_unlock($con, $playerId, 'united_nations', $c, $gameDate, 5, $gameId);
    }

    if ($won && $margin === 1) {
        $c['win_margin_streak']++;
    } else {
        $c['win_margin_streak'] = 0;
    }
    if ($c['win_margin_streak'] === 5) {
        k2_post_game_milestones_chrono_try_unlock($con, $playerId, 'knife_edge', $c, $gameDate, 5, $gameId);
    }

    if ($lost && $margin === 1) {
        $c['loss_margin_streak']++;
    } else {
        $c['loss_margin_streak'] = 0;
    }
    if ($c['loss_margin_streak'] === 5) {
        k2_post_game_milestones_chrono_try_unlock($con, $playerId, 'unlucky', $c, $gameDate, 5, $gameId);
    }

    if ($gf >= 10) {
        $c['merchant_streak']++;
    } else {
        $c['merchant_streak'] = 0;
    }
    if ($c['merchant_streak'] === 5) {
        k2_post_game_milestones_chrono_try_unlock($con, $playerId, 'merchant_streak', $c, $gameDate, 5, $gameId);
    }

    if ($gf === 10) {
        $c['exact_ten_streak']++;
    } else {
        $c['exact_ten_streak'] = 0;
    }
    if ($c['exact_ten_streak'] === 3) {
        k2_post_game_milestones_chrono_try_unlock($con, $playerId, 'minimalist_merchant', $c, $gameDate, 3, $gameId);
    }

    $newR = $playerId === (int) $game['idA']
        ? (float) $derived['NewRatingA']
        : (float) $derived['NewRatingB'];
    $ratings[$playerId] = $newR > 0 ? $newR : ($ratings[$playerId] ?? (float) $side['r_pre']);
    $lastGameDt[$playerId] = $gameDate;

    k2_post_game_milestones_maybe_giant_slayer(
        $con,
        $playerId,
        $opponentId,
        $won,
        (float) $side['r_pre'],
        (float) $side['r_opp'],
        $ratings,
        $lastGameDt,
        $gameDate,
        $gameId,
        [(int) $game['idA'], (int) $game['idB']]
    );

    $daysInMonth = (int) $dt->format('t');
    $daysPlayed = $c['days_by_month'][$monthKey];
    if (count($daysPlayed) >= $daysInMonth) {
        $allDays = range(1, $daysInMonth);
        $haveAll = true;
        foreach ($allDays as $d) {
            if (!in_array($d, $daysPlayed, true)) {
                $haveAll = false;
                break;
            }
        }
        if ($haveAll) {
            k2_post_game_milestones_chrono_try_unlock($con, $playerId, 'monthly_regular', $c, $gameDate, $daysInMonth, $gameId);
        }
    }

    $weekKey = k2_post_game_milestones_monday_week_key($dt);
    $isoDow = (int) $dt->format('N') - 1;
    if (!isset($c['week_days'][$weekKey])) {
        $c['week_days'][$weekKey] = [];
    }
    if (!in_array($isoDow, $c['week_days'][$weekKey], true)) {
        $c['week_days'][$weekKey][] = $isoDow;
    }
    if (count($c['week_days'][$weekKey]) === 7) {
        k2_post_game_milestones_chrono_try_unlock($con, $playerId, 'daily_habit', $c, $gameDate, 7, $gameId);
    }

    if (!in_array($weekKey, $c['week_keys'], true)) {
        $c['week_keys'][] = $weekKey;
        sort($c['week_keys']);
        if (count($c['week_keys']) >= 13) {
            $keys = $c['week_keys'];
            for ($i = 0, $n = count($keys) - 12; $i < $n; $i++) {
                $block = array_slice($keys, $i, 13);
                $ok = true;
                for ($j = 1; $j < 13; $j++) {
                    $d0 = new DateTimeImmutable($block[$j - 1], new DateTimeZone('UTC'));
                    $d1 = new DateTimeImmutable($block[$j], new DateTimeZone('UTC'));
                    if ($d0->diff($d1)->days > 10) {
                        $ok = false;
                        break;
                    }
                }
                if ($ok) {
                    k2_post_game_milestones_chrono_try_unlock($con, $playerId, 'weekly_regular', $c, $gameDate, 13, $gameId);
                    break;
                }
            }
        }
    }

    $monthsSorted = array_keys(array_filter(
        $c['games_by_month'],
        static fn (int $cnt): bool => $cnt > 0
    ));
    sort($monthsSorted);
    if (count($monthsSorted) >= 12) {
        for ($i = 0, $n = count($monthsSorted) - 11; $i < $n; $i++) {
            [$y0, $m0] = array_map('intval', explode('-', $monthsSorted[$i]));
            $ok = true;
            for ($j = 0; $j < 12; $j++) {
                $ym = $y0 + intdiv($m0 - 1 + $j, 12);
                $mm = ($m0 - 1 + $j) % 12 + 1;
                $mk = sprintf('%04d-%02d', $ym, $mm);
                if (($c['games_by_month'][$mk] ?? 0) < 1) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                k2_post_game_milestones_chrono_try_unlock($con, $playerId, 'year_round', $c, $gameDate, 12, $gameId);
                break;
            }
        }
    }

    $c['games']++;
}

/**
 * @param array<string, mixed> $c
 */
function k2_post_game_milestones_finalize_chrono_day(
    mysqli $con,
    int $playerId,
    array $c,
    int $gameId
): void {
    $dayKey = $c['current_day'] ?? null;
    if ($dayKey === null) {
        return;
    }
    $outcomes = $c['games_by_day'][$dayKey] ?? [];
    if (count($outcomes) < 5) {
        return;
    }
    $closeAt = (new DateTimeImmutable($dayKey, new DateTimeZone('UTC')))
        ->modify('+1 day')
        ->format('Y-m-d H:i:s');
    if (count(array_filter($outcomes, static fn ($o) => $o === 'W')) === count($outcomes)) {
        k2_post_game_milestones_chrono_try_unlock($con, $playerId, 'perfect_day', $c, $closeAt, 5, $gameId);
    }
    if (count(array_filter($outcomes, static fn ($o) => $o === 'L')) === count($outcomes)) {
        k2_post_game_milestones_chrono_try_unlock($con, $playerId, 'nightmare_day', $c, $closeAt, 5, $gameId);
    }
}

/**
 * Replay prior rated games into chrono state (live process-one). Batch replay skips this.
 *
 * @param array<int|string, mixed> $chronoState
 */
function k2_post_game_milestones_hydrate_chrono_until(
    mysqli $con,
    int $playerId,
    int $beforeGameId,
    array &$chronoState,
    array &$ratings,
    array &$lastGameDt
): void {
    $fromId = (int) ($chronoState['_hydrated_until'][$playerId] ?? 0);
    if ($fromId >= $beforeGameId - 1) {
        return;
    }

    $stmt = $con->prepare(
        'SELECT id, `Date`, idA, idB, GoalsA, GoalsB, ActualScore, '
        . 'RatingA, RatingB, NewRatingA, NewRatingB '
        . 'FROM ratedresults WHERE id > ? AND id < ? '
        . 'AND (idA = ? OR idB = ?) AND NewRatingA IS NOT NULL '
        . 'ORDER BY `Date` ASC, id ASC'
    );
    if ($stmt === false) {
        return;
    }
    $stmt->bind_param('iiii', $fromId, $beforeGameId, $playerId, $playerId);
    if (!$stmt->execute()) {
        $stmt->close();

        return;
    }
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $gid = (int) $row['id'];
        $idA = (int) $row['idA'];
        $idB = (int) $row['idB'];
        $game = $row;
        $derived = [
            'ActualScore' => (float) $row['ActualScore'],
            'RatingA' => (float) $row['RatingA'],
            'RatingB' => (float) $row['RatingB'],
            'NewRatingA' => (float) $row['NewRatingA'],
            'NewRatingB' => (float) $row['NewRatingB'],
        ];
        $opp = $playerId === $idA ? $idB : $idA;
        $side = k2_post_game_milestone_side_stats(
            $playerId,
            $idA,
            $idB,
            (int) $row['GoalsA'],
            (int) $row['GoalsB'],
            (float) $derived['ActualScore'],
            (float) $derived['RatingA'],
            (float) $derived['RatingB']
        );
        $stubSt = ['games' => 0];
        k2_post_game_milestones_chrono_for_player(
            $con,
            $playerId,
            $opp,
            $chronoState,
            $ratings,
            $lastGameDt,
            $game,
            $derived,
            $side,
            $stubSt
        );
        $fromId = $gid;
    }
    $stmt->close();
    $chronoState['_hydrated_until'][$playerId] = max($fromId, $beforeGameId - 1);
}

/**
 * @param array<int|string, mixed> $chronoState
 */
function k2_post_game_milestones_prepare_chrono_for_game(
    mysqli $con,
    array $game,
    array &$chronoState,
    array &$ratings,
    array &$lastGameDt
): void {
    if (($chronoState['_mode'] ?? '') === 'batch') {
        return;
    }
    $gameId = (int) $game['id'];
    k2_post_game_milestones_hydrate_chrono_until($con, (int) $game['idA'], $gameId, $chronoState, $ratings, $lastGameDt);
    k2_post_game_milestones_hydrate_chrono_until($con, (int) $game['idB'], $gameId, $chronoState, $ratings, $lastGameDt);
}

/**
 * @param array<int, float> $ratings
 * @param array<int, string> $lastGameDt
 * @param list<int> $inGame
 */
function k2_post_game_milestones_maybe_giant_slayer(
    mysqli $con,
    int $playerId,
    int $opponentId,
    bool $won,
    float $rPre,
    float $rOpp,
    array $ratings,
    array $lastGameDt,
    string $gameDate,
    int $gameId,
    array $inGame
): void {
    if (!$won || $opponentId !== k2_post_game_milestones_active_top_id($ratings, $lastGameDt, $gameDate, $inGame)) {
        return;
    }
    if ($opponentId === $playerId || $rOpp < $rPre) {
        return;
    }
    k2_post_game_milestone_try_insert_game($con, $playerId, 'giant_slayer', $gameDate, 1, $gameId);
}

/**
 * @param array<int, float> $ratings
 * @param array<int, string> $lastGameDt
 * @param list<int> $inGame
 */
function k2_post_game_milestones_active_top_id(
    array $ratings,
    array $lastGameDt,
    string $gameDate,
    array $inGame
): int {
    $at = new DateTimeImmutable($gameDate, new DateTimeZone('UTC'));
    $cutoff = $at->modify('-365 days');
    $playing = array_flip($inGame);
    $bestId = 0;
    $bestRating = -1.0;
    foreach ($ratings as $pid => $rating) {
        if (isset($playing[$pid])) {
            if ($rating > $bestRating || ($rating === $bestRating && $pid > $bestId)) {
                $bestRating = $rating;
                $bestId = (int) $pid;
            }
            continue;
        }
        if (!isset($lastGameDt[$pid])) {
            continue;
        }
        $lg = new DateTimeImmutable($lastGameDt[$pid], new DateTimeZone('UTC'));
        if ($lg < $cutoff) {
            continue;
        }
        if ($rating > $bestRating || ($rating === $bestRating && $pid > $bestId)) {
            $bestRating = $rating;
            $bestId = (int) $pid;
        }
    }

    return $bestId;
}

/** @var array<int, float> */
$k2_post_game_milestone_ratings = [];

/** @var array<int, string> */
$k2_post_game_milestone_last_game = [];

/** @var array<int, float> */
$k2_post_game_milestone_peak_rating = [];

function k2_post_game_milestones_reset_replay_cache(): void
{
    global $k2_post_game_milestone_ratings, $k2_post_game_milestone_last_game, $k2_post_game_milestone_peak_rating;
    $k2_post_game_milestone_ratings = [];
    $k2_post_game_milestone_last_game = [];
    $k2_post_game_milestone_peak_rating = [];
}

/**
 * @param array<string, mixed> $game
 * @param array<string, mixed> $derived
 * @param array<int, array<string, mixed>> &$players
 * @param array<int|string, mixed> $chronoState chrono state (`_mode` => `batch` for replay-to)
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
    string $weekStart,
    array &$chronoState
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

    global $k2_post_game_milestone_ratings, $k2_post_game_milestone_last_game;

    k2_post_game_milestones_prepare_chrono_for_game(
        $con,
        $game,
        $chronoState,
        $k2_post_game_milestone_ratings,
        $k2_post_game_milestone_last_game
    );

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
        $side = k2_post_game_milestone_side_stats($pid, $idA, $idB, $goalsA, $goalsB, $actualScore, (float) $derived['RatingA'], (float) $derived['RatingB']);
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

        k2_post_game_milestones_chrono_for_player(
            $con,
            $pid,
            $opp,
            $chronoState,
            $k2_post_game_milestone_ratings,
            $k2_post_game_milestone_last_game,
            $game,
            $derived,
            $side,
            $st
        );
    }
}

function k2_post_game_milestones_finalize_replay_chrono(
    mysqli $con,
    array &$chronoState
): void {
    foreach ($chronoState as $playerId => $c) {
        if (!is_array($c) || str_starts_with((string) $playerId, '_')) {
            continue;
        }
        if (($c['current_day'] ?? null) !== null) {
            k2_post_game_milestones_finalize_chrono_day($con, (int) $playerId, $c, (int) $c['last_gid']);
        }
    }
}

function k2_post_game_milestones_seed_lobby(mysqli $con): void
{
    if (!k2_post_game_milestones_table_available($con)) {
        return;
    }
    $sql = 'INSERT INTO player_milestones '
        . '(player_id, milestone_key, achieved_at, value, source_kind, source_game_id, '
        . 'source_league_kind, source_period_type, source_period_start) '
        . 'SELECT ID, \'entered_arena\', JoinDate, 1, \'lobby\', NULL, NULL, NULL, NULL '
        . 'FROM playertable WHERE NumberGames >= 1 '
        . 'AND NOT EXISTS ('
        . 'SELECT 1 FROM player_milestones pm WHERE pm.player_id = playertable.ID '
        . 'AND pm.milestone_key = \'entered_arena\''
        . ')';
    if (!$con->query($sql)) {
        throw new RuntimeException('seed entered_arena: ' . $con->error);
    }
}
