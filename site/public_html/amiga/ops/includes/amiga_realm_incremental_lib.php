<?php
/**
 * Incremental realm snapshot compute (prior row + tournament delta).
 *
 * Mirrors scripts/amiga/realm_incremental.py — live finalize path (tier 2).
 *
 * @see docs/amiga-realm-snapshot-policy.md
 */
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/lb_player_filters.php';

/**
 * @return array<string, mixed>
 */
function amiga_realm_load_prior_payload(mysqli $con, int $tournamentId): array
{
    $cutoff = amiga_realm_load_cutoff($con, $tournamentId);
    $sql = '
        SELECT r.*
        FROM amiga_realm_snapshots r
        WHERE (r.event_date, r.event_chrono, r.tournament_id) < (?, ?, ?)
        ORDER BY r.event_date DESC, r.event_chrono DESC, r.tournament_id DESC
        LIMIT 1
    ';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare prior realm: ' . $con->error);
    }
    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tid = (int) $cutoff['tournament_id'];
    $stmt->bind_param('sdi', $eventDate, $chrono, $tid);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute prior realm: ' . $stmt->error);
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row === null) {
        return [];
    }

    return $row;
}

/**
 * @return array{games: int, draws: int, goals: int, dd: int, cs: int}
 */
function amiga_realm_tournament_game_delta(mysqli $con, int $tournamentId): array
{
    $sql = '
        SELECT COUNT(*) AS games,
               SUM(CASE WHEN r.actual_score = 0.5 THEN 1 ELSE 0 END) AS draws,
               COALESCE(SUM(r.sum_of_goals), 0) AS goals,
               COALESCE(SUM(r.dd_player_a + r.dd_player_b), 0) AS dd,
               COALESCE(SUM(r.cs_player_a + r.cs_player_b), 0) AS cs
        FROM amiga_games g
        INNER JOIN amiga_game_ratings r ON r.game_id = g.id
        WHERE g.tournament_id = ?
    ';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare tournament delta: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute tournament delta: ' . $stmt->error);
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [
        'games' => (int) ($row['games'] ?? 0),
        'draws' => (int) ($row['draws'] ?? 0),
        'goals' => (int) ($row['goals'] ?? 0),
        'dd' => (int) ($row['dd'] ?? 0),
        'cs' => (int) ($row['cs'] ?? 0),
    ];
}

/**
 * @param array<string, mixed> $prior
 * @param array{games: int, draws: int, goals: int, dd: int, cs: int} $delta
 * @return array<string, mixed>
 */
function amiga_realm_merge_game_aggregates(
    array $prior,
    array $delta,
    int $numPlayers,
    mixed $diffOppAvg,
): array {
    $games = (int) ($prior['GamesPlayed'] ?? 0) + $delta['games'];
    $draws = (int) ($prior['NumberOfDraws'] ?? 0) + $delta['draws'];
    $decided = $games - $draws;
    $goals = (int) ($prior['GoalsScored'] ?? 0) + $delta['goals'];
    $dd = (int) ($prior['DoubleDigits'] ?? 0) + $delta['dd'];
    $cs = (int) ($prior['CleanSheets'] ?? 0) + $delta['cs'];

    return amiga_realm_aggregate_patch(
        $games,
        $draws,
        $decided,
        $goals,
        $dd,
        $cs,
        $numPlayers,
        $diffOppAvg,
    );
}

/**
 * @return array{0: int, 1: mixed}
 */
function amiga_realm_player_count_stats_present(mysqli $con): array
{
    $res = $con->query(
        'SELECT COUNT(*) AS n FROM amiga_player_current WHERE NumberGames >= 1'
    );
    if ($res === false) {
        throw new RuntimeException('player count: ' . $con->error);
    }
    $numPlayers = (int) $res->fetch_assoc()['n'];
    $res->free();

    $res = $con->query(
        'SELECT AVG(DifferentOpponents) AS a FROM amiga_player_current WHERE DifferentOpponents >= 1'
    );
    if ($res === false) {
        throw new RuntimeException('diff opp avg: ' . $con->error);
    }
    $diffOppAvg = $res->fetch_assoc()['a'];
    $res->free();

    return [$numPlayers, $diffOppAvg];
}

