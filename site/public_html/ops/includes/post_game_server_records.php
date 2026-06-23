<?php
/**
 * Server hall-of-fame state during replay (mirrors scripts/k2_rating_core/server_records.py).
 */
declare(strict_types=1);

/**
 * @return array<string, mixed>
 */
function k2_post_game_server_records_new(): array
{
    $holderInt = static fn (): array => [
        'value' => 0,
        'holder_id' => 0,
        'holder_name' => '',
        'date' => null,
        'game_id' => null,
    ];
    $holderFloat = static fn (): array => [
        'value' => 0.0,
        'holder_id' => 0,
        'holder_name' => '',
        'date' => null,
    ];
    $holderPair = static fn (): array => [
        'value' => 0,
        'holder_id_a' => 0,
        'holder_id_b' => 0,
        'holder_name_a' => '',
        'holder_name_b' => '',
        'date' => null,
        'game_id' => null,
    ];

    return [
        'most_games' => $holderInt(),
        'most_wins' => $holderInt(),
        'most_goals' => $holderInt(),
        'most_goals_one_game' => $holderInt(),
        'biggest_win_margin' => $holderInt(),
        'biggest_draw_sum' => $holderPair(),
        'biggest_sum_goals' => $holderPair(),
        'most_double_digits' => $holderInt(),
        'most_clean_sheets' => $holderInt(),
        'most_different_opponents' => $holderInt(),
        'most_different_victims' => $holderInt(),
        'most_dd_victims' => $holderInt(),
        'most_cs_victims' => $holderInt(),
        'biggest_rating_ascent' => $holderFloat(),
        'biggest_peak_rating' => $holderFloat(),
        'longest_win_streak' => $holderInt(),
        'longest_draw_streak' => $holderInt(),
        'longest_non_loss_streak' => $holderInt(),
    ];
}

function k2_post_game_server_try_int_max(
    array &$holder,
    int $value,
    int $holderId,
    string $holderName,
    ?string $gameDate,
    ?int $gameId = null
): void {
    if ($value > (int) $holder['value']) {
        $holder['value'] = $value;
        $holder['holder_id'] = $holderId;
        $holder['holder_name'] = $holderName;
        $holder['date'] = $gameDate;
        $holder['game_id'] = $gameId;
    }
}

function k2_post_game_server_try_float_max(
    array &$holder,
    float $value,
    int $holderId,
    string $holderName,
    ?string $gameDate
): void {
    if ($value > (float) $holder['value']) {
        $holder['value'] = $value;
        $holder['holder_id'] = $holderId;
        $holder['holder_name'] = $holderName;
        $holder['date'] = $gameDate;
    }
}

function k2_post_game_server_try_pair_max(
    array &$holder,
    int $value,
    int $idA,
    int $idB,
    string $nameA,
    string $nameB,
    ?string $gameDate,
    int $gameId
): void {
    if ($value > (int) $holder['value']) {
        $holder['value'] = $value;
        $holder['holder_id_a'] = $idA;
        $holder['holder_id_b'] = $idB;
        $holder['holder_name_a'] = $nameA;
        $holder['holder_name_b'] = $nameB;
        $holder['date'] = $gameDate;
        $holder['game_id'] = $gameId;
    }
}

/**
 * @param array<string, mixed> $state
 * @param array<string, mixed> $game
 * @param array<string, mixed> $derived
 * @param array<int, array<string, mixed>> $players
 * @param array<int, string> $names
 */
