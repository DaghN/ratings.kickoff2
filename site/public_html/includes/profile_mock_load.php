<?php
/**
 * Load playertable + related rows for profile lab mocks.
 * Sets $pm (array), $id (int), $con (mysqli). Includes helpers.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/profile_mock_helpers.php';

$PROFILE_MOCK_DEFAULT_ID = 237;

$id = isset($_GET['id']) ? (int) $_GET['id'] : $PROFILE_MOCK_DEFAULT_ID;
if ($id < 1) {
    $id = $PROFILE_MOCK_DEFAULT_ID;
}

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    die('Failed to connect to MySQL: ' . $con->connect_error);
}
$con->set_charset('utf8mb4');

$escId = (string) (int) $id;
$result = mysqli_query($con, "SELECT * FROM playertable WHERE id = '$escId' LIMIT 1");
$row = $result ? mysqli_fetch_assoc($result) : null;
if ($row === null) {
    die('Player not found.');
}

$rankResult = mysqli_query(
    $con,
    "SELECT COUNT(*)+1 AS plrank FROM playertable WHERE display = 1 AND rating > (SELECT rating FROM playertable WHERE id='$escId')"
);
$rankRow = mysqli_fetch_row($rankResult);
$rank = (int) $rankRow[0];

$display = (int) $row['Display'] === 1;
$rating = $display ? (int) round((float) $row['Rating']) : null;
$peak = $display && (float) $row['PeakRating'] != 0 ? (int) round((float) $row['PeakRating']) : null;
$games = (int) $row['NumberGames'];
$wins = (int) $row['NumberWins'];
$draws = (int) $row['NumberDraws'];
$losses = (int) $row['NumberLosses'];
$winPct = $games > 0 ? round(100 * (float) $row['WinRatio'], 1) : 0;

$monthResult = mysqli_query(
    $con,
    "SELECT COUNT(*) AS c FROM ratedresults WHERE (idA='$escId' OR idB='$escId') AND Date >= DATE_FORMAT(NOW(), '%Y-%m-01')"
);
$monthRow = mysqli_fetch_row($monthResult);
$gamesThisMonth = (int) $monthRow[0];

$yearResult = mysqli_query(
    $con,
    "SELECT COUNT(*) AS c FROM ratedresults WHERE (idA='$escId' OR idB='$escId') AND YEAR(Date) = YEAR(CURDATE())"
);
$yearRow = mysqli_fetch_row($yearResult);
$gamesThisYear = (int) $yearRow[0];

$recent = [];
$recentSql = "SELECT id, Date, idA, idB, NameA, NameB, GoalsA, GoalsB, ActualScore, AdjustmentA, AdjustmentB "
    . "FROM ratedresults WHERE idA='$escId' OR idB='$escId' ORDER BY Date DESC, id DESC LIMIT 10";
$recentResult = mysqli_query($con, $recentSql);
while ($recentResult && ($g = mysqli_fetch_assoc($recentResult))) {
    $recent[] = pm_parse_game_row($g, $id);
}

$rivals = [];
$rivalSql = "SELECT opp_id, opp_name, games FROM (
    SELECT CASE WHEN idA='$escId' THEN idB ELSE idA END AS opp_id,
           CASE WHEN idA='$escId' THEN NameB ELSE NameA END AS opp_name,
           COUNT(*) AS games
    FROM ratedresults WHERE idA='$escId' OR idB='$escId'
    GROUP BY opp_id, opp_name
) t ORDER BY games DESC LIMIT 6";
$rivalResult = mysqli_query($con, $rivalSql);
while ($rivalResult && ($r = mysqli_fetch_assoc($rivalResult))) {
    $rivals[] = [
        'id' => (int) $r['opp_id'],
        'name' => (string) $r['opp_name'],
        'games' => (int) $r['games'],
    ];
}

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
    $gRes = mysqli_query($con, "SELECT id, Date, idA, idB, NameA, NameB, GoalsA, GoalsB, ActualScore, AdjustmentA, AdjustmentB FROM ratedresults WHERE id = $gid LIMIT 1");
    $gRow = $gRes ? mysqli_fetch_assoc($gRes) : null;
    if ($gRow === null) {
        continue;
    }
    $parsed = pm_parse_highlight_row($gRow, $id);
    $trophies[] = array_merge($def, $parsed);
}

$form = [];
foreach (array_slice($recent, 0, 10) as $g) {
    $form[] = $g['outcome'][0];
}

$peakGap = ($peak !== null && $rating !== null) ? $peak - $rating : 0;

$firstGameResult = mysqli_query(
    $con,
    "SELECT Date FROM ratedresults WHERE idA='$escId' OR idB='$escId' ORDER BY Date ASC, id ASC LIMIT 1"
);
$firstGameRow = $firstGameResult ? mysqli_fetch_assoc($firstGameResult) : null;
$firstGameDate = $firstGameRow ? (string) $firstGameRow['Date'] : (string) $row['JoinDate'];
$firstGameTs = strtotime($firstGameDate) ?: 0;
$tenureYears = pm_years_on_ladder_since($firstGameDate);
$tenureLabel = pm_tenure_plus_label($tenureYears);

$differentOpponents = (int) $row['DifferentOpponents'];

$featuredRival = $rivals[0] ?? null;
$rivalH2h = ['wins' => 0, 'draws' => 0, 'losses' => 0];
if ($featuredRival !== null) {
    $oid = (int) $featuredRival['id'];
    $h2hSql = "SELECT "
        . "SUM(CASE WHEN (idA='$escId' AND ActualScore >= 0.99) OR (idB='$escId' AND ActualScore <= 0.01) THEN 1 ELSE 0 END) AS wins, "
        . "SUM(CASE WHEN ActualScore = 0.5 THEN 1 ELSE 0 END) AS draws, "
        . "SUM(CASE WHEN (idA='$escId' AND ActualScore <= 0.01) OR (idB='$escId' AND ActualScore >= 0.99) THEN 1 ELSE 0 END) AS losses "
        . "FROM ratedresults WHERE (idA='$escId' AND idB='$oid') OR (idA='$oid' AND idB='$escId')";
    $h2hRes = mysqli_query($con, $h2hSql);
    if ($h2hRes && ($h2hRow = mysqli_fetch_assoc($h2hRes))) {
        $rivalH2h['wins'] = (int) $h2hRow['wins'];
        $rivalH2h['draws'] = (int) $h2hRow['draws'];
        $rivalH2h['losses'] = (int) $h2hRow['losses'];
    }
}

$busiest = ['month' => null, 'day' => null, 'year' => null];
$busiestSql = [
    'month' => "SELECT DATE_FORMAT(Date, '%Y-%m') AS k, COUNT(*) AS c FROM ratedresults "
        . "WHERE idA='$escId' OR idB='$escId' GROUP BY k ORDER BY c DESC LIMIT 1",
    'day' => "SELECT DATE(Date) AS k, COUNT(*) AS c FROM ratedresults "
        . "WHERE idA='$escId' OR idB='$escId' GROUP BY k ORDER BY c DESC LIMIT 1",
    'year' => "SELECT YEAR(Date) AS k, COUNT(*) AS c FROM ratedresults "
        . "WHERE idA='$escId' OR idB='$escId' GROUP BY k ORDER BY c DESC LIMIT 1",
];
foreach ($busiestSql as $key => $sql) {
    $br = mysqli_query($con, $sql);
    if ($br && ($brow = mysqli_fetch_assoc($br))) {
        $busiest[$key] = ['key' => $brow['k'], 'count' => (int) $brow['c']];
    }
}

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
    'first_game_date' => date('M Y', $firstGameTs ?: time()),
    'different_opponents' => $differentOpponents,
    'different_victims' => (int) $row['DifferentVictims'],
    'featured_rival' => $featuredRival,
    'rival_h2h' => $rivalH2h,
    'busiest' => $busiest,
    'average_opponent_rating' => $display ? (int) round((float) $row['AverageOpponentRating']) : null,
    'goal_ratio' => $display ? round((float) $row['GoalRatio'], 2) : null,
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
    'recent' => $recent,
    'rivals' => $rivals,
    'trophies' => $trophies,
    'form' => $form,
    'initial' => strtoupper(substr((string) $row['Name'], 0, 1)),
];

$Name = $pm['name'];
$Rating = $row['Rating'];
$PeakRating = $row['PeakRating'];
$NumberGames = $games;
$Display = (int) $row['Display'];
