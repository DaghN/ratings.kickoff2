<?php
/**
 * Profile feast data — build $pm from playertable + aggregates.
 * Used by individual1.php (Profile tab).
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_feast_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_feast_profile.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';

/**
 * @return array<string, mixed>
 */
function player_feast_load_pm(mysqli $con, int $id): array
{
    $escId = (string) (int) $id;
    $result = k2_player_feast_query($con, 'playertable_row', "SELECT * FROM playertable WHERE id = '$escId' LIMIT 1");
    $row = $result ? mysqli_fetch_assoc($result) : null;
    if ($row === null) {
        throw new RuntimeException('Player not found.');
    }

    $rankResult = k2_player_feast_query(
        $con,
        'ladder_rank',
        "SELECT COUNT(*)+1 AS plrank FROM playertable WHERE display = 1 AND rating > (SELECT rating FROM playertable WHERE id='$escId')"
    );
    $rankRow = mysqli_fetch_row($rankResult);
    $rank = (int) $rankRow[0];

    $display = (int) $row['Display'] === 1;
    $rating = $display && !k2_db_is_null($row['Rating']) ? (int) round((float) $row['Rating']) : null;
    $peak = $display && !k2_db_is_null($row['PeakRating']) && (float) $row['PeakRating'] != 0
        ? (int) round((float) $row['PeakRating']) : null;
    $games = k2_db_is_null($row['NumberGames']) ? 0 : (int) $row['NumberGames'];
    $wins = k2_db_is_null($row['NumberWins']) ? 0 : (int) $row['NumberWins'];
    $draws = k2_db_is_null($row['NumberDraws']) ? 0 : (int) $row['NumberDraws'];
    $losses = k2_db_is_null($row['NumberLosses']) ? 0 : (int) $row['NumberLosses'];
    $winPct = $games > 0 && !k2_db_is_null($row['WinRatio'])
        ? round(100 * (float) $row['WinRatio'], 1) : ($games > 0 ? 0.0 : null);

    $monthResult = k2_player_feast_query(
        $con,
        'games_this_month',
        "SELECT COALESCE(SUM(games), 0) AS c FROM player_period_games WHERE period_type='month' AND player_id='$escId' AND period_start = DATE_FORMAT(NOW(), '%Y-%m-01')"
    );
    $monthRow = mysqli_fetch_row($monthResult);
    $gamesThisMonth = (int) $monthRow[0];

    $yearResult = k2_player_feast_query(
        $con,
        'games_this_year',
        "SELECT COALESCE(SUM(games), 0) AS c FROM player_period_games WHERE period_type='year' AND player_id='$escId' AND period_start = CONCAT(YEAR(CURDATE()), '-01-01')"
    );
    $yearRow = mysqli_fetch_row($yearResult);
    $gamesThisYear = (int) $yearRow[0];

    $trophyDefs = [
        [
            'key' => 'biggest_win',
            'label' => 'Biggest win',
            'game_id' => (int) $row['BiggestWinGameID'],
            'icon' => '⚡',
            'tag' => 'Margin',
        ],
        [
            'key' => 'biggest_draw',
            'label' => 'Biggest draw',
            'game_id' => (int) $row['BiggestDrawGameID'],
            'icon' => '⚖',
            'tag' => 'Stalemate epic',
        ],
        [
            'key' => 'goal_festival',
            'label' => 'Goal festival',
            'game_id' => (int) $row['MostGoalsScoredGameID'],
            'icon' => '🎯',
            'tag' => 'Attack',
        ],
        [
            'key' => 'shootout',
            'label' => 'Total goals bonanza',
            'game_id' => (int) $row['BiggestSumOfGoalsGameID'],
            'icon' => '🔥',
            'tag' => 'Chaos',
        ],
    ];

    $trophies = [];
    foreach ($trophyDefs as $def) {
        if ($def['game_id'] <= 0) {
            continue;
        }
        $gid = (int) $def['game_id'];
        $gRes = k2_player_feast_query($con, 'trophy_game_' . $def['key'], "SELECT id, Date, idA, idB, NameA, NameB, GoalsA, GoalsB, ActualScore, AdjustmentA, AdjustmentB FROM ratedresults WHERE id = $gid LIMIT 1");
        $gRow = $gRes ? mysqli_fetch_assoc($gRes) : null;
        if ($gRow === null) {
            continue;
        }
        $parsed = pm_parse_highlight_row($gRow, $id);
        $trophies[] = array_merge($def, $parsed);
    }

    $peakGap = ($peak !== null && $rating !== null) ? $peak - $rating : 0;

    $firstGameResult = k2_player_feast_query(
        $con,
        'first_rated_game',
        "SELECT Date FROM ratedresults WHERE idA='$escId' OR idB='$escId' ORDER BY Date ASC, id ASC LIMIT 1"
    );
    $firstGameRow = $firstGameResult ? mysqli_fetch_assoc($firstGameResult) : null;
    $firstGameDate = $firstGameRow ? (string) $firstGameRow['Date'] : (string) $row['JoinDate'];
    $firstGameTs = strtotime($firstGameDate) ?: 0;
    $tenureYears = pm_years_on_ladder_since($firstGameDate);
    $tenureLabel = pm_tenure_plus_label($tenureYears);

    $differentOpponents = (int) $row['DifferentOpponents'];

    $busiest = player_feast_load_busiest($con, $id);

    $careerRankGames = null;
    $careerRankWins = null;
    $careerRankGoals = null;
    $careerRankDoubleDigits = null;
    $careerRankOpponents = null;
    if ($display) {
        $careerRankGames = pm_playertable_career_stat_rank($con, $id, 'NumberGames');
        $careerRankWins = pm_playertable_career_stat_rank($con, $id, 'NumberWins');
        $careerRankGoals = pm_playertable_career_stat_rank($con, $id, 'GoalsFor');
        $careerRankDoubleDigits = pm_playertable_career_stat_rank($con, $id, 'DoubleDigits');
        $careerRankOpponents = pm_playertable_career_stat_rank($con, $id, 'DifferentOpponents');
    }

    return [
        'id' => $id,
        'name' => (string) $row['Name'],
        'rank' => $rank,
        'rating' => $rating,
        'peak' => $peak,
        'peak_gap' => $peakGap,
        'games' => $games,
        'wins' => $wins,
        'draws' => $draws,
        'losses' => $losses,
        'win_pct' => $winPct,
        'display' => $display,
        'join_date' => date('M Y', strtotime((string) $row['JoinDate'])),
        'join_date_ymd' => date('Y-m-d', strtotime((string) $row['JoinDate']) ?: time()),
        'last_game' => date('M j, Y', strtotime((string) $row['LastGame'])),
        'last_login' => date('M j, Y', strtotime((string) $row['LastLogin'])),
        'games_this_month' => $gamesThisMonth,
        'games_this_year' => $gamesThisYear,
        'longest_win_streak' => (int) $row['LongestWinningStreak'],
        'biggest_win_margin' => (int) $row['BiggestWinDifference'],
        'biggest_sum_goals' => (int) $row['BiggestSumOfGoals'],
        'most_goals_scored' => (int) $row['MostGoalsScored'],
        'years_on_ladder' => $tenureYears,
        'tenure_label' => $tenureLabel,
        'first_game_date' => date('M j, Y', $firstGameTs ?: time()),
        'first_game_date_ymd' => date('Y-m-d', $firstGameTs ?: time()),
        'different_opponents' => $differentOpponents,
        'different_victims' => (int) $row['DifferentVictims'],
        'busiest' => $busiest,
        'average_opponent_rating' => ($display && !k2_db_is_null($row['AverageOpponentRating']))
            ? (int) round((float) $row['AverageOpponentRating']) : null,
        'goal_ratio' => ($display && !k2_db_is_null($row['GoalRatio']))
            ? round((float) $row['GoalRatio'], 2) : null,
        'goals_for' => (int) $row['GoalsFor'],
        'goals_against' => (int) $row['GoalsAgainst'],
        'double_digits' => (int) $row['DoubleDigits'],
        'career_rank_games' => $careerRankGames,
        'career_rank_wins' => $careerRankWins,
        'career_rank_goals' => $careerRankGoals,
        'career_rank_double_digits' => $careerRankDoubleDigits,
        'career_rank_opponents' => $careerRankOpponents,
        'clean_sheets' => (int) $row['CleanSheets'],
        'winning_streak' => (int) $row['WinningStreak'],
        'trophies' => $trophies,
        'initial' => strtoupper(substr((string) $row['Name'], 0, 1)),
        'rating_raw' => (float) $row['Rating'],
        'peak_raw' => (float) $row['PeakRating'],
    ];
}

