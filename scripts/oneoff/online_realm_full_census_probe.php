<?php
declare(strict_types=1);
/**
 * Complete Online realm census — all canonical pages + online JSON APIs, ranked by worst curl time.
 *
 * Fixtures (ko2unity_db @ probe run):
 *   busy player 537 (geo4444, 11087 games)
 *   small player 375 (NIKOSZIKOS, 5 games)
 *   top opponent 433 (648 games vs busy)
 *   game 74879, milestone key absurd_day
 *   heavy period year 2021, month 2021-02, day 2026-07-04
 *
 * Usage: php scripts/oneoff/online_realm_full_census_probe.php [--base=http://ratingskickoff.test] [--out=path.md]
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

$busyPlayer = 537;
$smallPlayer = 375;
$topOpponent = 433;
$gameId = 74879;
$milestoneKey = 'absurd_day';
$periodDay = '2026-07-04';
$periodHeavyYear = '2021';
$periodHeavyMonth = '2021-02';

$variants = [
    'present' => '',
    'heavy-period' => 'heavy',
];

function online_feel_tier(float $seconds): string
{
    if ($seconds <= 0.25) {
        return 'Instant';
    }
    if ($seconds <= 0.40) {
        return 'Smooth';
    }
    if ($seconds <= 0.70) {
        return 'Noticeable';
    }
    return 'Heavy';
}

/** @var list<array{path: string, group: string, periodHeavy: bool}> */
$entries = [];

$add = static function (string $path, string $group, bool $periodHeavy = false) use (&$entries): void {
    $entries[] = ['path' => $path, 'group' => $group, 'periodHeavy' => $periodHeavy];
};

// Hub
foreach (['status.php', 'activity.php', 'hall-of-fame.php', 'league.php'] as $hub) {
    $add('/' . $hub, 'hub');
}

// Leaderboards
foreach ([
    'rating', 'goals', 'double-digits', 'streaks', 'victims', 'league-honours', 'milestones', 'peak-rating',
] as $wing) {
    $add('/leaderboards/' . $wing . '.php', 'leaderboards');
}
foreach (['participation', 'peaks', 'in-a-row'] as $wing) {
    $add('/leaderboards/activity/' . $wing . '.php', 'leaderboards-activity');
}

// Games hub
foreach (['recent', 'highlights', 'all'] as $wing) {
    $add('/games/' . $wing . '.php', 'games');
}

// Milestones hub
$add('/milestones/recent.php', 'milestones');
$add('/milestones/catalog.php', 'milestones');

// Player — busy
$add('/player/profile.php?id=' . $busyPlayer, 'player-busy');
$add('/player/games.php?id=' . $busyPlayer, 'player-busy');
foreach (['h2h', 'wdl', 'goals', 'dds'] as $wing) {
    $add('/player/opponents/' . $wing . '.php?id=' . $busyPlayer, 'player-opponents');
}
$add('/player/opponents/h2h.php?id=' . $busyPlayer . '&opponent=' . $topOpponent, 'player-opponents');
$add('/player/milestones/garden.php?id=' . $busyPlayer, 'player-milestones');
$add('/player/milestones/chronology.php?id=' . $busyPlayer, 'player-milestones');

// Player — small
$add('/player/profile.php?id=' . $smallPlayer, 'player-small');
$add('/player/games.php?id=' . $smallPlayer, 'player-small');
$add('/player/opponents/h2h.php?id=' . $smallPlayer, 'player-small');

// Entities
$add('/game.php?id=' . $gameId, 'game');
$add('/milestone.php?key=' . rawurlencode($milestoneKey), 'milestone');

// Online JSON APIs — Activity charts (realm=online)
$activityApis = [
    'server_games_by_day_recent.php?realm=online',
    'server_games_by_month.php?realm=online',
    'server_games_by_year.php?realm=online',
    'server_games_by_day_year.php?realm=online',
    'server_active_players_by_month.php?realm=online',
    'server_daily_active_players.php?realm=online&source=stored',
    'server_matchup_breadth.php?realm=online',
    'server_established_players_by_year.php?realm=online',
    'server_cumulative_established_by_month.php?realm=online',
    'server_established_rating_distribution.php?realm=online&bucket=100&min_games=20',
    'server_top_activity_eras.php?realm=online',
    'server_play_texture.php?realm=online',
];
foreach ($activityApis as $apiPath) {
    $add('/api/' . $apiPath, 'api-activity');
}

