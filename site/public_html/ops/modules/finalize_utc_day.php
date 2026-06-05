<?php
/**
 * PER-003 + day-close orchestrator — one UTC day tick (Steve midnight / timeline sim).
 *
 * @see docs/coordination/ops-orchestration-adr.md
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/ops_bootstrap.php';
require_once __DIR__ . '/finalize_league_period.php';
require_once __DIR__ . '/../includes/league_milestones_sync.php';
require_once __DIR__ . '/../includes/day_close_milestones.php';

/**
 * Closed UTC calendar day for a day tick (as_of is start of next day 00:00:01).
 */
function k2_ops_closed_utc_day_for_as_of(DateTimeImmutable $asOf): string
{
    return $asOf->modify('-1 day')->format('Y-m-d');
}

/**
 * Run league finalize, league event milestones, and day-close milestones.
 *
 * @return array{
 *   as_of: string,
 *   closed_utc_day: string,
 *   league_finalized: int,
 *   league_event_milestones_inserted: int,
 *   perfect_day: int,
 *   nightmare_day: int
 * }
 */
function k2_ops_finalize_utc_day(
    mysqli $con,
    ?DateTimeImmutable $asOf = null,
    bool $dryRun = false,
    ?string $closedUtcDay = null
): array {
    $asOf = $asOf ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $closedUtcDay = $closedUtcDay ?? k2_ops_closed_utc_day_for_as_of($asOf);

    if ($dryRun) {
        return [
            'as_of' => $asOf->format('Y-m-d\TH:i:s\Z'),
            'closed_utc_day' => $closedUtcDay,
            'league_finalized' => 0,
            'league_event_milestones_inserted' => 0,
            'perfect_day' => 0,
            'nightmare_day' => 0,
            'dry_run' => true,
        ];
    }

    // One step per commit — avoids long locks that freeze the work-site during simul.
    k2_ops_log(
        'FinalizeUtcDay step=league_finalize closed_utc_day=' . $closedUtcDay
        . ' as_of=' . $asOf->format('Y-m-d\TH:i:s\Z')
    );
    $league = k2_ops_finalize_league_due_periods($con, $asOf);

    k2_ops_log('FinalizeUtcDay step=league_event_milestones');
    $leagueMilestones = k2_league_sync_event_milestones($con);

    k2_ops_log('FinalizeUtcDay step=day_close');
    $dayClose = k2_day_close_finalize_utc_day($con, $closedUtcDay);

    return [
        'as_of' => $asOf->format('Y-m-d\TH:i:s\Z'),
        'closed_utc_day' => $closedUtcDay,
        'league_finalized' => (int) ($league['finalized'] ?? 0),
        'league_event_milestones_inserted' => $leagueMilestones,
        'perfect_day' => (int) ($dayClose['perfect_day'] ?? 0),
        'nightmare_day' => (int) ($dayClose['nightmare_day'] ?? 0),
    ];
}
