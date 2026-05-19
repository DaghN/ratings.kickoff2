<?php
/**
 * ONE-TIME / THROWAWAY — verify MySQL write access (KOOL Rating).
 *
 * Creates scratch table `_kool_dev_write_probe`, runs INSERT/UPDATE/DELETE, then drops it.
 * Does not touch playertable, ratedresults, or other ladder data.
 *
 * Usage (staging / dev DB only):
 * 1. Confirm with Steve which database staging config points at.
 * 2. Copy this file into the server's web root (e.g. public_html/).
 * 3. Open in browser:
 *      …/throwaway_db_write_probe.php?once=db-write-probe-one-shot
 * 4. Copy the summary (PASS/FAIL lines) into chat for Steve.
 * 5. Delete this file from the server immediately after.
 *
 * Do not leave this script on a public URL after use.
 */
header('Content-Type: text/html; charset=utf-8');

$key = 'db-write-probe-one-shot';
if (!isset($_GET['once']) || $_GET['once'] !== $key) {
    header('HTTP/1.1 404 Not Found');
    echo 'Not found.';
    exit;
}

$table = '_kool_dev_write_probe';

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>DB write probe</title>';
echo '<style>body{font-family:system-ui,sans-serif;max-width:52rem;margin:1rem;line-height:1.45}';
echo 'pre{background:#1a1a1a;color:#e8e8e8;padding:.75rem;overflow:auto}';
echo '.pass{color:#2d8a4e;font-weight:600}.fail{color:#c0392b;font-weight:600}</style></head><body>';
echo '<h1>Database write probe (throwaway)</h1>';
echo '<p>Scratch table: <code>' . htmlspecialchars($table, ENT_QUOTES, 'UTF-8') . '</code></p>';
echo '<p><strong>Delete this PHP file from the server when finished.</strong></p><hr>';

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    echo '<p class="fail">FAIL — connect: ' . htmlspecialchars($con->connect_error, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '</body></html>';
    exit;
}

$con->set_charset('utf8mb4');

$steps = [];

/**
 * @param mysqli $con
 * @param string $label
 * @param string $sql
 * @return bool
 */
function probe_step($con, $label, $sql) {
    global $steps;
    $ok = mysqli_query($con, $sql);
    $err = $ok ? '' : mysqli_error($con);
    $steps[] = ['label' => $label, 'ok' => (bool) $ok, 'error' => $err, 'sql' => $sql];
    return (bool) $ok;
}

echo '<h2>Connection context</h2><pre>';
$ctx = mysqli_query($con, 'SELECT DATABASE() AS db, CURRENT_USER() AS db_user, VERSION() AS version');
if ($ctx) {
    echo htmlspecialchars(print_r(mysqli_fetch_assoc($ctx), true), ENT_QUOTES, 'UTF-8');
    mysqli_free_result($ctx);
} else {
    echo htmlspecialchars(mysqli_error($con), ENT_QUOTES, 'UTF-8');
}
echo '</pre>';

// Clean up from a previous aborted run.
probe_step($con, 'DROP IF EXISTS (cleanup)', 'DROP TABLE IF EXISTS `' . $table . '`');

probe_step(
    $con,
    'CREATE TABLE',
    'CREATE TABLE `' . $table . '` (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        note VARCHAR(64) NOT NULL,
        touched_at DATETIME NOT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$insertOk = probe_step(
    $con,
    'INSERT',
    "INSERT INTO `" . $table . "` (note, touched_at) VALUES ('write-probe', NOW())"
);

$insertId = $insertOk ? (int) mysqli_insert_id($con) : 0;

if ($insertId > 0) {
    probe_step(
        $con,
        'UPDATE',
        "UPDATE `" . $table . "` SET note = 'write-probe-updated' WHERE id = " . $insertId
    );
    probe_step(
        $con,
        'SELECT (verify row)',
        "SELECT id, note, touched_at FROM `" . $table . "` WHERE id = " . $insertId . " LIMIT 1"
    );
    probe_step(
        $con,
        'DELETE',
        "DELETE FROM `" . $table . "` WHERE id = " . $insertId
    );
} else {
    $steps[] = [
        'label' => 'UPDATE / SELECT / DELETE',
        'ok' => false,
        'error' => 'Skipped — INSERT did not return an id.',
        'sql' => '(skipped)',
    ];
}

probe_step($con, 'DROP TABLE (teardown)', 'DROP TABLE IF EXISTS `' . $table . '`');

echo '<h2>Step results</h2><ul>';
$allOk = true;
foreach ($steps as $step) {
    $class = $step['ok'] ? 'pass' : 'fail';
    $status = $step['ok'] ? 'PASS' : 'FAIL';
    if (!$step['ok']) {
        $allOk = false;
    }
    echo '<li><span class="' . $class . '">' . $status . '</span> — '
        . htmlspecialchars($step['label'], ENT_QUOTES, 'UTF-8');
    if (!$step['ok'] && $step['error'] !== '') {
        echo '<br><small>' . htmlspecialchars($step['error'], ENT_QUOTES, 'UTF-8') . '</small>';
    }
    echo '</li>';
}
echo '</ul>';

echo '<h2>Summary</h2>';
if ($allOk) {
    echo '<p class="pass">All steps passed — this DB user can CREATE, INSERT, UPDATE, DELETE, and DROP on the connected database.</p>';
} else {
    echo '<p class="fail">One or more steps failed — write access may be partial or denied. Share this page with Steve (no passwords).</p>';
}

echo '<h2>SQL log</h2><pre>';
foreach ($steps as $step) {
    echo htmlspecialchars($step['label'], ENT_QUOTES, 'UTF-8') . ': '
        . ($step['ok'] ? 'OK' : 'FAIL') . "\n"
        . $step['sql'] . "\n";
    if (!$step['ok'] && $step['error'] !== '') {
        echo 'Error: ' . $step['error'] . "\n";
    }
    echo "\n";
}
echo '</pre>';

mysqli_close($con);

echo '<hr><p><strong>Done.</strong> Remove <code>throwaway_db_write_probe.php</code> from the server now.</p>';
echo '</body></html>';
