<?php
/**
 * Per-player career state during replay (mirrors scripts/ladder/player_state.py).
 */
declare(strict_types=1);

require_once __DIR__ . '/post_game_constants.php';

/**
 * @return array<string, mixed>
 */
function k2_post_game_player_state_new(): array
{
    return [
        'rating' => K2_POST_GAME_START_RATING,
        'display' => 0,
        'games' => 0,
        'wins' => 0,
        'draws' => 0,
        'losses' => 0,
        'goals_for' => 0,
        'goals_against' => 0,
        'most_goals_scored' => 0,
        'least_goals_scored' => K2_POST_GAME_SENTINEL_LEAST_GOALS,
        'most_goals_conceded' => 0,
        'least_goals_conceded' => K2_POST_GAME_SENTINEL_LEAST_GOALS,
        'biggest_win_difference' => 0,
        'biggest_draw_sum' => 0,
        'biggest_loss_difference' => 0,
        'smallest_sum_of_goals' => K2_POST_GAME_SENTINEL_LEAST_GOALS,
        'biggest_sum_of_goals' => 0,
        'double_digits' => 0,
        'clean_sheets' => 0,
        'double_digits_conceded' => 0,
        'clean_sheets_conceded' => 0,
        'different_opponents' => 0,
        'different_victims' => 0,
        'double_digits_victims' => 0,
        'clean_sheets_victims' => 0,
        'most_goals_conceded_victims' => 0,
        'least_goals_scored_victims' => 0,
        'biggest_loss_victims' => 0,
        'different_culprits' => 0,
        'double_digits_culprits' => 0,
        'clean_sheets_culprits' => 0,
        'most_goals_scored_culprits' => 0,
        'least_goals_conceded_culprits' => 0,
        'biggest_win_culprits' => 0,
        'sum_opponents_rating' => 0.0,
        'highest_rated_victim' => 0.0,
        'lowest_rated_culprit' => K2_POST_GAME_SENTINEL_LOWEST_RATING,
        'current_rating_ascent' => 0.0,
        'biggest_rating_ascent' => 0.0,
        'current_rating_descent' => 0.0,
        'biggest_rating_descent' => 0.0,
        'lowest_rating' => K2_POST_GAME_SENTINEL_LOWEST_RATING,
        'peak_rating' => 0.0,
        'winning_streak' => 0,
        'drawing_streak' => 0,
        'losing_streak' => 0,
        'non_win_streak' => 0,
        'non_draw_streak' => 0,
        'non_loss_streak' => 0,
        'longest_winning_streak' => 0,
        'longest_drawing_streak' => 0,
        'longest_losing_streak' => 0,
        'longest_non_win_streak' => 0,
        'longest_non_draw_streak' => 0,
        'longest_non_loss_streak' => 0,
        'score_streak' => 0,
        'merchant_streak' => 0,
        'exact_ten_goal_streak' => 0,
        'win_margin_one_streak' => 0,
        'loss_margin_one_streak' => 0,
        'last_game' => null,
        'last_game_id' => null,
        'last_win_game_id' => null,
        'last_draw_game_id' => null,
        'last_loss_game_id' => null,
        'lowest_rating_game_id' => null,
        'peak_rating_game_id' => null,
        'most_goals_scored_game_id' => null,
        'least_goals_scored_game_id' => null,
        'most_goals_conceded_game_id' => null,
        'least_goals_conceded_game_id' => null,
        'biggest_win_game_id' => null,
        'biggest_draw_game_id' => null,
        'biggest_loss_game_id' => null,
        'smallest_sum_of_goals_game_id' => null,
        'biggest_sum_of_goals_game_id' => null,
        'most_goals_scored_victim_id' => 0,
        'least_goals_conceded_victim_id' => 0,
        'biggest_win_victim_id' => 0,
        'most_goals_conceded_culprit_id' => 0,
        'least_goals_scored_culprit_id' => 0,
        'biggest_loss_culprit_id' => 0,
        'highest_rated_victim_game_id' => null,
        'lowest_rated_culprit_game_id' => null,
        '_network_opponents' => [],
        '_network_victims' => [],
        '_network_culprits' => [],
        '_network_dd_victims' => [],
        '_network_dd_culprits' => [],
        '_network_cs_victims' => [],
        '_network_cs_culprits' => [],
    ];
}

