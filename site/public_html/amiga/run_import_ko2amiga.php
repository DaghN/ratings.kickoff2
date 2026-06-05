<?php
/**
 * Staging / local — import ko2amiga_db.sql from amiga/_import/ into ko2amiga_db.
 *
 * Preview (no DB changes):
 *   /amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot&pwd=coffee
 *
 * Apply import (replaces all Amiga tables in ko2amiga_db):
 *   /amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot&pwd=coffee&apply=1
 *
 * Workflow: rebuild locally (setup_ko2amiga_db.ps1), WinSCP sync public_html, then Apply URL.
 * Only touches the database named in ko2amiga_config.local.php (must be ko2amiga_db).
 */
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

$key = 'ko2amiga-import-one-shot';
$importPassword = 'coffee';

if (!isset($_GET['once']) || $_GET['once'] !== $key) {
    header('HTTP/1.1 404 Not Found');
    echo 'Not found.';
    exit;
}

$apply = isset($_GET['apply']) && $_GET['apply'] === '1';
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
        echo '<p><strong>Mode:</strong> apply import (full replace)</p>';
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
$dumpFile = __DIR__ . '/_import/ko2amiga_db.sql';

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Amiga DB import</title>';
echo '<style>body{font-family:system-ui,sans-serif;max-width:56rem;margin:1rem;line-height:1.5}';
echo 'pre{background:#1a1a1a;color:#e8e8e8;padding:.75rem;overflow:auto;font-size:13px}';
echo '.pass{color:#2d8a4e;font-weight:600}.fail{color:#c0392b;font-weight:600}.warn{color:#e6a700;font-weight:600}';
echo 'a.btn{display:inline-block;margin:8px 8px 8px 0;padding:8px 14px;background:#444;color:#fff;text-decoration:none;border-radius:4px}';
echo 'a.btn-danger{background:#b71c1c}a.btn:hover{opacity:.9}</style></head><body>';

echo '<h1>Import <code>ko2amiga_db.sql</code></h1>';
echo '<p>Dump path: <code>' . htmlspecialchars($dumpFile, ENT_QUOTES, 'UTF-8') . '</code></p>';

if ($apply) {
    echo '<p><strong>Mode:</strong> <span class="warn">APPLY — full replace of Amiga tables</span></p>';
} else {
    echo '<p><strong>Mode:</strong> preview only (no import yet)</p>';
}

$self = htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? '/amiga/run_import_ko2amiga.php', ENT_QUOTES, 'UTF-8');
$queryBase = '?once=' . rawurlencode($key) . '&pwd=' . rawurlencode($importPassword);
$previewUrl = $self . $queryBase;
$applyUrl = $self . $queryBase . '&apply=1';
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

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    echo '<p class="fail">Connect failed: ' . htmlspecialchars($con->connect_error, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '</body></html>';
    exit;
}

$con->set_charset('utf8mb4');

/**
 * @return array{players:int,games:int,ok:bool,error:string}
 */
function k2_amiga_import_counts(mysqli $con): array
{
    $out = ['players' => 0, 'games' => 0, 'ok' => true, 'error' => ''];
    foreach (['playertable' => 'players', 'ratedresults' => 'games'] as $table => $key) {
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

/**
 * @return array{ok:bool,error:string,bytes:int}
 */
function k2_amiga_import_read_dump(string $path): array
{
    if (!is_file($path)) {
        return ['ok' => false, 'error' => 'Dump file not found.', 'bytes' => 0];
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
    // mysqldump --databases: we are already connected to ko2amiga_db.
    $sql = preg_replace('/^CREATE DATABASE .*?;\s*/mi', '', $sql) ?? $sql;
    $sql = preg_replace('/^USE `?' . preg_quote('ko2amiga_db', '/') . '`?;\s*/mi', '', $sql) ?? $sql;
    if (trim($sql) === '') {
        return ['ok' => false, 'error' => 'Dump file is empty after cleanup.', 'bytes' => 0];
    }
    return ['ok' => true, 'error' => '', 'bytes' => strlen($sql), 'sql' => $sql];
}

/**
 * @return array{ok:bool,error:string}
 */
function k2_amiga_import_run_sql(mysqli $con, string $sql): array
{
    if (!mysqli_multi_query($con, $sql)) {
        return ['ok' => false, 'error' => mysqli_error($con)];
    }
    do {
        if ($result = mysqli_store_result($con)) {
            mysqli_free_result($result);
        }
        if (mysqli_errno($con)) {
            return ['ok' => false, 'error' => mysqli_error($con)];
        }
    } while (mysqli_more_results($con) && mysqli_next_result($con));

    return ['ok' => true, 'error' => ''];
}

echo '<h2>Connection</h2><pre>';
$dbRow = mysqli_fetch_assoc(mysqli_query($con, 'SELECT DATABASE() AS db, CURRENT_USER() AS db_user, VERSION() AS version'));
echo htmlspecialchars(print_r($dbRow, true), ENT_QUOTES, 'UTF-8');
echo '</pre>';

echo '<h2>Dump file</h2><pre>';
if (is_file($dumpFile)) {
    echo 'exists: yes' . "\n";
    echo 'size: ' . number_format((int) filesize($dumpFile)) . " bytes\n";
    echo 'modified: ' . date('Y-m-d H:i:s', (int) filemtime($dumpFile)) . "\n";
} else {
    echo "exists: no\n";
    echo "Run scripts\\setup_ko2amiga_db.ps1 locally, then WinSCP sync public_html.\n";
}
echo '</pre>';

$before = k2_amiga_import_counts($con);
echo '<h2>Current row counts</h2><pre>';
if ($before['ok']) {
    echo 'playertable: ' . $before['players'] . "\n";
    echo 'ratedresults: ' . $before['games'] . "\n";
} else {
    echo 'Could not read counts (tables may not exist yet): ' . htmlspecialchars($before['error'], ENT_QUOTES, 'UTF-8') . "\n";
}
echo '</pre>';

if ($apply) {
    set_time_limit(300);
    $read = k2_amiga_import_read_dump($dumpFile);
    if (!$read['ok']) {
        echo '<p class="fail">Import aborted: ' . htmlspecialchars($read['error'], ENT_QUOTES, 'UTF-8') . '</p>';
    } else {
        echo '<h2>Import</h2><pre>';
        echo 'SQL payload: ' . number_format((int) $read['bytes']) . " bytes\n";
        $started = microtime(true);
        $run = k2_amiga_import_run_sql($con, $read['sql']);
        $elapsed = round(microtime(true) - $started, 2);
        if ($run['ok']) {
            echo 'status: OK' . "\n";
            echo 'elapsed: ' . $elapsed . " s\n";
            echo '</pre><p class="pass">Import finished.</p>';
        } else {
            echo 'status: FAIL' . "\n";
            echo 'error: ' . htmlspecialchars($run['error'], ENT_QUOTES, 'UTF-8') . "\n";
            echo 'elapsed: ' . $elapsed . " s\n";
            echo '</pre><p class="fail">Import failed — share this page (no passwords).</p>';
        }
    }

    $after = k2_amiga_import_counts($con);
    echo '<h2>Row counts after import</h2><pre>';
    if ($after['ok']) {
        echo 'playertable: ' . $after['players'] . "\n";
        echo 'ratedresults: ' . $after['games'] . "\n";
    } else {
        echo htmlspecialchars($after['error'], ENT_QUOTES, 'UTF-8') . "\n";
    }
    echo '</pre>';

    echo '<p>Spot-check: <a href="/amiga/rating.php">/amiga/rating.php</a></p>';
}

mysqli_close($con);
echo '</body></html>';
