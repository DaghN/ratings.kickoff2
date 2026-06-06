<?php
/**
 * Parse Steve-style CLI args: CMD=Verb key=value --target profile
 *
 * @return array{
 *   cmd: string,
 *   params: array<string, string>,
 *   target: ?string,
 *   dry_run: bool
 * }
 */
declare(strict_types=1);

function k2_ops_parse_dispatch_argv(array $argv): array
{
    $cmd = '';
    $params = [];
    $target = null;
    $dryRun = false;

    for ($i = 1, $n = count($argv); $i < $n; $i++) {
        $arg = $argv[$i];
        if ($arg === '--dry-run') {
            $dryRun = true;
            continue;
        }
        if ($arg === '--target' && isset($argv[$i + 1])) {
            $target = $argv[++$i];
            continue;
        }
        if (str_starts_with($arg, '--target=')) {
            $target = substr($arg, 9);
            continue;
        }
        if (preg_match('/^CMD=(.+)$/i', $arg, $m)) {
            $cmd = trim($m[1]);
            continue;
        }
        if (preg_match('/^([^=]+)=(.*)$/', $arg, $m)) {
            $key = strtolower($m[1]);
            if ($key === 'cmd') {
                $cmd = trim($m[2]);
            } else {
                $params[$key] = $m[2];
            }
            continue;
        }
        if ($cmd === '' && !str_starts_with($arg, '-')) {
            $cmd = $arg;
        }
    }

    if (isset($params['target']) && ($target === null || $target === '')) {
        $target = $params['target'];
    }
    if (isset($params['dry_run']) && in_array(strtolower($params['dry_run']), ['1', 'true', 'yes'], true)) {
        $dryRun = true;
    }

    return [
        'cmd' => $cmd,
        'params' => $params,
        'target' => $target,
        'dry_run' => $dryRun,
    ];
}

/**
 * Parse HTTP query params (GET/POST) into the same shape as k2_ops_parse_dispatch_argv().
 *
 * @param array<string, mixed> $query
 * @return array{
 *   cmd: string,
 *   params: array<string, string>,
 *   target: ?string,
 *   dry_run: bool
 * }
 */
function k2_ops_parse_dispatch_query(array $query): array
{
    $cmd = '';
    if (isset($query['CMD'])) {
        $cmd = trim((string) $query['CMD']);
    } elseif (isset($query['cmd'])) {
        $cmd = trim((string) $query['cmd']);
    }

    $params = [];
    $dryRun = false;

    foreach ($query as $key => $value) {
        if (!is_scalar($value)) {
            continue;
        }
        $normalized = strtolower((string) $key);
        if (in_array($normalized, ['cmd', 'key', 'pwd'], true)) {
            if ($normalized === 'cmd') {
                $cmd = trim((string) $value);
            }
            continue;
        }
        if ($normalized === 'dry_run' || $normalized === 'dry-run') {
            if (in_array(strtolower((string) $value), ['1', 'true', 'yes'], true)) {
                $dryRun = true;
            }
            continue;
        }
        $params[$normalized] = (string) $value;
    }

    return [
        'cmd' => $cmd,
        'params' => $params,
        'target' => $params['target'] ?? null,
        'dry_run' => $dryRun,
    ];
}