function amiga_realm_beats_holder(
    mixed $candidateValue,
    int $candidateId,
    mixed $holderValue,
    mixed $holderId,
    bool $higherIsBetter,
): bool {
    if ($candidateValue === null) {
        return false;
    }
    $cv = (float) $candidateValue;
    if ($higherIsBetter && $cv <= 0) {
        return false;
    }
    if ($holderValue === null || $holderId === null) {
        return true;
    }
    $hv = (float) $holderValue;
    $hid = (int) $holderId;
    if ($higherIsBetter) {
        return $cv > $hv || ($cv === $hv && $candidateId < $hid);
    }

    return $cv < $hv || ($cv === $hv && $candidateId < $hid);
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_realm_fetch_player_current_rows(mysqli $con): array
{
    $sql = "
        SELECT s.*, p.name AS player_name,
               COALESCE(wcs.tournaments_played, 0) AS wc_slice_tournaments_played,
               wcs.tournaments_played_last_rise_event_date
                   AS wc_slice_tournaments_played_last_rise_event_date,
               COALESCE(DATE_FORMAT(t.event_date, '%Y-%m-%d'), DATE_FORMAT(g.game_date, '%Y-%m-%d')) AS record_date
        FROM amiga_player_current s
        INNER JOIN amiga_players p ON p.id = s.player_id
        LEFT JOIN amiga_player_slice_totals wcs
            ON wcs.player_id = s.player_id AND wcs.slice_key = 'world_cup'
        LEFT JOIN amiga_games g ON g.id = s.LastGameGameID
        LEFT JOIN tournaments t ON t.id = g.tournament_id
        WHERE s.NumberGames >= 1
    ";
    $res = $con->query($sql);
    if ($res === false) {
        throw new RuntimeException('fetch player current: ' . $con->error);
    }
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $res->free();

    return $rows;
}

/**
 * @param list<array<string, mixed>> $playerRows
 * @return array<string, mixed>
 */
function amiga_realm_career_holders_from_rows(array $playerRows): array
{
    $holders = [
        ['NumberGames', 'MostGamesPlayed'],
        ['NumberWins', 'MostWins'],
        ['GoalsFor', 'MostGoalsScored'],
        ['DoubleDigits', 'MostDoubleDigits'],
        ['CleanSheets', 'MostCleanSheets'],
        ['DifferentOpponents', 'MostDifferentOpponents'],
        ['DifferentVictims', 'MostDifferentVictims'],
        ['DoubleDigitsVictims', 'MostDoubleDigitsVictims'],
        ['CleanSheetsVictims', 'MostCleanSheetsVictims'],
        ['BiggestRatingAscent', 'BiggestRatingAscent'],
        ['peak_year_games', 'MostGamesInOneYear'],
        ['peak_year_tournaments', 'MostTournamentsInOneYear'],
        ['tournaments_played', 'MostTournamentsPlayed'],
        ['event_gold', 'MostTournamentWins'],
        ['wc_slice_tournaments_played', 'MostWcPlayed'],
        ['countries_played_in', 'MostCountriesPlayedIn'],
        ['opponent_countries_faced', 'MostOpponentCountriesFaced'],
        ['opponent_countries_beaten', 'MostOpponentCountriesBeaten'],
    ];
    $dateFields = [
        'MostGamesInOneYear' => 'peak_year_games_year',
        'MostTournamentsInOneYear' => 'peak_year_tournaments_year',
        'MostTournamentsPlayed' => 'tournaments_played_last_rise_event_date',
        'MostTournamentWins' => 'event_gold_last_rise_event_date',
        'MostWcPlayed' => 'wc_slice_tournaments_played_last_rise_event_date',
        'MostCountriesPlayedIn' => 'countries_played_in_last_rise_event_date',
        'MostOpponentCountriesFaced' => 'opponent_countries_faced_last_rise_event_date',
        'MostOpponentCountriesBeaten' => 'opponent_countries_beaten_last_rise_event_date',
        'MostGamesPlayed' => 'number_games_last_rise_event_date',
        'MostWins' => 'number_wins_last_rise_event_date',
        'MostGoalsScored' => 'goals_for_last_rise_event_date',
        'MostDoubleDigits' => 'double_digits_last_rise_event_date',
        'MostCleanSheets' => 'clean_sheets_last_rise_event_date',
        'MostDifferentOpponents' => 'different_opponents_last_rise_event_date',
        'MostDifferentVictims' => 'different_victims_last_rise_event_date',
        'MostDoubleDigitsVictims' => 'double_digits_victims_last_rise_event_date',
        'MostCleanSheetsVictims' => 'clean_sheets_victims_last_rise_event_date',
        'BiggestRatingAscent' => 'biggest_rating_ascent_last_rise_event_date',
    ];
    $patch = [];
    foreach ($holders as [$valueCol, $prefix]) {
        $best = null;
        foreach ($playerRows as $row) {
            $value = $row[$valueCol] ?? null;
            if ($value === null || (float) $value <= 0) {
                continue;
            }
            $pid = (int) $row['player_id'];
            if (
                $best === null
                || (float) $value > (float) $best['value']
                || ((float) $value === (float) $best['value'] && $pid < $best['id'])
            ) {
                $best = [
                    'value' => $value,
                    'id' => $pid,
                    'name' => (string) $row['player_name'],
                    'date' => $row['record_date'] ?? null,
                    'row' => $row,
                ];
            }
        }
        if ($best === null) {
            continue;
        }
        $patch[$prefix] = $best['value'];
        $patch[$prefix . 'ID'] = $best['id'];
        $patch[$prefix . 'Name'] = $best['name'];
        $dateField = $dateFields[$prefix] ?? null;
        if ($dateField === 'peak_year_games_year' || $dateField === 'peak_year_tournaments_year') {
            $year = $best['row'][$dateField] ?? null;
            $patch[$prefix . 'Date'] = $year !== null ? ((int) $year) . '-12-31' : null;
        } elseif ($dateField !== null) {
            $patch[$prefix . 'Date'] = $best['row'][$dateField] !== null ? (string) $best['row'][$dateField] : null;
        } else {
            $patch[$prefix . 'Date'] = $best['date'] !== null ? (string) $best['date'] : null;
        }
    }

    return $patch;
}

/**
 * @param list<array<string, mixed>> $playerRows
 * @return array<string, mixed>
 */
function amiga_realm_ratio_leaders_from_rows(array $playerRows): array
{
    $leaders = [
        ['BiggestWinRatio', 'WinRatio', true, ''],
        ['BiggestGoalsForAverage', 'AverageGoalsFor', true, ''],
        ['SmallestGoalsAgainstAverage', 'AverageGoalsAgainst', false, ''],
        ['BiggestGoalRatio', 'GoalRatio', true, 'GoalRatio > -1'],
        ['BiggestDoubleDigitsRatio', 'DoubleDigitsRatio', true, ''],
        ['BiggestCleanSheetsRatio', 'CleanSheetsRatio', true, ''],
    ];
    $patch = [];
    foreach ($leaders as [$prefix, $column, $higher, $extra]) {
        $best = null;
        foreach ($playerRows as $row) {
            if ((int) ($row['NumberGames'] ?? 0) < AMIGA_REALM_ESTABLISHED_MIN_GAMES) {
                continue;
            }
            $value = $row[$column] ?? null;
            if ($value === null) {
                continue;
            }
            if ($extra !== '' && (float) $value <= -1) {
                continue;
            }
            $pid = (int) $row['player_id'];
            if (
                $best === null
                || amiga_realm_beats_holder(
                    $value,
                    $pid,
                    $best['value'],
                    $best['id'],
                    $higher,
                )
            ) {
                $best = [
                    'value' => $value,
                    'id' => $pid,
                    'name' => (string) $row['player_name'],
                ];
            }
        }
        if ($best === null) {
            continue;
        }
        $patch[$prefix] = $best['value'];
        $patch[$prefix . 'ID'] = $best['id'];
        $patch[$prefix . 'Name'] = $best['name'];
    }

    return $patch;
}

/**
 * @return array<string, mixed>
 */
function amiga_realm_tournament_single_game_candidates(mysqli $con, int $tournamentId): array
{
    $dateExpr = amiga_realm_game_event_date_sql();
    $sql = "
        SELECT g.id AS game_id, g.player_a_id, g.player_b_id,
               g.goals_a, g.goals_b, g.game_date,
               pa.name AS name_a, pb.name AS name_b,
               t.event_date AS tour_date,
               r.actual_score, r.goal_difference, r.sum_of_goals,
               r.rating_a, r.rating_b, r.adjustment_a, r.adjustment_b,
               r.new_rating_a, r.new_rating_b
        FROM amiga_games g
        INNER JOIN amiga_game_ratings r ON r.game_id = g.id
        INNER JOIN amiga_players pa ON pa.id = g.player_a_id
        INNER JOIN amiga_players pb ON pb.id = g.player_b_id
        LEFT JOIN tournaments t ON t.id = g.tournament_id
        WHERE g.tournament_id = ?
    ";
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare tournament games: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute tournament games: ' . $stmt->error);
    }
    $games = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $patch = [];
    $eventDate = null;
    $bestGoals = null;
    $bestWin = null;
    $bestDraw = null;
    $bestSum = null;
    $bestPeak = null;

    foreach ($games as $game) {
        if ($eventDate === null && $game['tour_date'] !== null) {
            $eventDate = (string) $game['tour_date'];
        }
        $gameId = (int) $game['game_id'];
        $recordDate = $eventDate ?? ($game['game_date'] !== null ? (string) $game['game_date'] : null);
        $goalsA = (int) $game['goals_a'];
        $goalsB = (int) $game['goals_b'];
        $idA = (int) $game['player_a_id'];
        $idB = (int) $game['player_b_id'];
        $nameA = (string) $game['name_a'];
        $nameB = (string) $game['name_b'];

        foreach ([[$idA, $goalsA, $nameA], [$idB, $goalsB, $nameB]] as [$pid, $goals, $name]) {
            if ($bestGoals === null || $goals > $bestGoals[0] || ($goals === $bestGoals[0] && $gameId < $bestGoals[1])) {
                $bestGoals = [$goals, $gameId, $pid, $name, $recordDate];
            }
        }

        $actual = (float) $game['actual_score'];
        if (in_array($actual, [0.0, 1.0], true) && $game['goal_difference'] !== null) {
            $margin = (int) $game['goal_difference'];
            if ($actual === 1.0) {
                $pid = $idA;
                $name = $nameA;
            } else {
                $pid = $idB;
                $name = $nameB;
            }
            if ($bestWin === null || $margin > $bestWin[0] || ($margin === $bestWin[0] && $gameId < $bestWin[1])) {
                $bestWin = [$margin, $gameId, $pid, $name, $recordDate];
            }
        }
        if ($actual === 0.5) {
            $drawSum = $goalsA + $goalsB;
            if ($bestDraw === null || $drawSum > $bestDraw[0] || ($drawSum === $bestDraw[0] && $gameId < $bestDraw[1])) {
                $bestDraw = [$drawSum, $gameId, $idA, $idB, $nameA, $nameB, $recordDate];
            }
        }
        $goalSum = (int) ($game['sum_of_goals'] ?? ($goalsA + $goalsB));
        if ($bestSum === null || $goalSum > $bestSum[0] || ($goalSum === $bestSum[0] && $gameId < $bestSum[1])) {
            $bestSum = [$goalSum, $gameId, $idA, $idB, $nameA, $nameB, $recordDate];
        }
        foreach ([
            [$idA, $nameA, $game['rating_a'], $game['adjustment_a'], $game['new_rating_a']],
            [$idB, $nameB, $game['rating_b'], $game['adjustment_b'], $game['new_rating_b']],
        ] as [$pid, $name, $rating, $adjustment, $newRating]) {
            if ($rating === null || $adjustment === null) {
                continue;
            }
            $peak = $newRating ?? ((float) $rating + (float) $adjustment);
            $peakF = (float) $peak;
            if ($bestPeak === null || $peakF > $bestPeak[0] || ($peakF === $bestPeak[0] && $gameId < $bestPeak[1])) {
                $bestPeak = [$peakF, $gameId, $pid, $name, $recordDate];
            }
        }
    }

    if ($bestGoals !== null) {
        $patch['MostGoalsScoredInOneGame'] = $bestGoals[0];
        $patch['MostGoalsScoredInOneGameID'] = $bestGoals[2];
        $patch['MostGoalsScoredInOneGameName'] = $bestGoals[3];
        $patch['MostGoalsScoredInOneGameDate'] = $bestGoals[4];
        $patch['MostGoalsScoredInOneGameGameID'] = $bestGoals[1];
    }
    if ($bestWin !== null) {
        $patch['BiggestWinDifference'] = $bestWin[0];
        $patch['BiggestWinDifferenceID'] = $bestWin[2];
        $patch['BiggestWinDifferenceName'] = $bestWin[3];
        $patch['BiggestWinDifferenceDate'] = $bestWin[4];
        $patch['BiggestWinDifferenceGameID'] = $bestWin[1];
    }
    if ($bestDraw !== null) {
        $patch['BiggestDrawSum'] = $bestDraw[0];
        $patch['BiggestDrawSumIDA'] = $bestDraw[2];
        $patch['BiggestDrawSumIDB'] = $bestDraw[3];
        $patch['BiggestDrawSumNameA'] = $bestDraw[4];
        $patch['BiggestDrawSumNameB'] = $bestDraw[5];
        $patch['BiggestDrawSumDate'] = $bestDraw[6];
        $patch['BiggestDrawSumGameID'] = $bestDraw[1];
    }
    if ($bestSum !== null) {
        $patch['BiggestSumOfGoals'] = $bestSum[0];
        $patch['BiggestSumOfGoalsIDA'] = $bestSum[2];
        $patch['BiggestSumOfGoalsIDB'] = $bestSum[3];
        $patch['BiggestSumOfGoalsNameA'] = $bestSum[4];
        $patch['BiggestSumOfGoalsNameB'] = $bestSum[5];
        $patch['BiggestSumOfGoalsDate'] = $bestSum[6];
        $patch['BiggestSumOfGoalsGameID'] = $bestSum[1];
    }
    if ($bestPeak !== null) {
        $patch['BiggestPeakRating'] = $bestPeak[0];
        $patch['BiggestPeakRatingID'] = $bestPeak[2];
        $patch['BiggestPeakRatingName'] = $bestPeak[3];
        $patch['BiggestPeakRatingDate'] = $bestPeak[4];
    }

    return $patch;
}

