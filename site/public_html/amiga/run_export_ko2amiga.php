<?php
/**
 * Staging / local — export ko2amiga_db for pull to local repair shop (PULL-1b).
 *
 * Preview:
 *   /amiga/run_export_ko2amiga.php?once=ko2amiga-export-one-shot&pwd=coffee
 *
 * Generate dump:
 *   /amiga/run_export_ko2amiga.php?once=ko2amiga-export-one-shot&pwd=coffee&generate=1
 *
 * Download dump (browser; same password gate):
 *   /amiga/run_export_ko2amiga.php?once=ko2amiga-export-one-shot&pwd=coffee&download=1
 */
declare(strict_types=1);

const AMIGA_EXPORTER_BUILD = 'a2-2026-07-08-export-v4';
const AMIGA_EXPORT_DIR = __DIR__ . '/_export';
const AMIGA_EXPORT_DUMP = 'ko2amiga_staging_pull.sql';

require_once __DIR__ . '/includes/amiga_staging_export_lib.php';

function k2_amiga_export_begin_stream(): void
{
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    @ini_set('zlib.output_compression', '0');
    @ini_set('implicit_flush', '1');
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
}

function k2_amiga_export_flush(): void
{
    if (function_exists('ob_flush')) {
        @ob_flush();
    }
    flush();
}

function k2_amiga_export_send_dump_download(string $dumpPath): void
{
    if (!is_file($dumpPath) || !is_readable($dumpPath)) {
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Dump file not found. Generate it first.';
        exit;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $bytes = (int) filesize($dumpPath);
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . AMIGA_EXPORT_DUMP . '"');
    header('Content-Length: ' . (string) $bytes);
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');

    $fh = fopen($dumpPath, 'rb');
    if ($fh === false) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Could not open dump file for reading.';
        exit;
    }

    $chunk = 1024 * 1024;
    while (!feof($fh)) {
        $buf = fread($fh, $chunk);
        if ($buf === false) {
            break;
        }
        echo $buf;
        flush();
    }
    fclose($fh);
    exit;
}

$key = 'ko2amiga-export-one-shot';
$exportPassword = 'coffee';

if (!isset($_GET['once']) || $_GET['once'] !== $key) {
    header('HTTP/1.1 404 Not Found');
    echo 'Not found.';
    exit;
}

$generate = isset($_GET['generate']) && $_GET['generate'] === '1';
$download = isset($_GET['download']) && $_GET['download'] === '1';
$pwdProvided = isset($_GET['pwd']);
$pwdOk = $pwdProvided && hash_equals($exportPassword, (string) $_GET['pwd']);

if (!$pwdOk) {
    header('Content-Type: text/html; charset=utf-8');
    $self = htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? '/amiga/run_export_ko2amiga.php', ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Amiga DB export — password</title>';
    echo '<style>body{font-family:system-ui,sans-serif;max-width:32rem;margin:2rem auto;line-height:1.5}';
    echo 'input[type=password]{width:100%;padding:.5rem;font-size:1rem;box-sizing:border-box}';
    echo 'button{margin-top:.75rem;padding:.5rem 1rem;font-size:1rem}';
    echo '.fail{color:#c0392b;font-weight:600}</style></head><body>';
    echo '<h1>Amiga DB export (staging pull)</h1>';
    if ($pwdProvided) {
        echo '<p class="fail">Incorrect password.</p>';
    } else {
        echo '<p>Password required to continue.</p>';
    }
    if ($generate) {
        echo '<p><strong>Mode:</strong> generate dump</p>';
    } elseif ($download) {
        echo '<p><strong>Mode:</strong> download dump</p>';
    } else {
        echo '<p><strong>Mode:</strong> preview only</p>';
    }
    echo '<form method="get" action="' . $self . '">';
    echo '<input type="hidden" name="once" value="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">';
    if ($generate) {
        echo '<input type="hidden" name="generate" value="1">';
    }
    if ($download) {
        echo '<input type="hidden" name="download" value="1">';
    }
    echo '<p><label for="pwd">Password</label><br><input type="password" id="pwd" name="pwd" autocomplete="current-password" required autofocus></p>';
    echo '<button type="submit">Continue</button></form></body></html>';
    exit;
}

$expectedDb = 'ko2amiga_db';
$dumpPath = AMIGA_EXPORT_DIR . '/' . AMIGA_EXPORT_DUMP;
$manifestPath = AMIGA_EXPORT_DIR . '/ko2amiga_staging_pull_manifest.json';
$formatJson = isset($_GET['format']) && $_GET['format'] === 'json';

/**
 * @param array<string, mixed> $payload
 */
function k2_amiga_export_json_out(array $payload, int $httpCode = 200): void
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
    exit;
}

/**
 * @return array<string, mixed>
 */
