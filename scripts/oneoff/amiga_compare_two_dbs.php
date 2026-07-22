<?php
declare(strict_types=1);
/**
 * Compare two Amiga DBs (e.g. ko2amiga_work vs ko2amiga_seal_cmp).
 * Usage: php amiga_compare_two_dbs.php <db_a> <db_b>
 */
if ($argc < 3) {
    fwrite(STDERR, "Usage: php amiga_compare_two_dbs.php <db_a> <db_b>\n");
    exit(2);
}
$dbA = (string) $argv[1];
$dbB = (string) $argv[2];
require dirname(__DIR__, 2) . '/site/config/ko2amiga_config.php';

function connect_db(string $host, string $user, string $pass, string $db, int $port): mysqli
{
    $con = new mysqli($host, $user, $pass, $db, $port);
    if ($con->connect_errno) {
        throw new RuntimeException("connect $db: " . $con->connect_error);
    }
    $con->set_charset('utf8mb4');
    return $con;
}

function tip(mysqli $con): array
{
    $r = $con->query(
        'SELECT id, name, event_date FROM tournaments '
        . 'WHERE COALESCE(rating_finalized,0)=1 '
        . 'ORDER BY event_date DESC, chrono DESC, id DESC LIMIT 1'
    );
    $row = $r ? $r->fetch_assoc() : null;
    return $row ?: ['id' => 0, 'name' => '', 'event_date' => ''];
}

function scalar(mysqli $con, string $sql): string
{
    $r = $con->query($sql);
    if (!$r) {
        return 'ERR:' . $con->error;
    }
    $row = $r->fetch_row();
    return $row ? (string) $row[0] : '';
}

$a = connect_db($dbhost, $username, $password, $dbA, (int) $dbportnum);
$b = connect_db($dbhost, $username, $password, $dbB, (int) $dbportnum);

$checks = [
    'tip_id' => 'SELECT id FROM tournaments WHERE COALESCE(rating_finalized,0)=1 ORDER BY event_date DESC, chrono DESC, id DESC LIMIT 1',
    'tip_name' => 'SELECT name FROM tournaments WHERE COALESCE(rating_finalized,0)=1 ORDER BY event_date DESC, chrono DESC, id DESC LIMIT 1',
    'tournaments' => 'SELECT COUNT(*) FROM tournaments',
    'finalized' => 'SELECT COUNT(*) FROM tournaments WHERE COALESCE(rating_finalized,0)=1',
    'games' => 'SELECT COUNT(*) FROM amiga_games',
    'players' => 'SELECT COUNT(*) FROM amiga_players',
    'event_snaps' => 'SELECT COUNT(*) FROM amiga_player_event_snapshots',
    'current_rows' => 'SELECT COUNT(*) FROM amiga_player_current',
    'matchup_sum' => 'SELECT COALESCE(SUM(games),0) FROM amiga_player_matchup_summary',
    'matchup_pairs' => 'SELECT COUNT(*) FROM amiga_player_matchup_summary',
    'realm_snaps' => 'SELECT COUNT(*) FROM amiga_realm_snapshots',
    'latest_realm_tid' => 'SELECT tournament_id FROM amiga_realm_snapshots ORDER BY event_date DESC, event_chrono DESC, tournament_id DESC LIMIT 1',
    'gst_most_games' => 'SELECT MostGamesPlayed FROM amiga_generalstats WHERE id=1',
    'current_elo_checksum' => 'SELECT COALESCE(SUM(elo_rank),0) FROM amiga_player_current',
    'current_rating_checksum' => 'SELECT ROUND(COALESCE(SUM(Rating),0),4) FROM amiga_player_current',
    'current_mgs_checksum' => 'SELECT COALESCE(SUM(MostGoalsScoredCulprits),0) FROM amiga_player_current',
    'current_bw_checksum' => 'SELECT COALESCE(SUM(BiggestWinCulprits),0) FROM amiga_player_current',
    'max_tournament_id' => 'SELECT MAX(id) FROM tournaments',
];

echo "A=$dbA  B=$dbB\n";
$tipA = tip($a);
$tipB = tip($b);
echo "tip_A=#{$tipA['id']} {$tipA['name']} {$tipA['event_date']}\n";
echo "tip_B=#{$tipB['id']} {$tipB['name']} {$tipB['event_date']}\n";

$mismatches = 0;
foreach ($checks as $label => $sql) {
    $va = scalar($a, $sql);
    $vb = scalar($b, $sql);
    $ok = ($va === $vb) ? 'OK' : 'DIFF';
    if ($ok === 'DIFF') {
        $mismatches++;
    }
    echo str_pad($label, 24) . " A=$va  B=$vb  $ok\n";
}

// Per-player present sample: count players where elo/rating/inverse differ
$a->select_db($dbA);
$b->select_db($dbB);
// Use information from both via fully-qualified names
$sqlDiff = "
SELECT COUNT(*) FROM (
  SELECT a.player_id
  FROM `{$dbA}`.amiga_player_current a
  INNER JOIN `{$dbB}`.amiga_player_current b ON b.player_id = a.player_id
  WHERE NOT (a.elo_rank <=> b.elo_rank)
     OR NOT (a.Rating <=> b.Rating)
     OR NOT (a.NumberGames <=> b.NumberGames)
     OR NOT (a.MostGoalsScoredCulprits <=> b.MostGoalsScoredCulprits)
     OR NOT (a.BiggestWinCulprits <=> b.BiggestWinCulprits)
     OR NOT (a.last_tournament_id <=> b.last_tournament_id)
) x";
$onlyA = scalar($a, "SELECT COUNT(*) FROM `{$dbA}`.amiga_player_current a LEFT JOIN `{$dbB}`.amiga_player_current b ON b.player_id=a.player_id WHERE b.player_id IS NULL");
$onlyB = scalar($a, "SELECT COUNT(*) FROM `{$dbB}`.amiga_player_current b LEFT JOIN `{$dbA}`.amiga_player_current a ON a.player_id=b.player_id WHERE a.player_id IS NULL");
$presentDiff = scalar($a, $sqlDiff);
echo str_pad('present_player_diffs', 24) . " $presentDiff\n";
echo str_pad('present_only_in_A', 24) . " $onlyA\n";
echo str_pad('present_only_in_B', 24) . " $onlyB\n";
if ((int) $presentDiff > 0 || (int) $onlyA > 0 || (int) $onlyB > 0) {
    $mismatches++;
}

// Kitchen tournaments after tip in A (work)
$extraA = scalar($a, "SELECT COUNT(*) FROM tournaments t
  INNER JOIN (SELECT event_date, chrono, id FROM tournaments WHERE id=" . (int) $tipB['id'] . ") tip
  ON (t.event_date > tip.event_date OR (t.event_date=tip.event_date AND t.chrono > tip.chrono)
      OR (t.event_date=tip.event_date AND t.chrono=tip.chrono AND t.id > tip.id))");
echo str_pad('tournaments_after_B_tip', 24) . " in_A=$extraA\n";

echo "\n";
if ($mismatches === 0) {
    echo "MATCH: $dbA equals seal baseline $dbB on compared metrics.\n";
    exit(0);
}
echo "MISMATCH: $mismatches check group(s) differ. Local work is NOT identical to this seal.\n";
exit(1);