<?php

declare(strict_types=1);

/**
 * Self-test for four stored results that were wrong until 2026-11-22:
 * activity-league tie-break, deleted accounts, league-win milestone source, Hall of Fame peak date.
 *
 *   php scripts/test_ops_stored_results.php [--keep]
 *
 * Plays a small invented ladder in the throwaway database ko2unity_storedtest (table layout and player
 * rows copied from ko2unity_work, read only) and drops it at the end.
 */

const TEST_DB = 'ko2unity_storedtest';
const SOURCE_DB = 'ko2unity_work';
const DELETED_ID = 999901; // games, but no playertable row

$public = dirname(__DIR__) . '/site/public_html';
$_SERVER['DOCUMENT_ROOT'] = $public;
require_once $public . '/ops/includes/ops_bootstrap.php';
require_once $public . '/ops/includes/ops_reset_universe.php';
require_once $public . '/ops/includes/ops_dispatch.php';
require_once $public . '/ops/modules/process_completed_game.php';
require_once $public . '/includes/league_standings.php';
k2_ops_require_cli();

$failures = 0;
function t_assert(string $label, bool $ok, string $detail = ''): void
{
    global $failures;
    $failures += $ok ? 0 : 1;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . ($ok || $detail === '' ? '' : " ($detail)") . PHP_EOL;
}

/** Run silently; ops code logs every step to stdout. */
function t_quiet(callable $fn): mixed
{
    k2_ops_dispatch_http_begin();
    try {
        return $fn();
    } finally {
        K2OpsDispatchHttpContext::$enabled = false;
    }
}

function t_row(mysqli $con, string $sql): array
{
    return $con->query($sql)->fetch_assoc() ?? [];
}

$con = new mysqli('127.0.0.1', 'root', '', SOURCE_DB);
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");
$con->query("SET SESSION sql_mode = ''");
$con->query('DROP DATABASE IF EXISTS ' . TEST_DB);
$con->query('CREATE DATABASE ' . TEST_DB . ' CHARACTER SET utf8mb4');
$tables = array_column($con->query(
    "SELECT TABLE_NAME AS t FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . SOURCE_DB . "'"
)->fetch_all(MYSQLI_ASSOC), 't');
foreach ($tables as $t) {
    $con->query('CREATE TABLE ' . TEST_DB . ".`$t` LIKE `$t`");
}
$con->query('INSERT INTO ' . TEST_DB . '.milestone_definitions SELECT * FROM milestone_definitions');
$con->query('INSERT INTO ' . TEST_DB . '.playertable SELECT * FROM playertable ORDER BY ID LIMIT 3');
$con->select_db(TEST_DB);
[$a, $b, $c] = array_map('intval', array_column($con->query('SELECT ID FROM playertable ORDER BY ID')->fetch_all(MYSQLI_ASSOC), 'ID'));
$d = DELETED_ID;

