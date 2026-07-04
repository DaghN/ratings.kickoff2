<?php
declare(strict_types=1);
/**
 * Complete Amiga realm census — all canonical pages + Amiga APIs, ranked by worst curl time.
 * Usage: php scripts/oneoff/amiga_realm_full_census_probe.php [--base=http://ratingskickoff.test] [--out=path.md]
 */
$base = 'http://ratingskickoff.test';
$outPath = '';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--base=')) {
        $base = rtrim(substr($arg, 7), '/');
    } elseif (str_starts_with($arg, '--out=')) {
        $outPath = substr($arg, 6);
    }
}

$busyPlayer = 382;
$smallPlayer = 1;
$topOpponent = 398;
$country = 'Germany';
$rivalCountry = 'Italy';
$oppCountry = 'Italy';
$heroCountry = 'England';
$tournamentOrdinary = 589;
$tournamentWc = 603;
$gameId = 27418;

$cutoffs = [
    'present' => '',
    'month:2002-06' => 'month:2002-06',
    'month:2014-07' => 'month:2014-07',
    'year:2024' => 'year:2024',
];

/** @var list<array{path: string, group: string, tt: bool}> */
$entries = [];

$add = static function (string $path, string $group, bool $tt = true) use (&$entries): void {
    $entries[] = ['path' => $path, 'group' => $group, 'tt' => $tt];
};

// Leaderboards
foreach ([
    'rating', 'tournament-honours', 'calendar-geo', 'goals', 'double-digits', 'victims', 'peak-rating',
] as $wing) {
    $add('/amiga/leaderboards/' . $wing . '.php', 'leaderboards');
}
foreach (['best', 'top', 'perfect'] as $wing) {
    $add('/amiga/leaderboards/performance-rating/' . $wing . '.php', 'leaderboards');
}

// World Cups hub
$add('/amiga/world-cups/chronology.php', 'world-cups');
foreach (['participation', 'goals', 'dds', 'geography', 'podium'] as $wing) {
    $add('/amiga/world-cups/stats/' . $wing . '.php', 'world-cups');
}
foreach (['honours', 'results', 'goals', 'dds', 'opponents'] as $wing) {
    $add('/amiga/world-cups/players/' . $wing . '.php', 'world-cups');
}
foreach (['honours', 'participation', 'results', 'goals', 'dds', 'opponents'] as $wing) {
    $add('/amiga/world-cups/countries/' . $wing . '.php', 'world-cups');
}

// Countries entity
$add('/amiga/countries.php', 'countries');
$add('/amiga/country/roster.php?country=' . rawurlencode($country), 'countries');
$add('/amiga/country/rivals/h2h.php?country=' . rawurlencode($heroCountry) . '&rival=' . rawurlencode($rivalCountry) . '&pick=games', 'countries');
foreach (['wdl', 'goals', 'dds'] as $wing) {
    $add('/amiga/country/rivals/' . $wing . '.php?country=' . rawurlencode($country), 'countries');
}

// Hub pages
$add('/amiga/hall-of-fame.php', 'hub');
$add('/amiga/tournaments.php', 'hub');
$add('/amiga/news.php', 'hub-present-only', false);
$add('/amiga/live-tournaments.php', 'hub-present-only', false);

// Games hub
foreach (['recent', 'highlights', 'all'] as $wing) {
    $add('/amiga/games/' . $wing . '.php', 'games');
}

// Activity shells
foreach ([
    'growth', 'people', 'geography/hosts', 'geography/nations', 'world-cups', 'texture', 'shape',
] as $wing) {
    $add('/amiga/activity/' . $wing . '.php', 'activity');
}

// Player — busy
$add('/amiga/player/profile.php?id=' . $busyPlayer, 'player-busy');
$add('/amiga/player/games.php?id=' . $busyPlayer, 'player-busy');
$add('/amiga/player/tournaments.php?id=' . $busyPlayer, 'player-busy');
$add('/amiga/player/videos.php?id=' . $busyPlayer, 'player-busy');
foreach (['h2h', 'wdl', 'goals', 'dds'] as $wing) {
    $add('/amiga/player/opponents/' . $wing . '.php?id=' . $busyPlayer, 'player-vs-player');
}
$add('/amiga/player/opponents/h2h.php?id=' . $busyPlayer . '&opponent=' . $topOpponent, 'player-vs-player');
foreach (['h2h', 'wdl', 'goals', 'dds'] as $wing) {
    $add('/amiga/player/opponents/country/' . $wing . '.php?id=' . $busyPlayer . '&country=' . rawurlencode($oppCountry), 'player-vs-country');
}

