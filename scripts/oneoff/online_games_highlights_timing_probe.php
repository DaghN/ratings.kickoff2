<?php
declare(strict_types=1);
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../site/public_html') ?: '';
include __DIR__ . '/../../site/config/ko2unitydb_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/games_highlights_helpers.php';
$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');
function bench(mysqli $c, string $label, string $sql): void {
    $t0 = microtime(true);
    $r = mysqli_query($c, $sql);
    $n = 0; while ($r && mysqli_fetch_assoc($r)) { $n++; }
    if ($r) { mysqli_free_result($r); }
    echo $label . ': ' . round((microtime(true) - $t0) * 1000, 1) . " ms rows=$n\n";
}
$old = 'SELECT id FROM ratedresults ORDER BY SumOfGoals DESC, id ASC LIMIT 100';
$new = 'SELECT r.id FROM (SELECT id FROM ratedresults ORDER BY SumOfGoals DESC, id ASC LIMIT 100) top_ids INNER JOIN ratedresults r ON r.id = top_ids.id';
$narrow = 'SELECT id FROM (SELECT id, SumOfGoals FROM ratedresults ORDER BY SumOfGoals DESC, id ASC LIMIT 100) t';
$fullJoin = 'SELECT r.id, r.Date, r.idA, r.NameA, r.idB, r.NameB, r.GoalsA, r.GoalsB, r.GoalDifference, r.SumOfGoals, r.ActualScore, r.RatingA, r.RatingB, r.RatingDifference, r.ExpectedScoreA, r.ExpectedScoreB, r.AdjustmentA, r.AdjustmentB FROM (SELECT id, SumOfGoals FROM ratedresults ORDER BY SumOfGoals DESC, id ASC LIMIT 100) top INNER JOIN ratedresults r ON r.id = top.id ORDER BY r.SumOfGoals DESC, r.id ASC';
bench($con, 'old_wide_select', 'SELECT id, Date, idA, NameA, idB, NameB, GoalsA, GoalsB, GoalDifference, SumOfGoals, ActualScore, RatingA, RatingB, RatingDifference, ExpectedScoreA, ExpectedScoreB, AdjustmentA, AdjustmentB FROM ratedresults ORDER BY SumOfGoals DESC, id ASC LIMIT 100');
bench($con, 'inner_id_only', $new);
bench($con, 'inner_narrow_cols', $fullJoin);
bench($con, 'lib_fetch', '');
$t0 = microtime(true); k2_games_highlights_fetch($con, 'most_goals'); echo 'lib_fetch: ' . round((microtime(true)-$t0)*1000,1) . " ms\n";
$r = mysqli_query($con, 'EXPLAIN ANALYZE SELECT id FROM ratedresults ORDER BY SumOfGoals DESC, id ASC LIMIT 100');
while ($r && ($row = mysqli_fetch_assoc($r))) { echo 'EXPLAIN: ' . ($row['EXPLAIN'] ?? json_encode($row)) . "\n"; }
if ($r) { mysqli_free_result($r); }
$con->close();