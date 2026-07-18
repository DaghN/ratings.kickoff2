<?php
/**
 * Staging / local — import ko2amiga_db into ko2amiga_db.
 *
 * Preview:
 *   /amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot&pwd=YOUR_OPS_PASSWORD
 *
 * Apply (multi-part — avoids gateway timeouts):
 *   /amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot&pwd=YOUR_OPS_PASSWORD&apply=1
 *   Optional: &part=1 (auto-continues through manifest parts)
 *
 * Password: site/config/amiga_ops_password.local.php (gitignored).
 */
declare(strict_types=1);

const AMIGA_IMPORTER_BUILD = 'a2-2026-06-06-b4';
const AMIGA_IMPORT_DIR = __DIR__ . '/_import';

function k2_amiga_import_begin_stream(): void
{
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    @ini_set('zlib.output_compression', '0');
    @ini_set('implicit_flush', '1');
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
}

function k2_amiga_import_flush(): void
{
    if (function_exists('ob_flush')) {
        @ob_flush();
    }
    flush();
}

function k2_amiga_import_stmt_label(string $stmt): string
{
    if (preg_match('/^INSERT\s+INTO\s+`?([a-zA-Z0-9_]+)`?/i', $stmt, $m)) {
        return 'INSERT ' . $m[1];
    }
    if (preg_match('/^CREATE\s+TABLE\s+`?([a-zA-Z0-9_]+)`?/i', $stmt, $m)) {
        return 'CREATE ' . $m[1];
    }
    if (preg_match('/^DROP\s+TABLE\s+`?([a-zA-Z0-9_]+)`?/i', $stmt, $m)) {
        return 'DROP ' . $m[1];
    }

    return substr(preg_replace('/\s+/', ' ', trim($stmt)) ?? '', 0, 48);
}

/**
 * @return list<string>
 */
function k2_amiga_import_manifest_parts(): array
{
    $manifestPath = AMIGA_IMPORT_DIR . '/ko2amiga_manifest.json';
    if (is_file($manifestPath)) {
        $raw = (string) file_get_contents($manifestPath);
        if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
            $raw = substr($raw, 3);
        }
        $json = json_decode($raw, true);
        if (is_array($json) && !empty($json['parts']) && is_array($json['parts'])) {
            return array_values(array_map('strval', $json['parts']));
        }
    }

    return ['ko2amiga_db.sql'];
}

/**
 * @return array{ok:bool,error:string,bytes:int,sql?:string}
 */
function k2_amiga_import_read_dump(string $path): array
{
    if (!is_file($path)) {
        return ['ok' => false, 'error' => 'Dump file not found: ' . basename($path), 'bytes' => 0];
    }
    if (!is_readable($path)) {
        return ['ok' => false, 'error' => 'Dump file is not readable.', 'bytes' => 0];
    }
    $sql = file_get_contents($path);
    if ($sql === false) {
        return ['ok' => false, 'error' => 'Could not read dump file.', 'bytes' => 0];
    }
    if (strncmp($sql, "\xEF\xBB\xBF", 3) === 0) {
        $sql = substr($sql, 3);
    }
    $sql = preg_replace('/^CREATE DATABASE .*?;\s*/mi', '', $sql) ?? $sql;
    $sql = preg_replace('/^USE `?ko2amiga_db`?;\s*/mi', '', $sql) ?? $sql;
    if (trim($sql) === '') {
        return ['ok' => false, 'error' => 'Dump file is empty after cleanup.', 'bytes' => 0];
    }

    return ['ok' => true, 'error' => '', 'bytes' => strlen($sql), 'sql' => $sql];
}

/**
 * @return list<string>
 */
function k2_amiga_import_split_sql(string $sql): array
{
    $statements = [];
    $buffer = '';
    foreach (preg_split('/\R/', $sql) as $line) {
        $trim = trim($line);
        if ($trim === '' || strncmp($trim, '--', 2) === 0) {
            continue;
        }
        $buffer .= $line . "\n";
        if (str_ends_with(rtrim($line), ';')) {
            $stmt = trim($buffer);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $buffer = '';
        }
    }
    $tail = trim($buffer);
    if ($tail !== '') {
        $statements[] = $tail;
    }

    return $statements;
}