/**
 * @param array<int, array<string, mixed>> $players
 * @return array<string, mixed>
 */
function k2_post_game_player_get(array &$players, int $playerId): array
{
    if (!isset($players[$playerId])) {
        $players[$playerId] = k2_post_game_player_state_new();
    }

    return $players[$playerId];
}

function k2_post_game_player_ratio(int $num, int $den): ?float
{
    if ($den <= 0) {
        return null;
    }

    return round($num / $den, 4, PHP_ROUND_HALF_EVEN);
}

function k2_post_game_player_db_count(int $value, int $games): ?int
{
    return $games <= 0 ? null : $value;
}

function k2_post_game_player_goal_ratio(int $goalsFor, int $goalsAgainst, int $games): ?float
{
    if ($games <= 0) {
        return null;
    }
    if ($goalsAgainst <= 0) {
        return K2_POST_GAME_SENTINEL_GOAL_RATIO;
    }

    return round($goalsFor / $goalsAgainst, 4, PHP_ROUND_HALF_EVEN);
}

/**
 * @param array<int, array<string, mixed>> $players
 */
function k2_post_game_player_transfer_record_count(
    mysqli $con,
    array &$players,
    int $prevOpponentId,
    int $newOpponentId,
    string $opponentAttr,
    int $beforeGameId
): void {
    if ($prevOpponentId === $newOpponentId) {
        return;
    }
    if ($prevOpponentId > 0) {
        if (!isset($players[$prevOpponentId])) {
            $players[$prevOpponentId] = k2_post_game_player_load($con, $prevOpponentId, $beforeGameId);
        }
        $prev = $players[$prevOpponentId];
        $players[$prevOpponentId][$opponentAttr] = max(0, (int) $prev[$opponentAttr] - 1);
    }
    if ($newOpponentId > 0) {
        if (!isset($players[$newOpponentId])) {
            $players[$newOpponentId] = k2_post_game_player_load($con, $newOpponentId, $beforeGameId);
        }
        $nxt = $players[$newOpponentId];
        $players[$newOpponentId][$opponentAttr] = (int) $nxt[$opponentAttr] + 1;
    }
}

/**
 * @param array<int, array<string, mixed>> $players
 */
function k2_post_game_player_update_streaks(array &$st, bool $won, bool $drew, bool $lost): void
{
    if ($won) {
        $st['winning_streak']++;
        $st['drawing_streak'] = 0;
        $st['losing_streak'] = 0;
        $st['non_win_streak'] = 0;
        $st['non_draw_streak']++;
        $st['non_loss_streak']++;
    } elseif ($drew) {
        $st['winning_streak'] = 0;
        $st['drawing_streak']++;
        $st['losing_streak'] = 0;
        $st['non_win_streak']++;
        $st['non_draw_streak'] = 0;
        $st['non_loss_streak']++;
    } else {
        $st['winning_streak'] = 0;
        $st['drawing_streak'] = 0;
        $st['losing_streak']++;
        $st['non_win_streak']++;
        $st['non_draw_streak']++;
        $st['non_loss_streak'] = 0;
    }

    $st['longest_winning_streak'] = max($st['longest_winning_streak'], $st['winning_streak']);
    $st['longest_drawing_streak'] = max($st['longest_drawing_streak'], $st['drawing_streak']);
    $st['longest_losing_streak'] = max($st['longest_losing_streak'], $st['losing_streak']);
    $st['longest_non_win_streak'] = max($st['longest_non_win_streak'], $st['non_win_streak']);
    $st['longest_non_draw_streak'] = max($st['longest_non_draw_streak'], $st['non_draw_streak']);
    $st['longest_non_loss_streak'] = max($st['longest_non_loss_streak'], $st['non_loss_streak']);
}

