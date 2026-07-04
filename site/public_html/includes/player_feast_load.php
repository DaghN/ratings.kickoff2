<?php
/**
 * Profile feast data — build $pm from playertable + aggregates.
 * Used by player/profile.php (Profile tab).
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_feast_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_feast_profile.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_feast_load_story.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_feast_load_bonanza.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_player_display_names.php';

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

    $games = k2_db_is_null($row['NumberGames'] ?? null) ? 0 : (int) $row['NumberGames'];
    $ladderVisible = $games >= 1;

    $rankResult = k2_player_feast_query(
        $con,
        'ladder_rank',
        "SELECT COUNT(*)+1 AS plrank FROM playertable WHERE NumberGames >= 1 AND rating > (SELECT rating FROM playertable WHERE id='$escId')"
    );
    $rankRow = mysqli_fetch_row($rankResult);
    $rank = (int) $rankRow[0];

    $display = $ladderVisible;
    $rating = $ladderVisible && !k2_db_is_null($row['Rating']) ? (int) round((float) $row['Rating']) : null;
    $peak = $ladderVisible && !k2_db_is_null($row['PeakRating']) && (float) $row['PeakRating'] != 0
        ? (int) round((float) $row['PeakRating']) : null;
    $wins = k2_db_is_null($row['NumberWins']) ? 0 : (int) $row['NumberWins'];
    $draws = k2_db_is_null($row['NumberDraws']) ? 0 : (int) $row['NumberDraws'];
    $losses = k2_db_is_null($row['NumberLosses']) ? 0 : (int) $row['NumberLosses'];
    $winPct = $games > 0 && !k2_db_is_null($row['WinRatio'])
        ? round(100 * (float) $row['WinRatio'], 1) : ($games > 0 ? 0.0 : null);

    $yearResult = k2_player_feast_query(
        $con,
        'games_this_year',
        "SELECT COALESCE(SUM(games), 0) AS c FROM player_period_games WHERE period_type='year' AND player_id='$escId' AND period_start = CONCAT(YEAR(CURDATE()), '-01-01')"
    );
    $yearRow = $yearResult ? mysqli_fetch_row($yearResult) : null;
    $gamesThisYear = $yearRow ? (int) $yearRow[0] : 0;

    $daysThisYear = player_feast_load_days_this_year($con, $id);

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
    ];

    $trophies = [];
    foreach ($trophyDefs as $def) {
        if ($def['game_id'] <= 0) {
            continue;
        }
        $gid = (int) $def['game_id'];
        $gRes = k2_player_feast_query($con, 'trophy_game_' . $def['key'], "SELECT id, Date, idA, idB, NameA, NameB, GoalsA, GoalsB, ActualScore, AdjustmentA, AdjustmentB FROM ratedresults WHERE id = $gid LIMIT 1");
        $gRow = $gRes ? mysqli_fetch_assoc($gRes) : null;
        $gRow = k2_rated_game_row_resolve($con, $gRow);
        if ($gRow === null) {
            continue;
        }
        $parsed = pm_parse_highlight_row($gRow, $id);
        $trophies[] = array_merge($def, $parsed);
    }

    $bonanza = player_feast_load_bonanza_trophy($con, $id, (int) $row['BiggestSumOfGoalsGameID']);
    if ($bonanza !== null) {
        $trophies[] = $bonanza;
    }

    $peakGap = ($peak !== null && $rating !== null) ? $peak - $rating : 0;

    $firstGameResult = k2_player_feast_query(
        $con,
        'first_rated_game',
        "SELECT Date FROM ratedresults WHERE idA='$escId' OR idB='$escId' ORDER BY Date ASC, id ASC LIMIT 1"
    );
    $firstGameRow = $firstGameResult ? mysqli_fetch_assoc($firstGameResult) : null;
    $firstGameDateRaw = $firstGameRow ? (string) $firstGameRow['Date'] : null;

    $lastGameResult = k2_player_feast_query(
        $con,
        'last_rated_game',
        "SELECT Date FROM ratedresults WHERE idA='$escId' OR idB='$escId' ORDER BY Date DESC, id DESC LIMIT 1"
    );
    $lastGameRow = $lastGameResult ? mysqli_fetch_assoc($lastGameResult) : null;
    $lastGameDateRaw = $lastGameRow ? (string) $lastGameRow['Date'] : null;

    $firstGameDateYmd = player_feast_rated_game_date_ymd($firstGameDateRaw);
    $tenureYears = $firstGameDateRaw !== null ? pm_years_on_ladder_since($firstGameDateRaw) : 0;
    $tenureLabel = $firstGameDateRaw !== null ? pm_tenure_plus_label($tenureYears) : '—';

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

    $pm = [
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
        'last_game' => player_feast_format_rated_game_date($lastGameDateRaw),
        'last_login' => date('M j, Y', strtotime((string) $row['LastLogin'])),
        'days_this_year' => $daysThisYear,
        'games_this_year' => $gamesThisYear,
        'longest_win_streak' => (int) $row['LongestWinningStreak'],
        'biggest_win_margin' => (int) $row['BiggestWinDifference'],
        'biggest_sum_goals' => (int) $row['BiggestSumOfGoals'],
        'most_goals_scored' => (int) $row['MostGoalsScored'],
        'years_on_ladder' => $tenureYears,
        'tenure_label' => $tenureLabel,
        'first_game_date' => player_feast_format_rated_game_date($firstGameDateRaw),
        'first_game_date_ymd' => $firstGameDateYmd,
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
        'max_rated_victim' => player_feast_load_max_rated_victim(
            $con,
            $id,
            $row['HighestRatedVictim'] ?? null,
            (int) ($row['HighestRatedVictimGameID'] ?? 0)
        ),
        'initial' => strtoupper(substr((string) $row['Name'], 0, 1)),
        'rating_raw' => (float) $row['Rating'],
        'peak_raw' => (float) $row['PeakRating'],
    ] + player_feast_load_glance_honours($con, $id, $games);

    $pm['story'] = player_feast_load_story_extras($con, $id);

    return $pm;
}

/**
 * Personal bests (busiest day / week / month / year) — same source as ranked8 peak leaderboards.
 *
 * @return array{week: ?array{key: string, count: int}, month: ?array{key: string, count: int}, day: ?array{key: string, count: int}, year: ?array{key: string, count: int}}
 */
