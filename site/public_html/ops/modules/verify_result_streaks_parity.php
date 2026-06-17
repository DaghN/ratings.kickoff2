<?php
/**
 * Result streak boundaries — stored truth vs chronological oracle (+ playertable counts).
 *
 * @see docs/website-data-contract.md § player_result_streaks
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/player_result_streaks.php';

/**
 * @return list<array{id: string, label: string, ok: bool, detail: string, severity: string}>
 */
function k2_ops_verify_result_streaks_parity(mysqli $con): array
{
    if (!k2_result_streak_table_ready($con)) {
        return [];
    }

    require_once __DIR__ . '/../includes/ops_verify_helpers.php';

    $checks = [];
    $mismatches = k2_result_streak_oracle_mismatches($con, null, true);
    $ok = $mismatches === [];
    $detail = $ok ? 'oracle + playertable match' : implode('; ', array_slice($mismatches, 0, 3));
    if (!$ok && count($mismatches) > 3) {
        $detail .= '; +' . (count($mismatches) - 3) . ' more';
    }

    $checks[] = k2_ops_verify_check(
        'result_streak_oracle',
        'player_result_streaks vs chronological walker (+ playertable Longest*)',
        $ok,
        $detail,
        $ok ? 'ok' : 'fail'
    );

    return $checks;
}
