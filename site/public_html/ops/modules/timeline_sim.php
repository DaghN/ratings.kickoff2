<?php
/**
 * Mode C timeline simul — post-game per game + PER-003 at each UTC day step.
 *
 * @see docs/work-db-prepare.md §5.1 Mode C
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/ops_bootstrap.php';
require_once __DIR__ . '/process_completed_game.php';
require_once __DIR__ . '/finalize_league_period.php';

/**
 * as-of = start of next UTC calendar day 00:00:01 (daily league close for $utcDayYmd).
 */
function k2_ops_timeline_as_of_after_utc_day(string $utcDayYmd): DateTimeImmutable
{
    return (new DateTimeImmutable($utcDayYmd . ' 00:00:00', new DateTimeZone('UTC')))
        ->modify('+1 day')
        ->setTime(0, 0, 1);
}

function k2_ops_timeline_finalize_utc_day(mysqli $con, string $utcDayYmd, bool $dryRun): int
{
    $asOf = k2_ops_timeline_as_of_after_utc_day($utcDayYmd);
    k2_ops_log(
        'timeline finalize utc_day=' . $utcDayYmd
        . ' as_of=' . $asOf->format('Y-m-d\TH:i:s\Z')
        . ($dryRun ? ' (dry-run)' : '')
    );
    if ($dryRun) {
        return 0;
    }
    $result = k2_ops_finalize_league_due_periods($con, $asOf);

    return (int) ($result['finalized'] ?? 0);
}

/**
 * @return array{
 *   processed: int,
 *   last_game_id: int|null,
 *   last_game_date: string|null,
 *   finalize_runs: int,
 *   instances_finalized: int,
 *   stop_at: string
 * }
 */
function k2_ops_timeline_sim_run(
    mysqli $con,
    DateTimeImmutable $stopAt,
    ?DateTimeImmutable $startAt = null,
    bool $dryRun = false
): array {
    $res = $con->query('SELECT id, `Date` FROM ratedresults ORDER BY `Date` ASC, id ASC');
    if ($res === false) {
        throw new RuntimeException('list games: ' . $con->error);
    }

    $openUtcDay = null;
    $processed = 0;
    $lastGameId = null;
    $lastGameDate = null;
    $finalizeRuns = 0;
    $instancesFinalized = 0;

    while ($row = $res->fetch_assoc()) {
        $gameId = (int) $row['id'];
        $gameAt = new DateTimeImmutable((string) $row['Date'], new DateTimeZone('UTC'));
        if ($gameAt > $stopAt) {
            break;
        }
        if ($startAt !== null && $gameAt < $startAt) {
            continue;
        }

        $gameDay = $gameAt->format('Y-m-d');

        if ($openUtcDay !== null && $gameDay > $openUtcDay) {
            $n = k2_ops_timeline_finalize_utc_day($con, $openUtcDay, $dryRun);
            ++$finalizeRuns;
            $instancesFinalized += $n;
            $openUtcDay = null;
        }

        $result = k2_ops_process_completed_game($con, $gameId, $dryRun);
        ++$processed;
        $lastGameId = $gameId;
        $lastGameDate = $gameAt->format('Y-m-d H:i:s');
        if ($result['committed'] === false && !$dryRun) {
            // still counted as processed attempt
        }
        $openUtcDay = $gameDay;
    }
    $res->free();

    if ($openUtcDay !== null) {
        $stopDay = $stopAt->format('Y-m-d');
        if ($openUtcDay < $stopDay) {
            $n = k2_ops_timeline_finalize_utc_day($con, $openUtcDay, $dryRun);
            ++$finalizeRuns;
            $instancesFinalized += $n;
        }
    }

    return [
        'processed' => $processed,
        'last_game_id' => $lastGameId,
        'last_game_date' => $lastGameDate,
        'finalize_runs' => $finalizeRuns,
        'instances_finalized' => $instancesFinalized,
        'stop_at' => $stopAt->format('Y-m-d\TH:i:s\Z'),
    ];
}