// Player — small roster
$add('/amiga/player/profile.php?id=' . $smallPlayer, 'player-small');
$add('/amiga/player/games.php?id=' . $smallPlayer, 'player-small');

// Tournament detail
foreach (['event-stats', 'standings', 'games', 'stages'] as $tab) {
    $add('/amiga/tournament/' . $tab . '.php?id=' . $tournamentOrdinary, 'tournament');
}
$add('/amiga/tournament/stages.php?id=' . $tournamentWc, 'tournament-wc');
$add('/amiga/tournament/videos/games.php?id=' . $tournamentOrdinary, 'tournament');
$add('/amiga/tournament/videos/atmosphere.php?id=' . $tournamentOrdinary, 'tournament');

// Game entity
$add('/amiga/game.php?id=' . $gameId, 'game');

// Admin / legacy
$add('/amiga/videos/orphans.php', 'admin', false);

// Amiga APIs — Activity
foreach (['career_games', 'rating'] as $kind) {
    $add('/api/amiga_community_histogram.php?kind=' . rawurlencode($kind), 'api-activity');
}
$add('/api/amiga_community_year_facts.php?slice=realm&metric=games', 'api-activity');
$add('/api/amiga_community_year_facts.php?slice=world_cup&metric=active_players', 'api-activity');
$add('/api/amiga_community_year_rates.php?rate=goals_per_game', 'api-activity');
$add('/api/amiga_community_year_rates.php?rate=draw_rate', 'api-activity');
$add('/api/amiga_community_snapshot_series.php?metric=NumberOfPlayers', 'api-activity');
$add('/api/amiga_community_slice_series.php?slice=player_nationality&metric=games&keys=England,Germany', 'api-activity');

// Amiga APIs — H2H (three grains)
$h2hApis = [
    'player_head_to_head.php',
    'player_h2h_scoreline_heatmap.php',
    'player_goals_scored_distribution.php',
    'player_h2h_total_goals_distribution.php',
];
$pvpQ = 'realm=amiga&id=' . $busyPlayer . '&opponent=' . $topOpponent;
$pvcQ = 'realm=amiga&id=' . $busyPlayer . '&opp_country=' . rawurlencode($oppCountry);
$cvcQ = 'realm=amiga&country=' . rawurlencode($heroCountry) . '&rival=' . rawurlencode($rivalCountry);
foreach ($h2hApis as $api) {
    $add('/api/' . $api . '?' . $pvpQ, 'api-h2h-pvp');
    $add('/api/' . $api . '?' . $pvcQ, 'api-h2h-pvc');
    $add('/api/' . $api . '?' . $cvcQ, 'api-h2h-cvc');
}
$add('/api/player_rating_history.php?realm=amiga&id=' . $busyPlayer, 'api-player');
$add('/api/player_rank_history.php?realm=amiga&id=' . $busyPlayer, 'api-player');
$add('/api/amiga_player_glance.php?id=' . $busyPlayer, 'api-player');
$add('/api/amiga_player_games_perf_rating.php?id=' . $busyPlayer, 'api-player');
$add('/api/player_h2h_opponent_search.php?player_id=' . $busyPlayer . '&q=jo', 'api-player');

$flagThreshold = 0.8;
$heavyThreshold = 0.70;
$priorPath = $outPath !== '' ? $outPath : __DIR__ . '/amiga_realm_full_census_results.md';

function census_feel_tier(float $worst): string
{
    if ($worst <= 0.25) {
        return 'Instant';
    }
    if ($worst <= 0.40) {
        return 'Smooth';
    }
    if ($worst <= 0.70) {
        return 'Noticeable';
    }

    return 'Heavy';
}

/** @return array<string, float> path => prior worst seconds */
function census_parse_prior_worst(string $path): array
{
    if (!is_readable($path)) {
        return [];
    }
    $text = file_get_contents($path);
    if (!is_string($text)) {
        return [];
    }
    $prior = [];
    if (preg_match_all(
        '/\|\s*\d+\s*\|\s*[^|]+\|\s*\*\*([\d.]+)\*\*[^|]*\|\s*[^|]+\|\s*[^|]+\|\s*[^|]+\|\s*[^|]+\|\s*[^|]+\|\s*`([^`]+)`\s*\|/',
        $text,
        $matches,
        PREG_SET_ORDER
    )) {
        foreach ($matches as $m) {
            $prior[$m[2]] = (float) $m[1];
        }
    }

    return $prior;
}

