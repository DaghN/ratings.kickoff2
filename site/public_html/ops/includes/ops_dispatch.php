<?php
/**
 * Dispatch router — maps CMD names to existing k2_ops_* modules (no business logic here).
 */
declare(strict_types=1);

final class K2OpsDispatchHttpContext
{
    public static bool $enabled = false;

    /** @var list<string> */
    public static array $lines = [];
}

final class K2OpsDispatchExit extends RuntimeException
{
    public function __construct(public readonly int $exitCode)
    {
        parent::__construct('dispatch exit ' . $exitCode);
    }
}

function k2_ops_dispatch_http_begin(): void
{
    K2OpsDispatchHttpContext::$enabled = true;
    K2OpsDispatchHttpContext::$lines = [];
}

/** @return list<string> */
function k2_ops_dispatch_http_lines(): array
{
    return K2OpsDispatchHttpContext::$lines;
}

require_once __DIR__ . '/ops_std.php';
require_once __DIR__ . '/ops_argv.php';
require_once __DIR__ . '/ops_work_target.php';
require_once __DIR__ . '/ops_bootstrap.php';

/** @var array<string, array{summary: string, required: list<string>, optional: list<string>}> */
const K2_OPS_DISPATCH_REGISTRY = [
    'ProcessCompletedGame' => [
        'summary' => 'Post-game derived update for one ratedresults row (P0–P7); earlier waiting games first.',
        'required' => ['game_id'],
        'optional' => ['dry_run'],
    ],
    'ProcessPending' => [
        'summary' => 'Process every waiting game in order (cron safety net; dry_run=1 only reports).',
        'required' => [],
        'optional' => ['dry_run', 'max_backlog'],
    ],
    'FinalizeLeagueDue' => [
        'summary' => 'PER-003: finalize closed league periods (optional as_of UTC).',
        'required' => [],
        'optional' => ['as_of'],
    ],
    'FinalizeUtcDay' => [
        'summary' => 'UTC day tick: league finalize + league event milestones + day-close milestones.',
        'required' => [],
        'optional' => ['as_of', 'closed_utc_day'],
    ],
    'ProcessPlayerRegistered' => [
        'summary' => 'Lobby milestone entered_arena for one player.',
        'required' => ['player_id'],
        'optional' => ['dry_run'],
    ],
];

function k2_ops_dispatch_usage(): void
{
    $lines = [
        'Usage: php dispatch.php CMD=<Name> [key=value ...] [--target <profile>]',
        '',
        'Target (required unless database= is a known work DB):',
        '  target=local-work | staging-work | local-dev | (any [section] in work-targets.ini)',
        '  database=kooldb1  (maps to profile with that work_database; ini sections included)',
        '',
        'Commands:',
    ];
    foreach (K2_OPS_DISPATCH_REGISTRY as $name => $meta) {
        $req = $meta['required'] === [] ? '' : ' requires ' . implode(', ', $meta['required']);
        $lines[] = "  {$name} — {$meta['summary']}{$req}";
    }
    $lines[] = '';
    $lines[] = 'Examples:';
    $lines[] = '  php dispatch.php CMD=ProcessCompletedGame game_id=57216 target=staging-work';
    $lines[] = '  php dispatch.php CMD=ProcessPending target=staging-work dry_run=1';
    $lines[] = '  php dispatch.php CMD=FinalizeLeagueDue target=staging-work as_of=2026-06-03T00:00:01Z';
    $lines[] = '  php dispatch.php CMD=FinalizeUtcDay target=staging-work as_of=2026-06-04T00:00:01Z';
    $lines[] = '';
    $lines[] = 'Exit codes: 0 ok | 1 error, game stays queued | 2 already processed (no DB change)'
        . ' | 3 refused, needs full recalculation | 64 usage';
    if (K2OpsDispatchHttpContext::$enabled) {
        foreach ($lines as $line) {
            K2OpsDispatchHttpContext::$lines[] = $line;
        }
        throw new K2OpsDispatchExit(64);
    }
    fwrite(stderr(), implode(PHP_EOL, $lines) . PHP_EOL);
}

function k2_ops_dispatch_log(string $message): void
{
    $line = '[dispatch] ' . $message;
    if (K2OpsDispatchHttpContext::$enabled) {
        K2OpsDispatchHttpContext::$lines[] = $line;
        return;
    }
    fwrite(stdout(), $line . PHP_EOL);
}

function k2_ops_dispatch_fail(string $message, int $exitCode = 1): never
{
    $line = '[dispatch] ERROR: ' . $message;
    if (K2OpsDispatchHttpContext::$enabled) {
        K2OpsDispatchHttpContext::$lines[] = $line;
        throw new K2OpsDispatchExit($exitCode);
    }
    fwrite(stderr(), $line . PHP_EOL);
    exit($exitCode);
}

