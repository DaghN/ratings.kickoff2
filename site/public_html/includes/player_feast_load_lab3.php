<?php
/**
 * Profile feast data — lab3 complete Profile content v1 build.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_feast_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_feast_profile.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/status_queries.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_play_streaks.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_milestones_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/league_standings.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/league_honours_leaderboard.php';

/**
 * @return array<string, mixed>
 */
function player_feast_load_pm(mysqli $con, int $id): array
{
    $escId = (string) (int) $id;
    $result = k2_player_feast_query($con, 'playertable_row_lab3', "SELECT * FROM playertable WHERE id = '$escId' LIMIT 1");
    $row = $result ? mysqli_fetch_assoc($result) : null;
    if ($row === null) {
        throw new RuntimeException('Player not found.');
    }

    $rankResult = k2_player_feast_query(
        $con,
        'ladder_rank_lab3',
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
        'games_this_month_lab3',
        "SELECT COALESCE(SUM(games), 0) AS c FROM player_period_games WHERE period_type='month' AND player_id='$escId' AND period_start = DATE_FORMAT(NOW(), '%Y-%m-01')"
    );
    $monthRow = mysqli_fetch_row($monthResult);
    $gamesThisMonth = (int) $monthRow[0];

    $yearResult = k2_player_feast_query(
        $con,
        'games_this_year_lab3',
        "SELECT COALESCE(SUM(games), 0) AS c FROM player_period_games WHERE period_type='year' AND player_id='$escId' AND period_start = CONCAT(YEAR(CURDATE()), '-01-01')"
    );
    $yearRow = mysqli_fetch_row($yearResult);
    $gamesThisYear = (int) $yearRow[0];

    $trophyDefs = [
        [
            'key' => 'biggest_win',
            'label' => 'Biggest win',
            'game_id' => (int) $row['BiggestWinGameID'],
            'icon' => '&#9889;',
            'tag' => 'Margin',
        ],
        [
            'key' => 'biggest_draw',
            'label' => 'Biggest draw',
            'game_id' => (int) $row['BiggestDrawGameID'],
            'icon' => '&#9878;',
            'tag' => 'Stalemate epic',
        ],
        [
            'key' => 'goal_festival',
            'label' => 'Goal festival',
            'game_id' => (int) $row['MostGoalsScoredGameID'],
            'icon' => '&#9673;',
            'tag' => 'Attack',
        ],
        [
            'key' => 'shootout',
            'label' => 'Total goals bonanza',
            'game_id' => (int) $row['BiggestSumOfGoalsGameID'],
            'icon' => '&#9670;',
            'tag' => 'Chaos',
        ],
    ];

    $trophies = [];
    foreach ($trophyDefs as $def) {
        if ($def['game_id'] <= 0) {
            continue;
        }
        $gid = (int) $def['game_id'];
        $gRes = k2_player_feast_query($con, 'trophy_game_lab3_' . $def['key'], "SELECT id, Date, idA, idB, NameA, NameB, GoalsA, GoalsB, ActualScore, AdjustmentA, AdjustmentB FROM ratedresults WHERE id = $gid LIMIT 1");
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
        'first_rated_game_lab3',
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

    $pm['play_streaks'] = player_feast_lab3_load_play_streaks($con, $id);
    $pm['best_year_wins'] = player_feast_lab3_load_best_year_wins($con, $id);
    $pm['distinct_days_played'] = player_feast_lab3_load_distinct_days($con, $id);
    $pm['max_rated_victim'] = player_feast_lab3_load_max_rated_victim($con, $id, (int) ($row['HighestRatedVictimGameID'] ?? 0));
    $pm['favourite_victim'] = player_feast_lab3_load_matchup($con, $id, 'favourite_victim');
    $pm['featured_rival'] = player_feast_lab3_load_matchup($con, $id, 'featured_rival');
    $pm['honours'] = player_feast_lab3_load_honours($con, $id);

    return $pm;
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
        'busiest_peak_period_lab3',
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
        $br = k2_player_feast_query($con, 'busiest_lab3_' . $key, $sql);
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

function player_feast_lab3_table_exists(mysqli $con, string $table): bool
{
    return function_exists('k2_status_table_exists') ? k2_status_table_exists($con, $table) : false;
}

/**
 * @return array{day: ?array<string, mixed>, week: ?array<string, mixed>}
 */
function player_feast_lab3_load_play_streaks(mysqli $con, int $id): array
{
    $out = ['day' => null, 'week' => null];
    if (!player_feast_lab3_table_exists($con, 'player_play_streaks')) {
        return $out;
    }
    $today = gmdate('Y-m-d');
    foreach (['day', 'week'] as $type) {
        try {
            $row = k2_play_streak_load_row($con, $id, $type);
        } catch (Throwable $e) {
            $row = null;
        }
        if (!$row) {
            continue;
        }
        $out[$type] = [
            'type' => $type,
            'current' => k2_play_streak_effective_current($row, $type, $today),
            'current_anchor' => (string) ($row['current_anchor'] ?? ''),
            'best' => (int) ($row['best_streak'] ?? 0),
            'best_achieved_at' => (string) ($row['best_achieved_at'] ?? ''),
            'best_last_game_id' => (int) ($row['best_last_game_id'] ?? 0),
        ];
    }

    return $out;
}

/**
 * @return ?array{year: int, wins: int, played: int}
 */
function player_feast_lab3_load_best_year_wins(mysqli $con, int $id): ?array
{
    if (!player_feast_lab3_table_exists($con, 'player_period_league')) {
        return null;
    }
    $pid = (int) $id;
    $res = k2_player_feast_query(
        $con,
        'best_year_wins_lab3',
        "SELECT YEAR(period_start) AS y, wins, played FROM player_period_league "
        . "WHERE player_id = $pid AND period_type = 'year' ORDER BY wins DESC, period_start ASC LIMIT 1"
    );
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if (!$row || (int) $row['wins'] < 1) {
        return null;
    }

    return ['year' => (int) $row['y'], 'wins' => (int) $row['wins'], 'played' => (int) $row['played']];
}

function player_feast_lab3_load_distinct_days(mysqli $con, int $id): int
{
    if (!player_feast_lab3_table_exists($con, 'player_period_games')) {
        return 0;
    }
    $pid = (int) $id;
    $res = k2_player_feast_query(
        $con,
        'distinct_days_lab3',
        "SELECT COUNT(*) AS c FROM player_period_games WHERE player_id = $pid AND period_type = 'day'"
    );
    $row = $res ? mysqli_fetch_assoc($res) : null;

    return $row ? (int) $row['c'] : 0;
}

/**
 * @return ?array<string, mixed>
 */
function player_feast_lab3_load_max_rated_victim(mysqli $con, int $id, int $gameId): ?array
{
    if ($gameId < 1) {
        return null;
    }
    $res = k2_player_feast_query(
        $con,
        'max_rated_victim_lab3',
        "SELECT id, Date, idA, idB, NameA, NameB, GoalsA, GoalsB, ActualScore, AdjustmentA, AdjustmentB, RatingA, RatingB "
        . "FROM ratedresults WHERE id = $gameId LIMIT 1"
    );
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if (!$row) {
        return null;
    }
    $parsed = pm_parse_highlight_row($row, $id);
    if ($parsed['outcome'] !== 'Win') {
        return null;
    }
    $isA = (int) pm_row_col($row, 'idA') === $id;
    $opponentRating = $isA ? (int) round((float) pm_row_col($row, 'RatingB')) : (int) round((float) pm_row_col($row, 'RatingA'));

    return array_merge($parsed, [
        'label' => 'Highest-rated victim',
        'tag' => 'Giant-killing',
        'icon' => '&#9733;',
        'opponent_rating' => $opponentRating,
    ]);
}

/**
 * @return ?array{opponent_id: int, opponent_name: string, games: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int}
 */
function player_feast_lab3_load_matchup(mysqli $con, int $id, string $kind): ?array
{
    if (!player_feast_lab3_table_exists($con, 'player_matchup_summary')) {
        return null;
    }
    $pid = (int) $id;
    $order = $kind === 'favourite_victim'
        ? 's.wins DESC, s.games DESC, p.Name ASC'
        : 's.games DESC, s.wins DESC, p.Name ASC';
    $where = $kind === 'favourite_victim' ? ' AND s.wins > 0' : '';
    $res = k2_player_feast_query(
        $con,
        'matchup_lab3_' . $kind,
        "SELECT s.opponent_id, p.Name AS opponent_name, s.games, s.wins, s.draws, s.losses, s.goals_for, s.goals_against "
        . "FROM player_matchup_summary s INNER JOIN playertable p ON p.ID = s.opponent_id "
        . "WHERE s.player_id = $pid$where ORDER BY $order LIMIT 1"
    );
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if (!$row) {
        return null;
    }

    return [
        'opponent_id' => (int) $row['opponent_id'],
        'opponent_name' => (string) $row['opponent_name'],
        'games' => (int) $row['games'],
        'wins' => (int) $row['wins'],
        'draws' => (int) $row['draws'],
        'losses' => (int) $row['losses'],
        'goals_for' => (int) $row['goals_for'],
        'goals_against' => (int) $row['goals_against'],
    ];
}

/**
 * @return array<string, mixed>
 */
function player_feast_lab3_load_honours(mysqli $con, int $id): array
{
    $counts = k2_milestone_player_counts($con, $id);
    $latestMs = player_feast_lab3_latest_milestone($con, $id, false);
    $leagueMs = player_feast_lab3_latest_milestone($con, $id, true);
    $latestMedal = player_feast_lab3_latest_league_medal($con, $id);
    $career = player_feast_lab3_league_totals($con, $id);

    $legendary = (int) ($counts['legendary'] ?? 0);
    $accomplished = (int) ($counts['accomplished'] ?? 0);
    $signatureLabel = '';
    if ($legendary > 0) {
        $signatureLabel = number_format($legendary) . ' legendary unlock' . ($legendary === 1 ? '' : 's');
    } elseif ($accomplished > 0) {
        $signatureLabel = number_format($accomplished) . ' accomplished unlock' . ($accomplished === 1 ? '' : 's');
    }

    return [
        'latest_milestone' => $latestMs,
        'league_milestone' => $leagueMs,
        'milestone_counts' => $counts,
        'signature_label' => $signatureLabel,
        'unlocks_12mo' => player_feast_lab3_milestones_last_12mo($con, $id),
        'latest_medal' => $latestMedal,
        'career_medals' => $career,
        'show_strip' => $latestMs !== null || $leagueMs !== null || $latestMedal !== null || ((int) ($career['podiums'] ?? 0) > 0),
    ];
}

/**
 * @return ?array{milestone_key: string, display_name: string, achieved_label: string, detail_href: string, league_label: string}
 */
function player_feast_lab3_latest_milestone(mysqli $con, int $id, bool $leagueOnly): ?array
{
    if (!k2_milestone_tables_ready($con)) {
        return null;
    }
    $pid = (int) $id;
    $source = $leagueOnly ? " AND pm.source_kind = 'league'" : '';
    $res = k2_player_feast_query(
        $con,
        $leagueOnly ? 'latest_league_milestone_lab3' : 'latest_milestone_lab3',
        "SELECT pm.milestone_key, pm.achieved_at, pm.source_league_kind, pm.source_period_type, pm.source_period_start, "
        . "md.display_name, md.tier_band "
        . "FROM player_milestones pm INNER JOIN milestone_definitions md ON md.milestone_key = pm.milestone_key "
        . "WHERE pm.player_id = $pid$source ORDER BY pm.achieved_at DESC, pm.milestone_key ASC LIMIT 1"
    );
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if (!$row) {
        return null;
    }
    $cup = (string) ($row['source_league_kind'] ?? '');
    $period = (string) ($row['source_period_type'] ?? '');
    $start = (string) ($row['source_period_start'] ?? '');
    $leagueLabel = '';
    if ($cup !== '' && $period !== '' && $start !== '') {
        $leagueLabel = k2_league_period_short_label($cup === 'activity' ? 'activity' : 'points', $period, $start);
    }

    return [
        'milestone_key' => (string) $row['milestone_key'],
        'display_name' => k2_milestone_strip_markdown((string) $row['display_name']),
        'achieved_label' => player_feast_lab3_short_date((string) $row['achieved_at']),
        'detail_href' => k2_milestone_detail_href((string) $row['milestone_key']),
        'league_label' => $leagueLabel,
        'tier_band' => (string) $row['tier_band'],
    ];
}

function player_feast_lab3_milestones_last_12mo(mysqli $con, int $id): int
{
    if (!k2_milestone_tables_ready($con)) {
        return 0;
    }
    $pid = (int) $id;
    $res = k2_player_feast_query(
        $con,
        'milestones_12mo_lab3',
        "SELECT COUNT(*) AS c FROM player_milestones WHERE player_id = $pid AND achieved_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 12 MONTH)"
    );
    $row = $res ? mysqli_fetch_assoc($res) : null;

    return $row ? (int) $row['c'] : 0;
}

/**
 * @return ?array{medal: string, medal_label: string, league_label: string, period_label: string, href: string}
 */
function player_feast_lab3_latest_league_medal(mysqli $con, int $id): ?array
{
    if (!player_feast_lab3_table_exists($con, 'player_league_award')) {
        return null;
    }
    $pid = (int) $id;
    $res = k2_player_feast_query(
        $con,
        'latest_league_medal_lab3',
        "SELECT league_kind, period_type, period_start, period_end, medal, finish_rank "
        . "FROM player_league_award WHERE player_id = $pid ORDER BY period_end DESC, finish_rank ASC LIMIT 1"
    );
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if (!$row) {
        return null;
    }
    $cup = (string) $row['league_kind'];
    $period = (string) $row['period_type'];
    $start = (string) $row['period_start'];
    $medal = (string) $row['medal'];
    $cupNorm = $cup === 'activity' ? 'activity' : 'points';

    return [
        'medal' => $medal,
        'medal_label' => ucfirst($medal),
        'league_label' => ucfirst($cupNorm) . ' league',
        'period_label' => k2_league_period_short_label($cupNorm, $period, $start),
        'href' => k2_league_period_href($cupNorm, $period, $start),
    ];
}

/**
 * @return array{wins: int, podiums: int, gold: int, silver: int, bronze: int}
 */
function player_feast_lab3_league_totals(mysqli $con, int $id): array
{
    $empty = ['wins' => 0, 'podiums' => 0, 'gold' => 0, 'silver' => 0, 'bronze' => 0];
    if (!player_feast_lab3_table_exists($con, 'player_league_totals')) {
        return $empty;
    }
    $pid = (int) $id;
    $res = k2_player_feast_query(
        $con,
        'league_totals_lab3',
        "SELECT wins, podiums, gold, silver, bronze FROM player_league_totals WHERE player_id = $pid LIMIT 1"
    );
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if (!$row) {
        return $empty;
    }

    return [
        'wins' => (int) $row['wins'],
        'podiums' => (int) $row['podiums'],
        'gold' => (int) $row['gold'],
        'silver' => (int) $row['silver'],
        'bronze' => (int) $row['bronze'],
    ];
}

function player_feast_lab3_short_date(string $date): string
{
    $ts = strtotime($date . ' UTC');
    if ($ts === false) {
        return '';
    }

    return gmdate('M j, Y', $ts);
}