/**
 * Personal bests (busiest day / month / year) — same source as ranked8 peak leaderboards.
 *
 * @return array{month: ?array{key: string, count: int}, day: ?array{key: string, count: int}, year: ?array{key: string, count: int}}
 */
function player_feast_load_busiest(mysqli $con, int $id): array
{
    $busiest = ['month' => null, 'day' => null, 'year' => null];
    $escId = (string) (int) $id;

    $peakResult = k2_player_feast_query(
        $con,
        'busiest_peak_period',
        "SELECT period_type, period_start, games FROM player_peak_period_games "
        . "WHERE player_id = '$escId' AND period_type IN ('day', 'month', 'year')"
    );
    if ($peakResult === false && mysqli_errno($con) === 1146) {
        return player_feast_load_busiest_from_ratedresults($con, $id);
    }
    if ($peakResult) {
        while ($prow = mysqli_fetch_assoc($peakResult)) {
            $ptype = (string) $prow['period_type'];
            if (!array_key_exists($ptype, $busiest)) {
                continue;
            }
            $busiest[$ptype] = [
                'key' => player_feast_busiest_period_key($ptype, (string) $prow['period_start']),
                'count' => (int) $prow['games'],
            ];
        }
    }

    return $busiest;
}

function player_feast_busiest_period_key(string $periodType, string $periodStart): string
{
    switch ($periodType) {
        case 'month':
            return substr($periodStart, 0, 7);
        case 'year':
            return (string) (int) substr($periodStart, 0, 4);
        case 'day':
        default:
            return $periodStart;
    }
}

