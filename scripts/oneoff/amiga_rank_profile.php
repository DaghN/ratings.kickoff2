<?php
declare(strict_types=1);

/**
 * Profile present vs snapshot career-rating rank queries.
 * Usage: php scripts/oneoff/amiga_rank_profile.php [iterations]
 */
require __DIR__ . '/../../site/public_html/includes/amiga_player_load.php';
require __DIR__ . '/../../site/public_html/includes/amiga_lb_snapshot_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

$iterations = isset($argv[1]) ? max(1, (int) $argv[1]) : 50;

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    fwrite(STDERR, "connect fail: {$con->connect_error}\n");
    exit(1);
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

function bench(callable $fn, int $warm = 3, int $iter = 50): array
{
    for ($i = 0; $i < $warm; $i++) {
        $fn();
    }
    $times = [];
    for ($i = 0; $i < $iter; $i++) {
        $t0 = hrtime(true);
        $fn();
        $times[] = (hrtime(true) - $t0) / 1e6;
    }
    sort($times);
    $n = count($times);
    $sum = array_sum($times);

    return [
        'iter' => $n,
        'min_ms' => $times[0],
        'p50_ms' => $times[(int) floor(($n - 1) * 0.5)],
        'p95_ms' => $times[(int) floor(($n - 1) * 0.95)],
        'max_ms' => $times[$n - 1],
        'mean_ms' => $sum / $n,
    ];
}

function fmt_stats(array $s): string
{
    return sprintf(
        'n=%d mean=%.2fms p50=%.2fms p95=%.2fms min=%.2fms max=%.2fms',
        $s['iter'],
        $s['mean_ms'],
        $s['p50_ms'],
        $s['p95_ms'],
        $s['min_ms'],
        $s['max_ms']
    );
}

$res = $con->query('SELECT COUNT(*) AS n FROM amiga_player_current WHERE NumberGames > 0');
$presentPlayers = $res ? (int) ($res->fetch_assoc()['n'] ?? 0) : 0;
$res = $con->query('SELECT COUNT(*) AS n FROM amiga_player_event_snapshots');
$snapshotRows = $res ? (int) ($res->fetch_assoc()['n'] ?? 0) : 0;
$res = $con->query('SELECT COUNT(DISTINCT player_id) AS n FROM amiga_player_event_snapshots');
$snapshotPlayers = $res ? (int) ($res->fetch_assoc()['n'] ?? 0) : 0;

echo "=== Dataset ===\n";
echo "present players (games>0): {$presentPlayers}\n";
echo "snapshot rows: {$snapshotRows}\n";
echo "snapshot distinct players: {$snapshotPlayers}\n";
echo "iterations per bench: {$iterations}\n\n";

$sampleIds = [];
$res = $con->query(
    'SELECT player_id FROM amiga_player_current WHERE NumberGames > 0 ORDER BY Rating DESC LIMIT 1'
);
if ($res && ($row = $res->fetch_assoc())) {
    $sampleIds['top_rated'] = (int) $row['player_id'];
}
$res = $con->query(
    'SELECT player_id FROM amiga_player_current WHERE NumberGames > 0 ORDER BY Rating ASC LIMIT 1'
);
if ($res && ($row = $res->fetch_assoc())) {
    $sampleIds['low_rated'] = (int) $row['player_id'];
}
$res = $con->query(
    'SELECT player_id FROM amiga_player_current WHERE NumberGames > 0 ORDER BY player_id ASC LIMIT 1 OFFSET '
    . (int) max(0, (int) floor($presentPlayers / 2))
);
if ($res && ($row = $res->fetch_assoc())) {
    $sampleIds['median_ladder'] = (int) $row['player_id'];
}

$cutoffs = [];
amiga_snapshot_context_reset();
$_GET['as'] = 'year:2003';
$ctx2003 = amiga_snapshot_context_from_request($con);
if ($ctx2003->isActive() && $ctx2003->cutoff() !== null) {
    $cutoffs['year:2003'] = $ctx2003;
}
amiga_snapshot_context_reset();
$_GET['as'] = 'year:2008';
$ctx2008 = amiga_snapshot_context_from_request($con);
if ($ctx2008->isActive() && $ctx2008->cutoff() !== null) {
    $cutoffs['year:2008'] = $ctx2008;
}
$events = amiga_rating_history_catalog_event($con);
if ($events !== []) {
    $lastId = (string) $events[count($events) - 1]['key'];
    amiga_snapshot_context_reset();
    $_GET['as'] = 'event:' . $lastId;
    $ctxLast = amiga_snapshot_context_from_request($con);
    if ($ctxLast->isActive() && $ctxLast->cutoff() !== null) {
        $cutoffs['event:' . $lastId] = $ctxLast;
    }
}
amiga_snapshot_context_reset();
$_GET = [];