/**
 * @param array<string, mixed> $prior
 * @param array<string, mixed> $candidates
 * @return array<string, mixed>
 */
function amiga_realm_merge_single_game_records(array $prior, array $candidates): array
{
    $patch = [];
    $simple = [
        ['MostGoalsScoredInOneGame', true],
        ['BiggestWinDifference', true],
        ['BiggestPeakRating', true],
    ];
    foreach ($simple as [$prefix, $higher]) {
        $valueKey = $prefix;
        $idKey = $prefix . 'ID';
        $candValue = $candidates[$valueKey] ?? null;
        $candId = $candidates[$idKey] ?? null;
        if (
            $candValue !== null
            && $candId !== null
            && amiga_realm_beats_holder(
                $candValue,
                (int) $candId,
                $prior[$valueKey] ?? null,
                $prior[$idKey] ?? null,
                $higher,
            )
        ) {
            foreach ([$valueKey, $idKey, $prefix . 'Name', $prefix . 'Date', $prefix . 'GameID'] as $key) {
                if (array_key_exists($key, $candidates)) {
                    $patch[$key] = $candidates[$key];
                }
            }
        } else {
            foreach ([$valueKey, $idKey, $prefix . 'Name', $prefix . 'Date', $prefix . 'GameID'] as $key) {
                if (array_key_exists($key, $prior)) {
                    $patch[$key] = $prior[$key];
                }
            }
        }
    }

    foreach (['BiggestDrawSum', 'BiggestSumOfGoals'] as $prefix) {
        $keys = match ($prefix) {
            'BiggestDrawSum' => [
                'BiggestDrawSum', 'BiggestDrawSumIDA', 'BiggestDrawSumIDB',
                'BiggestDrawSumNameA', 'BiggestDrawSumNameB', 'BiggestDrawSumDate', 'BiggestDrawSumGameID',
            ],
            default => [
                'BiggestSumOfGoals', 'BiggestSumOfGoalsIDA', 'BiggestSumOfGoalsIDB',
                'BiggestSumOfGoalsNameA', 'BiggestSumOfGoalsNameB', 'BiggestSumOfGoalsDate', 'BiggestSumOfGoalsGameID',
            ],
        };
        $candValue = $candidates[$prefix] ?? null;
        $idKey = $prefix === 'BiggestDrawSum' ? 'BiggestDrawSumIDA' : 'BiggestSumOfGoalsIDA';
        $candId = $candidates[$idKey] ?? null;
        $useCandidate = $candValue !== null
            && $candId !== null
            && amiga_realm_beats_holder(
                $candValue,
                (int) $candId,
                $prior[$prefix] ?? null,
                $prior[$idKey] ?? null,
                true,
            );
        $source = $useCandidate ? $candidates : $prior;
        foreach ($keys as $key) {
            if (array_key_exists($key, $source)) {
                $patch[$key] = $source[$key];
            }
        }
    }

    return $patch;
}

/**
 * Incremental finalize path (tier 2).
 *
 * @return array<string, mixed>
 */
function amiga_realm_build_generalstats_payload_incremental(mysqli $con, int $tournamentId): array
{
    $prior = amiga_realm_load_prior_payload($con, $tournamentId);
    $delta = amiga_realm_tournament_game_delta($con, $tournamentId);
    [$numPlayers, $diffOppAvg] = amiga_realm_player_count_stats_present($con);
    $playerRows = amiga_realm_fetch_player_current_rows($con);

    $patch = amiga_realm_merge_game_aggregates($prior, $delta, $numPlayers, $diffOppAvg);
    $patch = array_merge($patch, amiga_realm_career_holders_from_rows($playerRows));
    $patch = array_merge($patch, amiga_realm_ratio_leaders_from_rows($playerRows));
    $patch = array_merge(
        $patch,
        amiga_realm_merge_single_game_records(
            $prior,
            amiga_realm_tournament_single_game_candidates($con, $tournamentId),
        ),
    );

    return $patch;
}
