<?php
declare(strict_types=1);
require __DIR__ . '/../../site/public_html/includes/amiga_rating_history_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_lb_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_lb_snapshot_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_player_tournament_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

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
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    $stmt->close();
    return $rows;
}

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');
$_GET['as'] = 'year:2024';
amiga_snapshot_context_reset();
$ctx = amiga_snapshot_context_from_request($con);
$old = old_best_tt($con, $ctx);
$new = amiga_lb_performance_rating_rows_at_cutoff($con, $ctx);

$oldById = [];
foreach ($old as $r) { $oldById[(int)$r['player_id']] = $r; }
$newById = [];
foreach ($new as $r) { $newById[(int)$r['player_id']] = $r; }

$diffs = 0;
foreach ($oldById as $pid => $or) {
    $nr = $newById[$pid] ?? null;
    if ($nr === null) { echo "missing new {$pid}\n"; continue; }
    foreach ($or as $k => $v) {
        $ov = (string)($v ?? 'NULL');
        $nv = (string)($nr[$k] ?? 'NULL');
        if ($ov !== $nv) {
            echo "player {$pid} col {$k}: old={$ov} new={$nv}\n";
            $diffs++;
            if ($diffs >= 20) break 2;
        }
    }
}
if ($diffs === 0) {
    echo "per-player fields match; checking order...\n";
    for ($i = 0; $i < min(count($old), count($new)); $i++) {
        if ((int)$old[$i]['player_id'] !== (int)$new[$i]['player_id']) {
            echo "order diff at {$i}: old pid {$old[$i]['player_id']} new pid {$new[$i]['player_id']}\n";
            break;
        }
    }
}
$con->close();
// json mismatch debug
$_GET['as'] = 'year:2001';
amiga_snapshot_context_reset();
$ctx = amiga_snapshot_context_from_request($con);
$old = old_best_tt($con, $ctx);
$new = amiga_lb_performance_rating_rows_at_cutoff($con, $ctx);
$norm = static fn($rows) => array_map(static fn($r) => array_map('strval', array_map(fn($v) => $v ?? 'NULL', $r)), $rows);
$jo = json_encode($norm($old));
$jn = json_encode($norm($new));
echo "year:2001 json len old=" . strlen($jo) . " new=" . strlen($jn) . " equal=" . ($jo === $jn ? 'yes' : 'no') . "\n";
if ($jo !== $jn) {
    for ($i = 0; $i < strlen($jo) && $i < strlen($jn); $i++) {
        if ($jo[$i] !== $jn[$i]) {
            echo "first diff at byte {$i}\nold: " . substr($jo, max(0,$i-40), 120) . "\nnew: " . substr($jn, max(0,$i-40), 120) . "\n";
            break;
        }
    }
}