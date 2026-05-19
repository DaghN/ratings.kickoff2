<?php
/**
 * ONE-TIME / THROWAWAY — drop unused KungFu* columns from playertable.
 *
 * Dev / sandbox only. Does not touch ratedresults or other tables.
 *
 * Usage:
 * 1. Confirm staging config points at DEV database (not production).
 * 2. Copy to server public_html/.
 * 3. Preview (no changes):
 *      …/throwaway_drop_playertable_kungfu_columns.php?once=kungfu-columns-drop-one-shot
 * 4. Apply drops:
 *      …/throwaway_drop_playertable_kungfu_columns.php?once=kungfu-columns-drop-one-shot&apply=1
 * 5. Delete this file from the server immediately after.
 */
header('Content-Type: text/html; charset=utf-8');

$key = 'kungfu-columns-drop-one-shot';
if (!isset($_GET['once']) || $_GET['once'] !== $key) {
    header('HTTP/1.1 404 Not Found');
    echo 'Not found.';
    exit;
}

$apply = isset($_GET['apply']) && $_GET['apply'] === '1';
$table = 'playertable';

$columns = [
    'KungFuLevel',
    'KungFuWinBank',
    'KungFuLoseBank',
    'KungFuLastGameID',
    'KungFuLastGameDate',
    'KungFuNumberOfGames',
    'KungFuPeakLevel',
    'KungFuPeakLevelDate',
    'KungFuDisplay',
];

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Drop KungFu columns</title>';
echo '<style>body{font-family:system-ui,sans-serif;max-width:56rem;margin:1rem;line-height:1.5}';
echo 'pre{background:#1a1a1a;color:#e8e8e8;padding:.75rem;overflow:auto;font-size:13px}';
echo '.pass{color:#2d8a4e;font-weight:600}.fail{color:#c0392b;font-weight:600}.warn{color:#e6a700;font-weight:600}';
echo 'a.btn{display:inline-block;margin:8px 8px 8px 0;padding:8px 14px;background:#444;color:#fff;text-decoration:none;border-radius:4px}';
echo 'a.btn-danger{background:#b71c1c}a.btn:hover{opacity:.9}</style></head><body>';

echo '<h1>Drop <code>KungFu*</code> columns from <code>playertable</code></h1>';
echo '<p class="warn"><strong>Dev / sandbox only.</strong> Confirm <code>DATABASE()</code> below before applying.</p>';

if ($apply) {
    echo '<p><strong>Mode:</strong> <span class="warn">APPLY — columns will be dropped</span></p>';
} else {
    echo '<p><strong>Mode:</strong> preview only (no <code>ALTER TABLE</code> yet)</p>';
}

$self = htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? 'throwaway_drop_playertable_kungfu_columns.php', ENT_QUOTES, 'UTF-8');
$previewUrl = $self . '?once=' . rawurlencode($key);
$applyUrl = $self . '?once=' . rawurlencode($key) . '&apply=1';
echo '<p><a class="btn" href="' . $previewUrl . '">Preview again</a>';
if (!$apply) {
    echo '<a class="btn btn-danger" href="' . $applyUrl . '">Apply drops (irreversible)</a>';
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
$dbRow = mysqli_fetch_assoc(mysqli_query($con, 'SELECT DATABASE() AS db, CURRENT_USER() AS db_user'));
echo htmlspecialchars(print_r($dbRow, true), ENT_QUOTES, 'UTF-8');
echo '</pre>';

$tableEsc = '`' . mysqli_real_escape_string($con, $table) . '`';

echo '<h2>Column check</h2><ul>';
$existing = [];
$missing = [];
foreach ($columns as $col) {
    $colEsc = mysqli_real_escape_string($con, $col);
    $sql = "SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.COLUMNS "
        . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND COLUMN_NAME = '{$colEsc}'";
    $res = mysqli_query($con, $sql);
    $n = 0;
    if ($res) {
        $row = mysqli_fetch_assoc($res);
        $n = (int) $row['n'];
        mysqli_free_result($res);
    }
    if ($n > 0) {
        $existing[] = $col;
        echo '<li class="pass">' . htmlspecialchars($col, ENT_QUOTES, 'UTF-8') . ' — present</li>';
    } else {
        $missing[] = $col;
        echo '<li>— ' . htmlspecialchars($col, ENT_QUOTES, 'UTF-8') . ' — already absent</li>';
    }
}
echo '</ul>';

if (count($existing) === 0) {
    echo '<p class="pass"><strong>Nothing to do.</strong> All KungFu columns are already gone.</p>';
    mysqli_close($con);
    echo '<hr><p>Remove this PHP file from the server.</p></body></html>';
    exit;
}

if (!$apply) {
    echo '<h2>Planned SQL (not executed)</h2><pre>';
    echo 'ALTER TABLE ' . $tableEsc . "\n";
    $parts = [];
    foreach ($existing as $col) {
        $parts[] = '  DROP COLUMN `' . $col . '`';
    }
    echo implode(",\n", $parts) . ";\n";
    echo '</pre>';
    echo '<p>Use <a class="btn btn-danger" href="' . $applyUrl . '">Apply drops</a> when ready.</p>';
    mysqli_close($con);
    echo '<hr><p>Remove this PHP file from the server after you finish.</p></body></html>';
    exit;
}

echo '<h2>Applying drops</h2><pre>';

$dropped = [];
$errors = [];

foreach ($existing as $col) {
    $sql = 'ALTER TABLE ' . $tableEsc . ' DROP COLUMN `' . $col . '`';
    echo htmlspecialchars($sql, ENT_QUOTES, 'UTF-8') . ";\n";
    if (mysqli_query($con, $sql)) {
        echo "  -> OK\n";
        $dropped[] = $col;
    } else {
        $err = mysqli_error($con);
        echo '  -> FAIL: ' . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . "\n";
        $errors[] = ['column' => $col, 'error' => $err];
    }
}

echo '</pre>';

echo '<h2>Summary</h2><ul>';
echo '<li>Dropped: <strong>' . count($dropped) . '</strong> — '
    . htmlspecialchars(implode(', ', $dropped), ENT_QUOTES, 'UTF-8') . '</li>';
echo '<li>Already absent: <strong>' . count($missing) . '</strong></li>';
echo '<li>Errors: <strong>' . count($errors) . '</strong></li>';
echo '</ul>';

if (count($errors) === 0 && count($dropped) > 0) {
    echo '<p class="pass"><strong>Completed successfully.</strong> Update <code>docs/playertable-schema.md</code> when convenient.</p>';
} elseif (count($errors) > 0) {
    echo '<p class="fail"><strong>Completed with errors.</strong> See log above.</p>';
}

echo '<h2>Verify (remaining KungFu columns)</h2><pre>';
$verifySql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS "
    . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND COLUMN_NAME LIKE 'KungFu%' ORDER BY COLUMN_NAME";
$vres = mysqli_query($con, $verifySql);
$left = [];
if ($vres) {
    while ($vrow = mysqli_fetch_assoc($vres)) {
        $left[] = $vrow['COLUMN_NAME'];
    }
    mysqli_free_result($vres);
}
if (count($left) === 0) {
    echo "(none — all KungFu* columns removed)\n";
} else {
    echo htmlspecialchars(print_r($left, true), ENT_QUOTES, 'UTF-8');
}
echo '</pre>';

mysqli_close($con);

echo '<hr><p><strong>Delete this PHP file from the server now.</strong></p>';
echo '</body></html>';