function player_feast_load_busiest(mysqli $con, int $id): array
{
    $busiest = ['day' => null, 'week' => null, 'month' => null, 'year' => null];
    $escId = (string) (int) $id;

    $peakResult = k2_player_feast_query(
        $con,
        'busiest_peak_period',
        "SELECT period_type, period_start, games FROM player_peak_period_games "
        . "WHERE player_id = '$escId' AND period_type IN ('day', 'week', 'month', 'year')"
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
        case 'week':
        case 'day':
        default:
            return $periodStart;
    }
}

/**
 * @return array{week: ?array{key: string, count: int}, month: ?array{key: string, count: int}, day: ?array{key: string, count: int}, year: ?array{key: string, count: int}}
 */
function player_feast_load_busiest_from_ratedresults(mysqli $con, int $id): array
{
    $busiest = ['day' => null, 'week' => null, 'month' => null, 'year' => null];
    $escId = (string) (int) $id;
    $busiestSql = [
        'day' => "SELECT DATE(Date) AS k, COUNT(*) AS c FROM ratedresults "
            . "WHERE idA='$escId' OR idB='$escId' GROUP BY k ORDER BY c DESC LIMIT 1",
        'week' => "SELECT DATE_SUB(DATE(Date), INTERVAL WEEKDAY(Date) DAY) AS k, COUNT(*) AS c FROM ratedresults "
            . "WHERE idA='$escId' OR idB='$escId' GROUP BY k ORDER BY c DESC LIMIT 1",
        'month' => "SELECT DATE_FORMAT(Date, '%Y-%m') AS k, COUNT(*) AS c FROM ratedresults "
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

/**
 * Milestone tier counts + career league medals for the at-a-glance band.
 *
 * @return array{milestone_counts: ?array, milestone_catalog_total: int, league_gold: int, league_silver: int, league_bronze: int}
 */
function player_feast_load_glance_honours(mysqli $con, int $playerId, int $games): array
{
    $out = [
        'milestone_counts' => null,
        'milestone_catalog_total' => 0,
        'league_gold' => 0,
        'league_silver' => 0,
        'league_bronze' => 0,
    ];

    if ($games >= 1) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_milestones_helpers.php';
        if (k2_milestone_tables_ready($con)) {
            $out['milestone_catalog_total'] = k2_milestone_catalog_total($con);
            $out['milestone_counts'] = k2_milestone_player_counts($con, $playerId);
        }
    }

    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/status_queries.php';
    if (k2_status_table_exists($con, 'player_league_totals')) {
        $stmt = $con->prepare(
            'SELECT `gold`, `silver`, `bronze` FROM `player_league_totals` WHERE `player_id` = ? LIMIT 1'
        );
        if ($stmt !== false) {
            $stmt->bind_param('i', $playerId);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                $row = $res ? $res->fetch_assoc() : null;
                if ($res) {
                    $res->free();
                }
                if ($row) {
                    $out['league_gold'] = (int) $row['gold'];
                    $out['league_silver'] = (int) $row['silver'];
                    $out['league_bronze'] = (int) $row['bronze'];
                }
            }
            $stmt->close();
        }
    }

    return $out;
}

