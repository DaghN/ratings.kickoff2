<?php
/**
 * JSON cumulative unlocks for one milestone (step at each achieved_at).
 *
 * GET: key (required), realm (default online)
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/milestone_chart_api.inc.php';

$ctx = k2_milestone_chart_api_open();
if ($ctx === null) {
    exit;
}

$con = $ctx['con'];
$key = $ctx['key'];
$keyEsc = $ctx['key_esc'];
$realm = $ctx['realm'];

$def = k2_milestone_chart_definition($con, $keyEsc);
if ($def === null) {
    http_response_code(404);
    echo json_encode(['error' => 'unknown_milestone']);
    mysqli_close($con);
    exit;
}

$firstRated = k2_milestone_chart_first_rated_date($con);

$sql = "
    SELECT `achieved_at`
    FROM `player_milestones`
    WHERE `milestone_key` = '$keyEsc'
    ORDER BY `achieved_at` ASC, `player_id` ASC
";
$events = [];
$cumulative = 0;
$res = mysqli_query($con, $sql);
if ($res !== false) {
    while ($row = mysqli_fetch_assoc($res)) {
        $cumulative++;
        $events[] = [
            'date' => (string) $row['achieved_at'],
            'cumulative_unlocks' => $cumulative,
        ];
    }
    mysqli_free_result($res);
}

mysqli_close($con);

echo json_encode([
    'realm' => $realm,
    'milestone_key' => $key,
    'display_name' => $def['display_name'],
    'chart_token' => $def['chart_token'],
    'first_rated_date' => $firstRated,
    'total_unlocks' => $cumulative,
    'events' => $events,
]);
