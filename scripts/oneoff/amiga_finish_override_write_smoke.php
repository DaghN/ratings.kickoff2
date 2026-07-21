<?php
/**
 * Slice 1 smoke — Tier E full-ladder write helper.
 *
 * Always: pure validation cases (no DB).
 * With --db: pick a tournament with >=2 registered entrants on local Amiga DB,
 * replace inside a transaction, assert readback, then ROLLBACK (no lasting write).
 *
 * Run:
 *   php scripts/oneoff/amiga_finish_override_write_smoke.php
 *   php scripts/oneoff/amiga_finish_override_write_smoke.php --db
 */
declare(strict_types=1);

require __DIR__ . '/../../site/public_html/amiga/ops/includes/amiga_finish_override_write.php';
require __DIR__ . '/../../site/public_html/amiga/ops/includes/amiga_finish_confirm_proposal.php';

$fail = static function (string $step, string $message): void {
    fwrite(STDERR, "FAIL {$step}: {$message}\n");
    exit(1);
};

$pass = static function (string $step, string $detail = ''): void {
    echo 'PASS ' . $step . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
};

$expectFail = static function (string $step, callable $fn, string $needle) use ($fail): void {
    try {
        $fn();
        $fail($step, 'expected InvalidArgumentException containing: ' . $needle);
    } catch (InvalidArgumentException $e) {
        if (!str_contains($e->getMessage(), $needle)) {
            $fail($step, 'wrong message: ' . $e->getMessage());
        }
        echo "PASS {$step} — rejected as expected\n";
    }
};

// --- Pure validation ---
$entrants = [10, 20, 30];
$ok = [10 => 1, 20 => 2, 30 => 3];
amiga_ops_finish_override_validate_full_ladder($ok, $entrants);
$pass('V1', 'valid 1..3 ladder');

$expectFail('V2', static function () use ($entrants): void {
    amiga_ops_finish_override_validate_full_ladder([10 => 1, 20 => 2], $entrants);
}, 'expected 3');

$expectFail('V3', static function () use ($entrants): void {
    amiga_ops_finish_override_validate_full_ladder([10 => 1, 20 => 2, 99 => 3], $entrants);
}, 'registered entrants');

amiga_ops_finish_override_validate_full_ladder([10 => 1, 20 => 1, 30 => 3], $entrants);
$pass('V4', 'shared place 1 allowed (ties)');

$expectFail('V5', static function () use ($entrants): void {
    amiga_ops_finish_override_validate_full_ladder([10 => 1, 20 => 2, 30 => 4], $entrants);
}, '1..3');

$expectFail('V6', static function (): void {
    amiga_ops_finish_override_validate_full_ladder([], []);
}, 'no registered entrants');

amiga_ops_finish_override_validate_full_ladder([10 => 1, 20 => 3, 30 => 3], $entrants);
$pass('V7', 'shared place 3 allowed (ties)');

$expectFail('V7b', static function () use ($entrants): void {
    amiga_ops_finish_override_validate_full_ladder([10 => 0, 20 => 2, 30 => 3], $entrants);
}, '1..3');

amiga_ops_finish_override_validate_full_ladder([10 => 2, 20 => 2, 30 => 2], $entrants);
$pass('V7c', 'all tied at 2 within 1..N');

$dense = amiga_ops_finish_confirm_densify_ladder(
    [10, 20, 30],
    [10 => 2, 20 => null, 30 => 1],
    [10 => 0, 20 => 1, 30 => 2]
);
if ($dense !== [30 => 1, 10 => 2, 20 => 3]) {
    $fail('V8', 'densify unexpected: ' . json_encode($dense));
}
$pass('V8', 'densify partial derive → 1..3');

$fromPost = amiga_ops_finish_confirm_ladder_from_post(['10' => '1', '20' => '2', 'bad' => 'x']);
if (($fromPost[10] ?? null) !== 1 || ($fromPost[20] ?? null) !== 2 || isset($fromPost[0])) {
    $fail('V9', 'post parse unexpected: ' . json_encode($fromPost));
}
$pass('V9', 'post ladder parse');

if (!in_array('--db', $argv, true)) {
    echo "OK validation-only (pass --db for work-DB round-trip under rollback)\n";
    exit(0);
}

include __DIR__ . '/../../site/config/ko2amiga_config.php';
$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    $fail('DB0', "connect: {$con->connect_error}");
}
$con->set_charset('utf8mb4');
$pass('DB0', "connected {$database}");

