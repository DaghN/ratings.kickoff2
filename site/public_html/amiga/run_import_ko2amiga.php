<?php
/**
 * Staging / local — import ko2amiga_db into ko2amiga_db.
 *
 * Open:  /amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot
 * Gate:  admin password via POST form (session kept; do not put pwd in the URL).
 * Apply: POST apply=1 (multi-part; optional part=N). Session allows auto-continue GETs.
 *
 * Password file: amiga/_ops/amiga_ops_password.local.php ($admin_password).
 */
declare(strict_types=1);

const AMIGA_IMPORTER_BUILD = 'a2-2026-07-22-l5-s2';
const AMIGA_IMPORT_DIR = __DIR__ . '/_import';

require_once __DIR__ . '/includes/amiga_staging_import_lib.php';

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/includes/amiga_ops_password_lib.php';

$key = 'ko2amiga-import-one-shot';
$onceValue = (string) ($_POST['once'] ?? $_GET['once'] ?? '');
if ($onceValue !== $key) {
    header('HTTP/1.1 404 Not Found');
    echo 'Not found.';
    exit;
}

$apply = (isset($_POST['apply']) && (string) $_POST['apply'] === '1')
    || (isset($_GET['apply']) && (string) $_GET['apply'] === '1');
$partRaw = $_POST['part'] ?? $_GET['part'] ?? null;
$part = $partRaw !== null ? max(1, (int) $partRaw) : 1;

$gate = amiga_ops_gate('admin');
if (!$gate['ok']) {
    $self = (string) ($_SERVER['SCRIPT_NAME'] ?? '/amiga/run_import_ko2amiga.php');
    $hidden = ['once' => $key];
    if ($apply) {
        $hidden['apply'] = '1';
        if ($part > 1) {
            $hidden['part'] = (string) $part;
        }
    }
    amiga_ops_render_password_form(
        $self,
        'Amiga DB import — admin password',
        'Admin password required (' . ($apply ? 'apply import' : 'preview only') . ').',
        $hidden,
        $gate['provided'],
        'Admin password'
    );
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

$restoreMarkerPath = __DIR__ . '/_import/.restore_from_seal.json';
if (is_file($restoreMarkerPath)) {
    $rm = json_decode((string) file_get_contents($restoreMarkerPath), true);
    if (is_array($rm) && !empty($rm['seal_id'])) {
        echo '<p class="warn"><strong>Restore pack staged:</strong> seal <code>'
            . htmlspecialchars((string) $rm['seal_id'], ENT_QUOTES, 'UTF-8')
            . '</code>';
        if (!empty($rm['staged_at'])) {
            echo ' · ' . htmlspecialchars((string) $rm['staged_at'], ENT_QUOTES, 'UTF-8');
        }
        echo ' — Apply import replaces the live DB with this seal (full replace).</p>';
    }
}

$self = htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? '/amiga/run_import_ko2amiga.php', ENT_QUOTES, 'UTF-8');
$onceEsc = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
$queryBase = '?once=' . rawurlencode($key);

if ($apply) {
    echo '<p><strong>Mode:</strong> <span class="warn">APPLY — part ' . $part . ' / ' . count($manifestParts) . '</span></p>';
} else {
    echo '<p><strong>Mode:</strong> preview only (no import yet)</p>';
}

echo '<p style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">';
echo '<form method="post" action="' . $self . '" style="display:inline;margin:0">';
echo '<input type="hidden" name="once" value="' . $onceEsc . '">';
echo '<button class="btn" type="submit" style="cursor:pointer;border:0">Preview again</button></form>';
if (!$apply) {
    echo '<form method="post" action="' . $self . '" style="display:inline;margin:0">';
    echo '<input type="hidden" name="once" value="' . $onceEsc . '">';
    echo '<input type="hidden" name="apply" value="1">';
    echo '<input type="hidden" name="part" value="1">';
    echo '<button class="btn btn-danger" type="submit" style="cursor:pointer;border:0">Apply import</button></form>';
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
