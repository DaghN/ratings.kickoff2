<?php
/**
 * JSON player lookup for autocomplete (realm-ready).
 *
 * GET: q (required, min 2 chars), realm (online | amiga | all; default online), limit (default 15, max 30),
 *      as (Amiga time travel: only players rated by that date, with their rating then)
 * online / amiga: single-database substring match on Name (LIKE), case-insensitive per collation.
 * all: both databases merged, sorted by name, capped at limit.
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

$allowedRealms = ['online', 'amiga', 'all'];
if (!in_array($realm, $allowedRealms, true)) {
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

/**
 * @return list<array{id: int, name: string, rating: int, realm: string}>
 */
function ko2_player_search_rows(mysqli $con, string $pattern, int $limit, string $realmId): array
{
    $types = 'si';
    $params = [$pattern, $limit];
    if ($realmId === 'amiga') {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_snapshot_lib.php';
        $where = 'p.name IS NOT NULL AND p.name <> \'\' AND LOWER(p.name) LIKE LOWER(?) ESCAPE \'\\\\\'';
        $ctx = amiga_snapshot_context_from_request($con);
        $cutoff = $ctx->isActive() ? $ctx->cutoff() : null;
        if ($cutoff !== null) {
            $fromSql = amiga_lb_snapshot_from_sql('s');
            $types = 'sdisi';
            $params = [$cutoff['event_date'], $cutoff['chrono'], $cutoff['tournament_id'], $pattern, $limit];
        } else {
            $fromSql = 'FROM amiga_players p LEFT JOIN `' . amiga_player_career_table($con) . '` s ON s.player_id = p.id';
        }
        $sql = 'SELECT p.id AS ID, p.name AS Name, ROUND(COALESCE(s.Rating, 1600)) AS ratingRounded '
            . $fromSql . ' WHERE ' . $where . ' ORDER BY p.name ASC LIMIT ?';
    } else {
        $where = 'Name IS NOT NULL AND Name <> \'\' AND LOWER(Name) LIKE LOWER(?) ESCAPE \'\\\\\'';
        $sql = 'SELECT ID, Name, ROUND(COALESCE(Rating, 1600)) AS ratingRounded FROM playertable WHERE '
            . $where . ' ORDER BY Name ASC LIMIT ?';
    }

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $players = [];
    while ($row = $res->fetch_assoc()) {
        $players[] = [
            'id' => (int) $row['ID'],
            'name' => $row['Name'],
            'rating' => (int) $row['ratingRounded'],
            'realm' => $realmId,
        ];
    }

    $stmt->close();

    return $players;
}

/**
 * @return mysqli|null
 */
function ko2_player_search_connect_online()
{
    include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

    $con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
    if ($con->connect_errno) {
        return null;
    }

    $con->set_charset('utf8mb4');
    $con->query("SET time_zone = '+00:00'");

    return $con;
}

/**
 * @return mysqli|null
 */
function ko2_player_search_connect_amiga()
{
    $configRouter = __DIR__ . '/../../config/ko2amiga_config.php';
    if (!is_file($configRouter)) {
        return null;
    }

    include $configRouter;

    $con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
    if ($con->connect_errno) {
        return null;
    }

    $con->set_charset('utf8mb4');
    $con->query("SET time_zone = '+00:00'");

    return $con;
}

/**
 * @param list<array{id: int, name: string, rating: int, realm: string}> $players
 * @return list<array{id: int, name: string, rating: int, realm: string}>
 */
function ko2_player_search_merge_sort(array $players, int $limit): array
{
    usort($players, static function (array $a, array $b): int {
        return strcasecmp($a['name'], $b['name']);
    });

    if (count($players) <= $limit) {
        return $players;
    }

    return array_slice($players, 0, $limit);
}

$pattern = '%' . ko2_escape_like($qRaw) . '%';
$players = [];

if ($realm === 'online' || $realm === 'all') {
    $con = ko2_player_search_connect_online();
    if ($con === null) {
        http_response_code(500);
        echo json_encode(['error' => 'db_connect_failed']);
        exit;
    }

    $fetchLimit = $realm === 'all' ? $limit : $limit;
    $players = array_merge(
        $players,
        ko2_player_search_rows($con, $pattern, $fetchLimit, 'online')
    );
    mysqli_close($con);
}

if ($realm === 'amiga' || $realm === 'all') {
    $con = ko2_player_search_connect_amiga();
    if ($con !== null) {
        $fetchLimit = $realm === 'all' ? $limit : $limit;
        $players = array_merge(
            $players,
            ko2_player_search_rows($con, $pattern, $fetchLimit, 'amiga')
        );
        mysqli_close($con);
    }
}

if ($realm === 'all') {
    $players = ko2_player_search_merge_sort($players, $limit);
}

echo json_encode([
    'realm' => $realm,
    'players' => $players,
]);
