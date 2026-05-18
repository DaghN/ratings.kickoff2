<?php
/**
 * JSON player lookup for autocomplete (realm-ready).
 *
 * GET: q (required, min 2 chars), realm (default online), limit (default 15, max 30)
 * Online realm: playertable rows with Display = 1; matches Name substring (LIKE), case-insensitive per collation.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$realm = isset($_GET['realm']) ? strtolower(trim((string) $_GET['realm'])) : 'online';
$qRaw = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 15;
if ($limit < 1) {
    $limit = 15;
}
if ($limit > 30) {
    $limit = 30;
}

if ($realm !== 'online') {
    echo json_encode([
        'realm' => $realm,
        'players' => [],
        'meta' => ['note' => 'realm_not_implemented'],
    ]);
    exit;
}

if (strlen($qRaw) < 2) {
    echo json_encode([
        'realm' => $realm,
        'players' => [],
        'meta' => ['minChars' => 2],
    ]);
    exit;
}

/**
 * Escape LIKE wildcards and backslash for use with ESCAPE '\\'.
 */
function ko2_escape_like($s)
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
}

$pattern = '%' . ko2_escape_like($qRaw) . '%';

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'db_connect_failed']);
    exit;
}

$con->set_charset('utf8mb4');

$sql = 'SELECT ID, Name, ROUND(Rating) AS ratingRounded FROM playertable '
    . 'WHERE Display = 1 AND Name IS NOT NULL AND Name <> \'\' '
    . 'AND Name LIKE ? ESCAPE \'\\\\\' '
    . 'ORDER BY Name ASC LIMIT ?';

$stmt = $con->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'prepare_failed']);
    mysqli_close($con);
    exit;
}

$stmt->bind_param('si', $pattern, $limit);
$stmt->execute();
$res = $stmt->get_result();

$players = [];
while ($row = $res->fetch_assoc()) {
    $players[] = [
        'id' => (int) $row['ID'],
        'name' => $row['Name'],
        'rating' => (int) $row['ratingRounded'],
    ];
}

$stmt->close();
mysqli_close($con);

echo json_encode([
    'realm' => $realm,
    'players' => $players,
]);
