<?php
declare(strict_types=1);
/** Variant probe: narrow shapes for honours / calendar-geo / peak-rating er-join, with parity. */
require __DIR__ . '/../../site/public_html/includes/amiga_rating_history_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_lb_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_lb_snapshot_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_player_tournament_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

function ms(float $t0): float { return round((microtime(true) - $t0) * 1000, 1); }

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

function run_stmt(mysqli $con, string $sql, string $types, array $params): array {
    $stmt = $con->prepare($sql);
    if (!$stmt) { throw new RuntimeException($con->error); }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    $stmt->close();
    return $rows;
}

foreach (['year:2024', 'month:2014-07', 'event:589'] as $as) {
    $_GET['as'] = $as;
    amiga_snapshot_context_reset();
    $ctx = amiga_snapshot_context_from_request($con);
    $cutoff = $ctx->cutoff();
    $d = $cutoff['event_date']; $c = $cutoff['chrono']; $tid = $cutoff['tournament_id'];
    echo "=== {$as} (cutoff tid {$tid}) ===\n";

    // --- honours: narrow shape via shared helper ---
    $sqlNew = 'SELECT p.id AS player_id, p.name AS player_name, p.country,
                   COALESCE(s.Rating, 0) AS rating, s.tournaments_played,
                   s.event_gold, s.event_silver, s.event_bronze, s.event_podiums, s.perfect_events
            ' . amiga_lb_snapshot_from_sql('s') . '
            WHERE s.tournaments_played > 0
            ORDER BY ' . amiga_lb_tournament_honours_order_sql('s');
    $t0 = microtime(true);
    $newH = run_stmt($con, $sqlNew, 'sdi', [$d, $c, $tid]);
    $tNewH = ms($t0);
    $t0 = microtime(true);
    $oldH = amiga_lb_honours_rows_at_cutoff($con, $ctx);
    $tOldH = ms($t0);
    $parH = (json_encode(array_map(fn($r) => array_map('strval', $r), $oldH))
          === json_encode(array_map(fn($r) => array_map('strval', $r), $newH))) ? 'OK' : 'MISMATCH';
    echo "  honours: old {$tOldH} ms -> new {$tNewH} ms, parity {$parH} (" . count($newH) . ")\n";

    // --- calendar-geo: narrow shape via shared helper ---
    $sqlNewG = 'SELECT p.id AS player_id, p.name AS player_name, p.country,
                   COALESCE(s.Rating, 0) AS rating, s.peak_year_games, s.peak_year_games_year,
                   s.peak_year_tournaments, s.peak_year_tournaments_year, s.countries_played_in,
                   s.opponent_countries_faced, s.opponent_countries_beaten
            ' . amiga_lb_snapshot_from_sql('s') . '
            WHERE s.NumberGames > 0
            ORDER BY s.peak_year_games DESC, s.peak_year_games_year ASC, p.id ASC';
    $t0 = microtime(true);
    $newG = run_stmt($con, $sqlNewG, 'sdi', [$d, $c, $tid]);
    $tNewG = ms($t0);
    $t0 = microtime(true);
    $oldG = amiga_lb_calendar_geo_rows_at_cutoff($con, $ctx);
    $tOldG = ms($t0);
    $parG = (json_encode(array_map(fn($r) => array_map('strval', $r), $oldG))
          === json_encode(array_map(fn($r) => array_map('strval', $r), $newG))) ? 'OK' : 'MISMATCH';
    echo "  calendar-geo: old {$tOldG} ms -> new {$tNewG} ms, parity {$parG} (" . count($newG) . ")\n";

    // --- peak-rating er join: narrow window + PK join-back variant ---
    $erOld = '(SELECT x.player_id, x.peak_elo_rank, x.peak_elo_rank_tournament_id FROM (
                 SELECT er.player_id, er.peak_elo_rank, er.peak_elo_rank_tournament_id,
                        ROW_NUMBER() OVER (PARTITION BY er.player_id
                          ORDER BY er.event_date DESC, er.event_chrono DESC, er.tournament_id DESC) AS rn
                 FROM amiga_player_elo_rank_at_event er
                 WHERE (er.event_date, er.event_chrono, er.tournament_id) <= (?, ?, ?)
               ) x WHERE x.rn = 1)';
    $erNew = '(SELECT er.player_id, er.peak_elo_rank, er.peak_elo_rank_tournament_id
               FROM amiga_player_elo_rank_at_event er
               INNER JOIN (
                 SELECT y.player_id, y.tournament_id FROM (
                   SELECT e2.player_id, e2.tournament_id,
                          ROW_NUMBER() OVER (PARTITION BY e2.player_id
                            ORDER BY e2.event_date DESC, e2.event_chrono DESC, e2.tournament_id DESC) AS rn
                   FROM amiga_player_elo_rank_at_event e2
                   WHERE (e2.event_date, e2.event_chrono, e2.tournament_id) <= (?, ?, ?)
                 ) y WHERE y.rn = 1
               ) latest ON latest.player_id = er.player_id AND latest.tournament_id = er.tournament_id)';
    $erDense = '(SELECT er.player_id, er.peak_elo_rank, er.peak_elo_rank_tournament_id
               FROM amiga_player_elo_rank_at_event er WHERE er.tournament_id = ?)';

    $t0 = microtime(true);
    $a = run_stmt($con, 'SELECT * FROM ' . $erOld . ' q ORDER BY q.player_id', 'sdi', [$d, $c, $tid]);
    $tA = ms($t0);
    $t0 = microtime(true);
    $b = run_stmt($con, 'SELECT * FROM ' . $erNew . ' q ORDER BY q.player_id', 'sdi', [$d, $c, $tid]);
    $tB = ms($t0);
    $t0 = microtime(true);
    $e = run_stmt($con, 'SELECT * FROM ' . $erDense . ' q ORDER BY q.player_id', 'i', [$tid]);
    $tE = ms($t0);
    $pAB = (json_encode($a) === json_encode($b)) ? 'OK' : 'MISMATCH';
    $pAE = (json_encode($a) === json_encode($e)) ? 'OK' : 'MISMATCH';
    echo "  er-join: old-window {$tA} ms -> narrow+joinback {$tB} ms (parity {$pAB}) -> dense-event {$tE} ms (parity {$pAE}); rows " . count($a) . '/' . count($b) . '/' . count($e) . "\n";
}
$con->close();