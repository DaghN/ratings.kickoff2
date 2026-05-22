<?php
/**
 * ONE-TIME / THROWAWAY — add idA / idB indexes on ratedresults (Profile Phase A).
 *
 * Speeds player-scoped queries (individual1 feast load, chart APIs). Does not change data.
 *
 * Usage (staging first, then production when ready):
 * 1. Confirm with Steve which database this vhost uses (see DATABASE() on preview).
 * 2. Copy this file into the server public_html/ (WinSCP).
 * 3. Preview (no DDL):
 *      …/throwaway_ratedresults_player_indexes.php?once=ratedresults-player-indexes-one-shot
 * 4. Apply indexes:
 *      …/throwaway_ratedresults_player_indexes.php?once=ratedresults-player-indexes-one-shot&apply=1
 * 5. Delete this file from the server immediately after both environments are done.
 *
 * Local Laragon can use scripts/apply_ratedresults_player_indexes.ps1 instead.
 */
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$key = 'ratedresults-player-indexes-one-shot';
if (!isset($_GET['once']) || $_GET['once'] !== $key) {
    header('HTTP/1.1 404 Not Found');
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body style="font-family:system-ui,sans-serif;margin:2rem;line-height:1.5">';
    echo '<p><strong>Not found.</strong></p>';
    echo '<p>This script needs the full URL including the <code>once</code> query parameter.</p>';
    echo '<p>Copy-paste preview URL:</p>';
    echo '<pre style="background:#f4f4f4;padding:12px;overflow:auto">'
        . htmlspecialchars('/throwaway_ratedresults_player_indexes.php?once=' . $key, ENT_QUOTES, 'UTF-8')
        . '</pre>';
    echo '<p>Apply (after preview): add <code>&amp;apply=1</code> at the end.</p>';
    echo '<p>File must live in <strong>public_html</strong> (same folder as <code>individual1.php</code>).</p>';
    echo '</body></html>';
    exit;
}

$apply = isset($_GET['apply']) && $_GET['apply'] === '1';
$table = 'ratedresults';
$indexes = [
    'idx_ratedresults_idA' => 'idA',
    'idx_ratedresults_idB' => 'idB',
];

echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>ratedresults player indexes</title>';
echo '<style>body{font-family:system-ui,sans-serif;max-width:56rem;margin:1rem;line-height:1.5;color:#111}';
echo 'pre{background:#1a1a1a;color:#e8e8e8;padding:.75rem;overflow:auto;font-size:13px}';
echo '.pass{color:#2d8a4e;font-weight:600}.fail{color:#c0392b;font-weight:600}.warn{color:#b45309;font-weight:600}';
echo 'a.btn{display:inline-block;margin:8px 8px 8px 0;padding:8px 14px;background:#444;color:#fff;text-decoration:none;border-radius:4px}';
echo 'a.btn-danger{background:#b71c1c}a.btn:hover{opacity:.9}code{background:#eee;padding:2px 6px;border-radius:3px}</style></head><body>';

echo '<h1><code>ratedresults</code> indexes (Phase A)</h1>';
echo '<p>Adds <code>idx_ratedresults_idA</code> and <code>idx_ratedresults_idB</code> for faster profile/chart loads.</p>';
echo '<p class="warn"><strong>Check <code>DATABASE()</code> below</strong> before apply — staging vs production.</p>';

if ($apply) {
    echo '<p><strong>Mode:</strong> <span class="warn">APPLY — CREATE INDEX will run</span></p>';
} else {
    echo '<p><strong>Mode:</strong> preview only (no index creation yet)</p>';
}

