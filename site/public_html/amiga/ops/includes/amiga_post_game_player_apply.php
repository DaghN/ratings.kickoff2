<?php
/**
 * Per-game player state updates for Amiga (mirrors ops post_game_player_state apply helpers).
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_post_game_player_db.php';

/**
 * @param array<int, array<string, mixed>> $players
 */
function amiga_post_game_player_transfer_record_count(
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
            $players[$prevOpponentId] = amiga_post_game_player_load($con, $prevOpponentId, $beforeGameId);
        }
        $prev = $players[$prevOpponentId];
        $players[$prevOpponentId][$opponentAttr] = max(0, (int) $prev[$opponentAttr] - 1);
    }
    if ($newOpponentId > 0) {
        if (!isset($players[$newOpponentId])) {
            $players[$newOpponentId] = amiga_post_game_player_load($con, $newOpponentId, $beforeGameId);
        }
        $nxt = $players[$newOpponentId];
        $players[$newOpponentId][$opponentAttr] = (int) $nxt[$opponentAttr] + 1;
    }
}

/**
 * @param array<int, array<string, mixed>> $players
 */
function amiga_post_game_player_apply_match(
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
        $players[$playerId] = amiga_post_game_player_load($con, $playerId, $beforeGameId);
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
            amiga_post_game_player_transfer_record_count(
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
            amiga_post_game_player_transfer_record_count(
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
            amiga_post_game_player_transfer_record_count(
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
            amiga_post_game_player_transfer_record_count(
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
            amiga_post_game_player_transfer_record_count(
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
            amiga_post_game_player_transfer_record_count(
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
 * @param array<int, array<string, mixed>> $players
 */
function amiga_post_game_player_apply_conceded_network(
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
        $players[$victimId] = amiga_post_game_player_load($con, $victimId, $beforeGameId);
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