function census_fetch(string $base, string $path, string $as): array
{
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
    $hasError = is_string($body) && preg_match('/\b(Warning|Fatal error|Deprecated):/i', $body) === 1;

    return [
        'seconds' => $elapsed,
        'status' => $status,
        'error' => $hasError ? 'PHP' : ($err !== '' ? $err : ''),
        'bytes' => is_string($body) ? strlen($body) : 0,
    ];
}

/** @var array<string, array<string, array<string, mixed>>> */
$byPath = [];

foreach ($entries as $entry) {
    $path = $entry['path'];
    $runCutoffs = $entry['tt'] ? $cutoffs : ['present' => ''];
    $byPath[$path] = ['group' => $entry['group'], 'tt' => $entry['tt'], 'times' => []];
    foreach ($runCutoffs as $label => $as) {
        $byPath[$path]['times'][$label] = census_fetch($base, $path, $as);
    }
}

/** @var list<array<string, mixed>> */
$ranked = [];
foreach ($byPath as $path => $meta) {
    $times = $meta['times'];
    $vals = array_map(static fn(array $r): float => (float) $r['seconds'], $times);
    $worst = max($vals);
    $worstLabel = array_search($worst, $vals, true);
    $ranked[] = [
        'path' => $path,
        'group' => $meta['group'],
        'tt' => $meta['tt'],
        'present' => $times['present']['seconds'] ?? null,
        'early' => $times['month:2002-06']['seconds'] ?? null,
        'mid' => $times['month:2014-07']['seconds'] ?? null,
        'late' => $times['year:2024']['seconds'] ?? null,
        'worst' => $worst,
        'worst_cutoff' => is_string($worstLabel) ? $worstLabel : 'present',
        'status' => $times[$worstLabel]['status'] ?? 0,
        'flag' => $worst > $flagThreshold,
        'error' => $times[$worstLabel]['error'] ?? '',
    ];
}

usort($ranked, static fn(array $a, array $b): int => $b['worst'] <=> $a['worst'] ?: strcmp($a['path'], $b['path']));

foreach ($ranked as &$row) {
    $row['feel'] = census_feel_tier((float) $row['worst']);
}
unset($row);

$priorWorst = census_parse_prior_worst($priorPath);
$priorTimestamp = '';
if (is_readable($priorPath) && preg_match('/^# Amiga realm full census — (.+)$/m', file_get_contents($priorPath) ?: '', $tm)) {
    $priorTimestamp = trim($tm[1]);
}

$flagCount = count(array_filter($ranked, static fn(array $r): bool => $r['flag'] || $r['status'] !== 200 || $r['error'] !== ''));
$countOver80 = count(array_filter($ranked, static fn(array $r): bool => $r['worst'] > $flagThreshold));
$countOver70 = count(array_filter($ranked, static fn(array $r): bool => $r['worst'] > $heavyThreshold));
$tierCounts = ['Instant' => 0, 'Smooth' => 0, 'Noticeable' => 0, 'Heavy' => 0];
foreach ($ranked as $row) {
    $tierCounts[$row['feel']]++;
}

$lines = [];
$lines[] = '# Amiga realm full census — ' . date('Y-m-d H:i:s');
$lines[] = '';
$lines[] = 'Base: `' . $base . '` · Flag threshold: **' . $flagThreshold . ' s** · Entries: **' . count($ranked) . '** · Flagged: **' . $flagCount . '**';
$lines[] = '';
$lines[] = 'Fixtures: busy player **' . $busyPlayer . '**, opponent **' . $topOpponent . '**, country **' . $country . '**, rival **' . $rivalCountry . '**, tournament **' . $tournamentOrdinary . '**, WC **' . $tournamentWc . '**, game **' . $gameId . '**.';
$lines[] = '';
$lines[] = '## Summary';
$lines[] = '';
$lines[] = '| Metric | Count |';
$lines[] = '|--------|------:|';
$lines[] = '| Total paths | ' . count($ranked) . ' |';
$lines[] = '| > ' . $flagThreshold . ' s | ' . $countOver80 . ' |';
$lines[] = '| > ' . $heavyThreshold . ' s | ' . $countOver70 . ' |';
$lines[] = '| Feel: Instant (≤0.25 s) | ' . $tierCounts['Instant'] . ' |';
$lines[] = '| Feel: Smooth (≤0.40 s) | ' . $tierCounts['Smooth'] . ' |';
$lines[] = '| Feel: Noticeable (0.40–0.70 s) | ' . $tierCounts['Noticeable'] . ' |';
$lines[] = '| Feel: Heavy (>0.70 s) | ' . $tierCounts['Heavy'] . ' |';
$lines[] = '';
$lines[] = 'Feel tiers: **Instant** ≤0.25 s · **Smooth** ≤0.40 s · **Noticeable** 0.40–0.70 s · **Heavy** >0.70 s (worst cutoff per path).';
$lines[] = '';
$lines[] = '## All paths (worst-first)';
$lines[] = '';
$lines[] = '| Rank | Group | Feel | Worst (s) | @ cutoff | Present | Early | Mid | Late | Path |';
$lines[] = '|------|-------|------|-----------|----------|---------|-------|-----|------|------|';

