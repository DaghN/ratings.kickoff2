<?php
/**
 * JSON performance rating for the filtered games list on /amiga/player/games.php.
 *
 * GET: same filter params as games.php (id required; sort/dir ignored).
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$playerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($playerId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_id']);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_games_perf_lib.php';

include __DIR__ . '/../../config/ko2amiga_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'db_connect_failed']);
    exit;
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

try {
    $payload = amiga_player_games_list_performance_rating($con, $playerId, $_GET);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'query_failed']);
} finally {
    $con->close();
}