function k2_ops_profile_for_work_database(string $database): ?string
{
    foreach (array_keys(K2_OPS_DEFAULT_PROFILES) as $profile) {
        $data = K2_OPS_DEFAULT_PROFILES[$profile];
        if ((string) $data['work_database'] === $database) {
            return $profile;
        }
    }

    $ini = k2_ops_parse_work_targets_ini();
    if ($ini !== null) {
        foreach ($ini as $profile => $section) {
            if (!is_array($section)) {
                continue;
            }
            if ((string) ($section['work_database'] ?? '') === $database) {
                return (string) $profile;
            }
        }
    }

    return null;
}

function k2_ops_resolve_dispatch_target(?string $targetName, ?string $databaseName): string
{
    if ($targetName !== null && $targetName !== '') {
        return $targetName;
    }
    if ($databaseName !== null && $databaseName !== '') {
        $profile = k2_ops_profile_for_work_database($databaseName);
        if ($profile === null) {
            k2_ops_dispatch_fail(
                "database={$databaseName} is not a known work database for any ops profile",
                64
            );
        }
        return $profile;
    }

    k2_ops_dispatch_fail('Missing target= or database= (see dispatch.php CMD=Help)', 64);
}

/**
 * @param array<string, string> $params
 */
function k2_ops_dispatch_require_params(string $cmd, array $params, array $required): void
{
    foreach ($required as $key) {
        if (!isset($params[$key]) || $params[$key] === '') {
            k2_ops_dispatch_fail("CMD={$cmd} requires {$key}=", 64);
        }
    }
}

function k2_ops_dispatch_connect(string $profileName): mysqli
{
    $target = k2_ops_load_work_target($profileName);
    $allowDevDb = ($profileName === 'local-dev');

    if (!$allowDevDb) {
        k2_ops_assert_mutate_work_target($target);
    }

    k2_ops_dispatch_log(
        'profile=' . $target->profile
        . ' database=' . $target->workDatabase
    );

    return k2_ops_connect_work($target, $allowDevDb);
}

/**
 * @return int exit code
 */
function k2_ops_dispatch_run(string $cmd, array $params, bool $dryRun): int
{
    if ($cmd === '' || strcasecmp($cmd, 'Help') === 0 || strcasecmp($cmd, 'List') === 0) {
        k2_ops_dispatch_usage();
        return 64;
    }

    if (!isset(K2_OPS_DISPATCH_REGISTRY[$cmd])) {
        $known = implode(', ', array_keys(K2_OPS_DISPATCH_REGISTRY));
        k2_ops_dispatch_fail("Unknown CMD={$cmd}. Known: {$known}", 64);
    }

    $meta = K2_OPS_DISPATCH_REGISTRY[$cmd];
    k2_ops_dispatch_require_params($cmd, $params, $meta['required']);

    $profileName = k2_ops_resolve_dispatch_target(
        $params['target'] ?? null,
        $params['database'] ?? null
    );

    $t0 = microtime(true);
    k2_ops_dispatch_log("cmd={$cmd} dry_run=" . ($dryRun ? 'true' : 'false'));

    try {
        $exitCode = match ($cmd) {
            'ProcessCompletedGame' => k2_ops_dispatch_process_completed_game($params, $profileName, $dryRun),
            'ProcessPending' => k2_ops_dispatch_process_pending($params, $profileName, $dryRun),
            'FinalizeLeagueDue' => k2_ops_dispatch_finalize_league_due($params, $profileName),
            'FinalizeUtcDay' => k2_ops_dispatch_finalize_utc_day($params, $profileName, $dryRun),
            'ProcessPlayerRegistered' => k2_ops_dispatch_process_player_registered($params, $profileName, $dryRun),
            default => k2_ops_dispatch_fail("CMD={$cmd} not wired", 64),
        };
    } catch (K2OpsDispatchExit $e) {
        throw $e;
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        if (str_contains($msg, 'already processed')) {
            k2_ops_dispatch_fail($msg, 2);
        }
        k2_ops_dispatch_fail($msg . ' [' . $e::class . ']', 1);
    }

    $ms = round((microtime(true) - $t0) * 1000);
    k2_ops_dispatch_log("exit={$exitCode} duration_ms={$ms}");

    return $exitCode;
}

/**
 * Seconds one web request may spend on a backlog; the rest waits for the next call. CLI has no limit.
 */
