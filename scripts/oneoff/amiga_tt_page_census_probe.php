<?php
declare(strict_types=1);
/**
 * Phase 1 census: curl every TT-wired Amiga page at early/late cutoffs + present.
 * Flag total time > 0.8 s. Usage: php scripts/oneoff/amiga_tt_page_census_probe.php [--base=http://ratingskickoff.test]
 */
$base = 'http://ratingskickoff.test';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--base=')) {
        $base = rtrim(substr($arg, 7), '/');
    }
}

$busyPlayerId = 382;
$countryToken = 'Germany';
$tournamentId = 589;

$cutoffs = [
    'present' => '',
    'month:2002-06' => 'month:2002-06',
    'month:2014-07' => 'month:2014-07',
    'year:2024' => 'year:2024',
];

$paths = [
    // Leaderboards
    '/amiga/leaderboards/rating.php',
    '/amiga/leaderboards/tournament-honours.php',
    '/amiga/leaderboards/calendar-geo.php',
    '/amiga/leaderboards/goals.php',
    '/amiga/leaderboards/double-digits.php',
    '/amiga/leaderboards/victims.php',
    '/amiga/leaderboards/peak-rating.php',
    '/amiga/leaderboards/performance-rating/best.php',
    '/amiga/leaderboards/performance-rating/top.php',
    '/amiga/leaderboards/performance-rating/perfect.php',
    // World Cups hub
    '/amiga/world-cups/chronology.php',
    '/amiga/world-cups/stats/participation.php',
    '/amiga/world-cups/stats/goals.php',
    '/amiga/world-cups/stats/dds.php',
    '/amiga/world-cups/stats/geography.php',
    '/amiga/world-cups/players/honours.php',
    '/amiga/world-cups/players/results.php',
    '/amiga/world-cups/players/goals.php',
    '/amiga/world-cups/players/dds.php',
    '/amiga/world-cups/players/opponents.php',
    '/amiga/world-cups/countries/participation.php',
    '/amiga/world-cups/countries/results.php',
    '/amiga/world-cups/countries/goals.php',
    '/amiga/world-cups/countries/dds.php',
    '/amiga/world-cups/countries/opponents.php',
    // Countries hub
    '/amiga/countries.php',
    '/amiga/country/roster.php?country=' . rawurlencode($countryToken),
    '/amiga/country/rivals/wdl.php?country=' . rawurlencode($countryToken),
    '/amiga/country/rivals/goals.php?country=' . rawurlencode($countryToken),
    '/amiga/country/rivals/dds.php?country=' . rawurlencode($countryToken),
    // Hall of Fame + hub tabs
    '/amiga/hall-of-fame.php',
    '/amiga/tournaments.php',
    '/amiga/games/recent.php',
    '/amiga/games/highlights.php',
    '/amiga/games/all.php',
    // Activity hub (TT-wired per §5.5)
    '/amiga/activity/growth.php',
    '/amiga/activity/people.php',
    '/amiga/activity/geography/hosts.php',
    '/amiga/activity/geography/nations.php',
    '/amiga/activity/world-cups.php',
    '/amiga/activity/texture.php',
    '/amiga/activity/shape.php',
    // Player wings (busy player)
    '/amiga/player/profile.php?id=' . $busyPlayerId,
    '/amiga/player/games.php?id=' . $busyPlayerId,
    '/amiga/player/tournaments.php?id=' . $busyPlayerId,
    '/amiga/player/videos.php?id=' . $busyPlayerId,
    '/amiga/player/opponents/wdl.php?id=' . $busyPlayerId,
    '/amiga/player/opponents/goals.php?id=' . $busyPlayerId,
    '/amiga/player/opponents/dds.php?id=' . $busyPlayerId,
    '/amiga/player/opponents/h2h.php?id=' . $busyPlayerId,
    // Tournament detail (TT ribbon)
    '/amiga/tournament/event-stats.php?id=' . $tournamentId,
    '/amiga/tournament/standings.php?id=' . $tournamentId,
    '/amiga/tournament/games.php?id=' . $tournamentId,
    // Histogram API (activity charts at cutoff)
    '/api/amiga_community_histogram.php?kind=career_games',
    '/api/amiga_community_histogram.php?kind=rating',
];

$flagThreshold = 0.8;
$results = [];
$flags = [];

foreach ($paths as $path) {
    foreach ($cutoffs as $label => $as) {
        $url = $base . $path;
        if ($as !== '') {
            $url .= (str_contains($path, '?') ? '&' : '?') . 'as=' . rawurlencode($as);
        }

        $t0 = microtime(true);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/json'],
        ]);
        $body = curl_exec($ch);
        $info = curl_getinfo($ch);
        $err = curl_error($ch);
        curl_close($ch);
        $elapsed = round(microtime(true) - $t0, 3);

        $status = (int) ($info['http_code'] ?? 0);
        $trCount = is_string($body) ? substr_count($body, '<tr') : 0;
        $hasError = is_string($body) && (
            preg_match('/\b(Warning|Fatal error|Deprecated):/i', $body) === 1
        );

        $row = [
            'path' => $path,
            'cutoff' => $label,
            'status' => $status,
            'seconds' => $elapsed,
            'tr' => $trCount,
            'error' => $hasError ? 'PHP' : ($err !== '' ? $err : ''),
        ];
        $results[] = $row;

        if ($elapsed > $flagThreshold || $status !== 200 || $hasError) {
            $flags[] = $row;
        }
    }
}

echo "Amiga TT page census — base={$base} — " . count($paths) . ' paths x ' . count($cutoffs) . " cutoffs\n";
echo str_repeat('=', 100) . "\n";
printf("%-55s %-12s %7s %5s %6s\n", 'PATH', 'CUTOFF', 'SEC', 'HTTP', 'TR');
echo str_repeat('-', 100) . "\n";

foreach ($results as $r) {
    $slow = $r['seconds'] > $flagThreshold ? ' *SLOW*' : '';
    $bad = $r['status'] !== 200 || $r['error'] !== '' ? ' !BAD!' : '';
    printf(
        "%-55s %-12s %7.3f %5d %6d%s%s\n",
        $r['path'],
        $r['cutoff'],
        $r['seconds'],
        $r['status'],
        $r['tr'],
        $slow,
        $bad
    );
    if ($r['error'] !== '') {
        echo "    err: {$r['error']}\n";
    }
}

echo str_repeat('=', 100) . "\n";
echo 'Flagged (> ' . $flagThreshold . ' s or non-200 or PHP error): ' . count($flags) . "\n";
usort($flags, static fn($a, $b) => $b['seconds'] <=> $a['seconds']);
foreach ($flags as $f) {
    printf("  %.3fs  [%s]  %s\n", $f['seconds'], $f['cutoff'], $f['path']);
}