$sql = "SELECT e.tournament_id, COUNT(*) AS n
        FROM tournament_entrants e
        WHERE e.status = 'registered'
        GROUP BY e.tournament_id
        HAVING n >= 2
        ORDER BY e.tournament_id DESC
        LIMIT 1";
$res = $con->query($sql);
if ($res === false) {
    $fail('DB1', 'find tournament: ' . $con->error);
}
$row = $res->fetch_assoc();
$res->free();
if ($row === null) {
    $fail('DB1', 'no tournament with >=2 registered entrants');
}
$tid = (int) $row['tournament_id'];
$n = (int) $row['n'];
$pass('DB1', "tournament_id={$tid} entrants={$n}");

$entrantIds = amiga_ops_finish_override_registered_entrant_ids($con, $tid);
if (count($entrantIds) !== $n) {
    $fail('DB2', 'entrant id count mismatch');
}
$pass('DB2', 'loaded registered entrant ids');

// Build ladder: reverse seed order → positions 1..N
$ladder = [];
$pos = 1;
foreach (array_reverse($entrantIds) as $pid) {
    $ladder[(int) $pid] = $pos;
    $pos++;
}

require_once __DIR__ . '/../../site/public_html/amiga/ops/includes/amiga_post_game_participation.php';

$con->begin_transaction();
try {
    $summary = amiga_ops_finish_override_replace_full_ladder($con, $tid, $ladder, $entrantIds, false);
    if ((int) $summary['written'] !== $n) {
        $fail('DB3', 'written count ' . $summary['written']);
    }
    $pass('DB3', 'replace written=' . $summary['written']);

    $loaded = amiga_ops_participation_finish_overrides_for_tournament($con, $tid);
    ksort($loaded, SORT_NUMERIC);
    $expected = $ladder;
    ksort($expected, SORT_NUMERIC);
    if ($loaded !== $expected) {
        $fail('DB4', 'readback mismatch: ' . json_encode($loaded));
    }
    $pass('DB4', 'readback matches ladder');

    // Idempotent second replace
    $summary2 = amiga_ops_finish_override_replace_full_ladder($con, $tid, $ladder, $entrantIds, false);
    if ((int) $summary2['written'] !== $n) {
        $fail('DB5', 'idempotent written count');
    }
    $loaded2 = amiga_ops_participation_finish_overrides_for_tournament($con, $tid);
    ksort($loaded2, SORT_NUMERIC);
    if ($loaded2 !== $expected) {
        $fail('DB5', 'idempotent readback mismatch');
    }
    $pass('DB5', 'idempotent replace ok');
} finally {
    $con->rollback();
    $pass('DB6', 'rolled back (no lasting DB change)');
}

// Proposal build (read-only) for same tournament
$tstmt = $con->prepare(
    'SELECT id, name, has_league, has_cup, is_world_cup FROM tournaments WHERE id = ? LIMIT 1'
);
$tstmt->bind_param('i', $tid);
$tstmt->execute();
$tres = $tstmt->get_result();
$trow = $tres ? $tres->fetch_assoc() : null;
$tstmt->close();
if ($trow === null) {
    $fail('DB7', 'tournament row missing');
}
$entrantsRows = [];
foreach ($entrantIds as $pid) {
    $entrantsRows[] = [
        'player_id' => $pid,
        'player_name' => 'P' . $pid,
        'status' => 'registered',
    ];
}
$tableRows = [];
foreach ($entrantIds as $i => $pid) {
    $tableRows[] = ['player_id' => $pid, 'player_name' => 'P' . $pid, 'position' => $i + 1];
}
$proposal = amiga_ops_finish_confirm_build_proposal($con, $tid, $trow, $entrantsRows, $tableRows);
if ((int) $proposal['entrant_count'] !== $n || count($proposal['ordered']) !== $n) {
    $fail('DB7', 'proposal size mismatch: ' . json_encode($proposal));
}
try {
    amiga_ops_finish_override_validate_full_ladder($proposal['ladder'], $entrantIds);
} catch (InvalidArgumentException $e) {
    $fail('DB7', 'proposal ladder invalid: ' . $e->getMessage());
}
$pass('DB7', 'proposal source=' . $proposal['source'] . ' confirmed=' . ($proposal['confirmed'] ? '1' : '0'));

$con->close();
echo "OK validation + db smoke\n";
exit(0);