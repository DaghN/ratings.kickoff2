<?php
/**
 * P1 parity helpers — ratedresults derived columns (work DB).
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/ops_prepare_constants.php';

/** @var list<string> */
const K2_OPS_RATEDRESULTS_DERIVED_COLUMNS = [
    'RatingA',
    'RatingB',
    'RatingDifference',
    'ExpectedScoreA',
    'ExpectedScoreB',
    'AdjustmentA',
    'AdjustmentB',
    'NewRatingA',
    'NewRatingB',
    'ActualScore',
    'WinnerID',
    'SumOfGoals',
    'GoalDifference',
    'HomeWin',
    'Draw',
    'AwayWin',
    'DDPlayerA',
    'DDPlayerB',
    'CSPlayerA',
    'CSPlayerB',
];

/**
 * @return list<int>
 */
function k2_ops_list_game_ids_chronological(
    mysqli $con,
    ?int $limit = null,
    ?int $untilGameId = null
): array {
    $sql = 'SELECT id FROM ratedresults ORDER BY Date ASC, id ASC';
    $res = $con->query($sql);
    if ($res === false) {
        throw new RuntimeException('list games: ' . $con->error);
    }

    $ids = [];
    while ($row = $res->fetch_assoc()) {
        $gid = (int) $row['id'];
        if ($untilGameId !== null && $gid > $untilGameId) {
            break;
        }
        $ids[] = $gid;
        if ($limit !== null && count($ids) >= $limit) {
            break;
        }
    }
    $res->free();

    return $ids;
}

/**
 * @return array{total: int, with_derived: int, missing_derived: int, first_missing_id: ?int}
 */
function k2_ops_ratedresults_derived_coverage(
    mysqli $con,
    ?int $limit = null,
    ?int $untilGameId = null
): array {
    $ids = k2_ops_list_game_ids_chronological($con, $limit, $untilGameId);
    if ($ids === []) {
        return [
            'total' => 0,
            'with_derived' => 0,
            'missing_derived' => 0,
            'first_missing_id' => null,
        ];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $sql = "SELECT id, NewRatingA FROM ratedresults WHERE id IN ({$placeholders}) ORDER BY id ASC";

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare coverage: ' . $con->error);
    }
    $stmt->bind_param($types, ...$ids);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute coverage: ' . $stmt->error);
    }
    $res = $stmt->get_result();

    $withDerived = 0;
    $firstMissing = null;
    while ($row = $res->fetch_assoc()) {
        if ($row['NewRatingA'] !== null) {
            $withDerived++;
        } elseif ($firstMissing === null) {
            $firstMissing = (int) $row['id'];
        }
    }
    $stmt->close();

    $total = count($ids);

    return [
        'total' => $total,
        'with_derived' => $withDerived,
        'missing_derived' => $total - $withDerived,
        'first_missing_id' => $firstMissing,
    ];
}

/**
 * Compare derived columns for game ids present in both databases (PHP work vs Python reference run).
 *
 * @return list<array{name: string, ok: bool, detail: string}>
 */
function k2_ops_parity_ratedresults_vs_reference(
    mysqli $work,
    mysqli $reference,
    ?int $limit = null,
    ?int $untilGameId = null,
    float $floatTolerance = 0.001
): array {
    $ids = k2_ops_list_game_ids_chronological($work, $limit, $untilGameId);
    $results = [];
    if ($ids === []) {
        $results[] = ['name' => 'ratedresults_parity', 'ok' => false, 'detail' => 'no games in range'];
        return $results;
    }

    $mismatches = 0;
    $compared = 0;
    $cols = K2_OPS_RATEDRESULTS_DERIVED_COLUMNS;
    $floatCols = [
        'RatingA', 'RatingB', 'RatingDifference', 'ExpectedScoreA', 'ExpectedScoreB',
        'AdjustmentA', 'AdjustmentB', 'NewRatingA', 'NewRatingB', 'ActualScore',
    ];

    foreach ($ids as $gameId) {
        $stmtW = $work->prepare('SELECT ' . implode(', ', $cols) . ' FROM ratedresults WHERE id = ? LIMIT 1');
        $stmtR = $reference->prepare('SELECT ' . implode(', ', $cols) . ' FROM ratedresults WHERE id = ? LIMIT 1');
        if ($stmtW === false || $stmtR === false) {
            throw new RuntimeException('prepare parity row');
        }
        $stmtW->bind_param('i', $gameId);
        $stmtR->bind_param('i', $gameId);
        if (!$stmtW->execute() || !$stmtR->execute()) {
            throw new RuntimeException('execute parity row id=' . $gameId);
        }
        $rowW = $stmtW->get_result()->fetch_assoc();
        $rowR = $stmtR->get_result()->fetch_assoc();
        $stmtW->close();
        $stmtR->close();

        if ($rowW === false || $rowR === false) {
            $mismatches++;
            continue;
        }

        $compared++;
        foreach ($cols as $col) {
            $a = $rowW[$col];
            $b = $rowR[$col];
            if (in_array($col, $floatCols, true)) {
                if ($a === null && $b === null) {
                    continue;
                }
                if ($a === null || $b === null) {
                    $mismatches++;
                    break;
                }
                if (abs((float) $a - (float) $b) > $floatTolerance) {
                    $mismatches++;
                    break;
                }
            } elseif ((string) $a !== (string) $b) {
                $mismatches++;
                break;
            }
        }
    }

    $results[] = [
        'name' => 'ratedresults_derived_vs_reference',
        'ok' => $mismatches === 0,
        'detail' => "games={$compared} mismatches={$mismatches} tolerance={$floatTolerance}",
    ];

    return $results;
}

/**
 * @param list<array{name: string, ok: bool, detail: string}> $results
 */
function k2_ops_print_post_game_parity_report(array $results): int
{
    $fail = 0;
    foreach ($results as $r) {
        $tag = $r['ok'] ? 'PASS' : 'FAIL';
        if (!$r['ok']) {
            $fail++;
        }
        k2_ops_log("[{$tag}] {$r['name']} — {$r['detail']}");
    }
    if ($fail === 0) {
        k2_ops_log('Parity: all checks passed');
    } else {
        k2_ops_log("Parity: {$fail} check(s) failed");
    }

    return $fail === 0 ? 0 : 1;
}