/**
 * @return array{ok:bool,error:string,statements:int}
 */
function k2_amiga_import_run_sql(mysqli $con, string $sql, bool $echoProgress): array
{
    mysqli_query($con, 'SET FOREIGN_KEY_CHECKS = 0');
    mysqli_query($con, 'SET UNIQUE_CHECKS = 0');
    @mysqli_query($con, 'SET SESSION max_allowed_packet = 67108864');

    $statements = k2_amiga_import_split_sql($sql);
    $total = count($statements);
    $done = 0;

    foreach ($statements as $stmt) {
        if (!mysqli_query($con, $stmt)) {
            return [
                'ok' => false,
                'error' => mysqli_error($con),
                'statements' => $done,
            ];
        }
        $done++;
        if ($echoProgress) {
            echo 'progress: ' . $done . ' / ' . $total . ' — ' . k2_amiga_import_stmt_label($stmt) . "\n";
            k2_amiga_import_flush();
        }
    }

    mysqli_query($con, 'SET FOREIGN_KEY_CHECKS = 1');
    mysqli_query($con, 'SET UNIQUE_CHECKS = 1');

    return ['ok' => true, 'error' => '', 'statements' => $done];
}

function k2_amiga_import_table_exists(mysqli $con, string $table): bool
{
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
    if ($safe === '') {
        return false;
    }
    $res = mysqli_query($con, "SHOW TABLES LIKE '" . mysqli_real_escape_string($con, $safe) . "'");
    if (!$res) {
        return false;
    }
    $exists = mysqli_num_rows($res) > 0;
    mysqli_free_result($res);

    return $exists;
}

/**
 * @return array{players:int,games:int,ok:bool,error:string,missing:bool}
 */
function k2_amiga_import_counts(mysqli $con): array
{
    $out = ['players' => 0, 'games' => 0, 'ok' => true, 'error' => '', 'missing' => false];
    foreach (['amiga_players' => 'players', 'amiga_games' => 'games'] as $table => $key) {
        if (!k2_amiga_import_table_exists($con, $table)) {
            $out['missing'] = true;
            continue;
        }
        $res = mysqli_query($con, 'SELECT COUNT(*) AS c FROM `' . $table . '`');
        if (!$res) {
            $out['ok'] = false;
            $out['error'] = mysqli_error($con);
            return $out;
        }
        $row = mysqli_fetch_assoc($res);
        mysqli_free_result($res);
        $out[$key] = (int) ($row['c'] ?? 0);
    }

    return $out;
}

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../../config/amiga_ops_password.php';

$key = 'ko2amiga-import-one-shot';
$importPassword = amiga_ops_require_password();

if (!isset($_GET['once']) || $_GET['once'] !== $key) {
    header('HTTP/1.1 404 Not Found');
    echo 'Not found.';
    exit;
}

$apply = isset($_GET['apply']) && $_GET['apply'] === '1';
$part = isset($_GET['part']) ? max(1, (int) $_GET['part']) : 1;
$pwdProvided = isset($_GET['pwd']);
$pwdOk = $pwdProvided && hash_equals($importPassword, (string) $_GET['pwd']);

if (!$pwdOk) {
    $self = htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? '/amiga/run_import_ko2amiga.php', ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Amiga DB import — password</title>';
    echo '<style>body{font-family:system-ui,sans-serif;max-width:32rem;margin:2rem auto;line-height:1.5}';
    echo 'input[type=password]{width:100%;padding:.5rem;font-size:1rem;box-sizing:border-box}';
    echo 'button{margin-top:.75rem;padding:.5rem 1rem;font-size:1rem}';
    echo '.fail{color:#c0392b;font-weight:600}</style></head><body>';
    echo '<h1>Amiga DB import</h1>';
    if ($pwdProvided) {
        echo '<p class="fail">Incorrect password.</p>';
    } else {
        echo '<p>Password required to continue.</p>';
    }
    if ($apply) {
        echo '<p><strong>Mode:</strong> apply import (multi-part)</p>';
    } else {
        echo '<p><strong>Mode:</strong> preview only</p>';
    }
    echo '<form method="get" action="' . $self . '">';
    echo '<input type="hidden" name="once" value="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">';
    if ($apply) {
        echo '<input type="hidden" name="apply" value="1">';
    }
    echo '<p><label for="pwd">Password</label><br><input type="password" id="pwd" name="pwd" autocomplete="current-password" required autofocus></p>';
    echo '<button type="submit">Continue</button></form></body></html>';
    exit;
}

