<?php
/**
 * playertable load/write + prior-game network sets for per-game post-game (P2).
 */
declare(strict_types=1);

require_once __DIR__ . '/post_game_constants.php';
require_once __DIR__ . '/post_game_player_state.php';

function k2_post_game_db_float(mixed $val, float $default = 0.0): float
{
    if ($val === null || $val === '') {
        return $default;
    }

    return (float) $val;
}

function k2_post_game_db_int(mixed $val, int $default = 0): int
{
    if ($val === null || $val === '') {
        return $default;
    }

    return (int) $val;
}

/**
 * Pre-game ratings for both sides (one round-trip).
 *
 * @return array{a: float, b: float}
 */
function k2_post_game_load_player_ratings(mysqli $con, int $idA, int $idB): array
{
    $stmt = $con->prepare('SELECT ID, Rating FROM playertable WHERE ID IN (?, ?)');
    if ($stmt === false) {
        throw new RuntimeException('prepare playertable ratings: ' . $con->error);
    }
    $stmt->bind_param('ii', $idA, $idB);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute playertable ratings: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $ratings = [];
    while ($row = $res->fetch_assoc()) {
        $ratings[(int) $row['ID']] = k2_post_game_db_float(
            $row['Rating'] ?? null,
            K2_POST_GAME_START_RATING
        );
    }
    $stmt->close();

    // Mirror Python players.setdefault: rated game may reference IDs not in playertable yet.
    $start = K2_POST_GAME_START_RATING;

    return [
        'a' => $ratings[$idA] ?? $start,
        'b' => $ratings[$idB] ?? $start,
    ];
}

/**
 * Build opponent/victim/culprit sets from processed games before $beforeGameId.
 *
 * @return array{
 *   _network_opponents: array<int, true>,
 *   _network_victims: array<int, true>,
 *   _network_culprits: array<int, true>,
 *   _network_dd_victims: array<int, true>,
 *   _network_dd_culprits: array<int, true>,
 *   _network_cs_victims: array<int, true>,
 *   _network_cs_culprits: array<int, true>
 * }
 */
function k2_post_game_build_network_sets(mysqli $con, int $playerId, int $beforeGameId): array
{
    $sets = [
        '_network_opponents' => [],
        '_network_victims' => [],
        '_network_culprits' => [],
        '_network_dd_victims' => [],
        '_network_dd_culprits' => [],
        '_network_cs_victims' => [],
        '_network_cs_culprits' => [],
    ];

    $sql = 'SELECT idA, idB, ActualScore, DDPlayerA, DDPlayerB, CSPlayerA, CSPlayerB '
        . 'FROM ratedresults WHERE id < ? AND NewRatingA IS NOT NULL '
        . 'AND (idA = ? OR idB = ?) ORDER BY id ASC';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare network sets: ' . $con->error);
    }
    $stmt->bind_param('iii', $beforeGameId, $playerId, $playerId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute network sets: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $idA = (int) $row['idA'];
        $idB = (int) $row['idB'];
        $score = (float) $row['ActualScore'];
        $ddA = (int) $row['DDPlayerA'];
        $ddB = (int) $row['DDPlayerB'];
        $csA = (int) $row['CSPlayerA'];
        $csB = (int) $row['CSPlayerB'];

        if ($idA === $playerId) {
            $opp = $idB;
            $playerScore = $score;
            $ddFor = $ddA === 1;
            $csFor = $csA === 1;
            $ddAgainst = $ddB === 1;
            $csAgainst = $csB === 1;
        } else {
            $opp = $idA;
            $playerScore = $score === 0.5 ? 0.5 : 1.0 - $score;
            $ddFor = $ddB === 1;
            $csFor = $csB === 1;
            $ddAgainst = $ddA === 1;
            $csAgainst = $csA === 1;
        }

        $sets['_network_opponents'][$opp] = true;
        if ($playerScore === 1.0) {
            $sets['_network_victims'][$opp] = true;
        } elseif ($playerScore === 0.0) {
            $sets['_network_culprits'][$opp] = true;
        }
        if ($ddFor) {
            $sets['_network_dd_victims'][$opp] = true;
        }
        if ($ddAgainst) {
            $sets['_network_dd_culprits'][$opp] = true;
        }
        if ($csFor) {
            $sets['_network_cs_victims'][$opp] = true;
        }
        if ($csAgainst) {
            $sets['_network_cs_culprits'][$opp] = true;
        }
    }
    $stmt->close();

    return $sets;
}