function k2_ops_dispatch_backlog_budget(): ?float
{
    if (!K2OpsDispatchHttpContext::$enabled) {
        return null;
    }
    ignore_user_abort(true);
    if (function_exists('set_time_limit')) {
        set_time_limit(0);
    }

    return 10.0;
}

/**
 * @param list<int> $gameIds
 */
function k2_ops_dispatch_format_ids(array $gameIds, int $max = 20): string
{
    if ($gameIds === []) {
        return 'none';
    }
    $text = implode(',', array_slice($gameIds, 0, $max));

    return count($gameIds) > $max ? $text . ',... (' . count($gameIds) . ' in all)' : $text;
}

/**
 * @param array<string, string> $params
 */
function k2_ops_dispatch_process_completed_game(array $params, string $profileName, bool $dryRun): int
{
    $gameId = (int) $params['game_id'];
    if ($gameId <= 0) {
        k2_ops_dispatch_fail('game_id must be a positive integer', 64);
    }

    require_once dirname(__DIR__) . '/modules/process_pending_games.php';

    $con = k2_ops_dispatch_connect($profileName);
    try {
        if ($dryRun) {
            $result = k2_ops_process_completed_game($con, $gameId, true);
        } else {
            $budget = k2_ops_dispatch_backlog_budget();
            if (!k2_ops_game_queue_lock($con, 15)) {
                k2_ops_dispatch_fail('busy: another process is updating the ladder; game ' . $gameId . ' stays queued', 1);
            }
            $run = k2_ops_process_game_in_order($con, $gameId, $budget);
            if ($run['caught_up'] !== []) {
                k2_ops_dispatch_log(
                    'ProcessCompletedGame caught up ' . count($run['caught_up']) . ' earlier game(s): '
                    . k2_ops_dispatch_format_ids($run['caught_up'])
                );
            }
            if ($run['status'] === 'refused') {
                k2_ops_dispatch_log(
                    'ProcessCompletedGame game_id=' . $gameId . ' REFUSED: ' . $run['reason']
                    . '; needs a full recalculation (zero-derived + run_ops_sim.php)'
                );

                return 3;
            }
            if ($run['status'] === 'backlog') {
                k2_ops_dispatch_fail(
                    'backlog not cleared yet (' . $run['reason'] . '): ' . $run['remaining']
                    . ' game(s) left up to game ' . $gameId . '; call again or run CMD=ProcessPending',
                    1
                );
            }
            $result = $run['result'] ?? ['skipped' => true, 'skip_reason' => $run['reason']];
        }
        if (!empty($result['skipped'])) {
            $reason = (string) ($result['skip_reason'] ?? 'unknown');
            k2_ops_dispatch_log(
                'ProcessCompletedGame game_id=' . $gameId
                . ' skipped=true reason=' . $reason
            );
            if ($reason === 'already_processed') {
                return 2;
            }

            return 0;
        }
        $d = $result['derived'];
        k2_ops_dispatch_log(
            'ProcessCompletedGame game_id=' . $gameId
            . ' committed=' . ($result['committed'] ? 'true' : 'false')
            . ' NewRatingA=' . round((float) ($d['NewRatingA'] ?? 0), 3)
            . ' NewRatingB=' . round((float) ($d['NewRatingB'] ?? 0), 3)
        );
        if (!$result['committed'] && !$dryRun) {
            k2_ops_dispatch_fail('ProcessCompletedGame did not commit', 1);
        }
    } finally {
        $con->close();
    }

    return 0;
}

/**
 * @param array<string, string> $params
 */
function k2_ops_dispatch_process_pending(array $params, string $profileName, bool $dryRun): int
{
    require_once dirname(__DIR__) . '/modules/process_pending_games.php';

    $maxBacklog = isset($params['max_backlog']) && (int) $params['max_backlog'] > 0
        ? (int) $params['max_backlog']
        : K2_OPS_GAME_QUEUE_MAX_BACKLOG;
    $con = k2_ops_dispatch_connect($profileName);
    try {
        if ($dryRun) {
            $frontier = k2_ops_game_queue_frontier($con);
            $pending = array_column(k2_ops_game_queue_pending($con), 'id');
            $holes = k2_ops_game_queue_holes($con);
            k2_ops_dispatch_log('ProcessPending last_processed=' . ($frontier['id'] ?? 'none'));
            k2_ops_dispatch_log(
                'ProcessPending waiting=' . count($pending) . ' ids=' . k2_ops_dispatch_format_ids($pending)
            );
            k2_ops_dispatch_log(
                'ProcessPending holes=' . count($holes) . ' ids=' . k2_ops_dispatch_format_ids($holes)
                . ($holes !== [] ? ' (need a full recalculation)' : '')
            );

            return 0;
        }

        $budget = k2_ops_dispatch_backlog_budget();
        if (!k2_ops_game_queue_lock($con, 0)) {
            k2_ops_dispatch_log('ProcessPending busy=true (another process is updating the ladder)');

            return 0;
        }
        $run = k2_ops_process_pending_games($con, null, null, $budget, $maxBacklog);
        k2_ops_dispatch_log(
            'ProcessPending processed=' . count($run['processed'])
            . ' ids=' . k2_ops_dispatch_format_ids($run['processed'])
            . ' remaining=' . $run['remaining']
        );
        if ($run['remaining'] > 0) {
            k2_ops_dispatch_fail('backlog not cleared yet (time limit reached); call again', 1);
        }
    } finally {
        $con->close();
    }

    return 0;
}

