<?php
/**
 * Dispatch router — maps CMD names to existing k2_ops_* modules (no business logic here).
 */
declare(strict_types=1);

require_once __DIR__ . '/ops_argv.php';
require_once __DIR__ . '/ops_work_target.php';
require_once __DIR__ . '/ops_bootstrap.php';

/** @var array<string, array{summary: string, required: list<string>, optional: list<string>}> */
const K2_OPS_DISPATCH_REGISTRY = [
    'ProcessCompletedGame' => [
        'summary' => 'Post-game derived update for one ratedresults row (P0–P7).',
        'required' => ['game_id'],
        'optional' => ['dry_run'],
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
        '  target=local-work | staging-work | local-dev',
        '  database=kooldb1  (maps to profile with that work_database)',
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
    $lines[] = '  php dispatch.php CMD=FinalizeLeagueDue target=staging-work as_of=2026-06-03T00:00:01Z';
    $lines[] = '  php dispatch.php CMD=FinalizeUtcDay target=staging-work as_of=2026-06-04T00:00:01Z';
    $lines[] = '';
    $lines[] = 'Exit codes: 0 ok | 1 error | 2 already processed (no DB change) | 64 usage';
    fwrite(STDERR, implode(PHP_EOL, $lines) . PHP_EOL);
}

function k2_ops_dispatch_log(string $message): void
{
    fwrite(STDOUT, '[dispatch] ' . $message . PHP_EOL);
}

function k2_ops_dispatch_fail(string $message, int $exitCode = 1): never
{
    fwrite(STDERR, '[dispatch] ERROR: ' . $message . PHP_EOL);
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
    k2_ops_dispatch_log("CMD={$cmd} dry_run=" . ($dryRun ? 'true' : 'false'));

    try {
        $exitCode = match ($cmd) {
            'ProcessCompletedGame' => k2_ops_dispatch_process_completed_game($params, $profileName, $dryRun),
            'FinalizeLeagueDue' => k2_ops_dispatch_finalize_league_due($params, $profileName),
            'FinalizeUtcDay' => k2_ops_dispatch_finalize_utc_day($params, $profileName, $dryRun),
            'ProcessPlayerRegistered' => k2_ops_dispatch_process_player_registered($params, $profileName, $dryRun),
            default => k2_ops_dispatch_fail("CMD={$cmd} not wired", 64),
        };
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
 * @param array<string, string> $params
 */
function k2_ops_dispatch_process_completed_game(array $params, string $profileName, bool $dryRun): int
{
    $gameId = (int) $params['game_id'];
    if ($gameId <= 0) {
        k2_ops_dispatch_fail('game_id must be a positive integer', 64);
    }

    require_once dirname(__DIR__) . '/modules/process_completed_game.php';

    $con = k2_ops_dispatch_connect($profileName);
    try {
        $result = k2_ops_process_completed_game($con, $gameId, $dryRun);
        if (!empty($result['skipped'])) {
            k2_ops_dispatch_log(
                'ProcessCompletedGame game_id=' . $gameId
                . ' skipped=true reason=' . ($result['skip_reason'] ?? 'unknown')
            );

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
    require_once dirname(__DIR__) . '/modules/finalize_utc_day.php';

    $asOf = k2_ops_parse_as_of($params['as_of'] ?? null);
    $closedUtcDay = isset($params['closed_utc_day']) && $params['closed_utc_day'] !== ''
        ? $params['closed_utc_day']
        : null;

    $con = k2_ops_dispatch_connect($profileName);
    try {
        $result = k2_ops_finalize_utc_day($con, $asOf, $dryRun, $closedUtcDay);
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