function k2_amiga_export_read_status(string $dumpPath, string $manifestPath): array
{
    $exists = is_file($dumpPath);
    $out = [
        'ok' => $exists,
        'exists' => $exists,
        'dump_file' => AMIGA_EXPORT_DUMP,
        'exporter_build' => AMIGA_EXPORTER_BUILD,
    ];
    if ($exists) {
        $out['bytes'] = (int) filesize($dumpPath);
        $out['modified'] = gmdate('Y-m-d H:i:s', (int) filemtime($dumpPath)) . ' UTC';
    }
    if (is_file($manifestPath)) {
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (is_array($manifest)) {
            $out['manifest'] = $manifest;
        }
    }

    return $out;
}

if (isset($_GET['status']) && $_GET['status'] === '1') {
    k2_amiga_export_json_out(k2_amiga_export_read_status($dumpPath, $manifestPath));
}

if ($download) {
    k2_amiga_export_send_dump_download($dumpPath);
}

if ($generate && $formatJson) {
    set_time_limit(600);
    ini_set('memory_limit', '512M');
    include __DIR__ . '/../../config/ko2amiga_config.php';
    if (($database ?? '') !== $expectedDb) {
        k2_amiga_export_json_out([
            'ok' => false,
            'error' => 'config database must be ko2amiga_db',
            'got' => (string) ($database ?? ''),
        ], 500);
    }
    mysqli_report(MYSQLI_REPORT_OFF);
    $con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
    if ($con->connect_errno) {
        k2_amiga_export_json_out([
            'ok' => false,
            'error' => 'connect failed: ' . $con->connect_error,
        ], 500);
    }
    $con->set_charset('utf8mb4');
    $tables = k2_amiga_export_table_list();
    $started = microtime(true);
    $run = k2_amiga_export_write_pull_dump(
        $con,
        (string) $dbhost,
        (int) $dbportnum,
        (string) $username,
        (string) $password,
        (string) $database,
        $tables,
        $dumpPath
    );
    $elapsed = round(microtime(true) - $started, 2);
    mysqli_close($con);
    if (!$run['ok']) {
        k2_amiga_export_json_out([
            'ok' => false,
            'error' => $run['error'],
            'method' => $run['method'],
            'elapsed' => $elapsed,
            'exporter_build' => AMIGA_EXPORTER_BUILD,
        ], 500);
    }
    $manifest = [
        'generated' => gmdate('Y-m-d H:i:s') . ' UTC',
        'source_database' => $database,
        'dump_file' => AMIGA_EXPORT_DUMP,
        'method' => $run['method'],
        'bytes' => $run['bytes'],
        'tables' => $run['tables'],
        'exporter_build' => AMIGA_EXPORTER_BUILD,
        'elapsed' => $elapsed,
    ];
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    k2_amiga_export_json_out([
        'ok' => true,
        'generate' => $manifest,
        'download_url' => ($_SERVER['SCRIPT_NAME'] ?? '/amiga/run_export_ko2amiga.php')
            . '?once=ko2amiga-export-one-shot&download=1',
    ]);
}

header('Content-Type: text/html; charset=utf-8');

if ($generate) {
    set_time_limit(600);
    ini_set('memory_limit', '512M');
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }
    k2_amiga_export_begin_stream();
}

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Amiga DB export (staging pull)</title>';
echo '<style>body{font-family:system-ui,sans-serif;max-width:56rem;margin:1rem;line-height:1.5}';
echo 'pre{background:#1a1a1a;color:#e8e8e8;padding:.75rem;overflow:auto;font-size:13px}';
echo 'code{background:#eee;padding:2px 6px;border-radius:3px}';
echo '.pass{color:#2d8a4e;font-weight:600}.fail{color:#c0392b;font-weight:600}.warn{color:#e6a700;font-weight:600}';
echo 'a.btn{display:inline-block;margin:8px 8px 8px 0;padding:8px 14px;background:#444;color:#fff;text-decoration:none;border-radius:4px}';
echo 'a.btn-primary{background:#1565c0}a.btn-danger{background:#b71c1c}a.btn:hover{opacity:.9}</style></head><body>';

echo '<h1>Export <code>ko2amiga_db</code> (staging pull)</h1>';
echo '<p>Exporter build: <code>' . AMIGA_EXPORTER_BUILD . '</code></p>';
echo '<p>Writes <code>amiga/_export/' . AMIGA_EXPORT_DUMP . '</code> — download in browser or WinSCP → import into local <code>ko2amiga_work</code>.</p>';

$self = htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? '/amiga/run_export_ko2amiga.php', ENT_QUOTES, 'UTF-8');
$queryBase = '?once=' . rawurlencode($key) . '&pwd=' . rawurlencode($exportPassword);
$previewUrl = $self . $queryBase;
$generateUrl = $self . $queryBase . '&generate=1';
$downloadUrl = $self . $queryBase . '&download=1';

if ($generate) {
    echo '<p><strong>Mode:</strong> <span class="warn">GENERATE</span></p>';
} else {
    echo '<p><strong>Mode:</strong> preview only</p>';
}