/**
 * @return array{month: ?array{key: string, count: int}, day: ?array{key: string, count: int}, year: ?array{key: string, count: int}}
 */
function player_feast_load_busiest_from_ratedresults(mysqli $con, int $id): array
{
    $busiest = ['month' => null, 'day' => null, 'year' => null];
    $escId = (string) (int) $id;
    $busiestSql = [
        'month' => "SELECT DATE_FORMAT(Date, '%Y-%m') AS k, COUNT(*) AS c FROM ratedresults "
            . "WHERE idA='$escId' OR idB='$escId' GROUP BY k ORDER BY c DESC LIMIT 1",
        'day' => "SELECT DATE(Date) AS k, COUNT(*) AS c FROM ratedresults "
            . "WHERE idA='$escId' OR idB='$escId' GROUP BY k ORDER BY c DESC LIMIT 1",
        'year' => "SELECT YEAR(Date) AS k, COUNT(*) AS c FROM ratedresults "
            . "WHERE idA='$escId' OR idB='$escId' GROUP BY k ORDER BY c DESC LIMIT 1",
    ];
    foreach ($busiestSql as $key => $sql) {
        $br = k2_player_feast_query($con, 'busiest_' . $key, $sql);
        if ($br && ($brow = mysqli_fetch_assoc($br))) {
            $busiest[$key] = ['key' => (string) $brow['k'], 'count' => (int) $brow['c']];
        }
    }

    return $busiest;
}

/** @param array<string, mixed> $pm */
function player_feast_expose_hero_vars(array $pm): void
{
    global $Name, $Rating, $PeakRating, $NumberGames, $Display, $rank;

    $Name = $pm['name'];
    $rank = (int) $pm['rank'];
    $NumberGames = (int) $pm['games'];
    $Display = !empty($pm['display']) ? 1 : 0;
    $Rating = ($Display === 1 && $pm['rating'] !== null) ? (float) $pm['rating'] : null;
    $PeakRating = ($Display === 1 && $pm['peak'] !== null) ? (float) $pm['peak'] : null;
}

