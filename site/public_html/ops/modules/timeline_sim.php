<?php
/**
 * Mode C timeline simul — post-game per game + FinalizeUtcDay at each UTC day step.
 *
 * @see docs/work-db-prepare.md §5.1 Mode C
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/ops_bootstrap.php';
require_once __DIR__ . '/process_completed_game.php';
require_once __DIR__ . '/finalize_utc_day.php';

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
        'timeline FinalizeUtcDay closed_utc_day=' . $utcDayYmd
        . ' as_of=' . $asOf->format('Y-m-d\TH:i:s\Z')
        . ($dryRun ? ' (dry-run)' : '')
    );
    $result = k2_ops_finalize_utc_day($con, $asOf, $dryRun, $utcDayYmd);
    k2_ops_log(
        'timeline FinalizeUtcDay done league_finalized=' . ($result['league_finalized'] ?? 0)
        . ' league_event_milestones=' . ($result['league_event_milestones_inserted'] ?? 0)
        . ' perfect_day=' . ($result['perfect_day'] ?? 0)
        . ' nightmare_day=' . ($result['nightmare_day'] ?? 0)
    );

    return (int) ($result['league_finalized'] ?? 0);
}

/**
 * @return list<int>
 */
function k2_ops_timeline_list_game_ids(
    mysqli $con,
    DateTimeImmutable $stopAt,
    ?DateTimeImmutable $startAt,
    ?int $untilGameId
): array {
    $res = $con->query(
        'SELECT id, UNIX_TIMESTAMP(`Date`) AS date_utc_ts FROM ratedresults ORDER BY `Date` ASC, id ASC'
    );
    if ($res === false) {
        throw new RuntimeException('list games: ' . $con->error);
    }

    $ids = [];
    while ($row = $res->fetch_assoc()) {
        $gameId = (int) $row['id'];
        $ts = (int) ($row['date_utc_ts'] ?? 0);
        $gameAt = $ts > 0
            ? DateTimeImmutable::createFromFormat('U', (string) $ts, new DateTimeZone('UTC'))
            : k2_post_game_row_utc_datetime($row);
        if ($gameAt === false) {
            throw new RuntimeException('invalid date_utc_ts for ratedresults id=' . $gameId);
        }
        if ($gameAt > $stopAt) {
            break;
        }
        if ($untilGameId !== null && $gameId > $untilGameId) {
            break;
        }
        if ($startAt !== null && $gameAt < $startAt) {
            continue;
        }
        $ids[] = $gameId;
    }
    $res->free();

    return $ids;
}

/**
 * @return array{
 *   processed: int,
 *   skipped: int,
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
    bool $dryRun = false,
    ?int $untilGameId = null
): array {
    $gameIds = k2_ops_timeline_list_game_ids($con, $stopAt, $startAt, $untilGameId);
    k2_ops_log('timeline_sim games_in_scope=' . count($gameIds));

    $openUtcDay = null;
    $processed = 0;
    $skipped = 0;
    $lastGameId = null;
    $lastGameDate = null;
    $finalizeRuns = 0;
    $instancesFinalized = 0;

    foreach ($gameIds as $gameId) {
        $game = k2_ops_load_rated_game_row($con, $gameId);
        $gameAt = k2_post_game_row_utc_datetime($game);
        $gameDay = $gameAt->format('Y-m-d');

        if ($openUtcDay !== null && $gameDay > $openUtcDay) {
            $n = k2_ops_timeline_finalize_utc_day($con, $openUtcDay, $dryRun);
            ++$finalizeRuns;
            $instancesFinalized += $n;
            $openUtcDay = null;
        }

        $result = k2_ops_process_completed_game($con, $gameId, $dryRun);
        if (!empty($result['skipped'])) {
            ++$skipped;
        } else {
            ++$processed;
        }
        $lastGameId = $gameId;
        $lastGameDate = $gameAt->format('Y-m-d H:i:s');
        $openUtcDay = $gameDay;

        if (($processed + $skipped) % 25 === 0) {
            k2_ops_log(
                'timeline_sim progress processed=' . $processed
                . ' skipped=' . $skipped
                . ' last_id=' . $gameId
            );
        }
    }

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
        'skipped' => $skipped,
        'last_game_id' => $lastGameId,
        'last_game_date' => $lastGameDate,
        'finalize_runs' => $finalizeRuns,
        'instances_finalized' => $instancesFinalized,
        'stop_at' => $stopAt->format('Y-m-d\TH:i:s\Z'),
    ];
}