// Status period APIs — default day + heavy year as separate path suffixes
$add('/api/status_period_day_games.php?period=day&key=' . $periodDay, 'api-status', true);
$add('/api/status_period_points_league.php?period=day&key=' . $periodDay, 'api-status', true);
$add('/api/server_period_activity_leaderboard.php?period=day&key=' . $periodDay, 'api-status', true);

// Player chart APIs
$playerApis = [
    'player_head_to_head.php?id=' . $busyPlayer . '&opponent=' . $topOpponent,
    'player_rating_history.php?id=' . $busyPlayer,
    'player_rank_history.php?id=' . $busyPlayer,
    'player_games_by_month.php?id=' . $busyPlayer,
    'player_goals_scored_distribution.php?id=' . $busyPlayer . '&opponent=' . $topOpponent,
    'player_h2h_scoreline_heatmap.php?id=' . $busyPlayer . '&opponent=' . $topOpponent,
    'player_h2h_total_goals_distribution.php?id=' . $busyPlayer . '&opponent=' . $topOpponent,
    'player_top_opponents.php?id=' . $busyPlayer,
    'player_h2h_opponent_search.php?player_id=' . $busyPlayer . '&q=jo',
    'player_glance.php?id=' . $busyPlayer,
    'player_compare_rating_history.php?id=' . $busyPlayer . '&compare=' . $topOpponent,
    'player_compare_rank_history.php?id=' . $busyPlayer . '&compare=' . $topOpponent,
    'player_search.php?q=geo',
    'lb_peak_rating_context.php?id=' . $busyPlayer,
    'milestone_unlocks_by_year.php?key=' . rawurlencode($milestoneKey),
    'milestone_cumulative_unlocks.php?key=' . rawurlencode($milestoneKey),
];
foreach ($playerApis as $apiPath) {
    $add('/api/' . $apiPath, 'api-player');
}

$flagThreshold = 0.8;

