<?php
declare(strict_types=1);
/** SQL variant test: narrow window scan + join-back vs current wide snap.* scan. */

require __DIR__ . '/../../site/public_html/includes/amiga_rating_history_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_lb_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_lb_snapshot_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

function ms(float $start): float
{
    return round((microtime(true) - $start) * 1000, 1);
}

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    fwrite(STDERR, "connect fail\n");
    exit(1);
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

// Table shape
$res = $con->query("SELECT COUNT(*) n FROM amiga_player_event_snapshots");
echo 'snapshot rows total: ' . $res->fetch_assoc()['n'] . "\n";
$res = $con->query("SHOW INDEX FROM amiga_player_event_snapshots");
echo "indexes:\n";
while ($row = $res->fetch_assoc()) {
    echo "  {$row['Key_name']} seq {$row['Seq_in_index']} col {$row['Column_name']}\n";
}
$res = $con->query("SELECT COUNT(*) n FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'amiga_player_event_snapshots'");
echo 'snapshot column count: ' . $res->fetch_assoc()['n'] . "\n\n";

$select = 'SELECT p.id AS ID, p.name AS Name, s.Rating, s.NumberGames, s.NumberWins, s.NumberDraws, s.NumberLosses, '
    . 's.WinRatio, s.DrawRatio, s.LossRatio, s.AverageOpponentRating, p.country AS Country ';

$cutoffs = [
    'month:2014-07' => null,
    'event:589' => null,
    'month:2025-09' => null,
];

foreach (array_keys($cutoffs) as $as) {
    $_GET['as'] = $as;
    amiga_snapshot_context_reset();
    $ctx = amiga_snapshot_context_from_request($con);
    $cutoff = $ctx->cutoff();
    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tid = $cutoff['tournament_id'];

    echo "=== {$as} (cutoff {$eventDate} / {$tid}) ===\n";

    // A) Current: wide snap.* window scan
    $sqlA = $select . amiga_lb_snapshot_from_sql('s') . ' WHERE s.NumberGames > 0 ORDER BY s.Rating DESC';
    foreach ([1, 2] as $run) {
        $t0 = microtime(true);
        $stmt = $con->prepare($sqlA);
        $stmt->bind_param('sdi', $eventDate, $chrono, $tid);
        $stmt->execute();
        $r = $stmt->get_result();
        $n = 0;
        while ($r->fetch_assoc()) { $n++; }
        $stmt->close();
        echo "  A wide snap.* run{$run}: " . ms($t0) . " ms ({$n} rows)\n";
    }

    // B) Narrow window scan (id + tuple only) + join back for the wide row
    $sqlB = $select
        . "FROM amiga_players p\n"
        . "INNER JOIN (\n"
        . "    SELECT x.player_id, x.tournament_id FROM (\n"
        . "        SELECT snap.player_id, snap.tournament_id,\n"
        . "            ROW_NUMBER() OVER (\n"
        . "                PARTITION BY snap.player_id\n"
        . "                ORDER BY snap.event_date DESC, snap.event_chrono DESC, snap.tournament_id DESC\n"
        . "            ) AS rn\n"
        . "        FROM amiga_player_event_snapshots snap\n"
        . "        WHERE (snap.event_date, snap.event_chrono, snap.tournament_id) <= (?, ?, ?)\n"
        . "    ) x\n"
        . "    WHERE x.rn = 1\n"
        . ") latest ON latest.player_id = p.id\n"
        . "INNER JOIN amiga_player_event_snapshots s ON s.player_id = latest.player_id AND s.tournament_id = latest.tournament_id\n"
        . ' WHERE s.NumberGames > 0 ORDER BY s.Rating DESC';
    foreach ([1, 2] as $run) {
        $t0 = microtime(true);
        $stmt = $con->prepare($sqlB);
        $stmt->bind_param('sdi', $eventDate, $chrono, $tid);
        $stmt->execute();
        $r = $stmt->get_result();
        $n = 0;
        while ($r->fetch_assoc()) { $n++; }
        $stmt->close();
        echo "  B narrow+joinback run{$run}: " . ms($t0) . " ms ({$n} rows)\n";
    }

    // Parity check A vs B (id -> rating)
    $fetch = function (string $sql) use ($con, $eventDate, $chrono, $tid): array {
        $stmt = $con->prepare($sql);
        $stmt->bind_param('sdi', $eventDate, $chrono, $tid);
        $stmt->execute();
        $r = $stmt->get_result();
        $out = [];
        while ($row = $r->fetch_assoc()) {
            $out[(int) $row['ID']] = (string) $row['Rating'];
        }
        $stmt->close();

        return $out;
    };
    $a = $fetch($sqlA);
    $b = $fetch($sqlB);
    echo '  parity: ' . ($a === $b ? 'OK' : 'MISMATCH (' . count($a) . ' vs ' . count($b) . ')') . "\n";
}

$con->close();
echo "OK\n";