<?php
/**
 * JSON histogram buckets for the Amiga Activity Shape wing.
 *
 * GET: kind (whitelist), as (time travel cutoff).
 *
 * @see docs/amiga-activity-charts-implementation-plan.md §3
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_community_stats_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_community_histogram_lib.php';

$kind = isset($_GET['kind']) ? strtolower(trim((string) $_GET['kind'])) : '';
if ($kind === '' || !in_array($kind, AMIGA_COMMUNITY_HISTOGRAM_KINDS, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_kind']);
    exit;
}

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
    $ctx = amiga_snapshot_context_from_request($con);
    $cutoffTid = amiga_community_cutoff_tournament_id_for_read($con, $ctx);
    $meta = amiga_community_histogram_kind_meta($kind);

    $cutoffInfo = null;
    if ($ctx->isActive()) {
        $cutoffEntry = $ctx->cutoff();
        if ($cutoffEntry !== null) {
            $cutoffInfo = [
                'label' => (string) $cutoffEntry['label'],
                'event_date' => (string) $cutoffEntry['event_date'],
                'partial_year' => (int) substr((string) $cutoffEntry['event_date'], 0, 4),
            ];
        }
    }

    if ($cutoffTid === null) {
        echo json_encode([
            'kind' => $kind,
            'title' => $meta['title'],
            'population' => 0,
            'population_label' => $meta['population_label'],
            'buckets' => [],
            'cutoff' => $cutoffInfo,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $payload = amiga_community_histogram_compute($con, $kind, $ctx);
    $payload['title'] = $meta['title'];
    $payload['cutoff'] = $cutoffInfo;

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'histogram_failed']);
} finally {
    $con->close();
}