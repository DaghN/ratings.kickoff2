<?php
declare(strict_types=1);
/**
 * HTTP smoke for strict_types API endpoints (local + optional staging).
 * Usage: php scripts/oneoff/verify_strict_mysqli_port_fix.php [--base=http://ratingskickoff.test]
 */
$base = 'http://ratingskickoff.test';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--base=')) {
        $base = rtrim(substr($arg, 7), '/');
    }
}

$paths = [
    '/api/milestone_unlocks_by_year.php?realm=online&key=welcome_to_the_ladder',
    '/api/milestone_cumulative_unlocks.php?realm=online&key=welcome_to_the_ladder',
    '/api/player_glance.php?id=537',
    '/api/status_room_pulse.php?revision=0',
    '/api/amiga_community_histogram.php?kind=career_games',
    '/api/amiga_player_glance.php?id=1',
    '/api/amiga_community_year_rates.php?kind=games_per_day&as=2020-01-01',
];

echo 'Base: ' . $base . PHP_EOL;
$fail = 0;
foreach ($paths as $path) {
    $url = $base . $path;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false || $err !== '') {
        echo 'FAIL ' . $path . ' curl=' . $err . PHP_EOL;
        $fail++;
        continue;
    }
    $trim = ltrim((string) $body);
    $jsonOk = ($trim !== '' && ($trim[0] === '{' || $trim[0] === '['));
    $ok = ($code >= 200 && $code < 300 && $jsonOk);
    echo ($ok ? 'OK  ' : 'FAIL') . ' HTTP ' . $code . ' json=' . ($jsonOk ? 'yes' : 'no') . ' ' . $path . PHP_EOL;
    if (!$ok) {
        $fail++;
    }
}
exit($fail > 0 ? 1 : 0);