function k2_post_game_update_server_records_after_game(
    array &$state,
    array $game,
    array $derived,
    array &$players,
    array $names
): void {
    $gameId = (int) $game['id'];
    $gameDate = (string) $game['Date'];
    $idA = (int) $game['idA'];
    $idB = (int) $game['idB'];
    $goalsA = (int) $game['GoalsA'];
    $goalsB = (int) $game['GoalsB'];
    $nameA = $names[$idA] ?? '';
    $nameB = $names[$idB] ?? '';
    $actualScore = (float) $derived['ActualScore'];
    $goalDifference = (int) $derived['GoalDifference'];
    $sumOfGoals = (int) $derived['SumOfGoals'];
    $ddA = (bool) $derived['DDPlayerA'];
    $ddB = (bool) $derived['DDPlayerB'];
    $csA = (bool) $derived['CSPlayerA'];
    $csB = (bool) $derived['CSPlayerB'];

    if (!isset($players[$idA], $players[$idB])) {
        throw new RuntimeException("server records: missing player state for idA={$idA} idB={$idB}");
    }
    $pa = $players[$idA];
    $pb = $players[$idB];

    foreach (
        [
            [$idA, $nameA, $pa],
            [$idB, $nameB, $pb],
        ] as [$pid, $pname, $st]
    ) {
        k2_post_game_server_try_int_max($state['most_games'], (int) $st['games'], $pid, $pname, $gameDate);
        k2_post_game_server_try_int_max($state['most_wins'], (int) $st['wins'], $pid, $pname, $gameDate);
        k2_post_game_server_try_int_max($state['most_goals'], (int) $st['goals_for'], $pid, $pname, $gameDate);

        $flags = $st['game_flags'] ?? [];
        if (!empty($flags['new_opponent'])) {
            k2_post_game_server_try_int_max(
                $state['most_different_opponents'],
                (int) $st['different_opponents'],
                $pid,
                $pname,
                $gameDate
            );
        }
        if (!empty($flags['new_victim'])) {
            k2_post_game_server_try_int_max(
                $state['most_different_victims'],
                (int) $st['different_victims'],
                $pid,
                $pname,
                $gameDate
            );
        }
        if (!empty($flags['new_dd_victim'])) {
            k2_post_game_server_try_int_max(
                $state['most_dd_victims'],
                (int) $st['double_digits_victims'],
                $pid,
                $pname,
                $gameDate
            );
        }
        if (!empty($flags['new_cs_victim'])) {
            k2_post_game_server_try_int_max(
                $state['most_cs_victims'],
                (int) $st['clean_sheets_victims'],
                $pid,
                $pname,
                $gameDate
            );
        }

        if (($ddA && $pid === $idA) || ($ddB && $pid === $idB)) {
            k2_post_game_server_try_int_max(
                $state['most_double_digits'],
                (int) $st['double_digits'],
                $pid,
                $pname,
                $gameDate
            );
        }
        if (($csA && $pid === $idA) || ($csB && $pid === $idB)) {
            k2_post_game_server_try_int_max(
                $state['most_clean_sheets'],
                (int) $st['clean_sheets'],
                $pid,
                $pname,
                $gameDate
            );
        }

        k2_post_game_server_try_float_max(
            $state['biggest_rating_ascent'],
            (float) $st['current_rating_ascent'],
            $pid,
            $pname,
            $gameDate
        );
        k2_post_game_server_try_float_max(
            $state['biggest_peak_rating'],
            (float) $st['peak_rating'],
            $pid,
            $pname,
            $gameDate
        );
        k2_post_game_server_try_int_max(
            $state['longest_win_streak'],
            (int) $st['longest_winning_streak'],
            $pid,
            $pname,
            $gameDate
        );
        k2_post_game_server_try_int_max(
            $state['longest_non_loss_streak'],
            (int) $st['longest_non_loss_streak'],
            $pid,
            $pname,
            $gameDate
        );
        if ($actualScore === 0.5) {
            k2_post_game_server_try_int_max(
                $state['longest_draw_streak'],
                (int) $st['longest_drawing_streak'],
                $pid,
                $pname,
                $gameDate
            );
        }
    }

    k2_post_game_server_try_int_max(
        $state['most_goals_one_game'],
        $goalsA,
        $idA,
        $nameA,
        $gameDate,
        $gameId
    );
    k2_post_game_server_try_int_max(
        $state['most_goals_one_game'],
        $goalsB,
        $idB,
        $nameB,
        $gameDate,
        $gameId
    );

    if ($actualScore === 1.0) {
        k2_post_game_server_try_int_max(
            $state['biggest_win_margin'],
            $goalDifference,
            $idA,
            $nameA,
            $gameDate,
            $gameId
        );
    } elseif ($actualScore === 0.0) {
        k2_post_game_server_try_int_max(
            $state['biggest_win_margin'],
            $goalDifference,
            $idB,
            $nameB,
            $gameDate,
            $gameId
        );
    }

    if ($actualScore === 0.5) {
        k2_post_game_server_try_pair_max(
            $state['biggest_draw_sum'],
            $sumOfGoals,
            $idA,
            $idB,
            $nameA,
            $nameB,
            $gameDate,
            $gameId
        );
    }

    k2_post_game_server_try_pair_max(
        $state['biggest_sum_goals'],
        $sumOfGoals,
        $idA,
        $idB,
        $nameA,
        $nameB,
        $gameDate,
        $gameId
    );
}