$rank = 0;
foreach ($ranked as $row) {
    $rank++;
    $fmt = static fn(?float $v): string => $v === null ? '—' : sprintf('%.3f', $v);
    $flag = $row['flag'] ? ' ⚠' : '';
    $bad = ($row['status'] !== 200 || $row['error'] !== '') ? ' ❌' : '';
    $lines[] = sprintf(
        '| %d | %s | %s | **%.3f**%s%s | %s | %s | %s | %s | %s | `%s` |',
        $rank,
        $row['group'],
        $row['feel'],
        $row['worst'],
        $flag,
        $bad,
        $row['worst_cutoff'],
        $fmt($row['present']),
        $fmt($row['early']),
        $fmt($row['mid']),
        $fmt($row['late']),
        $row['path']
    );
}

if ($priorWorst !== []) {
    $improved = [];
    $regressed = [];
    $newPaths = [];
    foreach ($ranked as $row) {
        $path = $row['path'];
        if (!isset($priorWorst[$path])) {
            $newPaths[] = $row;
            continue;
        }
        $delta = round($row['worst'] - $priorWorst[$path], 3);
        if ($delta <= -0.05) {
            $improved[] = ['path' => $path, 'prior' => $priorWorst[$path], 'now' => $row['worst'], 'delta' => $delta];
        } elseif ($delta >= 0.05) {
            $regressed[] = ['path' => $path, 'prior' => $priorWorst[$path], 'now' => $row['worst'], 'delta' => $delta];
        }
    }
    usort($improved, static fn(array $a, array $b): int => $a['delta'] <=> $b['delta']);
    usort($regressed, static fn(array $a, array $b): int => $b['delta'] <=> $a['delta']);

    $lines[] = '';
    $lines[] = '## Delta vs prior census';
    $lines[] = '';
    $lines[] = 'Prior run: **' . ($priorTimestamp !== '' ? $priorTimestamp : basename($priorPath)) . '** · threshold ±0.05 s for improved/regressed lists.';
    $lines[] = '';
    $lines[] = '### Improved (≥0.05 s faster)';
    $lines[] = '';
    if ($improved === []) {
        $lines[] = '_None._';
    } else {
        foreach ($improved as $item) {
            $lines[] = sprintf('- **%.3f → %.3f s** (Δ %.3f) `%s`', $item['prior'], $item['now'], $item['delta'], $item['path']);
        }
    }
    $lines[] = '';
    $lines[] = '### Regressed (≥0.05 s slower)';
    $lines[] = '';
    if ($regressed === []) {
        $lines[] = '_None._';
    } else {
        foreach ($regressed as $item) {
            $lines[] = sprintf('- **%.3f → %.3f s** (Δ +%.3f) `%s`', $item['prior'], $item['now'], $item['delta'], $item['path']);
        }
    }
    if ($newPaths !== []) {
        $lines[] = '';
        $lines[] = '### New paths (not in prior census)';
        $lines[] = '';
        foreach ($newPaths as $row) {
            $lines[] = sprintf('- **%.3f s** `%s`', $row['worst'], $row['path']);
        }
    }
}

$lines[] = '';
$lines[] = '## Flagged (> ' . $flagThreshold . ' s or HTTP ≠ 200 or PHP error)';
$lines[] = '';
foreach ($ranked as $row) {
    if (!$row['flag'] && $row['status'] === 200 && $row['error'] === '') {
        continue;
    }
    $lines[] = sprintf('- **%.3f s** [%s] `%s`%s', $row['worst'], $row['worst_cutoff'], $row['path'], $row['error'] !== '' ? ' — ' . $row['error'] : '');
}

$report = implode("\n", $lines) . "\n";

echo $report;

if ($outPath === '') {
    $outPath = __DIR__ . '/amiga_realm_full_census_results.md';
}
file_put_contents($outPath, $report);
echo "\nWrote: {$outPath}\n";