<?php
/**
 * ONE-TIME / THROWAWAY — introspect MySQL `generalstatstable` (server-wide stats row).
 *
 * Usage (dev / staging only):
 * 1. Copy this file into the server's web root (e.g. public_html/).
 * 2. Open in browser:
 *      …/throwaway_generalstatstable_schema.php?once=generalstatstable-schema-one-shot
 * 3. Click inside the grey box → Ctrl+A → Ctrl+C (one copy).
 * 4. In docs/generalstatstable-schema.md, select from COPY START through EOF → paste once.
 * 5. Delete this file from the server.
 *
 * Do not leave this script on a public URL after use.
 */
header('Content-Type: text/html; charset=utf-8');

$key = 'generalstatstable-schema-one-shot';
if (!isset($_GET['once']) || $_GET['once'] !== $key) {
    header('HTTP/1.1 404 Not Found');
    echo 'Not found.';
    exit;
}

$lines = [];
$lines[] = '========== COPY START (generalstatstable schema) ==========';
$lines[] = 'Generated: ' . gmdate('Y-m-d H:i:s') . ' UTC';
$lines[] = 'Table: generalstatstable (server-wide aggregates; prod uses id=1)';
$lines[] = '';

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    $lines[] = 'ERROR: connect failed: ' . $con->connect_error;
    $lines[] = '========== COPY END (generalstatstable schema) ==========';
    echo_page($lines);
    exit;
}

$con->set_charset('utf8mb4');

/**
 * @param mysqli $con
 * @param string[] $lines
 */
function append_section($con, &$lines, $title, $sql) {
    $lines[] = '--- ' . $title . ' ---';
    $result = mysqli_query($con, $sql);
    if ($result === false) {
        $lines[] = 'QUERY ERROR: ' . mysqli_error($con);
        $lines[] = 'SQL: ' . $sql;
        $lines[] = '';
        return;
    }
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_free_result($result);
    $lines[] = print_r($rows, true);
    $lines[] = '';
}

$dbRow = mysqli_fetch_assoc(mysqli_query($con, 'SELECT DATABASE() AS db'));
$lines[] = '--- Connection (no secrets) ---';
$lines[] = print_r($dbRow, true);
$lines[] = '';

append_section($con, $lines, 'Row count', 'SELECT COUNT(*) AS total_rows FROM generalstatstable');

append_section(
    $con,
    $lines,
    'Rows by id',
    'SELECT id, COUNT(*) AS n FROM generalstatstable GROUP BY id ORDER BY id'
);

append_section(
    $con,
    $lines,
    'Headline counters on id=1 (if present)',
    'SELECT id, NumberOfPlayers, GamesPlayed, NumberOfDecidedGames, NumberOfDraws, GoalsScored '
    . 'FROM generalstatstable WHERE id = 1'
);

append_section($con, $lines, 'SHOW FULL COLUMNS FROM generalstatstable', 'SHOW FULL COLUMNS FROM generalstatstable');

append_section($con, $lines, 'SHOW CREATE TABLE generalstatstable', 'SHOW CREATE TABLE generalstatstable');

append_section($con, $lines, 'SHOW INDEX FROM generalstatstable', 'SHOW INDEX FROM generalstatstable');

mysqli_close($con);

$lines[] = '========== COPY END (generalstatstable schema) ==========';

echo_page($lines);

/**
 * @param string[] $lines
 */
function echo_page($lines) {
    $text = implode("\n", $lines);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>generalstatstable schema</title>';
    echo '<style>body{font-family:system-ui,sans-serif;max-width:56rem;margin:1rem;line-height:1.45}';
    echo 'pre{background:#1a1a1a;color:#e8e8e8;padding:1rem;overflow:auto;font-size:12px;line-height:1.35}</style>';
    echo '</head><body>';
    echo '<h1>generalstatstable — schema dump (throwaway)</h1>';
    echo '<p><strong>One copy:</strong> click inside the grey box below, then <kbd>Ctrl+A</kbd> → <kbd>Ctrl+C</kbd>.</p>';
    echo '<p><strong>One paste:</strong> open <code>docs/generalstatstable-schema.md</code>, select from ';
    echo '<code>========== COPY START</code> through end of file, paste (replace placeholder block).</p>';
    echo '<p>When finished, <strong>delete this PHP file from the server</strong>.</p>';
    echo '<pre>';
    echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    echo '</pre>';
    echo '</body></html>';
}
