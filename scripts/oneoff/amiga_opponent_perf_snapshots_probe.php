<?php
declare(strict_types=1);

/**
 * Feasibility probe: per-opponent performance (TPR) rating for one hero
 * at EVERY snapshot (each tournament the hero entered).
 *
 * A snapshot cutoff = (event_date, event_chrono, tournament_id); games included
 * are those whose tournament tuple is <= the cutoff (same as site time-travel).
 *
 * Usage: php scripts/oneoff/amiga_opponent_perf_snapshots_probe.php [playerId]
 */

require __DIR__ . '/../../site/public_html/includes/performance_rating.php';
require __DIR__ . '/../../site/public_html/includes/amiga_db.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

$playerId = isset($argv[1]) ? max(1, (int) $argv[1]) : 386;

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    fwrite(STDERR, "connect fail: {$con->connect_error}\n");
    exit(1);
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

// ---------- Load all hero games once (with tournament tuple), chrono order ----------
$tLoad0 = hrtime(true);
$sql = 'SELECT r.idA, r.idB, r.RatingA, r.RatingB, r.ActualScore, '
    . 'r.tournament_event_date AS ed, r.tournament_chrono AS ch, r.tournament_id AS tid '
    . amiga_rated_games_from_sql()
    . ' WHERE (r.idA = ? OR r.idB = ?) '
    . ' ORDER BY r.tournament_event_date ASC, r.tournament_chrono ASC, r.tournament_id ASC, r.id ASC';
$stmt = $con->prepare($sql);
$stmt->bind_param('ii', $playerId, $playerId);
$stmt->execute();
$res = $stmt->get_result();
$games = [];
while ($row = $res->fetch_assoc()) {
    $idA = (int) $row['idA'];
    $isA = $idA === $playerId;
    $games[] = [
        'opp'   => $isA ? (int) $row['idB'] : $idA,
        'oppR'  => $isA ? (float) $row['RatingB'] : (float) $row['RatingA'],
        'score' => $isA ? (float) $row['ActualScore'] : 1.0 - (float) $row['ActualScore'],
        'ed'    => (string) $row['ed'],
        'ch'    => (float) $row['ch'],
        'tid'   => (int) $row['tid'],
    ];
}
$stmt->close();

// ---------- Load hero snapshot cutoffs (tournaments entered), chrono order ----------
$snaps = [];
$rs = $con->query(
    'SELECT event_date AS ed, event_chrono AS ch, tournament_id AS tid '
    . 'FROM amiga_player_event_snapshots WHERE player_id = ' . (int) $playerId
    . ' ORDER BY event_date ASC, event_chrono ASC, tournament_id ASC'
);
while ($x = $rs->fetch_assoc()) {
    $snaps[] = ['ed' => (string) $x['ed'], 'ch' => (float) $x['ch'], 'tid' => (int) $x['tid']];
}
$tLoad1 = hrtime(true);

$nGames = count($games);
$nSnaps = count($snaps);

// tuple compare: a <= b ?
$tupleLe = static function (array $a, array $b): bool {
    if ($a['ed'] !== $b['ed']) {
        return $a['ed'] < $b['ed'];
    }
    if ($a['ch'] !== $b['ch']) {
        return $a['ch'] < $b['ch'];
    }
    return $a['tid'] <= $b['tid'];
};

// ============================================================
// APPROACH A — naive: per snapshot, re-scan all games, solve all opponents.
// ============================================================
$tA0 = hrtime(true);
$pairsComputedA = 0;
$nonNullA = 0;
foreach ($snaps as $snap) {
    $byOpp = [];
    foreach ($games as $g) {
        if ($tupleLe($g, $snap)) {
            $byOpp[$g['opp']][] = ['opponent' => $g['oppR'], 'score' => $g['score']];
        }
    }
    foreach ($byOpp as $pairs) {
        $pairsComputedA++;
        if (performance_rating_from_pairs($pairs) !== null) {
            $nonNullA++;
        }
    }
}
$tA1 = hrtime(true);

// ============================================================
// APPROACH B — incremental: walk games once, advance snapshot pointer,
// re-solve only opponents whose game set changed since last snapshot.
// Emits one perf value per (snapshot, opponent-faced-so-far).
// ============================================================
$tB0 = hrtime(true);
$byOpp = [];        // opp => list of pairs
$cache = [];        // opp => last solved perf (int|null)
$gi = 0;
$pairsEmittedB = 0; // total (snapshot, opponent) grid cells
$nonNullB = 0;      // cells with a real perf value
$solveCalls = 0;    // actual solver invocations
foreach ($snaps as $snap) {
    $dirty = [];
    while ($gi < $nGames && $tupleLe($games[$gi], $snap)) {
        $g = $games[$gi];
        $byOpp[$g['opp']][] = ['opponent' => $g['oppR'], 'score' => $g['score']];
        $dirty[$g['opp']] = true;
        $gi++;
    }
    foreach ($dirty as $opp => $_) {
        $perf = performance_rating_from_pairs($byOpp[$opp]);
        $cache[$opp] = $perf !== null ? (int) round($perf) : null;
        $solveCalls++;
    }
    // Emit current grid row for this snapshot (all opponents faced so far)
    foreach ($cache as $perf) {
        $pairsEmittedB++;
        if ($perf !== null) {
            $nonNullB++;
        }
    }
}
$tB1 = hrtime(true);

// ---------- changed-only persistence estimate (store a row only when value changes) ----------
$tC0 = hrtime(true);
$byOpp = [];
$last = [];        // opp => last stored perf value
$gi = 0;
$changedRows = 0;
foreach ($snaps as $snap) {
    $dirty = [];
    while ($gi < $nGames && $tupleLe($games[$gi], $snap)) {
        $g = $games[$gi];
        $byOpp[$g['opp']][] = ['opponent' => $g['oppR'], 'score' => $g['score']];
        $dirty[$g['opp']] = true;
        $gi++;
    }
    foreach ($dirty as $opp => $_) {
        $perf = performance_rating_from_pairs($byOpp[$opp]);
        $val = $perf !== null ? (int) round($perf) : null;
        if (!array_key_exists($opp, $last) || $last[$opp] !== $val) {
            $changedRows++;
            $last[$opp] = $val;
        }
    }
}
$tC1 = hrtime(true);

$ms = static fn (float $a, float $b): float => ($b - $a) / 1e6;

echo "=== Per-snapshot opponent perf probe ===\n";
echo "hero player id      : {$playerId}\n";
echo "rated games (total) : {$nGames}\n";
echo "snapshots (events)  : {$nSnaps}\n";
printf("DB load (games+snaps): %.2f ms\n\n", $ms($tLoad0, $tLoad1));

echo "--- Grid size (one value per snapshot x opponent-so-far) ---\n";
echo "total grid cells     : {$pairsEmittedB}\n";
echo "  non-null perf      : {$nonNullB}\n";
echo "  null (min/perfect) : " . ($pairsEmittedB - $nonNullB) . "\n";
echo "changed-only rows    : {$changedRows}  (if persisting deltas only)\n\n";

echo "--- Timing ---\n";
printf("A) naive recompute per snapshot : %.2f ms   (%d solves)\n", $ms($tA0, $tA1), $pairsComputedA);
printf("B) incremental walk             : %.2f ms   (%d solves, %d cells emitted)\n", $ms($tB0, $tB1), $solveCalls, $pairsEmittedB);
printf("C) incremental + delta dedupe   : %.2f ms\n", $ms($tC0, $tC1));
printf("\nFull backfill (load + incremental): %.2f ms\n", $ms($tLoad0, $tLoad1) + $ms($tB0, $tB1));

$con->close();