echo '<p><a class="btn" href="' . $previewUrl . '">Preview</a>';
if (!$generate) {
    echo '<a class="btn btn-primary" href="' . $generateUrl . '">Generate dump</a>';
}
if (is_file($dumpPath)) {
    echo '<a class="btn btn-primary" href="' . $downloadUrl . '">Download dump</a>';
}
echo '</p><hr>';

include __DIR__ . '/../../config/ko2amiga_config.php';

if (($database ?? '') !== $expectedDb) {
    echo '<p class="fail">Refusing: config database must be <code>' . htmlspecialchars($expectedDb, ENT_QUOTES, 'UTF-8')
        . '</code>, got <code>' . htmlspecialchars((string) ($database ?? ''), ENT_QUOTES, 'UTF-8') . '</code>.</p>';
    echo '</body></html>';
    exit;
}

mysqli_report(MYSQLI_REPORT_OFF);

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    echo '<p class="fail">Connect failed: ' . htmlspecialchars($con->connect_error, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '</body></html>';
    exit;
}

$con->set_charset('utf8mb4');

echo '<h2>Connection</h2><pre>';
$dbRow = mysqli_fetch_assoc(mysqli_query($con, 'SELECT DATABASE() AS db, CURRENT_USER() AS db_user, VERSION() AS version'));
echo htmlspecialchars(print_r($dbRow, true), ENT_QUOTES, 'UTF-8');
echo '</pre>';

$tables = k2_amiga_export_table_list();
echo '<h2>Export scope</h2><pre>';
echo 'tables: ' . count($tables) . "\n";
echo 'output: amiga/_export/' . AMIGA_EXPORT_DUMP . "\n";
echo 'mysqldump: ' . (k2_amiga_export_resolve_mysqldump() ?? '(not found — will use PHP fallback)') . "\n";
echo '</pre>';

if (is_file($dumpPath)) {
    echo '<h2>Current dump file</h2><pre>';
    echo 'path: ' . AMIGA_EXPORT_DUMP . "\n";
    echo 'bytes: ' . number_format((int) filesize($dumpPath)) . "\n";
    echo 'modified: ' . date('Y-m-d H:i:s', (int) filemtime($dumpPath)) . "\n";
    if (is_file($manifestPath)) {
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (is_array($manifest)) {
            echo 'generated: ' . (string) ($manifest['generated'] ?? '?') . "\n";
            echo 'method: ' . (string) ($manifest['method'] ?? '?') . "\n";
        }
    }
    echo '</pre>';
    echo '<p class="pass"><a class="btn btn-primary" href="' . $downloadUrl . '">Download dump</a> (~'
        . number_format((int) filesize($dumpPath) / 1024 / 1024, 1) . ' MB) or WinSCP <code>public_html/amiga/_export/'
        . AMIGA_EXPORT_DUMP . '</code></p>';
} else {
    echo '<p>No dump file yet — click <strong>Generate dump</strong>.</p>';
}

if ($generate) {
    echo '<h2>Generate</h2><pre>';
    k2_amiga_export_flush();
    $started = microtime(true);
    $run = k2_amiga_export_write_pull_dump(
        $con,
        (string) $dbhost,
        (int) $dbportnum,
        (string) $username,
        (string) $password,
        (string) $database,
        $tables,
        $dumpPath
    );
    $elapsed = round(microtime(true) - $started, 2);
    if ($run['ok']) {
        $manifest = [
            'generated' => gmdate('Y-m-d H:i:s') . ' UTC',
            'source_database' => $database,
            'dump_file' => AMIGA_EXPORT_DUMP,
            'method' => $run['method'],
            'bytes' => $run['bytes'],
            'tables' => $run['tables'],
            'exporter_build' => AMIGA_EXPORTER_BUILD,
        ];
        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        echo 'status: OK' . "\n";
        echo 'method: ' . $run['method'] . "\n";
        echo 'bytes: ' . number_format((int) $run['bytes']) . "\n";
        echo 'tables: ' . (int) $run['tables'] . "\n";
        echo 'elapsed: ' . $elapsed . " s\n";
        echo '</pre>';
        echo '<p class="pass">Dump written. <a class="btn btn-primary" href="' . $downloadUrl . '">Download dump</a> (~'
            . number_format((int) $run['bytes'] / 1024 / 1024, 1) . ' MB) or WinSCP <code>public_html/amiga/_export/'
            . AMIGA_EXPORT_DUMP . '</code> → import into <code>ko2amiga_work</code>.</p>';
    } else {
        echo 'status: FAIL' . "\n";
        echo 'error: ' . htmlspecialchars($run['error'], ENT_QUOTES, 'UTF-8') . "\n";
        if (($run['method'] ?? '') !== '') {
            echo 'method: ' . $run['method'] . "\n";
        }
        echo 'elapsed: ' . $elapsed . " s\n";
        echo '</pre><p class="fail">Export failed — share this page.</p>';
    }
}

mysqli_close($con);
echo '</body></html>';