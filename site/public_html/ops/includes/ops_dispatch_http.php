<?php
/**
 * HTTP bridge for ops dispatch — used by dispatch_request.php (game server, remote cron).
 */
declare(strict_types=1);

require_once __DIR__ . '/ops_dispatch.php';

function k2_ops_dispatch_http_ini_path(): string
{
    return dirname(__DIR__) . '/config/dispatch-http.ini';
}

function k2_ops_dispatch_http_shared_key(): ?string
{
    $path = k2_ops_dispatch_http_ini_path();
    if (!is_file($path)) {
        return null;
    }
    $ini = parse_ini_file($path, true, INI_SCANNER_TYPED);
    if (!is_array($ini) || !isset($ini['http']) || !is_array($ini['http'])) {
        return null;
    }
    $key = trim((string) ($ini['http']['shared_key'] ?? ''));
    if ($key === '' || $key === 'change-me-before-go-live') {
        return null;
    }

    return $key;
}

/**
 * @param array<string, mixed> $query
 * @return array{http_status: int, body: array<string, mixed>}
 */
function k2_ops_dispatch_http_handle(array $query): array
{
    $sharedKey = k2_ops_dispatch_http_shared_key();
    if ($sharedKey === null) {
        return k2_ops_dispatch_http_error(
            503,
            'dispatch-http.ini missing or shared_key not configured',
            64
        );
    }

    $providedKey = isset($query['key']) ? (string) $query['key'] : (isset($query['pwd']) ? (string) $query['pwd'] : '');
    if ($providedKey === '' || !hash_equals($sharedKey, $providedKey)) {
        return k2_ops_dispatch_http_error(401, 'unauthorized', 64);
    }

    $parsed = k2_ops_parse_dispatch_query($query);
    if ($parsed['cmd'] === '') {
        return k2_ops_dispatch_http_error(400, 'missing CMD', 64);
    }
	
    k2_ops_dispatch_http_begin();
    // Until a reply is built, dying early must not look like success to the game server.
    http_response_code(500);

    try {
        $exitCode = k2_ops_dispatch_run($parsed['cmd'], $parsed['params'], $parsed['dry_run']);
    } catch (K2OpsDispatchExit $e) {
        $exitCode = $e->exitCode;
    }
    return k2_ops_dispatch_http_result($exitCode, $parsed['cmd'], k2_ops_dispatch_http_lines());
}

/**
 * @param list<string> $log
 * @return array{http_status: int, body: array<string, mixed>}
 */
function k2_ops_dispatch_http_result(int $exitCode, string $cmd, array $log): array
{
    $ok = $exitCode === 0 || $exitCode === 2;
    if (!$ok) {
        error_log('[k2-ops] cmd=' . $cmd . ' exit=' . $exitCode . ' ' . implode(' | ', array_slice($log, -3)));
    }
    // Every handled outcome answers 200 and the JSON says what happened: the live web server
    // blocks an address after a few error replies, and a blocked game server could no longer
    // report games. Busy or failed games stay queued (exit 1), and refused ones (exit 3) are in
    // the error log above. Error codes are kept for real faults: bad key 401, missing config
    // 503, bad call 400, PHP crash 500.
    $httpStatus = $exitCode === 64 ? 400 : 200;

    return [
        'http_status' => $httpStatus,
        'body' => [
            'ok' => $ok,
            'exit' => $exitCode,
            'cmd' => $cmd,
            'log' => $log,
        ],
    ];
}

/**
 * @return array{http_status: int, body: array<string, mixed>}
 */
function k2_ops_dispatch_http_error(int $httpStatus, string $error, int $exitCode): array
{
    return [
        'http_status' => $httpStatus,
        'body' => [
            'ok' => false,
            'exit' => $exitCode,
            'error' => $error,
            'log' => [],
        ],
    ];
}

/**
 * @param array{http_status: int, body: array<string, mixed>} $response
 */
function k2_ops_dispatch_http_send(array $response): never
{
    http_response_code($response['http_status']);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($response['body'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit(0);
}