/**
 * Milestone facilitators (gen_milestone_chrono_sql.py); persisted on playertable (SCH-018).
 *
 * @param array<string, mixed> $st
 */
function k2_post_game_player_update_milestone_streaks(
    array &$st,
    int $goalsFor,
    int $goalsAgainst,
    bool $won,
    bool $lost
): void {
    if ($goalsFor > 0) {
        $st['score_streak']++;
    } else {
        $st['score_streak'] = 0;
    }

    if ($goalsFor >= 10) {
        $st['merchant_streak']++;
    } else {
        $st['merchant_streak'] = 0;
    }

    if ($goalsFor === 10) {
        $st['exact_ten_goal_streak']++;
    } else {
        $st['exact_ten_goal_streak'] = 0;
    }

    $margin = ($won || $lost) ? abs($goalsFor - $goalsAgainst) : 0;
    if ($won && $margin === 1) {
        $st['win_margin_one_streak']++;
    } else {
        $st['win_margin_one_streak'] = 0;
    }
    if ($lost && $margin === 1) {
        $st['loss_margin_one_streak']++;
    } else {
        $st['loss_margin_one_streak'] = 0;
    }
}

/**
 * Career peak/nadir — contract § Career peak and nadir (unset until 20 games).
 *
 * @param array<string, mixed> $st
 */
function k2_post_game_player_apply_career_peak_nadir(array &$st, float $newRating, int $gameId): void
{
    $games = (int) $st['games'];
    if ($games < K2_POST_GAME_ESTABLISHED_MIN_GAMES) {
        return;
    }
    if ($games === K2_POST_GAME_ESTABLISHED_MIN_GAMES) {
        $st['peak_rating'] = $newRating;
        $st['peak_rating_game_id'] = $gameId;
        $st['lowest_rating'] = $newRating;
        $st['lowest_rating_game_id'] = $gameId;

        return;
    }
    if ($newRating > (float) $st['peak_rating']) {
        $st['peak_rating'] = $newRating;
        $st['peak_rating_game_id'] = $gameId;
    }
    if ($newRating < (float) $st['lowest_rating']) {
        $st['lowest_rating'] = $newRating;
        $st['lowest_rating_game_id'] = $gameId;
    }
}

/**
 * @param array<int, array<string, mixed>> $players players touched this game (loaded from DB + network sets)
 */
