<?php
/**
 * JSON player glance card for Amiga name hover (tiers A/B).
 *
 * GET: id (required); as= for time travel cutoff (optional).
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

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_glance_lib.php';

include __DIR__ . '/../../config/ko2amiga_config.php';

$con = new mysqli($dbhost, $username, $password, $database, (int) $dbportnum);
if ($con->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'db_connect_failed']);
    exit;
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

try {
    $payload = amiga_player_glance_payload($con, $playerId);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_id']);
} catch (RuntimeException) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
} finally {
    $con->close();
}