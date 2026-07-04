<?php
declare(strict_types=1);
require __DIR__ . '/../../site/public_html/includes/amiga_slice_snapshot_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_country_slice_snapshot_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

function old_wc_player_rows(mysqli $con, AmigaSnapshotContext $ctx, string $view): array {
    $cutoff = $ctx->cutoff();
    $sliceKey = amiga_slice_key_world_cup();
    $sql = 'SELECT wcs.player_id, wcs.tournaments_played AS wc_played, wcs.gold AS wc_gold, wcs.silver AS wc_silver, wcs.bronze AS wc_bronze, wcs.podiums AS wc_podiums, wcs.games, wcs.wins, wcs.draws, wcs.losses, wcs.goals_for, wcs.goals_against, wcs.points, ' . amiga_lb_wc_slice_v2_select_sql('wcs') . ' FROM (SELECT x.player_id, x.tournaments_played, x.gold, x.silver, x.bronze, x.podiums, x.games, x.wins, x.draws, x.losses, x.goals_for, x.goals_against, x.points, ' . str_replace('wcs.', 'x.', amiga_lb_wc_slice_v2_select_sql('x')) . ' FROM (SELECT s.*, ROW_NUMBER() OVER (PARTITION BY s.player_id ORDER BY s.event_date DESC, s.event_chrono DESC, s.as_of_tournament_id DESC) AS rn FROM amiga_player_slice_at_event s WHERE s.slice_key = ? AND (s.event_date, s.event_chrono, s.as_of_tournament_id) <= (?, ?, ?)) x WHERE x.rn = 1 AND x.tournaments_played > 0) wcs ORDER BY ' . amiga_lb_wc_slice_order_sql($view);
    $stmt = $con->prepare($sql);
    $eventDate = $cutoff['event_date']; $chrono = $cutoff['chrono']; $tid = $cutoff['tournament_id'];
    $stmt->bind_param('ssdi', $sliceKey, $eventDate, $chrono, $tid);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) { $rows[] = $row; }
    $stmt->close();
    return $rows;
}

function wc_slice_rows_for_parity(array $rows): array {
    usort($rows, static fn(array $a, array $b): int => ((int) $a['player_id']) <=> ((int) $b['player_id']));
    return $rows;
}

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');
$cutoffs = ['year:2001', 'month:2002-06', 'month:2014-07', 'event:589', 'year:2024', 'event:22'];
$fail = 0;
foreach ($cutoffs as $as) {
    $_GET['as'] = $as;
    amiga_snapshot_context_reset();
    $ctx = amiga_snapshot_context_from_request($con);
    foreach (['honours', 'results', 'goals'] as $view) {
        $old = wc_slice_rows_for_parity(old_wc_player_rows($con, $ctx, $view));
        $new = wc_slice_rows_for_parity(amiga_lb_wc_slice_rows_at_cutoff($con, $ctx, $view));
        $o = json_encode($old); $n = json_encode($new);
        if ($o !== $n) { echo "MISMATCH {$as} {$view}\n"; $fail++; }
    }
}
echo $fail === 0 ? "WC player slice parity OK\n" : "FAIL {$fail}\n";
$con->close();