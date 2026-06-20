<?php
declare(strict_types=1);

require __DIR__ . '/../../site/public_html/includes/amiga_rating_history_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    fwrite(STDERR, "connect fail: {$con->connect_error}\n");
    exit(1);
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

$events = amiga_rating_history_catalog_event($con);
$months = amiga_rating_history_catalog_month($con);
$years = amiga_rating_history_catalog_year($con);
echo 'events=' . count($events) . ' months=' . count($months) . ' years=' . count($years) . PHP_EOL;

$view = amiga_rating_history_resolve_view($con, 'event', null);
echo 'ladder_rows=' . count($view['ladder']) . PHP_EOL;
if ($view['ladder'] !== []) {
    $top = $view['ladder'][0];
    echo 'top=' . $top['name'] . ' ' . $top['rating_after'] . ' rank=' . $top['rank'] . PHP_EOL;
}

$firstMonth = $months[0]['key'] ?? '';
$viewM = amiga_rating_history_resolve_view($con, 'month', $firstMonth);
echo 'first_month=' . $firstMonth . ' players=' . count($viewM['ladder']) . PHP_EOL;

// month with no finalize but in range - find one
foreach ($months as $m) {
    if (empty($m['has_finalize_in_period']) && !empty($m['cutoff_tournament_id'])) {
        $viewEmpty = amiga_rating_history_resolve_view($con, 'month', (string) $m['key']);
        echo 'quiet_month=' . $m['key'] . ' players=' . count($viewEmpty['ladder']) . PHP_EOL;
        break;
    }
}

$con->close();
echo "OK\n";

// Race payload smoke (optional argv: race)
if (in_array('race', $argv ?? [], true)) {
    $con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
    $con->set_charset('utf8mb4');
    $payload = amiga_rating_history_top10_race_payload($con);
    echo 'race frames=' . $payload['meta']['frameCount']
        . ' players=' . count($payload['players'])
        . ' json_bytes=' . strlen(json_encode($payload)) . PHP_EOL;
    $con->close();
}
