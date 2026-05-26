<?php
/**
 * JSON cumulative Double Digit Merchants — one step per player at first 10+ goal game.
 * Ordered by first double-digit date (then game id). Y increases by 1 at each event.
 *
 * GET: realm (default online)
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$realm = isset($_GET['realm']) ? strtolower(trim((string) $_GET['realm'])) : 'online';
$goalsRequired = 10;

if ($realm !== 'online') {
    echo json_encode([
        'realm' => $realm,
        'goals_required' => $goalsRequired,
        'events' => [],
        'meta' => ['note' => 'realm_not_implemented'],
    ]);
    exit;
}

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'db_connect_failed']);
    exit;
}

$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

$sql = "SELECT `achieved_at` AS game_date FROM `player_milestones` "
    . "WHERE `milestone_key` = 'dd_merchant_10' "
    . "ORDER BY `achieved_at` ASC";

$res = mysqli_query($con, $sql);
if ($res === false) {
    http_response_code(500);
    echo json_encode(['error' => 'query_failed']);
    mysqli_close($con);
    exit;
}

$events = [];
$cumulative = 0;
while ($row = mysqli_fetch_assoc($res)) {
    $cumulative++;
    $events[] = [
        'date' => $row['game_date'],
        'cumulative_merchants' => $cumulative,
    ];
}

mysqli_close($con);

echo json_encode([
    'realm' => $realm,
    'goals_required' => $goalsRequired,
    'events' => $events,
    'meta' => [
        'rule' => 'One point per player when they first score ' . $goalsRequired
            . ' or more goals in a rated game.',
    ],
]);