function k2_post_game_player_apply_match(
    mysqli $con,
    array &$players,
    int $playerId,
    int $opponentId,
    float $opponentRatingBefore,
    int $goalsFor,
    int $goalsAgainst,
    float $actualScore,
    int $goalDifference,
    int $sumOfGoals,
    bool $ddFor,
    bool $csFor,
    float $oldRating,
    float $newRating,
    float $adjustment,
    int $gameId,
    string $gameDate,
    int $beforeGameId
): void {
    if (!isset($players[$playerId])) {
        $players[$playerId] = k2_post_game_player_load($con, $playerId, $beforeGameId);
    }
    $st = $players[$playerId];

    $st['game_flags'] = [
        'new_opponent' => false,
        'new_victim' => false,
        'new_culprit' => false,
        'new_dd_victim' => false,
        'new_cs_victim' => false,
    ];

    $st['games']++;
    if ($st['games'] >= 1) {
        $st['display'] = 1;
    }

    $won = $actualScore === 1.0;
    $drew = $actualScore === 0.5;
    $lost = $actualScore === 0.0;

    if ($won) {
        $st['wins']++;
    } elseif ($drew) {
        $st['draws']++;
    } else {
        $st['losses']++;
    }

    $st['goals_for'] += $goalsFor;
    $st['goals_against'] += $goalsAgainst;
    $st['rating'] = $newRating;
    $st['last_game'] = $gameDate;
    $st['last_game_id'] = $gameId;

    $beforeOpp = count($st['_network_opponents']);
    $st['_network_opponents'][$opponentId] = true;
    $st['different_opponents'] = count($st['_network_opponents']);
    $st['game_flags']['new_opponent'] = count($st['_network_opponents']) > $beforeOpp;

    if ($won) {
        $beforeVic = count($st['_network_victims']);
        $st['_network_victims'][$opponentId] = true;
        $st['different_victims'] = count($st['_network_victims']);
        $st['game_flags']['new_victim'] = count($st['_network_victims']) > $beforeVic;
    }
    if ($lost) {
        $beforeCul = count($st['_network_culprits']);
        $st['_network_culprits'][$opponentId] = true;
        $st['different_culprits'] = count($st['_network_culprits']);
        $st['game_flags']['new_culprit'] = count($st['_network_culprits']) > $beforeCul;
    }
    if ($ddFor) {
        $beforeDd = count($st['_network_dd_victims']);
        $st['_network_dd_victims'][$opponentId] = true;
        $st['double_digits_victims'] = count($st['_network_dd_victims']);
        $st['game_flags']['new_dd_victim'] = count($st['_network_dd_victims']) > $beforeDd;
    }
    if ($csFor) {
        $beforeCs = count($st['_network_cs_victims']);
        $st['_network_cs_victims'][$opponentId] = true;
        $st['clean_sheets_victims'] = count($st['_network_cs_victims']);
        $st['game_flags']['new_cs_victim'] = count($st['_network_cs_victims']) > $beforeCs;
    }

    $st['sum_opponents_rating'] += $opponentRatingBefore;

    if ($goalsFor >= 1 && $goalsFor > (int) $st['most_goals_scored']) {
        if ((int) $st['most_goals_scored_victim_id'] !== $opponentId) {
            k2_post_game_player_transfer_record_count(
                $con,
                $players,
                (int) $st['most_goals_scored_victim_id'],
                $opponentId,
                'most_goals_scored_culprits',
                $beforeGameId
            );
            $st['most_goals_scored_victim_id'] = $opponentId;
        }
        $st['most_goals_scored'] = $goalsFor;
        $st['most_goals_scored_game_id'] = $gameId;
    }

    if ($goalsFor < (int) $st['least_goals_scored']) {
        if ((int) $st['least_goals_scored_culprit_id'] !== $opponentId) {
            k2_post_game_player_transfer_record_count(
                $con,
                $players,
                (int) $st['least_goals_scored_culprit_id'],
                $opponentId,
                'least_goals_scored_victims',
                $beforeGameId
            );
            $st['least_goals_scored_culprit_id'] = $opponentId;
        }
        $st['least_goals_scored'] = $goalsFor;
        $st['least_goals_scored_game_id'] = $gameId;
    }

    if ($goalsAgainst > (int) $st['most_goals_conceded']) {
        if ((int) $st['most_goals_conceded_culprit_id'] !== $opponentId) {
            k2_post_game_player_transfer_record_count(
                $con,
                $players,
                (int) $st['most_goals_conceded_culprit_id'],
                $opponentId,
                'most_goals_conceded_victims',
                $beforeGameId
            );
            $st['most_goals_conceded_culprit_id'] = $opponentId;
        }
        $st['most_goals_conceded'] = $goalsAgainst;
        $st['most_goals_conceded_game_id'] = $gameId;
    }

    if ($goalsAgainst < (int) $st['least_goals_conceded']) {
        if ((int) $st['least_goals_conceded_victim_id'] !== $opponentId) {
            k2_post_game_player_transfer_record_count(
                $con,
                $players,
                (int) $st['least_goals_conceded_victim_id'],
                $opponentId,
                'least_goals_conceded_culprits',
                $beforeGameId
            );
            $st['least_goals_conceded_victim_id'] = $opponentId;
        }
        $st['least_goals_conceded'] = $goalsAgainst;
        $st['least_goals_conceded_game_id'] = $gameId;
    }

    if ($won && $goalDifference > (int) $st['biggest_win_difference']) {
        if ((int) $st['biggest_win_victim_id'] !== $opponentId) {
            k2_post_game_player_transfer_record_count(
                $con,
                $players,
                (int) $st['biggest_win_victim_id'],
                $opponentId,
                'biggest_win_culprits',
                $beforeGameId
            );
            $st['biggest_win_victim_id'] = $opponentId;
        }
        $st['biggest_win_difference'] = $goalDifference;
        $st['biggest_win_game_id'] = $gameId;
    }

    if ($drew && $sumOfGoals > (int) $st['biggest_draw_sum']) {
        $st['biggest_draw_sum'] = $sumOfGoals;
        $st['biggest_draw_game_id'] = $gameId;
    }

    if ($lost && $goalDifference > (int) $st['biggest_loss_difference']) {
        if ((int) $st['biggest_loss_culprit_id'] !== $opponentId) {
            k2_post_game_player_transfer_record_count(
                $con,
                $players,
                (int) $st['biggest_loss_culprit_id'],
                $opponentId,
                'biggest_loss_victims',
                $beforeGameId
            );
            $st['biggest_loss_culprit_id'] = $opponentId;
        }
        $st['biggest_loss_difference'] = $goalDifference;
        $st['biggest_loss_game_id'] = $gameId;
    }

    if ($sumOfGoals < (int) $st['smallest_sum_of_goals']) {
        $st['smallest_sum_of_goals'] = $sumOfGoals;
        $st['smallest_sum_of_goals_game_id'] = $gameId;
    }
    if ($sumOfGoals > (int) $st['biggest_sum_of_goals']) {
        $st['biggest_sum_of_goals'] = $sumOfGoals;
        $st['biggest_sum_of_goals_game_id'] = $gameId;
    }

    if ($ddFor) {
        $st['double_digits']++;
    }
    if ($goalsAgainst >= 10) {
        $st['double_digits_conceded']++;
    }
    if ($csFor) {
        $st['clean_sheets']++;
    }
    if ($goalsFor === 0) {
        $st['clean_sheets_conceded']++;
    }

    if ($won && $opponentRatingBefore > (float) $st['highest_rated_victim']) {
        $st['highest_rated_victim'] = $opponentRatingBefore;
        $st['highest_rated_victim_game_id'] = $gameId;
    }
    if ($lost && $opponentRatingBefore < (float) $st['lowest_rated_culprit']) {
        $st['lowest_rated_culprit'] = $opponentRatingBefore;
        $st['lowest_rated_culprit_game_id'] = $gameId;
    }

    if ($newRating > $oldRating) {
        $st['current_rating_ascent'] += abs($adjustment);
        $st['current_rating_descent'] = 0.0;
    } elseif ($newRating < $oldRating) {
        $st['current_rating_descent'] += abs($adjustment);
        $st['current_rating_ascent'] = 0.0;
    }

    if ($st['current_rating_ascent'] > $st['biggest_rating_ascent']) {
        $st['biggest_rating_ascent'] = $st['current_rating_ascent'];
    }
    if ($st['current_rating_descent'] > $st['biggest_rating_descent']) {
        $st['biggest_rating_descent'] = $st['current_rating_descent'];
    }

    k2_post_game_player_apply_career_peak_nadir($st, $newRating, $gameId);

    k2_post_game_player_update_streaks($st, $won, $drew, $lost);
    k2_post_game_player_update_milestone_streaks($st, $goalsFor, $goalsAgainst, $won, $lost);

    if ($won) {
        $st['last_win_game_id'] = $gameId;
    } elseif ($drew) {
        $st['last_draw_game_id'] = $gameId;
    } else {
        $st['last_loss_game_id'] = $gameId;
    }

    $players[$playerId] = $st;
}