$expectedDb = 'ko2amiga_db';
$manifestParts = k2_amiga_import_manifest_parts();
$usesParts = count($manifestParts) > 1 || $manifestParts[0] !== 'ko2amiga_db.sql';

if ($apply) {
    set_time_limit(300);
    ini_set('memory_limit', '512M');
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }
    k2_amiga_import_begin_stream();
    register_shutdown_function(static function (): void {
        $err = error_get_last();
        if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            echo "\nFATAL: " . $err['message'] . ' in ' . $err['file'] . ':' . $err['line'] . "\n";
            k2_amiga_import_flush();
        }
    });
}

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Amiga DB import</title>';
echo '<style>body{font-family:system-ui,sans-serif;max-width:56rem;margin:1rem;line-height:1.5}';
echo 'pre{background:#1a1a1a;color:#e8e8e8;padding:.75rem;overflow:auto;font-size:13px}';
echo '.pass{color:#2d8a4e;font-weight:600}.fail{color:#c0392b;font-weight:600}.warn{color:#e6a700;font-weight:600}';
echo 'a.btn{display:inline-block;margin:8px 8px 8px 0;padding:8px 14px;background:#444;color:#fff;text-decoration:none;border-radius:4px}';
echo 'a.btn-danger{background:#b71c1c}a.btn:hover{opacity:.9}</style></head><body>';

echo '<h1>Import <code>ko2amiga_db</code></h1>';
echo '<p>Importer build: <code>' . AMIGA_IMPORTER_BUILD . '</code></p>';

$self = htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? '/amiga/run_import_ko2amiga.php', ENT_QUOTES, 'UTF-8');
$queryBase = '?once=' . rawurlencode($key) . '&pwd=' . rawurlencode($importPassword);
$previewUrl = $self . $queryBase;
$applyUrl = $self . $queryBase . '&apply=1&part=1';

if ($apply) {
    echo '<p><strong>Mode:</strong> <span class="warn">APPLY — part ' . $part . ' / ' . count($manifestParts) . '</span></p>';
} else {
    echo '<p><strong>Mode:</strong> preview only (no import yet)</p>';
}

