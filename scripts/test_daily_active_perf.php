<?php
/**
 * Performance comparison: stored vs raw daily active players API.
 * Calls each source mode multiple times and reports average timings.
 */

$iterations = 5;
$baseUrl = 'http://ratingskickoff.test/api/server_daily_active_players.php?realm=online';

function measureApi($url, $iterations) {
    $timings = [];
    $queryTimings = [];
    $lastMeta = null;

    for ($i = 0; $i < $iterations; $i++) {
        $t0 = microtime(true);
        $response = file_get_contents($url);
        $httpMs = round((microtime(true) - $t0) * 1000, 2);

        $j = json_decode($response, true);
        $timings[] = $httpMs;
        $queryTimings[] = $j['meta']['query_ms'] ?? 0;
        $lastMeta = $j['meta'] ?? [];
    }

    sort($timings);
    sort($queryTimings);

    return [
        'http_avg_ms' => round(array_sum($timings) / count($timings), 2),
        'http_median_ms' => $timings[(int) floor(count($timings) / 2)],
        'http_min_ms' => $timings[0],
        'http_max_ms' => $timings[count($timings) - 1],
        'query_avg_ms' => round(array_sum($queryTimings) / count($queryTimings), 2),
        'query_median_ms' => $queryTimings[(int) floor(count($queryTimings) / 2)],
        'query_min_ms' => $queryTimings[0],
        'query_max_ms' => $queryTimings[count($queryTimings) - 1],
        'days' => $lastMeta['total_days'] ?? 0,
    ];
}

echo "=== Daily Active Players API Performance Comparison ===" . PHP_EOL;
echo "Iterations per source: $iterations" . PHP_EOL;
echo str_repeat('-', 55) . PHP_EOL;

echo PHP_EOL . "Source: STORED (server_daily_activity table)" . PHP_EOL;
$stored = measureApi($baseUrl . '&source=stored', $iterations);
echo "  Days returned:    {$stored['days']}" . PHP_EOL;
echo "  HTTP avg:         {$stored['http_avg_ms']} ms" . PHP_EOL;
echo "  HTTP median:      {$stored['http_median_ms']} ms" . PHP_EOL;
echo "  HTTP min/max:     {$stored['http_min_ms']} / {$stored['http_max_ms']} ms" . PHP_EOL;
echo "  Query avg:        {$stored['query_avg_ms']} ms" . PHP_EOL;
echo "  Query median:     {$stored['query_median_ms']} ms" . PHP_EOL;
echo "  Query min/max:    {$stored['query_min_ms']} / {$stored['query_max_ms']} ms" . PHP_EOL;

echo PHP_EOL . "Source: RAW (ratedresults UNION ALL idA/idB)" . PHP_EOL;
$raw = measureApi($baseUrl . '&source=raw', $iterations);
echo "  Days returned:    {$raw['days']}" . PHP_EOL;
echo "  HTTP avg:         {$raw['http_avg_ms']} ms" . PHP_EOL;
echo "  HTTP median:      {$raw['http_median_ms']} ms" . PHP_EOL;
echo "  HTTP min/max:     {$raw['http_min_ms']} / {$raw['http_max_ms']} ms" . PHP_EOL;
echo "  Query avg:        {$raw['query_avg_ms']} ms" . PHP_EOL;
echo "  Query median:     {$raw['query_median_ms']} ms" . PHP_EOL;
echo "  Query min/max:    {$raw['query_min_ms']} / {$raw['query_max_ms']} ms" . PHP_EOL;

echo PHP_EOL . str_repeat('-', 55) . PHP_EOL;
$speedup = $raw['query_avg_ms'] > 0
    ? round($raw['query_avg_ms'] / max($stored['query_avg_ms'], 0.01), 1)
    : 'N/A';
echo "Speedup (query): {$speedup}x faster with stored path" . PHP_EOL;
$httpSpeedup = $raw['http_avg_ms'] > 0
    ? round($raw['http_avg_ms'] / max($stored['http_avg_ms'], 0.01), 1)
    : 'N/A';
echo "Speedup (HTTP):  {$httpSpeedup}x faster with stored path" . PHP_EOL;