/**
 * Load holder state from generalstatstable id=1 row (DB source of truth between games).
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function k2_post_game_server_records_from_gst_row(array $row): array
{
    $int = static fn (string $prefix): array => [
        'value' => (int) ($row[$prefix] ?? 0),
        'holder_id' => (int) ($row[$prefix . 'ID'] ?? 0),
        'holder_name' => (string) ($row[$prefix . 'Name'] ?? ''),
        'date' => $row[$prefix . 'Date'] ?? null,
        'game_id' => isset($row[$prefix . 'GameID']) ? (int) $row[$prefix . 'GameID'] : null,
    ];
    $float = static fn (string $prefix): array => [
        'value' => (float) ($row[$prefix] ?? 0),
        'holder_id' => (int) ($row[$prefix . 'ID'] ?? 0),
        'holder_name' => (string) ($row[$prefix . 'Name'] ?? ''),
        'date' => $row[$prefix . 'Date'] ?? null,
    ];
    $pair = static fn (string $prefix): array => [
        'value' => (int) ($row[$prefix] ?? 0),
        'holder_id_a' => (int) ($row[$prefix . 'IDA'] ?? 0),
        'holder_id_b' => (int) ($row[$prefix . 'IDB'] ?? 0),
        'holder_name_a' => (string) ($row[$prefix . 'NameA'] ?? ''),
        'holder_name_b' => (string) ($row[$prefix . 'NameB'] ?? ''),
        'date' => $row[$prefix . 'Date'] ?? null,
        'game_id' => isset($row[$prefix . 'GameID']) ? (int) $row[$prefix . 'GameID'] : null,
    ];

    return [
        'most_games' => $int('MostGamesPlayed'),
        'most_wins' => $int('MostWins'),
        'most_goals' => $int('MostGoalsScored'),
        'most_goals_one_game' => $int('MostGoalsScoredInOneGame'),
        'biggest_win_margin' => $int('BiggestWinDifference'),
        'biggest_draw_sum' => $pair('BiggestDrawSum'),
        'biggest_sum_goals' => $pair('BiggestSumOfGoals'),
        'most_double_digits' => $int('MostDoubleDigits'),
        'most_clean_sheets' => $int('MostCleanSheets'),
        'most_different_opponents' => $int('MostDifferentOpponents'),
        'most_different_victims' => $int('MostDifferentVictims'),
        'most_dd_victims' => $int('MostDoubleDigitsVictims'),
        'most_cs_victims' => $int('MostCleanSheetsVictims'),
        'biggest_rating_ascent' => $float('BiggestRatingAscent'),
        'biggest_peak_rating' => $float('BiggestPeakRating'),
        'longest_win_streak' => $int('LongestWinningStreak'),
        'longest_draw_streak' => $int('LongestDrawingStreak'),
        'longest_non_loss_streak' => $int('LongestNonLossStreak'),
    ];
}

/**
 * @param array<string, mixed> $state
 * @return array<string, mixed>
 */