// [date, player A, goals A, goals B, player B]
$games = [
    // a and b play two games each. a's first is g1 (as A), b's is g2 (as A); as B, b comes before a.
    ['2025-01-06 10:00:00', $a, 3, 1, $c],
    ['2025-01-06 10:10:00', $b, 3, 1, $c],
    ['2025-01-06 10:20:00', $c, 1, 3, $b],
    ['2025-01-06 10:30:00', $c, 1, 3, $a],
    // The deleted account: three games.
    ['2025-01-07 10:00:00', $d, 5, 0, $c],
    ['2025-01-07 10:10:00', $b, 2, 2, $d],
    ['2025-01-07 10:20:00', $d, 4, 1, $c],
];
for ($day = 1; $day <= 12; $day++) { // a wins twelve daily points leagues
    $games[] = [sprintf('2025-02-%02d 10:00:00', $day), $a, 4, 1, $b];
}
$ins = $con->prepare('INSERT INTO ratedresults (id, `Date`, idA, NameA, GoalsA, GoalsB, NameB, idB) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
foreach ($games as $i => [$date, $idA, $gA, $gB, $idB]) {
    $id = $i + 1;
    [$nameA, $nameB] = ["p$idA", "p$idB"];
    $ins->bind_param('isisiisi', $id, $date, $idA, $nameA, $gA, $gB, $nameB, $idB);
    $ins->execute();
}
t_quiet(function () use ($con, $games) {
    k2_ops_reset_universe($con, false);
    k2_ops_truncate_aggregate_tables($con, false);
    foreach (array_keys($games) as $i) {
        k2_ops_process_completed_game($con, $i + 1);
    }
    k2_league_finalize_due_periods($con, new DateTimeImmutable('2025-03-02T00:00:00Z'));
});

// 1. Activity tie: c 4 games, then a and b with 2 each; a played first.
$rank = array_column($con->query(
    "SELECT player_id FROM player_league_award WHERE league_kind = 'activity' AND period_type = 'day' "
    . "AND period_start = '2025-01-06' ORDER BY finish_rank"
)->fetch_all(MYSQLI_ASSOC), 'player_id');
t_assert('activity tie goes to the earlier first game on either side', array_map('intval', $rank) === [$c, $a, $b], implode(',', $rank));

// 2. Deleted account keeps its rating and its games.
$g5 = t_row($con, 'SELECT NewRatingA FROM ratedresults WHERE id = 5');
$g6 = t_row($con, 'SELECT RatingB, NewRatingB FROM ratedresults WHERE id = 6');
$g7 = t_row($con, 'SELECT RatingA FROM ratedresults WHERE id = 7');
t_assert('deleted account: rating carries on as B', $g6['RatingB'] === $g5['NewRatingA'], json_encode([$g5, $g6]));
t_assert('deleted account: rating carries on as A', $g7['RatingA'] === $g6['NewRatingB'], json_encode([$g6, $g7]));
$gst = t_row($con, 'SELECT NumberOfPlayers, GamesPlayed FROM generalstatstable WHERE id = 1');
t_assert('deleted account: counted as one player', (int) $gst['NumberOfPlayers'] === 4, json_encode($gst));
$cRow = t_row($con, "SELECT BiggestWinCulprits, MostGoalsConcededVictims FROM playertable WHERE ID = $c");
// By hand: a conceded most against c (1 victim). b and the deleted account have their biggest win over c (2).
t_assert(
    'deleted account: one record per player, not one per game',
    [(int) $cRow['MostGoalsConcededVictims'], (int) $cRow['BiggestWinCulprits']] === [1, 2],
    json_encode($cRow)
);
$state = k2_post_game_player_load($con, $d, 8);
t_assert(
    'deleted account: rebuilt state',
    [$state['games'], $state['wins'], $state['draws'], $state['goals_for'], $state['most_goals_scored'], $state['different_opponents']]
        === [3, 2, 1, 11, 5, 2],
    json_encode([$state['games'], $state['wins'], $state['draws'], $state['goals_for']])
);
$welcome = t_row($con, "SELECT source_game_id FROM player_milestones WHERE milestone_key = 'newbie_welcomer' AND player_id = $b");
t_assert('deleted account: second game is no debut', (int) ($welcome['source_game_id'] ?? 0) !== 6, json_encode($welcome));

// 3. The tenth league win names its league.
$m = t_row($con, "SELECT * FROM player_milestones WHERE milestone_key = 'league_wins_10' AND player_id = $a");
$w = t_row($con, "SELECT league_kind, period_type, period_start, period_end FROM player_league_award WHERE is_winner = 1 "
    . "AND player_id = $a ORDER BY period_end, league_kind, period_type, period_start LIMIT 1 OFFSET 9");
t_assert(
    'league_wins_10 stores its league',
    $m !== [] && [$m['source_kind'], $m['source_league_kind'], $m['source_period_type'], $m['source_period_start'], $m['achieved_at']]
        === ['league', $w['league_kind'], $w['period_type'], $w['period_start'], $w['period_end']],
    json_encode([$m, $w])
);

// 4. Hall of Fame peak: a stored peak rounded up (2000.007 -> 2000.01) is not a new record in the next game.
$records = k2_post_game_server_records_new();
$st = k2_post_game_player_state_new();
$play = function (int $gameId, float $peak, int $peakGameId, string $date) use (&$records, $st, $a, $b) {
    $players = [$a => ['peak_rating' => $peak, 'peak_rating_game_id' => $peakGameId] + $st, $b => $st];
    $game = ['id' => $gameId, 'Date' => $date, 'idA' => $a, 'idB' => $b, 'GoalsA' => 1, 'GoalsB' => 0];
    $derived = ['ActualScore' => 1.0, 'GoalDifference' => 1, 'SumOfGoals' => 1,
        'DDPlayerA' => 0, 'DDPlayerB' => 0, 'CSPlayerA' => 1, 'CSPlayerB' => 0];
    k2_post_game_update_server_records_after_game($records, $game, $derived, $players, []);
};
$play(101, 2000.007, 101, '2025-05-01 10:00:00');
$play(102, 2000.01, 101, '2025-05-02 10:00:00');
t_assert('Hall of Fame peak keeps its date', $records['biggest_peak_rating']['date'] === '2025-05-01 10:00:00', json_encode($records['biggest_peak_rating']));
$play(103, 2000.5, 103, '2025-05-03 10:00:00');
t_assert('Hall of Fame peak moves with a real new peak', $records['biggest_peak_rating']['date'] === '2025-05-03 10:00:00');

if (!in_array('--keep', $argv, true)) {
    $con->query('DROP DATABASE ' . TEST_DB);
}
echo $failures === 0 ? 'ALL PASS' . PHP_EOL : "$failures FAILED" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
