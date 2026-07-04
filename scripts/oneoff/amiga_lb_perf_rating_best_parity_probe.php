<?php
declare(strict_types=1);
/** Parity: performance-rating Best wing — old wide windows vs new narrow shapes. */
require __DIR__ . '/../../site/public_html/includes/amiga_rating_history_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_lb_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_lb_snapshot_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_player_tournament_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

function ms(float $t0): float { return round((microtime(true) - $t0) * 1000, 1); }

function row_key(array $row): string {
    return implode('|', array_map(static fn($v) => (string) ($v ?? 'NULL'), $row));
}

function old_best_tt(mysqli $con, AmigaSnapshotContext $ctx): array {
    $cutoff = $ctx->cutoff();
    $visibility = amiga_tournament_public_visibility_where('t');
    $sql = 'SELECT ranked.player_id, ranked.player_name, ranked.Rating, ranked.country, ranked.NumberGames,
                   ranked.tournament_id, ranked.tournament_name, ranked.event_date, ranked.event_chrono,
                   ranked.event_games, ranked.event_wins, ranked.event_draws, ranked.event_losses,
                   ranked.performance_rating, ranked.host_country
            FROM (
                SELECT pl.id AS player_id, pl.name AS player_name, s.Rating, pl.country AS country, s.NumberGames,
                       part.tournament_id, part.tournament_name, t.country AS host_country,
                       part.event_date, part.event_chrono, part.games AS event_games,
                       part.wins AS event_wins, part.draws AS event_draws, part.losses AS event_losses,
                       part.performance_rating,
                       ROW_NUMBER() OVER (
                           PARTITION BY part.player_id
                           ORDER BY part.performance_rating DESC, part.games DESC, part.tournament_id DESC
                       ) AS rn
                FROM amiga_player_event_snapshots part
                INNER JOIN amiga_players pl ON pl.id = part.player_id
                INNER JOIN (
                    SELECT x.player_id, x.Rating, x.NumberGames FROM (
                        SELECT snap.player_id, snap.Rating, snap.NumberGames,
                            ROW_NUMBER() OVER (
                                PARTITION BY snap.player_id
                                ORDER BY snap.event_date DESC, snap.event_chrono DESC, snap.tournament_id DESC
                            ) AS rn
                        FROM amiga_player_event_snapshots snap
                        WHERE (snap.event_date, snap.event_chrono, snap.tournament_id) <= (?, ?, ?)
                    ) x WHERE x.rn = 1
                ) s ON s.player_id = part.player_id
                INNER JOIN tournaments t ON t.id = part.tournament_id
                WHERE (part.event_date, part.event_chrono, part.tournament_id) <= (?, ?, ?)
                  AND part.performance_rating IS NOT NULL AND part.games >= 2
                  AND s.NumberGames > 0 AND ' . $visibility . '
            ) ranked WHERE ranked.rn = 1
            ORDER BY ranked.performance_rating DESC, ranked.event_games DESC, ranked.Rating DESC, ranked.player_id ASC';
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

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

foreach (['present', 'year:2001', 'year:2024', 'month:2014-07', 'event:589', 'event:22', 'month:2025-09'] as $as) {
    if ($as === 'present') { unset($_GET['as']); } else { $_GET['as'] = $as; }
    amiga_snapshot_context_reset();
    $ctx = amiga_snapshot_context_from_request($con);

    $t0 = microtime(true);
    $new = amiga_lb_performance_rating_rows($con, $ctx);
    $tNew = ms($t0);
    $newNorm = array_map(static fn($r) => array_map('strval', array_map(fn($v) => $v ?? 'NULL', $r)), $new);

    if ($ctx->isActive()) {
        $t0 = microtime(true);
        $old = old_best_tt($con, $ctx);
        $tOld = ms($t0);
        $par = (json_encode($old) === json_encode($newNorm)) ? 'OK' : 'MISMATCH';
        echo "{$as}: old {$tOld} ms -> new {$tNew} ms, parity {$par} (" . count($old) . '/' . count($newNorm) . " rows)\n";
    } else {
        echo "{$as}: new {$tNew} ms (" . count($newNorm) . " rows)\n";
    }
}
$con->close();