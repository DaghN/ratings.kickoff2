<?php
/**
 * JSON unlock counts per calendar year for one milestone (full ladder year span).
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
if ($firstRated === null) {
    echo json_encode([
        'realm' => $realm,
        'milestone_key' => $key,
        'display_name' => $def['display_name'],
        'chart_token' => $def['chart_token'],
        'first_rated_date' => null,
        'first_year' => null,
        'total_unlocks' => 0,
        'years' => [],
    ]);
    mysqli_close($con);
    exit;
}

$firstYear = (int) gmdate('Y', strtotime($firstRated . ' UTC'));
$currentYear = (int) gmdate('Y');

$sql = "
    SELECT YEAR(`achieved_at`) AS yr, COUNT(*) AS unlocks
    FROM `player_milestones`
    WHERE `milestone_key` = '$keyEsc'
    GROUP BY yr
";
$countsByYear = [];
$res = mysqli_query($con, $sql);
if ($res !== false) {
    while ($row = mysqli_fetch_assoc($res)) {
        if ($row['yr'] === null) {
            continue;
        }
        $countsByYear[(int) $row['yr']] = (int) $row['unlocks'];
    }
    mysqli_free_result($res);
}

$totalUnlocks = array_sum($countsByYear);

$years = [];
for ($yr = $firstYear; $yr <= $currentYear; $yr++) {
    $years[] = [
        'year' => $yr,
        'unlocks' => $countsByYear[$yr] ?? 0,
    ];
}

mysqli_close($con);

echo json_encode([
    'realm' => $realm,
    'milestone_key' => $key,
    'display_name' => $def['display_name'],
    'chart_token' => $def['chart_token'],
    'first_rated_date' => $firstRated,
    'first_year' => $firstYear,
    'total_unlocks' => $totalUnlocks,
    'years' => $years,
]);
