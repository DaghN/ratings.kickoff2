<?php
/**
 * JSON career Elo rank after each global finalize for one player.
 *
 * GET: id (required), realm=amiga, optional as= (time travel)
 *
 * @see docs/amiga-player-rank-chart-policy.md
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$realm = isset($_GET['realm']) ? strtolower(trim((string) $_GET['realm'])) : 'online';
$playerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($playerId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_id']);
    exit;
}

if ($realm !== 'amiga') {
    echo json_encode([
        'realm' => $realm,
        'playerId' => $playerId,
        'playerName' => null,
        'points' => [],
        'meta' => ['note' => 'realm_not_implemented'],
    ]);
    exit;
}

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2amiga_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_snapshot_context.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_rank_history_lib.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'db_connect_failed']);
    exit;
}

$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

$ctx = amiga_snapshot_context_from_request($con);
$payload = amiga_player_rank_history_payload($con, $playerId, $ctx);
mysqli_close($con);

if ($payload === null) {
    echo json_encode([
        'realm' => $realm,
        'playerId' => $playerId,
        'playerName' => null,
        'points' => [],
        'meta' => ['note' => 'player_not_found'],
    ]);
    exit;
}

$response = [
    'realm' => $realm,
    'playerId' => $payload['playerId'],
    'playerName' => $payload['playerName'],
    'currentRank' => $payload['currentRank'],
    'points' => $payload['points'],
    'meta' => $payload['meta'],
];

if ($payload['timelineStart'] !== null) {
    $response['timelineStart'] = $payload['timelineStart'];
}

echo json_encode($response);