echo "=== Present rank (amiga_player_career_rating_rank_sql) ===\n";
foreach ($sampleIds as $label => $playerId) {
    $stats = bench(function () use ($con, $playerId): void {
        $stmt = $con->prepare(amiga_player_career_rating_rank_sql($con));
        if (!$stmt) {
            throw new RuntimeException('prepare failed');
        }
        $stmt->bind_param('i', $playerId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        if ($row === null) {
            throw new RuntimeException('no rank');
        }
    }, 3, $iterations);
    echo "player {$label} (#{$playerId}): " . fmt_stats($stats) . "\n";
}

echo "\n=== Snapshot rank only (amiga_player_snapshot_rating_rank) ===\n";
foreach ($cutoffs as $cutoffLabel => $ctx) {
    $snap = amiga_player_snapshot_row_at_cutoff($con, $sampleIds['top_rated'] ?? 1, $ctx);
    $rating = $snap !== null && !k2_db_is_null($snap['Rating'] ?? null)
        ? (float) $snap['Rating'] : 0.0;
    foreach ($sampleIds as $label => $playerId) {
        $snapRow = amiga_player_snapshot_row_at_cutoff($con, $playerId, $ctx);
        $r = $snapRow !== null && !k2_db_is_null($snapRow['Rating'] ?? null)
            ? (float) $snapRow['Rating'] : 0.0;
        $stats = bench(function () use ($con, $r, $ctx): void {
            amiga_player_snapshot_rating_rank($con, $r, $ctx);
        }, 3, $iterations);
        echo "{$cutoffLabel} player {$label} (#{$playerId}): " . fmt_stats($stats) . "\n";
    }
}

echo "\n=== Snapshot row fetch only (amiga_player_snapshot_row_at_cutoff) ===\n";
foreach ($cutoffs as $cutoffLabel => $ctx) {
    foreach ($sampleIds as $label => $playerId) {
        $stats = bench(function () use ($con, $playerId, $ctx): void {
            amiga_player_snapshot_row_at_cutoff($con, $playerId, $ctx);
        }, 3, $iterations);
        echo "{$cutoffLabel} player {$label} (#{$playerId}): " . fmt_stats($stats) . "\n";
    }
}

echo "\n=== Full hero load at cutoff (snapshot row + rank) ===\n";
foreach ($cutoffs as $cutoffLabel => $ctx) {
    $validPlayer = null;
    foreach ($sampleIds as $label => $playerId) {
        $snapRow = amiga_player_snapshot_row_at_cutoff($con, $playerId, $ctx);
        if ($snapRow !== null && (int) ($snapRow['NumberGames'] ?? 0) > 0) {
            $validPlayer = ['label' => $label, 'id' => $playerId];
            break;
        }
    }
    if ($validPlayer === null) {
        echo "{$cutoffLabel}: no sample player with games at cutoff\n";
        continue;
    }
    $playerId = $validPlayer['id'];
    $stats = bench(function () use ($con, $playerId, $ctx): void {
        amiga_player_load_at_cutoff($con, $playerId, $ctx);
    }, 3, $iterations);
    echo "{$cutoffLabel} {$validPlayer['label']} (#{$playerId}): " . fmt_stats($stats) . "\n";
}

echo "\n=== Present full hero load ===\n";
foreach (['top_rated', 'median_ladder'] as $label) {
    if (!isset($sampleIds[$label])) {
        continue;
    }
    $playerId = $sampleIds[$label];
    $stats = bench(function () use ($con, $playerId): void {
        amiga_player_load_present($con, $playerId);
    }, 3, $iterations);
    echo "{$label} (#{$playerId}): " . fmt_stats($stats) . "\n";
}

echo "\n=== Reference: rating LB at cutoff (all players, sorted) ===\n";
foreach ($cutoffs as $cutoffLabel => $ctx) {
    $stats = bench(function () use ($con, $ctx): void {
        amiga_lb_rating_rows_at_cutoff($con, $ctx);
    }, 2, max(10, (int) floor($iterations / 5)));
    echo "{$cutoffLabel}: " . fmt_stats($stats) . "\n";
}

echo "\n=== Reference: rating LB present ===\n";
amiga_snapshot_context_reset();
$_GET = [];
$ctxPresent = amiga_snapshot_context_from_request($con);
$stats = bench(function () use ($con, $ctxPresent): void {
    amiga_lb_rating_rows_at_cutoff($con, $ctxPresent);
}, 2, max(10, (int) floor($iterations / 5)));
echo 'present: ' . fmt_stats($stats) . "\n";

$con->close();