echo '<p><a class="btn" href="' . $previewUrl . '">Preview again</a>';
if (!$apply) {
    echo '<a class="btn btn-danger" href="' . $applyUrl . '">Apply import</a>';
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

echo '<h2>Import files</h2><pre>';
$manifestPath = AMIGA_IMPORT_DIR . '/ko2amiga_manifest.json';
if (is_file($manifestPath)) {
    echo 'manifest: yes' . "\n";
    echo 'generated: ' . (string) (json_decode((string) file_get_contents($manifestPath), true)['generated'] ?? '?') . "\n";
    echo 'parts: ' . count($manifestParts) . "\n";
} else {
    echo "manifest: no (fallback to ko2amiga_db.sql)\n";
}
foreach ($manifestParts as $i => $file) {
    $path = AMIGA_IMPORT_DIR . '/' . $file;
    $n = $i + 1;
    $mark = ($apply && $n === $part) ? '>' : ' ';
    if (is_file($path)) {
        echo $mark . ' ' . $n . '. ' . $file . ' — ' . number_format((int) filesize($path)) . " bytes\n";
    } else {
        echo $mark . ' ' . $n . '. ' . $file . " — MISSING\n";
    }
}
$fullDump = AMIGA_IMPORT_DIR . '/ko2amiga_db.sql';
if (is_file($fullDump)) {
    echo 'full dump: ' . number_format((int) filesize($fullDump)) . ' bytes, modified '
        . date('Y-m-d H:i:s', (int) filemtime($fullDump)) . "\n";
}
echo '</pre>';

$before = k2_amiga_import_counts($con);
echo '<h2>Current row counts</h2><pre>';
if (!$before['ok']) {
    echo 'Could not read counts: ' . htmlspecialchars($before['error'], ENT_QUOTES, 'UTF-8') . "\n";
} elseif ($before['missing']) {
    echo "tables not created yet (normal before part 1)\n";
    echo 'amiga_players: —' . "\n";
    echo 'amiga_games: —' . "\n";
} else {
    echo 'amiga_players: ' . $before['players'] . "\n";
    echo 'amiga_games: ' . $before['games'] . "\n";
}
echo '</pre>';

if ($apply) {
    if ($part > count($manifestParts)) {
        echo '<p class="fail">Invalid part number.</p>';
    } else {
        $partFile = $manifestParts[$part - 1];
        $partPath = AMIGA_IMPORT_DIR . '/' . $partFile;
        echo '<h2>Import part ' . $part . '</h2><pre>';
        echo 'file: ' . htmlspecialchars($partFile, ENT_QUOTES, 'UTF-8') . "\n";
        k2_amiga_import_flush();

        $read = k2_amiga_import_read_dump($partPath);
        if (!$read['ok']) {
            echo 'status: FAIL' . "\n";
            echo 'error: ' . htmlspecialchars($read['error'], ENT_QUOTES, 'UTF-8') . "\n";
            echo '</pre><p class="fail">Import aborted.</p>';
        } else {
            echo 'SQL payload: ' . number_format((int) $read['bytes']) . " bytes\n";
            k2_amiga_import_flush();
            $started = microtime(true);
            $run = k2_amiga_import_run_sql($con, $read['sql'], true);
            $elapsed = round(microtime(true) - $started, 2);
            if ($run['ok']) {
                echo 'status: OK' . "\n";
                echo 'statements: ' . (int) ($run['statements'] ?? 0) . "\n";
                echo 'elapsed: ' . $elapsed . " s\n";
                echo '</pre>';
                if ($part < count($manifestParts)) {
                    $next = $part + 1;
                    $nextUrl = $self . $queryBase . '&apply=1&part=' . $next;
                    echo '<p class="pass">Part ' . $part . ' finished. Continuing to part ' . $next . '…</p>';
                    echo '<p><a class="btn" href="' . htmlspecialchars($nextUrl, ENT_QUOTES, 'UTF-8') . '">Continue now</a></p>';
                    echo '<meta http-equiv="refresh" content="2;url=' . htmlspecialchars($nextUrl, ENT_QUOTES, 'UTF-8') . '">';
                } else {
                    echo '<p class="pass">All parts finished.</p>';
                }
            } else {
                echo 'status: FAIL' . "\n";
                echo 'error: ' . htmlspecialchars($run['error'], ENT_QUOTES, 'UTF-8') . "\n";
                echo 'statements done: ' . (int) ($run['statements'] ?? 0) . "\n";
                echo 'elapsed: ' . $elapsed . " s\n";
                echo '</pre><p class="fail">Import failed on part ' . $part . ' — share this page.</p>';
            }
        }
    }

    $after = k2_amiga_import_counts($con);
    echo '<h2>Row counts now</h2><pre>';
    if (!$after['ok']) {
        echo htmlspecialchars($after['error'], ENT_QUOTES, 'UTF-8') . "\n";
    } elseif ($after['missing']) {
        echo "tables not present yet (schema part may not have run)\n";
        echo 'amiga_players: —' . "\n";
        echo 'amiga_games: —' . "\n";
    } else {
        echo 'amiga_players: ' . $after['players'] . "\n";
        echo 'amiga_games: ' . $after['games'] . "\n";
    }
    echo '</pre>';

    if ($part >= count($manifestParts)) {
        echo '<p>Spot-check: <a href="/amiga/rating.php">/amiga/rating.php</a></p>';
    }
}

mysqli_close($con);
echo '</body></html>';
