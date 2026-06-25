<?php
/**
 * H2H opponent search — global name match with games vs context player.
 *
 * GET: player_id (required), q (min 2 chars), limit (default 15, max 30)
 * JSON: { played: [{id, name, games_vs}], others: [{id, name, games_vs}] }
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$playerId = isset($_GET['player_id']) ? (int) $_GET['player_id'] : 0;
$qRaw = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 15;
if ($limit < 1) {
    $limit = 15;
}
if ($limit > 30) {
    $limit = 30;
}

if ($playerId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'player_id_required']);
    exit;
}

if (strlen($qRaw) < 2) {
    echo json_encode([
        'played' => [],
        'others' => [],
        'meta' => ['minChars' => 2],
    ]);
    exit;
}

$realm = isset($_GET['realm']) ? strtolower(trim((string) $_GET['realm'])) : 'online';

function k2_h2h_search_escape_like(string $s): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
}

function k2_h2h_search_sort_results(array &$played, array &$others, int $limit): void
{
    usort(
        $played,
        static function (array $a, array $b): int {
            $byGames = $b['games_vs'] <=> $a['games_vs'];
            if ($byGames !== 0) {
                return $byGames;
            }

            return strcasecmp($a['name'], $b['name']);
        }
    );

    usort(
        $others,
        static function (array $a, array $b): int {
            return strcasecmp($a['name'], $b['name']);
        }
    );

    $played = array_slice($played, 0, $limit);
    $others = array_slice($others, 0, max(0, $limit - count($played)));
}

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
$pattern = '%' . k2_h2h_search_escape_like($qRaw) . '%';
$fetchLimit = min(60, max($limit * 3, $limit));

if ($realm === 'amiga') {
    include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2amiga_config.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_snapshot_context.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_opponents_h2h.php';

    $con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
    if ($con->connect_errno) {
        http_response_code(500);
        echo json_encode(['error' => 'db']);
        exit;
    }

    $con->set_charset('utf8mb4');
    $con->query("SET time_zone = '+00:00'");

    $ctx = amiga_snapshot_context_from_request($con);
    $gamesByOpponent = [];
    foreach (amiga_player_opponents_h2h_played_opponents($con, $playerId, $ctx) as $row) {
        $gamesByOpponent[(int) $row['opponent_id']] = (int) $row['games'];
    }

    $sql = 'SELECT p.id AS id, p.name AS name FROM amiga_players p '
        . 'WHERE p.display = 1 AND p.name IS NOT NULL AND p.name <> \'\' '
        . 'AND p.id <> ? AND LOWER(p.name) LIKE LOWER(?) ESCAPE \'\\\\\' '
        . 'ORDER BY p.name ASC LIMIT ?';
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        $con->close();
        http_response_code(500);
        echo json_encode(['error' => 'query']);
        exit;
    }
    $stmt->bind_param('isi', $playerId, $pattern, $fetchLimit);
    if (!$stmt->execute()) {
        $stmt->close();
        $con->close();
        http_response_code(500);
        echo json_encode(['error' => 'query']);
        exit;
    }

    $res = $stmt->get_result();
    $played = [];
    $others = [];
    while ($row = $res->fetch_assoc()) {
        $oid = (int) $row['id'];
        $gamesVs = $gamesByOpponent[$oid] ?? 0;
        $item = [
            'id' => $oid,
            'name' => (string) $row['name'],
            'games_vs' => $gamesVs,
        ];
        if ($gamesVs > 0) {
            $played[] = $item;
        } else {
            $others[] = $item;
        }
    }
    $stmt->close();
    $con->close();

    k2_h2h_search_sort_results($played, $others, $limit);
    echo json_encode([
        'played' => $played,
        'others' => $others,
    ]);
    exit;
}

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'db']);
    exit;
}

$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/status_queries.php';

$pattern = '%' . k2_h2h_search_escape_like($qRaw) . '%';
$fetchLimit = min(60, max($limit * 3, $limit));

$hasSummary = k2_status_table_exists($con, 'player_matchup_summary');

if ($hasSummary) {
    $sql = 'SELECT p.ID AS id, p.Name AS name, COALESCE(m.games, 0) AS games_vs '
        . 'FROM playertable p '
        . 'LEFT JOIN player_matchup_summary m ON m.player_id = ? AND m.opponent_id = p.ID '
        . 'WHERE p.Display = 1 AND p.Name IS NOT NULL AND p.Name <> \'\' '
        . 'AND p.ID <> ? AND LOWER(p.Name) LIKE LOWER(?) ESCAPE \'\\\\\' '
        . 'ORDER BY p.Name ASC LIMIT ?';
} else {
    $sql = 'SELECT p.ID AS id, p.Name AS name, 0 AS games_vs '
        . 'FROM playertable p '
        . 'WHERE p.Display = 1 AND p.Name IS NOT NULL AND p.Name <> \'\' '
        . 'AND p.ID <> ? AND LOWER(p.Name) LIKE LOWER(?) ESCAPE \'\\\\\' '
        . 'ORDER BY p.Name ASC LIMIT ?';
}

$stmt = $con->prepare($sql);
if (!$stmt) {
    $con->close();
    http_response_code(500);
    echo json_encode(['error' => 'query']);
    exit;
}

if ($hasSummary) {
    $stmt->bind_param('iisi', $playerId, $playerId, $pattern, $fetchLimit);
} else {
    $stmt->bind_param('isi', $playerId, $pattern, $fetchLimit);
}

if (!$stmt->execute()) {
    $stmt->close();
    $con->close();
    http_response_code(500);
    echo json_encode(['error' => 'query']);
    exit;
}

$res = $stmt->get_result();
$played = [];
$others = [];

while ($row = $res->fetch_assoc()) {
    $item = [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'games_vs' => (int) $row['games_vs'],
    ];
    if ($item['games_vs'] > 0) {
        $played[] = $item;
    } else {
        if (!$hasSummary) {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_opponents_h2h.php';
            $item['games_vs'] = player_opponents_h2h_pair_games_live($con, $playerId, $item['id']);
            if ($item['games_vs'] > 0) {
                $played[] = $item;
                continue;
            }
        }
        $others[] = $item;
    }
}

$stmt->close();
$con->close();

k2_h2h_search_sort_results($played, $others, $limit);

echo json_encode([
    'played' => $played,
    'others' => $others,
]);
