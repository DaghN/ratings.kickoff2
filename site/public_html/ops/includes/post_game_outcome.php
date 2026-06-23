<?php
/**
 * Match outcome from goals (mirrors scripts/k2_rating_core/outcome.py).
 */
declare(strict_types=1);

require_once __DIR__ . '/post_game_constants.php';

/**
 * @return array{
 *   actual_score: float,
 *   winner_id: int,
 *   sum_of_goals: int,
 *   goal_difference: int,
 *   home_win: int,
 *   draw: int,
 *   away_win: int,
 *   dd_player_a: int,
 *   dd_player_b: int,
 *   cs_player_a: int,
 *   cs_player_b: int
 * }
 */
function k2_post_game_outcome_from_goals(int $goalsA, int $goalsB, int $idA, int $idB): array
{
    if ($goalsA > $goalsB) {
        return [
            'actual_score' => 1.0,
            'winner_id' => $idA,
            'sum_of_goals' => $goalsA + $goalsB,
            'goal_difference' => $goalsA - $goalsB,
            'home_win' => 1,
            'draw' => 0,
            'away_win' => 0,
            'dd_player_a' => $goalsA >= 10 ? 1 : 0,
            'dd_player_b' => $goalsB >= 10 ? 1 : 0,
            'cs_player_a' => $goalsB === 0 ? 1 : 0,
            'cs_player_b' => $goalsA === 0 ? 1 : 0,
        ];
    }
    if ($goalsA < $goalsB) {
        return [
            'actual_score' => 0.0,
            'winner_id' => $idB,
            'sum_of_goals' => $goalsA + $goalsB,
            'goal_difference' => $goalsB - $goalsA,
            'home_win' => 0,
            'draw' => 0,
            'away_win' => 1,
            'dd_player_a' => $goalsA >= 10 ? 1 : 0,
            'dd_player_b' => $goalsB >= 10 ? 1 : 0,
            'cs_player_a' => $goalsB === 0 ? 1 : 0,
            'cs_player_b' => $goalsA === 0 ? 1 : 0,
        ];
    }

    return [
        'actual_score' => 0.5,
        'winner_id' => K2_POST_GAME_WINNER_ID_DRAW,
        'sum_of_goals' => $goalsA + $goalsB,
        'goal_difference' => 0,
        'home_win' => 0,
        'draw' => 1,
        'away_win' => 0,
        'dd_player_a' => $goalsA >= 10 ? 1 : 0,
        'dd_player_b' => $goalsB >= 10 ? 1 : 0,
        'cs_player_a' => $goalsB === 0 ? 1 : 0,
        'cs_player_b' => $goalsA === 0 ? 1 : 0,
    ];
}