function census_fetch(string $base, string $path, string $variantKey, bool $periodHeavy): array
{
    global $periodDay, $periodHeavyYear, $periodHeavyMonth;

    $url = $base . $path;
    if ($periodHeavy && $variantKey === 'heavy-period') {
        $heavy = '';
        if (str_contains($path, 'status_period_day_games.php') || str_contains($path, 'status_period_points_league.php') || str_contains($path, 'server_period_activity_leaderboard.php')) {
            $heavy = preg_replace('/period=day&key=[^&]+/', 'period=year&key=' . rawurlencode($periodHeavyYear), $path);
            if ($heavy === $path) {
                $heavy = preg_replace('/\?.*$/', '?period=year&key=' . rawurlencode($periodHeavyYear), $path);
            }
            $url = $base . $heavy;
        }
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
    $trCount = (is_string($body) && str_contains($path, '.php') && !str_contains($path, '/api/'))
        ? preg_match_all('/<tr\b/i', $body)
        : null;

    return [
        'seconds' => $elapsed,
        'status' => $status,
        'error' => $hasError ? 'PHP' : ($err !== '' ? $err : ''),
        'bytes' => is_string($body) ? strlen($body) : 0,
        'tr_count' => $trCount,
        'url' => $url,
    ];
}

/** @var array<string, array<string, mixed>> */
$byPath = [];

foreach ($entries as $entry) {
    $path = $entry['path'];
    $runVariants = $entry['periodHeavy'] ? $variants : ['present' => ''];
    $byPath[$path] = ['group' => $entry['group'], 'periodHeavy' => $entry['periodHeavy'], 'times' => []];
    foreach ($runVariants as $label => $_) {
        $byPath[$path]['times'][$label] = census_fetch($base, $path, $label, $entry['periodHeavy']);
    }
}

/** @var list<array<string, mixed>> */
$ranked = [];
foreach ($byPath as $path => $meta) {
    $times = $meta['times'];
    $vals = array_map(static fn(array $r): float => (float) $r['seconds'], $times);
    $worst = max($vals);
    $worstLabel = array_search($worst, $vals, true);
    $presentSec = $times['present']['seconds'] ?? $worst;
    $ranked[] = [
        'path' => $path,
        'group' => $meta['group'],
        'periodHeavy' => $meta['periodHeavy'],
        'present' => $times['present']['seconds'] ?? null,
        'heavy' => $times['heavy-period']['seconds'] ?? null,
        'worst' => $worst,
        'worst_variant' => is_string($worstLabel) ? $worstLabel : 'present',
        'feel' => online_feel_tier($worst),
        'status' => $times[$worstLabel]['status'] ?? 0,
        'flag' => $worst > $flagThreshold,
        'error' => $times[$worstLabel]['error'] ?? '',
        'tr_count' => $times[$worstLabel]['tr_count'] ?? null,
        'bytes' => $times[$worstLabel]['bytes'] ?? 0,
    ];
}

usort($ranked, static fn(array $a, array $b): int => $b['worst'] <=> $a['worst'] ?: strcmp($a['path'], $b['path']));

$flagCount = count(array_filter($ranked, static fn(array $r): bool => $r['flag']));
$heavyFeelCount = count(array_filter($ranked, static fn(array $r): bool => $r['feel'] === 'Heavy'));
$errorCount = count(array_filter($ranked, static fn(array $r): bool => $r['status'] !== 200 || $r['error'] !== ''));

$tierCounts = ['Instant' => 0, 'Smooth' => 0, 'Noticeable' => 0, 'Heavy' => 0];
foreach ($ranked as $row) {
    $tierCounts[$row['feel']]++;
}

$lines = [];
$lines[] = '# Online realm full census — ' . date('Y-m-d H:i:s');
$lines[] = '';
$lines[] = 'Base: `' . $base . '` · DB: **ko2unity_db** · Flag threshold: **' . $flagThreshold . ' s**';
$lines[] = 'Entries: **' . count($ranked) . '** · Flagged (>0.8 s): **' . $flagCount . '** · Feel Heavy (>0.70 s): **' . $heavyFeelCount . '** · HTTP/PHP errors: **' . $errorCount . '**';
$lines[] = 'Feel tiers: Instant ≤0.25 s · Smooth ≤0.40 s · Noticeable 0.40–0.70 s · Heavy >0.70 s';
$lines[] = 'Tier counts: Instant **' . $tierCounts['Instant'] . '** · Smooth **' . $tierCounts['Smooth'] . '** · Noticeable **' . $tierCounts['Noticeable'] . '** · Heavy **' . $tierCounts['Heavy'] . '**';
$lines[] = '';
$lines[] = 'Fixtures: busy **' . $busyPlayer . '**, small **' . $smallPlayer . '**, opponent **' . $topOpponent . '**, game **' . $gameId . '**, milestone **' . $milestoneKey . '**, day **' . $periodDay . '**, heavy year **' . $periodHeavyYear . '**.';
$lines[] = '';
$lines[] = '| Rank | Group | Feel | Worst (s) | Present | Heavy | `<tr` | Path |';
$lines[] = '|------|-------|------|-----------|---------|-------|------|------|';

$rank = 0;
foreach ($ranked as $row) {
    $rank++;
    $fmt = static fn(?float $v): string => $v === null ? '—' : sprintf('%.3f', $v);
    $flag = $row['flag'] ? ' ⚠' : '';
    $bad = ($row['status'] !== 200 || $row['error'] !== '') ? ' ❌' : '';
    $tr = $row['tr_count'] === null ? '—' : (string) $row['tr_count'];
    $lines[] = sprintf(
        '| %d | %s | %s | **%.3f**%s%s | %s | %s | %s | `%s` |',
        $rank,
        $row['group'],
        $row['feel'],
        $row['worst'],
        $flag,
        $bad,
        $fmt($row['present']),
        $fmt($row['heavy']),
        $tr,
        $row['path']
    );
}

$lines[] = '';
$lines[] = '## Flagged (> ' . $flagThreshold . ' s or HTTP ≠ 200 or PHP error)';
$lines[] = '';
foreach ($ranked as $row) {
    if (!$row['flag'] && $row['status'] === 200 && $row['error'] === '') {
        continue;
    }
    $lines[] = sprintf('- **%.3f s** [%s / %s] `%s`%s', $row['worst'], $row['feel'], $row['worst_variant'], $row['path'], $row['error'] !== '' ? ' — ' . $row['error'] : '');
}

$report = implode("\n", $lines) . "\n";

echo $report;

if ($outPath === '') {
    $outPath = __DIR__ . '/online_realm_full_census_results.md';
}
file_put_contents($outPath, $report);
echo "\nWrote: {$outPath}\n";