$self = htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? 'throwaway_ratedresults_player_indexes.php', ENT_QUOTES, 'UTF-8');
$previewUrl = $self . '?once=' . rawurlencode($key);
$applyUrl = $self . '?once=' . rawurlencode($key) . '&apply=1';
echo '<p><a class="btn" href="' . $previewUrl . '">Preview again</a>';
if (!$apply) {
    echo '<a class="btn btn-danger" href="' . $applyUrl . '">Create indexes</a>';
}
echo '</p><hr>';

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    echo '<p class="fail">Connect failed: ' . htmlspecialchars($con->connect_error, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '</body></html>';
    exit;
}

$con->set_charset('utf8mb4');

echo '<h2>Connection</h2><pre>';
$ctx = mysqli_query($con, 'SELECT DATABASE() AS db, CURRENT_USER() AS db_user, VERSION() AS version');
echo $ctx ? htmlspecialchars(print_r(mysqli_fetch_assoc($ctx), true), ENT_QUOTES, 'UTF-8') : htmlspecialchars(mysqli_error($con), ENT_QUOTES, 'UTF-8');
echo '</pre>';

$countRes = mysqli_query($con, 'SELECT COUNT(*) AS c FROM `' . mysqli_real_escape_string($con, $table) . '`');
if ($countRes && ($countRow = mysqli_fetch_assoc($countRes))) {
    echo '<p>Rows in <code>' . htmlspecialchars($table, ENT_QUOTES, 'UTF-8') . '</code>: <strong>'
        . number_format((int) $countRow['c']) . '</strong></p>';
}

echo '<h2>Index status</h2><ul>';
$toCreate = [];
foreach ($indexes as $indexName => $column) {
    $indexEsc = mysqli_real_escape_string($con, $indexName);
    $tableEsc = mysqli_real_escape_string($con, $table);
    $sql = "SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.STATISTICS "
        . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$tableEsc}' AND INDEX_NAME = '{$indexEsc}'";
    $res = mysqli_query($con, $sql);
    $exists = false;
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $exists = (int) $row['n'] > 0;
    }
    if ($exists) {
        echo '<li class="pass"><code>' . htmlspecialchars($indexName, ENT_QUOTES, 'UTF-8') . '</code> on '
            . htmlspecialchars($column, ENT_QUOTES, 'UTF-8') . ' — already present</li>';
    } else {
        $toCreate[$indexName] = $column;
        echo '<li class="warn"><code>' . htmlspecialchars($indexName, ENT_QUOTES, 'UTF-8') . '</code> on '
            . htmlspecialchars($column, ENT_QUOTES, 'UTF-8') . ' — missing</li>';
    }
}
echo '</ul>';

if ($apply) {
    echo '<h2>Apply</h2><ul>';
    if (!$toCreate) {
        echo '<li class="pass">Nothing to do — both indexes already exist.</li>';
    } else {
        $tableEsc = '`' . mysqli_real_escape_string($con, $table) . '`';
        foreach ($toCreate as $indexName => $column) {
            $colEsc = '`' . mysqli_real_escape_string($con, $column) . '`';
            $indexSql = 'CREATE INDEX `' . mysqli_real_escape_string($con, $indexName) . "` ON {$tableEsc} ({$colEsc})";
            $t0 = microtime(true);
            $ok = mysqli_query($con, $indexSql);
            $ms = round((microtime(true) - $t0) * 1000, 1);
            if ($ok) {
                echo '<li class="pass">Created <code>' . htmlspecialchars($indexName, ENT_QUOTES, 'UTF-8')
                    . '</code> in ' . $ms . ' ms</li>';
            } else {
                echo '<li class="fail">Failed <code>' . htmlspecialchars($indexName, ENT_QUOTES, 'UTF-8') . '</code>: '
                    . htmlspecialchars(mysqli_error($con), ENT_QUOTES, 'UTF-8') . '</li>';
            }
        }
    }
    echo '</ul>';
    echo '<p><a class="btn" href="' . $previewUrl . '">Re-check status</a></p>';
}

echo '<hr><p><strong>Delete this PHP file from the server when finished.</strong></p>';
echo '</body></html>';

mysqli_close($con);
