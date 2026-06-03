<?php
/**
 * PER-003 league finalize on work DB (wraps league_standings.php).
 *
 * @see docs/leagues-rules-spec.md
 * @see docs/coordination/periodic-register.md (PER-003)
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/ops_bootstrap.php';

/**
 * Parse CLI --as-of for simulated midnight / timeline simul.
 */
function k2_ops_parse_as_of(?string $raw): ?DateTimeImmutable
{
    if ($raw === null || $raw === '') {
        return null;
    }
    try {
        return new DateTimeImmutable($raw, new DateTimeZone('UTC'));
    } catch (Exception) {
        fwrite(STDERR, "Invalid --as-of (use ISO-8601 UTC, e.g. 2026-05-27T00:00:01Z): {$raw}\n");
        exit(1);
    }
}

function k2_ops_require_league_awards_schema(mysqli $con): void
{
    if (!k2_ops_table_exists($con, 'player_league_award')) {
        fwrite(STDERR, "Table player_league_award missing — run migrate-work on work DB first.\n");
        exit(1);
    }
}

/**
 * Finalize closed league instances not yet in league_period (PER-003).
 *
 * @return array{finalized: int, as_of: string}
 */
function k2_ops_finalize_league_due_periods(mysqli $con, ?DateTimeImmutable $asOf = null): array
{
    require_once dirname(__DIR__, 2) . '/includes/league_standings.php';

    k2_ops_require_league_awards_schema($con);

    $asOf = $asOf ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $result = k2_league_finalize_due_periods($con, $asOf);

    return [
        'finalized' => (int) ($result['finalized'] ?? 0),
        'as_of' => $asOf->format('Y-m-d\TH:i:s\Z'),
    ];
}

/**
 * Full awards rebuild for all closed periods (REP-012).
 *
 * @return array{instances: int, awards: int, as_of: string}
 */
function k2_ops_rebuild_all_league_awards(mysqli $con, ?DateTimeImmutable $asOf = null): array
{
    require_once dirname(__DIR__, 2) . '/includes/league_standings.php';

    k2_ops_require_league_awards_schema($con);

    $asOf = $asOf ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $result = k2_league_rebuild_all_awards($con, $asOf);

    return [
        'instances' => (int) ($result['instances'] ?? 0),
        'awards' => (int) ($result['awards'] ?? 0),
        'as_of' => $asOf->format('Y-m-d\TH:i:s\Z'),
    ];
}

/**
 * Rebuild player_league_totals + player_league_slice_totals from awards (REP-013).
 */
function k2_ops_rebuild_league_player_aggregates(mysqli $con): void
{
    require_once dirname(__DIR__, 2) . '/includes/league_standings.php';

    k2_ops_require_league_awards_schema($con);
    k2_league_rebuild_player_aggregates($con);
}

function k2_ops_log_league_aggregate_counts(mysqli $con): void
{
    $players = 0;
    $wins = 0;
    $totalsRes = $con->query(
        'SELECT COUNT(*) AS players, COALESCE(SUM(wins), 0) AS wins FROM player_league_totals'
    );
    if ($totalsRes !== false) {
        $t = $totalsRes->fetch_assoc();
        $players = (int) ($t['players'] ?? 0);
        $wins = (int) ($t['wins'] ?? 0);
        $totalsRes->free();
    }
    k2_ops_log("Totals: {$players} players, {$wins} career wins");

    if (!k2_ops_table_exists($con, 'player_league_slice_totals')) {
        return;
    }
    $sliceRes = $con->query(
        'SELECT COUNT(*) AS slice_rows, COALESCE(SUM(gold), 0) AS gold FROM player_league_slice_totals'
    );
    if ($sliceRes === false) {
        return;
    }
    $s = $sliceRes->fetch_assoc();
    $sliceRes->free();
    k2_ops_log(
        'Slice totals: ' . (int) ($s['slice_rows'] ?? 0) . ' rows, '
        . (int) ($s['gold'] ?? 0) . ' gold (sum across slices)'
    );
}
