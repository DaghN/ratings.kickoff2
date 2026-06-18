<?php
/**
 * Profile Moments — Total goals bonanza card selection.
 *
 * Primary: playertable BiggestSumOfGoalsGameID when opponent scored < 3× hero.
 * Fallback: highest SumOfGoals career game where the 3× ratio passes (global, not H2H).
 */

function player_feast_bonanza_ratio_ok(int $goalsFor, int $goalsAgainst): bool
{
    return $goalsAgainst < (3 * $goalsFor);
}

/**
 * @return array<string, mixed>|null
 */
function player_feast_load_bonanza_game_row(mysqli $con, int $gameId): ?array
{
    if ($gameId <= 0) {
        return null;
    }
    $gid = (int) $gameId;
    $gRes = k2_player_feast_query(
        $con,
        'trophy_game_shootout',
        "SELECT id, Date, idA, idB, NameA, NameB, GoalsA, GoalsB, ActualScore, AdjustmentA, AdjustmentB, SumOfGoals
         FROM ratedresults WHERE id = $gid LIMIT 1"
    );
    $gRow = $gRes ? mysqli_fetch_assoc($gRes) : null;
    if ($gRow === null) {
        return null;
    }
    $gRow = k2_rated_game_row_resolve($con, $gRow);
    return is_array($gRow) ? $gRow : null;
}

/**
 * Highest combined-goal game where opponent scored < 3× hero.
 *
 * @return array<string, mixed>|null
 */
function player_feast_load_bonanza_ratio_fallback(mysqli $con, int $playerId): ?array
{
    if ($playerId < 1) {
        return null;
    }
    $escPid = (string) (int) $playerId;
    $gRes = k2_player_feast_query(
        $con,
        'trophy_game_shootout_ratio_fallback',
        "SELECT id, Date, idA, idB, NameA, NameB, GoalsA, GoalsB, ActualScore, AdjustmentA, AdjustmentB, SumOfGoals
         FROM ratedresults
         WHERE (idA = '$escPid' AND GoalsB < 3 * GoalsA)
            OR (idB = '$escPid' AND GoalsA < 3 * GoalsB)
         ORDER BY SumOfGoals DESC, Date DESC, id DESC
         LIMIT 1"
    );
    $gRow = $gRes ? mysqli_fetch_assoc($gRes) : null;
    if ($gRow === null) {
        return null;
    }
    $gRow = k2_rated_game_row_resolve($con, $gRow);
    return is_array($gRow) ? $gRow : null;
}

/**
 * @return array<string, mixed>|null Trophy row for Moments mosaic, or omit card.
 */
function player_feast_load_bonanza_trophy(mysqli $con, int $playerId, int $biggestSumGameId): ?array
{
    $def = [
        'key' => 'shootout',
        'label' => 'Total goals bonanza',
        'icon' => '🔥',
        'tag' => 'Chaos',
    ];

    $primaryRow = player_feast_load_bonanza_game_row($con, $biggestSumGameId);
    if ($primaryRow === null) {
        return null;
    }

    $parsed = pm_parse_highlight_row($primaryRow, $playerId);
    if (player_feast_bonanza_ratio_ok((int) $parsed['goals_for'], (int) $parsed['goals_against'])) {
        return array_merge($def, $parsed, ['game_id' => (int) pm_row_col($primaryRow, 'id')]);
    }

    $fallbackRow = player_feast_load_bonanza_ratio_fallback($con, $playerId);
    if ($fallbackRow === null) {
        return null;
    }

    $fallbackParsed = pm_parse_highlight_row($fallbackRow, $playerId);
    return array_merge($def, $fallbackParsed, ['game_id' => (int) pm_row_col($fallbackRow, 'id')]);
}
