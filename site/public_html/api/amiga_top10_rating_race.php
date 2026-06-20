<?php
/**
 * JSON payload for Amiga top-10 Elo line race (News tab animation).
 *
 * GET: top (optional, default 10, max 20)
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_rating_history_lib.php';
include __DIR__ . '/../../config/ko2amiga_config.php';

$topN = isset($_GET['top']) ? (int) $_GET['top'] : 10;

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'db_connect_failed']);
    exit;
}

$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

try {
    $payload = amiga_rating_history_top10_race_payload($con, $topN);
} catch (Throwable $e) {
    mysqli_close($con);
    http_response_code(500);
    echo json_encode(['error' => 'payload_failed']);
    exit;
}

mysqli_close($con);

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
