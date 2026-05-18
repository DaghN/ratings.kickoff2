<?php
/**
 * ONE-TIME / THROWAWAY — introspect MySQL `playertable` (KOOL Rating).
 *
 * Usage (staging only recommended):
 * 1. Copy this file into the server's web root next to index.php (e.g. public_html/).
 * 2. Open in browser:
 *      …/throwaway_playertable_schema.php?once=playertable-schema-one-shot
 * 3. Copy all output below the headings into chat / docs.
 * 4. Delete this file from the server (and remove from public_html if you copied it there).
 *
 * Do not leave this script deployed on a public URL after use.
 */
header('Content-Type: text/html; charset=utf-8');

$key = 'playertable-schema-one-shot';
if (!isset($_GET['once']) || $_GET['once'] !== $key) {
    header('HTTP/1.1 404 Not Found');
    echo 'Not found.';
    exit;
}

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>playertable schema</title></head><body>';
echo '<h1>playertable — schema dump (throwaway)</h1>';
echo '<p><strong>Copy everything inside the &lt;pre&gt; blocks,</strong> then delete this script from the server.</p>';
echo '<p>Unlock parameter name: <code>once</code> · value was matched.</p><hr>';

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    echo '<pre>' . htmlspecialchars($con->connect_error, ENT_QUOTES, 'UTF-8') . '</pre></body></html>';
    exit;
}

$con->set_charset('utf8mb4');

/**
 * @param mysqli $con
 */
function dump_section($con, $title, $sql) {
    echo '<h2>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>';
    echo '<pre>';
    $result = mysqli_query($con, $sql);
    if (!$result) {
        echo htmlspecialchars(mysqli_error($con), ENT_QUOTES, 'UTF-8');
        echo '</pre>';
        return;
    }
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_free_result($result);
    echo htmlspecialchars(print_r($rows, true), ENT_QUOTES, 'UTF-8');
    echo '</pre>';
}

dump_section($con, 'Row counts', 'SELECT COUNT(*) AS total_rows FROM playertable');

dump_section(
    $con,
    'display flag breakdown',
    'SELECT display, COUNT(*) AS n FROM playertable GROUP BY display ORDER BY display'
);

dump_section(
    $con,
    'PlayerRank sentinel breakdown (top values)',
    'SELECT PlayerRank, COUNT(*) AS n FROM playertable GROUP BY PlayerRank ORDER BY n DESC LIMIT 30'
);

dump_section($con, 'SHOW FULL COLUMNS FROM playertable', 'SHOW FULL COLUMNS FROM playertable');

dump_section($con, 'SHOW CREATE TABLE playertable', 'SHOW CREATE TABLE playertable');

mysqli_close($con);

echo '<hr><p><strong>Done.</strong> Remove this PHP file from the server now.</p>';
echo '</body></html>';
