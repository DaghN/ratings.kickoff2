<?php
declare(strict_types=1);

require __DIR__ . '/../../site/public_html/includes/amiga_rating_history_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_countries_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

function ms(float $start): float
{
    return round((microtime(true) - $start) * 1000, 1);
}

function bench(string $label, callable $fn): mixed
{
    $t0 = microtime(true);
    $result = $fn();
    echo $label . ': ' . ms($t0) . " ms\n";
    return $result;
}

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    fwrite(STDERR, "connect fail: {$con->connect_error}\n");
    exit(1);
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

echo "=== Catalog sizes ===\n";
$events = bench('catalog_event', fn () => amiga_rating_history_catalog_event($con));
$years = bench('catalog_year', fn () => amiga_rating_history_catalog_year($con));
$months = bench('catalog_month', fn () => amiga_rating_history_catalog_month($con));
echo 'counts events=' . count($events) . ' years=' . count($years) . ' months=' . count($months) . "\n";

echo "\n=== Month catalog N+1 probe ===\n";
$bounds = amiga_rating_history_date_bounds($con);
echo 'date_bounds min=' . ($bounds['min_date'] ?? '?') . ' max=' . ($bounds['max_date'] ?? '?') . "\n";
$t0 = microtime(true);
$monthQueryCount = 0;
$start = DateTimeImmutable::createFromFormat('!Y-m-d', substr($bounds['min_date'], 0, 7) . '-01');
$end = DateTimeImmutable::createFromFormat('!Y-m-d', substr($bounds['max_date'], 0, 7) . '-01');
for ($cursor = $start; $cursor <= $end; $cursor = $cursor->modify('+1 month')) {
    amiga_rating_history_cutoff_tournament_for_month_end($con, $cursor->format('Y-m'));
    $monthQueryCount++;
}
echo 'month_cutoff_queries=' . $monthQueryCount . ' elapsed=' . ms($t0) . " ms\n";

echo "\n=== Countries index (SQL GROUP BY) at late cutoff ===\n";
$_GET['as'] = 'event:589';
$GLOBALS['_amiga_snapshot_context'] = null;
$ctxLate = amiga_snapshot_context_from_request($con);
bench('countries_query_index_rows', fn () => amiga_countries_query_index_rows($con, $ctxLate));

echo "\n=== Rating LB at same cutoff ===\n";
$_GET['as'] = 'event:589';
$GLOBALS['_amiga_snapshot_context'] = null;
$ctxR = amiga_lb_context($con);
bench('lb_career_rows', function () use ($con, $ctxR) {
    $res = amiga_lb_query_career($con, $ctxR, 'SELECT p.id ', 'ORDER BY s.Rating DESC');
    $n = 0;
    while ($res->fetch_assoc()) { $n++; }
    return $n;
});

echo "\n=== Full TT page cost model (month wing, event:589) ===\n";
$_GET['as'] = 'month:2016-03';
$GLOBALS['_amiga_snapshot_context'] = null;
bench('header_context_month_wing', fn () => amiga_snapshot_context_from_request($con));
$_GET['as'] = 'event:589';
$GLOBALS['_amiga_snapshot_context'] = null;
bench('header_context_event_wing', fn () => amiga_snapshot_context_from_request($con));

$con->close();
echo "OK\n";