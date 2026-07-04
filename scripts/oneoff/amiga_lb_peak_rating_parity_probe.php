<?php
declare(strict_types=1);
/** Parity: full peak-rating TT query — old window-er shape vs new dense-event lib shape. */
require __DIR__ . '/../../site/public_html/includes/amiga_rating_history_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_lb_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_lb_snapshot_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_player_tournament_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

function ms(float $t0): float { return round((microtime(true) - $t0) * 1000, 1); }

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

function old_peak_rows(mysqli $con, AmigaSnapshotContext $ctx): array {
    $cutoff = $ctx->cutoff();
    $selectBase = 'SELECT p.id AS ID, p.name AS Name, s.Rating, p.country AS Country, s.NumberGames, '
        . 's.PeakRating, s.LowestRating, s.AverageOpponentRating, s.HighestRatedVictim, s.LowestRatedCulprit, '
        . 's.peak_rating_tournament_id, tpr.name AS peak_rating_tournament_name, peak_snap.rating_delta AS peak_rating_delta, ';
    $joinPeakSnap = ' LEFT JOIN amiga_player_event_snapshots peak_snap '
        . 'ON peak_snap.player_id = p.id AND peak_snap.tournament_id = s.peak_rating_tournament_id ';
    $peakRankPlayedJoinTt = ' LEFT JOIN amiga_player_event_snapshots pr_rank_snap '
        . 'ON pr_rank_snap.player_id = p.id AND pr_rank_snap.tournament_id = er.peak_elo_rank_tournament_id '
        . 'AND pr_rank_snap.NumberGames > 0 ';
    $sql = $selectBase
        . 'tpr.event_date AS peak_rating_date, er.peak_elo_rank, er.peak_elo_rank_tournament_id, '
        . 'tpke.name AS peak_elo_rank_tournament_name, tpke.event_date AS peak_elo_rank_date, '
        . '(pr_rank_snap.player_id IS NOT NULL) AS peak_elo_rank_played_in_event '
        . amiga_lb_snapshot_from_sql('s')
        . ' LEFT JOIN tournaments tpr ON tpr.id = s.peak_rating_tournament_id '
        . $joinPeakSnap
        . ' LEFT JOIN ('
        . '    SELECT x.player_id, x.peak_elo_rank, x.peak_elo_rank_tournament_id FROM ('
        . '        SELECT er.player_id, er.peak_elo_rank, er.peak_elo_rank_tournament_id,'
        . '            ROW_NUMBER() OVER ('
        . '                PARTITION BY er.player_id'
        . '                ORDER BY er.event_date DESC, er.event_chrono DESC, er.tournament_id DESC'
        . '            ) AS rn'
        . '        FROM amiga_player_elo_rank_at_event er'
        . '        WHERE (er.event_date, er.event_chrono, er.tournament_id) <= (?, ?, ?)'
        . '    ) x WHERE x.rn = 1'
        . ') er ON er.player_id = p.id '
        . 'LEFT JOIN tournaments tpke ON tpke.id = er.peak_elo_rank_tournament_id '
        . $peakRankPlayedJoinTt
        . 'WHERE ' . amiga_lb_player_where_sql() . ' '
        . 'ORDER BY s.PeakRating DESC, s.Rating DESC';
    $stmt = $con->prepare($sql);
    $d = $cutoff['event_date']; $c = $cutoff['chrono']; $t = $cutoff['tournament_id'];
    $stmt->bind_param('sdisdi', $d, $c, $t, $d, $c, $t);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) { $rows[] = array_map('strval', array_map(fn($v) => $v ?? 'NULL', $r)); }
    $stmt->close();
    return $rows;
}

foreach (['year:2024', 'year:2001', 'month:2002-06', 'month:2014-07', 'event:589', 'event:22', 'month:2025-09'] as $as) {
    $_GET['as'] = $as;
    amiga_snapshot_context_reset();
    $ctx = amiga_snapshot_context_from_request($con);
    $t0 = microtime(true);
    $old = old_peak_rows($con, $ctx);
    $tOld = ms($t0);
    $t0 = microtime(true);
    $res = amiga_lb_query_peak_rating($con, $ctx);
    $new = [];
    while ($r = $res->fetch_assoc()) { $new[] = array_map('strval', array_map(fn($v) => $v ?? 'NULL', $r)); }
    $tNew = ms($t0);
    $par = (json_encode($old) === json_encode($new)) ? 'OK' : 'MISMATCH';
    echo "{$as}: old {$tOld} ms -> new {$tNew} ms, parity {$par} (" . count($old) . '/' . count($new) . " rows)\n";
}
$con->close();