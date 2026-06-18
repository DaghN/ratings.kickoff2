<?php
/**
 * Rated games for one player in one UTC week (profile played-weeks tooltip).
 * GET: id (player), week=Y-m-d (Monday UTC)
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$playerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$week = isset($_GET['week']) ? trim((string) $_GET['week']) : '';

if ($playerId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_id']);
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $week)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_week']);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_player_display_names.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_rated_game_row.php';

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'db_connect_failed']);
    exit;
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

try {
    $start = new DateTimeImmutable($week . ' 00:00:00', new DateTimeZone('UTC'));
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_week']);
    mysqli_close($con);
    exit;
}
$end = $start->modify('+7 days');
$startSql = $start->format('Y-m-d H:i:s');
$endSql = $end->format('Y-m-d H:i:s');

$stmt = $con->prepare(
    'SELECT `id`, `idA`, `idB`, `NameA`, `NameB`, `GoalsA`, `GoalsB`, '
    . '`RatingA`, `RatingB`, `NewRatingA`, `AdjustmentA`, `Date` '
    . 'FROM `ratedresults` '
    . 'WHERE `Date` >= ? AND `Date` < ? AND (`idA` = ? OR `idB` = ?) '
    . 'ORDER BY `Date` DESC, `id` DESC'
);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'prepare_failed']);
    mysqli_close($con);
    exit;
}
$stmt->bind_param('ssii', $startSql, $endSql, $playerId, $playerId);
$stmt->execute();
$result = $stmt->get_result();
$raw = [];
while ($row = $result->fetch_assoc()) {
    $raw[] = $row;
}
$stmt->close();
$nameMap = k2_player_display_names_for_rated_rows($con, $raw);
$games = [];
foreach (k2_rated_games_apply_display_names($raw, $nameMap) as $row) {
    $processed = k2_rated_game_is_processed($row);
    $games[] = [
        'id' => (int) $row['id'],
        'id_a' => (int) $row['idA'],
        'id_b' => (int) $row['idB'],
        'name_a' => (string) $row['NameA'],
        'name_b' => (string) $row['NameB'],
        'goals_a' => (int) $row['GoalsA'],
        'goals_b' => (int) $row['GoalsB'],
        'rating_a' => $processed && $row['RatingA'] !== null ? (int) round((float) $row['RatingA']) : null,
        'rating_b' => $processed && $row['RatingB'] !== null ? (int) round((float) $row['RatingB']) : null,
        'at' => (string) $row['Date'],
    ];
}
mysqli_close($con);

echo json_encode([
    'player_id' => $playerId,
    'week' => $week,
    'games' => $games,
]);