function k2_post_game_server_holder_patch(array $state): array
{
    $fmt = static fn ($value): ?string => $value === null ? null : (string) $value;

    return [
        'MostGamesPlayed' => $state['most_games']['value'],
        'MostGamesPlayedID' => $state['most_games']['holder_id'],
        'MostGamesPlayedName' => $state['most_games']['holder_name'],
        'MostGamesPlayedDate' => $fmt($state['most_games']['date']),
        'MostWins' => $state['most_wins']['value'],
        'MostWinsID' => $state['most_wins']['holder_id'],
        'MostWinsName' => $state['most_wins']['holder_name'],
        'MostWinsDate' => $fmt($state['most_wins']['date']),
        'MostGoalsScored' => $state['most_goals']['value'],
        'MostGoalsScoredID' => $state['most_goals']['holder_id'],
        'MostGoalsScoredName' => $state['most_goals']['holder_name'],
        'MostGoalsScoredDate' => $fmt($state['most_goals']['date']),
        'MostGoalsScoredInOneGame' => $state['most_goals_one_game']['value'],
        'MostGoalsScoredInOneGameID' => $state['most_goals_one_game']['holder_id'],
        'MostGoalsScoredInOneGameName' => $state['most_goals_one_game']['holder_name'],
        'MostGoalsScoredInOneGameDate' => $fmt($state['most_goals_one_game']['date']),
        'MostGoalsScoredInOneGameGameID' => $state['most_goals_one_game']['game_id'],
        'BiggestWinDifference' => $state['biggest_win_margin']['value'],
        'BiggestWinDifferenceID' => $state['biggest_win_margin']['holder_id'],
        'BiggestWinDifferenceName' => $state['biggest_win_margin']['holder_name'],
        'BiggestWinDifferenceDate' => $fmt($state['biggest_win_margin']['date']),
        'BiggestWinDifferenceGameID' => $state['biggest_win_margin']['game_id'],
        'BiggestDrawSum' => $state['biggest_draw_sum']['value'],
        'BiggestDrawSumIDA' => $state['biggest_draw_sum']['holder_id_a'],
        'BiggestDrawSumIDB' => $state['biggest_draw_sum']['holder_id_b'],
        'BiggestDrawSumNameA' => $state['biggest_draw_sum']['holder_name_a'],
        'BiggestDrawSumNameB' => $state['biggest_draw_sum']['holder_name_b'],
        'BiggestDrawSumDate' => $fmt($state['biggest_draw_sum']['date']),
        'BiggestDrawSumGameID' => $state['biggest_draw_sum']['game_id'],
        'BiggestSumOfGoals' => $state['biggest_sum_goals']['value'],
        'BiggestSumOfGoalsIDA' => $state['biggest_sum_goals']['holder_id_a'],
        'BiggestSumOfGoalsIDB' => $state['biggest_sum_goals']['holder_id_b'],
        'BiggestSumOfGoalsNameA' => $state['biggest_sum_goals']['holder_name_a'],
        'BiggestSumOfGoalsNameB' => $state['biggest_sum_goals']['holder_name_b'],
        'BiggestSumOfGoalsDate' => $fmt($state['biggest_sum_goals']['date']),
        'BiggestSumOfGoalsGameID' => $state['biggest_sum_goals']['game_id'],
        'MostDoubleDigits' => $state['most_double_digits']['value'],
        'MostDoubleDigitsID' => $state['most_double_digits']['holder_id'],
        'MostDoubleDigitsName' => $state['most_double_digits']['holder_name'],
        'MostDoubleDigitsDate' => $fmt($state['most_double_digits']['date']),
        'MostCleanSheets' => $state['most_clean_sheets']['value'],
        'MostCleanSheetsID' => $state['most_clean_sheets']['holder_id'],
        'MostCleanSheetsName' => $state['most_clean_sheets']['holder_name'],
        'MostCleanSheetsDate' => $fmt($state['most_clean_sheets']['date']),
        'MostDifferentOpponents' => $state['most_different_opponents']['value'],
        'MostDifferentOpponentsID' => $state['most_different_opponents']['holder_id'],
        'MostDifferentOpponentsName' => $state['most_different_opponents']['holder_name'],
        'MostDifferentOpponentsDate' => $fmt($state['most_different_opponents']['date']),
        'MostDifferentVictims' => $state['most_different_victims']['value'],
        'MostDifferentVictimsID' => $state['most_different_victims']['holder_id'],
        'MostDifferentVictimsName' => $state['most_different_victims']['holder_name'],
        'MostDifferentVictimsDate' => $fmt($state['most_different_victims']['date']),
        'MostDoubleDigitsVictims' => $state['most_dd_victims']['value'],
        'MostDoubleDigitsVictimsID' => $state['most_dd_victims']['holder_id'],
        'MostDoubleDigitsVictimsName' => $state['most_dd_victims']['holder_name'],
        'MostDoubleDigitsVictimsDate' => $fmt($state['most_dd_victims']['date']),
        'MostCleanSheetsVictims' => $state['most_cs_victims']['value'],
        'MostCleanSheetsVictimsID' => $state['most_cs_victims']['holder_id'],
        'MostCleanSheetsVictimsName' => $state['most_cs_victims']['holder_name'],
        'MostCleanSheetsVictimsDate' => $fmt($state['most_cs_victims']['date']),
        'BiggestRatingAscent' => $state['biggest_rating_ascent']['value'] > 0
            ? (float) $state['biggest_rating_ascent']['value'] : null,
        'BiggestRatingAscentID' => $state['biggest_rating_ascent']['holder_id'],
        'BiggestRatingAscentName' => $state['biggest_rating_ascent']['holder_name'],
        'BiggestRatingAscentDate' => $fmt($state['biggest_rating_ascent']['date']),
        'BiggestPeakRating' => $state['biggest_peak_rating']['value'] > 0
            ? (float) $state['biggest_peak_rating']['value'] : null,
        'BiggestPeakRatingID' => $state['biggest_peak_rating']['holder_id'],
        'BiggestPeakRatingName' => $state['biggest_peak_rating']['holder_name'],
        'BiggestPeakRatingDate' => $fmt($state['biggest_peak_rating']['date']),
        'LongestWinningStreak' => $state['longest_win_streak']['value'],
        'LongestWinningStreakID' => $state['longest_win_streak']['holder_id'],
        'LongestWinningStreakName' => $state['longest_win_streak']['holder_name'],
        'LongestWinningStreakDate' => $fmt($state['longest_win_streak']['date']),
        'LongestDrawingStreak' => $state['longest_draw_streak']['value'],
        'LongestDrawingStreakID' => $state['longest_draw_streak']['holder_id'],
        'LongestDrawingStreakName' => $state['longest_draw_streak']['holder_name'],
        'LongestDrawingStreakDate' => $fmt($state['longest_draw_streak']['date']),
        'LongestNonLossStreak' => $state['longest_non_loss_streak']['value'],
        'LongestNonLossStreakID' => $state['longest_non_loss_streak']['holder_id'],
        'LongestNonLossStreakName' => $state['longest_non_loss_streak']['holder_name'],
        'LongestNonLossStreakDate' => $fmt($state['longest_non_loss_streak']['date']),
    ];
}