/** @param array<string, mixed> $pm */
function player_feast_expose_hero_vars(array $pm): void
{
    global $Name, $Rating, $PeakRating, $NumberGames, $Display, $rank;

    $Name = $pm['name'];
    $rank = (int) $pm['rank'];
    $NumberGames = (int) $pm['games'];
    $Display = (int) $pm['games'] >= 1 ? 1 : 0;
    $Rating = ($Display === 1 && $pm['rating'] !== null) ? (float) $pm['rating'] : null;
    $PeakRating = ($Display === 1 && $pm['peak'] !== null) ? (float) $pm['peak'] : null;
}

/** Distinct UTC days with at least one rated game in the current calendar year. */
function player_feast_load_days_this_year(mysqli $con, int $id): int
{
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/status_queries.php';
    if (!k2_status_table_exists($con, 'player_period_games')) {
        return 0;
    }

    $escId = (string) (int) $id;
    $result = k2_player_feast_query(
        $con,
        'days_this_year',
        "SELECT COUNT(*) AS c FROM player_period_games "
        . "WHERE player_id = '$escId' AND period_type = 'day' AND games > 0 "
        . "AND period_start >= CONCAT(YEAR(CURDATE()), '-01-01') "
        . "AND period_start <= CURDATE()"
    );
    $row = $result ? mysqli_fetch_row($result) : null;

    return $row ? (int) $row[0] : 0;
}

/**
 * M03 — highest-rated opponent ever beaten (playertable ground truth + game row).
 *
 * @return array<string, mixed>|null
 */
function player_feast_load_max_rated_victim(mysqli $con, int $id, mixed $highestRatedVictim, int $gameId): ?array
{
    if ($gameId < 1) {
        return null;
    }
    $gRes = k2_player_feast_query(
        $con,
        'max_rated_victim',
        'SELECT id, Date, idA, idB, NameA, NameB, GoalsA, GoalsB, ActualScore, AdjustmentA, AdjustmentB '
        . "FROM ratedresults WHERE id = $gameId LIMIT 1"
    );
    $gRow = $gRes ? mysqli_fetch_assoc($gRes) : null;
    $gRow = k2_rated_game_row_resolve($con, $gRow);
    if ($gRow === null) {
        return null;
    }
    $parsed = pm_parse_highlight_row($gRow, $id);
    $parsed['victim_rating'] = ($highestRatedVictim === null || k2_db_is_null($highestRatedVictim))
        ? null
        : (int) round((float) $highestRatedVictim);

    return $parsed;
}