/**
 * @param array<string, mixed> $row playertable fetch_assoc
 * @return array<string, mixed>
 */
function k2_post_game_player_state_from_db_row(array $row): array
{
    $games = k2_post_game_db_int($row['NumberGames'] ?? null, 0);
    if ($games <= 0) {
        $st = k2_post_game_player_state_new();
        $st['rating'] = k2_post_game_db_float($row['Rating'] ?? null, K2_POST_GAME_START_RATING);

        return $st;
    }

    return [
        'rating' => k2_post_game_db_float($row['Rating'] ?? null, K2_POST_GAME_START_RATING),
        'display' => k2_post_game_db_int($row['Display'] ?? null, 0),
        'games' => $games,
        'wins' => k2_post_game_db_int($row['NumberWins'] ?? null, 0),
        'draws' => k2_post_game_db_int($row['NumberDraws'] ?? null, 0),
        'losses' => k2_post_game_db_int($row['NumberLosses'] ?? null, 0),
        'goals_for' => k2_post_game_db_int($row['GoalsFor'] ?? null, 0),
        'goals_against' => k2_post_game_db_int($row['GoalsAgainst'] ?? null, 0),
        'most_goals_scored' => k2_post_game_db_int($row['MostGoalsScored'] ?? null, 0),
        'least_goals_scored' => k2_post_game_db_int($row['LeastGoalsScored'] ?? null, K2_POST_GAME_SENTINEL_LEAST_GOALS),
        'most_goals_conceded' => k2_post_game_db_int($row['MostGoalsConceded'] ?? null, 0),
        'least_goals_conceded' => k2_post_game_db_int($row['LeastGoalsConceded'] ?? null, K2_POST_GAME_SENTINEL_LEAST_GOALS),
        'biggest_win_difference' => k2_post_game_db_int($row['BiggestWinDifference'] ?? null, 0),
        'biggest_draw_sum' => k2_post_game_db_int($row['BiggestDrawSum'] ?? null, 0),
        'biggest_loss_difference' => k2_post_game_db_int($row['BiggestLossDifference'] ?? null, 0),
        'smallest_sum_of_goals' => k2_post_game_db_int($row['SmallestSumOfGoals'] ?? null, K2_POST_GAME_SENTINEL_LEAST_GOALS),
        'biggest_sum_of_goals' => k2_post_game_db_int($row['BiggestSumOfGoals'] ?? null, 0),
        'double_digits' => k2_post_game_db_int($row['DoubleDigits'] ?? null, 0),
        'clean_sheets' => k2_post_game_db_int($row['CleanSheets'] ?? null, 0),
        'double_digits_conceded' => k2_post_game_db_int($row['DoubleDigitsConceded'] ?? null, 0),
        'clean_sheets_conceded' => k2_post_game_db_int($row['CleanSheetsConceded'] ?? null, 0),
        'different_opponents' => k2_post_game_db_int($row['DifferentOpponents'] ?? null, 0),
        'different_victims' => k2_post_game_db_int($row['DifferentVictims'] ?? null, 0),
        'double_digits_victims' => k2_post_game_db_int($row['DoubleDigitsVictims'] ?? null, 0),
        'clean_sheets_victims' => k2_post_game_db_int($row['CleanSheetsVictims'] ?? null, 0),
        'most_goals_conceded_victims' => k2_post_game_db_int($row['MostGoalsConcededVictims'] ?? null, 0),
        'least_goals_scored_victims' => k2_post_game_db_int($row['LeastGoalsScoredVictims'] ?? null, 0),
        'biggest_loss_victims' => k2_post_game_db_int($row['BiggestLossVictims'] ?? null, 0),
        'different_culprits' => k2_post_game_db_int($row['DifferentCulprits'] ?? null, 0),
        'double_digits_culprits' => k2_post_game_db_int($row['DoubleDigitsCulprits'] ?? null, 0),
        'clean_sheets_culprits' => k2_post_game_db_int($row['CleanSheetsCulprits'] ?? null, 0),
        'most_goals_scored_culprits' => k2_post_game_db_int($row['MostGoalsScoredCulprits'] ?? null, 0),
        'least_goals_conceded_culprits' => k2_post_game_db_int($row['LeastGoalsConcededCulprits'] ?? null, 0),
        'biggest_win_culprits' => k2_post_game_db_int($row['BiggestWinCulprits'] ?? null, 0),
        'sum_opponents_rating' => k2_post_game_db_float($row['SumOfOpponentsRating'] ?? null, 0.0),
        'highest_rated_victim' => k2_post_game_db_float($row['HighestRatedVictim'] ?? null, 0.0),
        'lowest_rated_culprit' => k2_post_game_db_float(
            $row['LowestRatedCulprit'] ?? null,
            K2_POST_GAME_SENTINEL_LOWEST_RATING
        ),
        'current_rating_ascent' => k2_post_game_db_float($row['CurrentRatingAscent'] ?? null, 0.0),
        'biggest_rating_ascent' => k2_post_game_db_float($row['BiggestRatingAscent'] ?? null, 0.0),
        'current_rating_descent' => k2_post_game_db_float($row['CurrentRatingDescent'] ?? null, 0.0),
        'biggest_rating_descent' => k2_post_game_db_float($row['BiggestRatingDescent'] ?? null, 0.0),
        'lowest_rating' => k2_post_game_db_float($row['LowestRating'] ?? null, K2_POST_GAME_SENTINEL_LOWEST_RATING),
        'peak_rating' => k2_post_game_db_float($row['PeakRating'] ?? null, 0.0),
        'winning_streak' => k2_post_game_db_int($row['WinningStreak'] ?? null, 0),
        'drawing_streak' => k2_post_game_db_int($row['DrawingStreak'] ?? null, 0),
        'losing_streak' => k2_post_game_db_int($row['LosingStreak'] ?? null, 0),
        'non_win_streak' => k2_post_game_db_int($row['NonWinStreak'] ?? null, 0),
        'non_draw_streak' => k2_post_game_db_int($row['NonDrawStreak'] ?? null, 0),
        'non_loss_streak' => k2_post_game_db_int($row['NonLossStreak'] ?? null, 0),
        'longest_winning_streak' => k2_post_game_db_int($row['LongestWinningStreak'] ?? null, 0),
        'longest_drawing_streak' => k2_post_game_db_int($row['LongestDrawingStreak'] ?? null, 0),
        'longest_losing_streak' => k2_post_game_db_int($row['LongestLosingStreak'] ?? null, 0),
        'longest_non_win_streak' => k2_post_game_db_int($row['LongestNonWinStreak'] ?? null, 0),
        'longest_non_draw_streak' => k2_post_game_db_int($row['LongestNonDrawStreak'] ?? null, 0),
        'longest_non_loss_streak' => k2_post_game_db_int($row['LongestNonLossStreak'] ?? null, 0),
        'score_streak' => k2_post_game_db_int($row['ScoreStreak'] ?? null, 0),
        'merchant_streak' => k2_post_game_db_int($row['MerchantStreak'] ?? null, 0),
        'exact_ten_goal_streak' => k2_post_game_db_int($row['ExactTenGoalStreak'] ?? null, 0),
        'win_margin_one_streak' => k2_post_game_db_int($row['WinMarginOneStreak'] ?? null, 0),
        'loss_margin_one_streak' => k2_post_game_db_int($row['LossMarginOneStreak'] ?? null, 0),
        'last_game' => $row['LastGame'] ?? K2_POST_GAME_LASTGAME_RESET,
        'last_game_id' => $row['LastGameGameID'] !== null ? (int) $row['LastGameGameID'] : null,
        'last_win_game_id' => $row['LastWinGameID'] !== null ? (int) $row['LastWinGameID'] : null,
        'last_draw_game_id' => $row['LastDrawGameID'] !== null ? (int) $row['LastDrawGameID'] : null,
        'last_loss_game_id' => $row['LastLossGameID'] !== null ? (int) $row['LastLossGameID'] : null,
        'lowest_rating_game_id' => $row['LowestRatingGameID'] !== null ? (int) $row['LowestRatingGameID'] : null,
        'peak_rating_game_id' => $row['PeakRatingGameID'] !== null ? (int) $row['PeakRatingGameID'] : null,
        'most_goals_scored_game_id' => $row['MostGoalsScoredGameID'] !== null ? (int) $row['MostGoalsScoredGameID'] : null,
        'least_goals_scored_game_id' => $row['LeastGoalsScoredGameID'] !== null ? (int) $row['LeastGoalsScoredGameID'] : null,
        'most_goals_conceded_game_id' => $row['MostGoalsConcededGameID'] !== null ? (int) $row['MostGoalsConcededGameID'] : null,
        'least_goals_conceded_game_id' => $row['LeastGoalsConcededGameID'] !== null ? (int) $row['LeastGoalsConcededGameID'] : null,
        'biggest_win_game_id' => $row['BiggestWinGameID'] !== null ? (int) $row['BiggestWinGameID'] : null,
        'biggest_draw_game_id' => $row['BiggestDrawGameID'] !== null ? (int) $row['BiggestDrawGameID'] : null,
        'biggest_loss_game_id' => $row['BiggestLossGameID'] !== null ? (int) $row['BiggestLossGameID'] : null,
        'smallest_sum_of_goals_game_id' => $row['SmallestSumOfGoalsGameID'] !== null ? (int) $row['SmallestSumOfGoalsGameID'] : null,
        'biggest_sum_of_goals_game_id' => $row['BiggestSumOfGoalsGameID'] !== null ? (int) $row['BiggestSumOfGoalsGameID'] : null,
        'most_goals_scored_victim_id' => k2_post_game_db_int($row['MostGoalsScoredVictimID'] ?? null, 0),
        'least_goals_conceded_victim_id' => k2_post_game_db_int($row['LeastGoalsConcededVictimID'] ?? null, 0),
        'biggest_win_victim_id' => k2_post_game_db_int($row['BiggestWinVictimID'] ?? null, 0),
        'most_goals_conceded_culprit_id' => k2_post_game_db_int($row['MostGoalsConcededCulpritID'] ?? null, 0),
        'least_goals_scored_culprit_id' => k2_post_game_db_int($row['LeastGoalsScoredCulpritID'] ?? null, 0),
        'biggest_loss_culprit_id' => k2_post_game_db_int($row['BiggestLossCulpritID'] ?? null, 0),
        'highest_rated_victim_game_id' => $row['HighestRatedVictimGameID'] !== null ? (int) $row['HighestRatedVictimGameID'] : null,
        'lowest_rated_culprit_game_id' => $row['LowestRatedCulpritGameID'] !== null ? (int) $row['LowestRatedCulpritGameID'] : null,
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
 * @return array<string, mixed>
 */
function k2_post_game_player_load(mysqli $con, int $playerId, int $beforeGameId): array
{
    $stmt = $con->prepare('SELECT * FROM playertable WHERE ID = ? LIMIT 1');
    if ($stmt === false) {
        throw new RuntimeException('prepare playertable load: ' . $con->error);
    }
    $stmt->bind_param('i', $playerId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute playertable load: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : false;
    $stmt->close();
    if ($row === false || $row === null) {
        $st = k2_post_game_player_state_new();
    } else {
        $st = k2_post_game_player_state_from_db_row($row);
    }
    $network = k2_post_game_build_network_sets($con, $playerId, $beforeGameId);
    foreach ($network as $key => $set) {
        $st[$key] = $set;
        $countKey = match ($key) {
            '_network_opponents' => 'different_opponents',
            '_network_victims' => 'different_victims',
            '_network_culprits' => 'different_culprits',
            '_network_dd_victims' => 'double_digits_victims',
            '_network_dd_culprits' => 'double_digits_culprits',
            '_network_cs_victims' => 'clean_sheets_victims',
            '_network_cs_culprits' => 'clean_sheets_culprits',
            default => null,
        };
        if ($countKey !== null) {
            $st[$countKey] = count($set);
        }
    }

    return $st;
}

/**
 * @param array<string, mixed> $dbRow from k2_post_game_player_to_db_row
 */
function k2_post_game_player_write(mysqli $con, array $dbRow): void
{
    $playerId = (int) $dbRow['ID'];
    unset($dbRow['ID']);
    $cols = array_keys($dbRow);
    $sets = array_map(static fn (string $c): string => "`{$c}` = ?", $cols);
    $sql = 'UPDATE playertable SET ' . implode(', ', $sets) . ' WHERE ID = ?';

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare playertable write: ' . $con->error);
    }

    $types = '';
    $values = [];
    foreach ($dbRow as $col => $val) {
        if ($val === null) {
            $types .= 's';
            $values[] = null;
        } elseif (is_int($val)) {
            $types .= 'i';
            $values[] = $val;
        } elseif (is_float($val)) {
            $types .= 'd';
            $values[] = $val;
        } else {
            $types .= 's';
            $values[] = (string) $val;
        }
    }
    $types .= 'i';
    $values[] = $playerId;

    $bind = [$types];
    foreach ($values as $i => $v) {
        $bind[] = &$values[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute playertable write id=' . $playerId . ': ' . $stmt->error);
    }
    $stmt->close();
}