/**
 * @param array<string, string> $params
 */
function k2_ops_dispatch_finalize_league_due(array $params, string $profileName): int
{
    require_once dirname(__DIR__) . '/modules/finalize_league_period.php';

    $asOf = k2_ops_parse_as_of($params['as_of'] ?? null);
    $con = k2_ops_dispatch_connect($profileName);
    try {
        $result = k2_ops_finalize_league_due_periods($con, $asOf);
        k2_ops_dispatch_log(
            'FinalizeLeagueDue finalized=' . $result['finalized']
            . ' as_of=' . $result['as_of']
        );
        k2_ops_dispatch_log(
            '[NOTE] FinalizeLeagueDue is league-only. Nightly cron should use CMD=FinalizeUtcDay '
            . '(league + league milestones + perfect_day/nightmare_day).'
        );
    } finally {
        $con->close();
    }

    return 0;
}

/**
 * @param array<string, string> $params
 */
function k2_ops_dispatch_finalize_utc_day(array $params, string $profileName, bool $dryRun): int
{
    require_once dirname(__DIR__) . '/modules/process_pending_games.php';

    $asOf = k2_ops_parse_as_of($params['as_of'] ?? null);
    $closedUtcDay = isset($params['closed_utc_day']) && $params['closed_utc_day'] !== ''
        ? $params['closed_utc_day']
        : null;

    $con = k2_ops_dispatch_connect($profileName);
    try {
        if ($dryRun) {
            $result = k2_ops_finalize_utc_day($con, $asOf, true, $closedUtcDay);
        } else {
            if (!k2_ops_game_queue_lock($con, 300)) {
                k2_ops_dispatch_fail('busy: another process is updating the ladder; day not finalized', 1);
            }
            // A day is closed only after every game of that day is in. A failing game stops here (exit 1).
            $result = k2_ops_finalize_utc_day_after_pending($con, $asOf, $closedUtcDay);
            if ($result['caught_up'] !== []) {
                k2_ops_dispatch_log(
                    'FinalizeUtcDay caught up ' . count($result['caught_up']) . ' waiting game(s): '
                    . k2_ops_dispatch_format_ids($result['caught_up'])
                );
            }
            $holes = k2_ops_game_queue_holes($con);
            if ($holes !== []) {
                k2_ops_dispatch_log(
                    '[WARN] ' . count($holes) . ' game(s) were never processed and need a full recalculation: '
                    . k2_ops_dispatch_format_ids($holes)
                );
            }
        }
        k2_ops_dispatch_log(
            'FinalizeUtcDay'
            . ' closed_utc_day=' . $result['closed_utc_day']
            . ' as_of=' . $result['as_of']
            . ' league_finalized=' . $result['league_finalized']
            . ' league_event_milestones=' . $result['league_event_milestones_inserted']
            . ' perfect_day=' . $result['perfect_day']
            . ' nightmare_day=' . $result['nightmare_day']
            . ($dryRun ? ' dry_run=true' : '')
        );
    } finally {
        $con->close();
    }

    return 0;
}

/**
 * @param array<string, string> $params
 */
function k2_ops_dispatch_process_player_registered(array $params, string $profileName, bool $dryRun): int
{
    $playerId = (int) $params['player_id'];
    if ($playerId <= 0) {
        k2_ops_dispatch_fail('player_id must be a positive integer', 64);
    }

    require_once dirname(__DIR__) . '/modules/process_player_registered.php';

    $con = k2_ops_dispatch_connect($profileName);
    try {
        $result = k2_ops_process_player_registered($con, $playerId, $dryRun);
        k2_ops_dispatch_log(
            'ProcessPlayerRegistered player_id=' . $playerId
            . ' entered_arena_inserted=' . ($result['entered_arena_inserted'] ? '1' : '0')
            . ' committed=' . ($result['committed'] ? 'true' : 'false')
        );
    } finally {
        $con->close();
    }

    return 0;
}