/**
 * When opponent scores DD/CS against this player, update victim's culprit network sets.
 *
 * @param array<int, array<string, mixed>> $players
 */
function k2_post_game_player_apply_conceded_network(
    mysqli $con,
    array &$players,
    int $victimId,
    int $culpritId,
    int $beforeGameId,
    bool $ddAgainst,
    bool $csAgainst
): void {
    if (!$ddAgainst && !$csAgainst) {
        return;
    }
    if (!isset($players[$victimId])) {
        $players[$victimId] = k2_post_game_player_load($con, $victimId, $beforeGameId);
    }
    $st = $players[$victimId];
    if ($ddAgainst) {
        $st['_network_dd_culprits'][$culpritId] = true;
        $st['double_digits_culprits'] = count($st['_network_dd_culprits']);
    }
    if ($csAgainst) {
        $st['_network_cs_culprits'][$culpritId] = true;
        $st['clean_sheets_culprits'] = count($st['_network_cs_culprits']);
    }
    $players[$victimId] = $st;
}

/**
 * @param array<string, mixed> $st
 * @return array<string, mixed>
 */
function k2_post_game_player_to_db_row(array $st, int $playerId): array
{
    $g = (int) $st['games'];
    $gf = (int) $st['goals_for'];
    $ga = (int) $st['goals_against'];

    return [
        'ID' => $playerId,
        'Display' => $g > 0 ? (int) $st['display'] : 0,
        'Rating' => (float) $st['rating'],
        'NumberGames' => k2_post_game_player_db_count($g, $g),
        'NumberWins' => k2_post_game_player_db_count((int) $st['wins'], $g),
        'NumberDraws' => k2_post_game_player_db_count((int) $st['draws'], $g),
        'NumberLosses' => k2_post_game_player_db_count((int) $st['losses'], $g),
        'WinRatio' => k2_post_game_player_ratio((int) $st['wins'], $g),
        'DrawRatio' => k2_post_game_player_ratio((int) $st['draws'], $g),
        'LossRatio' => k2_post_game_player_ratio((int) $st['losses'], $g),
        'GoalsFor' => k2_post_game_player_db_count($gf, $g),
        'GoalsAgainst' => k2_post_game_player_db_count($ga, $g),
        'AverageGoalsFor' => $g > 0 ? round($gf / $g, 4, PHP_ROUND_HALF_EVEN) : null,
        'AverageGoalsAgainst' => $g > 0 ? round($ga / $g, 4, PHP_ROUND_HALF_EVEN) : null,
        'GoalRatio' => k2_post_game_player_goal_ratio($gf, $ga, $g),
        'MostGoalsScored' => $st['most_goals_scored'] > 0 ? (int) $st['most_goals_scored'] : null,
        'LeastGoalsScored' => (int) $st['least_goals_scored'],
        'MostGoalsConceded' => $st['most_goals_conceded'] > 0 ? (int) $st['most_goals_conceded'] : null,
        'LeastGoalsConceded' => (int) $st['least_goals_conceded'],
        'BiggestWinDifference' => $st['biggest_win_difference'] > 0 ? (int) $st['biggest_win_difference'] : null,
        'BiggestDrawSum' => $st['biggest_draw_sum'] > 0 ? (int) $st['biggest_draw_sum'] : null,
        'BiggestLossDifference' => $st['biggest_loss_difference'] > 0 ? (int) $st['biggest_loss_difference'] : null,
        'SmallestSumOfGoals' => (int) $st['smallest_sum_of_goals'],
        'BiggestSumOfGoals' => $st['biggest_sum_of_goals'] > 0 ? (int) $st['biggest_sum_of_goals'] : null,
        'DoubleDigits' => $st['double_digits'] > 0 ? (int) $st['double_digits'] : null,
        'CleanSheets' => $st['clean_sheets'] > 0 ? (int) $st['clean_sheets'] : null,
        'DoubleDigitsConceded' => $st['double_digits_conceded'] > 0 ? (int) $st['double_digits_conceded'] : null,
        'CleanSheetsConceded' => $st['clean_sheets_conceded'] > 0 ? (int) $st['clean_sheets_conceded'] : null,
        'DoubleDigitsRatio' => k2_post_game_player_ratio((int) $st['double_digits'], $g),
        'CleanSheetsRatio' => k2_post_game_player_ratio((int) $st['clean_sheets'], $g),
        'DoubleDigitsConcededRatio' => k2_post_game_player_ratio((int) $st['double_digits_conceded'], $g),
        'CleanSheetsConcededRatio' => k2_post_game_player_ratio((int) $st['clean_sheets_conceded'], $g),
        'DifferentOpponents' => k2_post_game_player_db_count((int) $st['different_opponents'], $g),
        'DifferentVictims' => k2_post_game_player_db_count((int) $st['different_victims'], $g),
        'DoubleDigitsVictims' => k2_post_game_player_db_count((int) $st['double_digits_victims'], $g),
        'CleanSheetsVictims' => k2_post_game_player_db_count((int) $st['clean_sheets_victims'], $g),
        'MostGoalsConcededVictims' => k2_post_game_player_db_count((int) $st['most_goals_conceded_victims'], $g),
        'LeastGoalsScoredVictims' => k2_post_game_player_db_count((int) $st['least_goals_scored_victims'], $g),
        'BiggestLossVictims' => k2_post_game_player_db_count((int) $st['biggest_loss_victims'], $g),
        'DifferentCulprits' => k2_post_game_player_db_count((int) $st['different_culprits'], $g),
        'DoubleDigitsCulprits' => k2_post_game_player_db_count((int) $st['double_digits_culprits'], $g),
        'CleanSheetsCulprits' => k2_post_game_player_db_count((int) $st['clean_sheets_culprits'], $g),
        'MostGoalsScoredCulprits' => k2_post_game_player_db_count((int) $st['most_goals_scored_culprits'], $g),
        'LeastGoalsConcededCulprits' => k2_post_game_player_db_count((int) $st['least_goals_conceded_culprits'], $g),
        'BiggestWinCulprits' => k2_post_game_player_db_count((int) $st['biggest_win_culprits'], $g),
        'SumOfOpponentsRating' => $st['sum_opponents_rating'] > 0 ? (float) $st['sum_opponents_rating'] : null,
        'AverageOpponentRating' => $g > 0 ? round((float) $st['sum_opponents_rating'] / $g, 3, PHP_ROUND_HALF_EVEN) : null,
        'HighestRatedVictim' => $st['highest_rated_victim'] > 0 ? (float) $st['highest_rated_victim'] : null,
        'LowestRatedCulprit' => (float) $st['lowest_rated_culprit'],
        'CurrentRatingAscent' => $st['current_rating_ascent'] > 0 ? (float) $st['current_rating_ascent'] : null,
        'BiggestRatingAscent' => $st['biggest_rating_ascent'] > 0 ? (float) $st['biggest_rating_ascent'] : null,
        'CurrentRatingDescent' => $st['current_rating_descent'] > 0 ? (float) $st['current_rating_descent'] : null,
        'BiggestRatingDescent' => $st['biggest_rating_descent'] > 0 ? (float) $st['biggest_rating_descent'] : null,
        'LowestRating' => (float) $st['lowest_rating'],
        'PeakRating' => $st['peak_rating'] > 0 ? (float) $st['peak_rating'] : null,
        'WinningStreak' => $st['winning_streak'] > 0 ? (int) $st['winning_streak'] : null,
        'DrawingStreak' => $st['drawing_streak'] > 0 ? (int) $st['drawing_streak'] : null,
        'LosingStreak' => $st['losing_streak'] > 0 ? (int) $st['losing_streak'] : null,
        'NonWinStreak' => $st['non_win_streak'] > 0 ? (int) $st['non_win_streak'] : null,
        'NonDrawStreak' => $st['non_draw_streak'] > 0 ? (int) $st['non_draw_streak'] : null,
        'NonLossStreak' => $st['non_loss_streak'] > 0 ? (int) $st['non_loss_streak'] : null,
        'LongestWinningStreak' => $st['longest_winning_streak'] > 0 ? (int) $st['longest_winning_streak'] : null,
        'LongestDrawingStreak' => $st['longest_drawing_streak'] > 0 ? (int) $st['longest_drawing_streak'] : null,
        'LongestLosingStreak' => $st['longest_losing_streak'] > 0 ? (int) $st['longest_losing_streak'] : null,
        'LongestNonWinStreak' => $st['longest_non_win_streak'] > 0 ? (int) $st['longest_non_win_streak'] : null,
        'LongestNonDrawStreak' => $st['longest_non_draw_streak'] > 0 ? (int) $st['longest_non_draw_streak'] : null,
        'LongestNonLossStreak' => $st['longest_non_loss_streak'] > 0 ? (int) $st['longest_non_loss_streak'] : null,
        'ScoreStreak' => $st['score_streak'] > 0 ? (int) $st['score_streak'] : null,
        'MerchantStreak' => $st['merchant_streak'] > 0 ? (int) $st['merchant_streak'] : null,
        'ExactTenGoalStreak' => $st['exact_ten_goal_streak'] > 0 ? (int) $st['exact_ten_goal_streak'] : null,
        'WinMarginOneStreak' => $st['win_margin_one_streak'] > 0 ? (int) $st['win_margin_one_streak'] : null,
        'LossMarginOneStreak' => $st['loss_margin_one_streak'] > 0 ? (int) $st['loss_margin_one_streak'] : null,
        'LastGame' => $st['last_game'],
        'LastGameGameID' => $st['last_game_id'],
        'LastWinGameID' => $st['last_win_game_id'],
        'LastDrawGameID' => $st['last_draw_game_id'],
        'LastLossGameID' => $st['last_loss_game_id'],
        'LowestRatingGameID' => $st['lowest_rating_game_id'],
        'PeakRatingGameID' => $st['peak_rating_game_id'],
        'MostGoalsScoredGameID' => $st['most_goals_scored_game_id'],
        'LeastGoalsScoredGameID' => $st['least_goals_scored_game_id'],
        'MostGoalsConcededGameID' => $st['most_goals_conceded_game_id'],
        'LeastGoalsConcededGameID' => $st['least_goals_conceded_game_id'],
        'BiggestWinGameID' => $st['biggest_win_game_id'],
        'BiggestDrawGameID' => $st['biggest_draw_game_id'],
        'BiggestLossGameID' => $st['biggest_loss_game_id'],
        'SmallestSumOfGoalsGameID' => $st['smallest_sum_of_goals_game_id'],
        'BiggestSumOfGoalsGameID' => $st['biggest_sum_of_goals_game_id'],
        'MostGoalsScoredVictimID' => $st['most_goals_scored_victim_id'] > 0 ? (int) $st['most_goals_scored_victim_id'] : null,
        'LeastGoalsConcededVictimID' => $st['least_goals_conceded_victim_id'] > 0 ? (int) $st['least_goals_conceded_victim_id'] : null,
        'BiggestWinVictimID' => $st['biggest_win_victim_id'] > 0 ? (int) $st['biggest_win_victim_id'] : null,
        'MostGoalsConcededCulpritID' => $st['most_goals_conceded_culprit_id'] > 0 ? (int) $st['most_goals_conceded_culprit_id'] : null,
        'LeastGoalsScoredCulpritID' => $st['least_goals_scored_culprit_id'] > 0 ? (int) $st['least_goals_scored_culprit_id'] : null,
        'BiggestLossCulpritID' => $st['biggest_loss_culprit_id'] > 0 ? (int) $st['biggest_loss_culprit_id'] : null,
        'HighestRatedVictimGameID' => $st['highest_rated_victim_game_id'],
        'LowestRatedCulpritGameID' => $st['lowest_rated_culprit_game_id'],
    ];
}
