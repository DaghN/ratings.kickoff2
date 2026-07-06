<?php
/**
 * Shared bootstrap for milestone detail chart JSON APIs.
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';

/**
 * @return array{con: mysqli, key: string, key_esc: string}|null
 */
function k2_milestone_chart_api_open(string $method = 'GET'): ?array
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        http_response_code(405);
        echo json_encode(['error' => 'method_not_allowed']);
        return null;
    }

    header('Content-Type: application/json; charset=utf-8');

    $key = isset($_GET['key']) ? trim((string) $_GET['key']) : '';
    if ($key === '' || !preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key)) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_key']);
        return null;
    }

    $realm = isset($_GET['realm']) ? strtolower(trim((string) $_GET['realm'])) : 'online';
    if ($realm !== 'online') {
        echo json_encode([
            'realm' => $realm,
            'milestone_key' => $key,
            'meta' => ['note' => 'realm_not_implemented'],
        ]);
        return null;
    }

    include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

    $con = new mysqli($dbhost, $username, $password, $database, (int) $dbportnum);
    if ($con->connect_errno) {
        http_response_code(500);
        echo json_encode(['error' => 'db_connect_failed']);
        return null;
    }

    $con->set_charset('utf8mb4');
    $con->query("SET time_zone = '+00:00'");

    return [
        'con' => $con,
        'key' => $key,
        'key_esc' => mysqli_real_escape_string($con, $key),
        'realm' => $realm,
    ];
}

/**
 * @return array{display_name: string, chart_token: string}|null
 */
function k2_milestone_chart_definition(mysqli $con, string $keyEsc): ?array
{
    $res = mysqli_query(
        $con,
        "SELECT `display_name`, `chart_token` FROM `milestone_definitions` WHERE `milestone_key` = '$keyEsc' LIMIT 1"
    );
    if ($res === false) {
        return null;
    }
    $row = mysqli_fetch_assoc($res);
    mysqli_free_result($res);
    if (!$row) {
        return null;
    }

    return [
        'display_name' => str_replace('**', '', (string) $row['display_name']),
        'chart_token' => (string) $row['chart_token'],
    ];
}

function k2_milestone_chart_first_rated_date(mysqli $con): ?string
{
    $res = mysqli_query($con, 'SELECT MIN(`Date`) AS first_game FROM `ratedresults`');
    if ($res === false) {
        return null;
    }
    $row = mysqli_fetch_assoc($res);
    mysqli_free_result($res);
    if (!$row || $row['first_game'] === null || $row['first_game'] === '') {
        return null;
    }

    return (string) $row['first_game'];
}
