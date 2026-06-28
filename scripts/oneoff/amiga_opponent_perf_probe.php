<?php
declare(strict_types=1);

/**
 * One-off feasibility probe: per-opponent performance (TPR) rating for one hero
 * at PRESENT time. Mirrors the H2H pair perf logic, but for every opponent at once.
 *
 * Usage: php scripts/oneoff/amiga_opponent_perf_probe.php [playerId] [iterations]
 */

require __DIR__ . '/../../site/public_html/includes/performance_rating.php';
require __DIR__ . '/../../site/public_html/includes/amiga_db.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

$playerId   = isset($argv[1]) ? max(1, (int) $argv[1]) : 149;
$iterations = isset($argv[2]) ? max(1, (int) $argv[2]) : 30;

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    fwrite(STDERR, "connect fail: {$con->connect_error}\n");
    exit(1);
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

// Fetch all rated games for the hero (present time), one round trip.
function fetch_hero_games(mysqli $con, int $playerId): array
{
    $sql = 'SELECT r.idA, r.idB, r.RatingA, r.RatingB, r.ActualScore '
        . amiga_rated_games_from_sql()
        . ' WHERE r.idA = ? OR r.idB = ?';
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('prepare failed: ' . $con->error);
    }
    $stmt->bind_param('ii', $playerId, $playerId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    return $rows;
}

// Group hero games by opponent into perf-rating pairs.
function group_pairs_by_opponent(array $rows, int $playerId): array
{
    $byOpp = [];
    foreach ($rows as $row) {
        $idA = (int) $row['idA'];
        $idB = (int) $row['idB'];
        $score = (float) $row['ActualScore'];
        if ($idA === $playerId) {
            $opp = $idB;
            $byOpp[$opp][] = ['opponent' => (float) $row['RatingB'], 'score' => $score];
        } else {
            $opp = $idA;
            $byOpp[$opp][] = ['opponent' => (float) $row['RatingA'], 'score' => 1.0 - $score];
        }
    }

    return $byOpp;
}

// Solve perf rating per opponent.
function solve_all(array $byOpp): array
{
    $out = [];
    foreach ($byOpp as $opp => $pairs) {
        $out[$opp] = [
            'games' => count($pairs),
            'perf'  => performance_rating_from_pairs($pairs),
        ];
    }

    return $out;
}

// ---- One realistic cold pass with stage timing ----
$t0 = hrtime(true);
$rows = fetch_hero_games($con, $playerId);
$t1 = hrtime(true);
$byOpp = group_pairs_by_opponent($rows, $playerId);
$t2 = hrtime(true);
$results = solve_all($byOpp);
$t3 = hrtime(true);

$queryMs = ($t1 - $t0) / 1e6;
$groupMs = ($t2 - $t1) / 1e6;
$solveMs = ($t3 - $t2) / 1e6;
$totalMs = ($t3 - $t0) / 1e6;

$nGames     = count($rows);
$nOpponents = count($results);
$nWithPerf  = 0;
$nMinGames  = 0;
$nPerfect   = 0;
foreach ($results as $r) {
    if ($r['perf'] !== null) {
        $nWithPerf++;
    } elseif ($r['games'] < PERFORMANCE_RATING_MIN_GAMES) {
        $nMinGames++;
    } else {
        $nPerfect++;
    }
}

echo "=== Opponent perf-rating probe (PRESENT) ===\n";
echo "hero player id : {$playerId}\n";
echo "total rated games: {$nGames}\n";
echo "distinct opponents: {$nOpponents}\n";
echo "  with perf rating : {$nWithPerf}\n";
echo "  null (min games) : {$nMinGames}\n";
echo "  null (perfect)   : {$nPerfect}\n\n";

echo "--- Cold single pass timing ---\n";
printf("query (fetch all games) : %.3f ms\n", $queryMs);
printf("group by opponent       : %.3f ms\n", $groupMs);
printf("solve perf (all opps)   : %.3f ms\n", $solveMs);
printf("TOTAL                   : %.3f ms\n\n", $totalMs);

// ---- Warm bench: full pipeline (query + group + solve), N iterations ----
$times = [];
for ($i = 0; $i < $iterations; $i++) {
    $a = hrtime(true);
    $r = fetch_hero_games($con, $playerId);
    $g = group_pairs_by_opponent($r, $playerId);
    solve_all($g);
    $times[] = (hrtime(true) - $a) / 1e6;
}
sort($times);
$n = count($times);
printf(
    "--- Warm full-pipeline bench (n=%d) ---\nmean=%.3f ms  p50=%.3f ms  p95=%.3f ms  min=%.3f ms  max=%.3f ms\n\n",
    $n,
    array_sum($times) / $n,
    $times[(int) floor(($n - 1) * 0.5)],
    $times[(int) floor(($n - 1) * 0.95)],
    $times[0],
    $times[$n - 1]
);

// ---- Solve-only bench (compute cost, excludes DB) ----
$solveTimes = [];
for ($i = 0; $i < $iterations; $i++) {
    $a = hrtime(true);
    solve_all($byOpp);
    $solveTimes[] = (hrtime(true) - $a) / 1e6;
}
sort($solveTimes);
$sn = count($solveTimes);
printf(
    "--- Warm solve-only bench (n=%d, %d opponents) ---\nmean=%.3f ms  p50=%.3f ms  p95=%.3f ms  min=%.3f ms  max=%.3f ms\n\n",
    $sn,
    $nOpponents,
    array_sum($solveTimes) / $sn,
    $solveTimes[(int) floor(($sn - 1) * 0.5)],
    $solveTimes[(int) floor(($sn - 1) * 0.95)],
    $solveTimes[0],
    $solveTimes[$sn - 1]
);

// ---- Sample of opponents with most games ----
uasort($results, static fn ($a, $b) => $b['games'] <=> $a['games']);
echo "--- Top 10 opponents by games (perf rating) ---\n";
$shown = 0;
foreach ($results as $opp => $r) {
    $perf = $r['perf'] !== null ? (string) (int) round($r['perf']) : '—';
    printf("opp #%-5d games=%-4d perf=%s\n", $opp, $r['games'], $perf);
    if (++$shown >= 10) {
        break;
    }
}

$con->close();