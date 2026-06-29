<?php
/**
 * Amiga Opponents country grain — roll-up from stored pair matchup rows.
 *
 * @see docs/amiga-opponents-country-grain-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_countries_lib.php';
require_once __DIR__ . '/amiga_player_opponents_load.php';
require_once __DIR__ . '/amiga_snapshot_context.php';

function amiga_player_opponents_country_token_from_field(?string $country): string
{
    $raw = trim((string) $country);

    return $raw === '' ? AMIGA_COUNTRIES_UNKNOWN_TOKEN : $raw;
}

/**
 * @return array<string, mixed>
 */
function amiga_player_opponents_country_empty_bucket(string $countryToken): array
{
    return [
        'country_token' => $countryToken,
        'games' => 0,
        'wins' => 0,
        'draws' => 0,
        'losses' => 0,
        'goals_for' => 0,
        'goals_against' => 0,
        'max_goals_for' => 0,
        'max_goals_against' => 0,
        'min_goals_for' => 0,
        'min_goals_against' => 0,
        'max_win_margin' => null,
        'max_loss_margin' => null,
        'max_draw_goals' => null,
        'max_goal_sum' => 0,
        'min_goal_sum' => 0,
        'double_digits' => 0,
        'double_digits_conceded' => 0,
        'clean_sheets' => 0,
        'clean_sheets_conceded' => 0,
        'performance_rating' => null,
        'performance_rating_vs_hero' => null,
    ];
}

/**
 * @param list<array<string, mixed>> $pairRows normalized matchup rows
 * @return list<array<string, mixed>>
 */
function amiga_player_opponents_country_rollup_from_pair_rows(array $pairRows): array
{
    /** @var array<string, array<string, mixed>> $buckets */
    $buckets = [];

    foreach ($pairRows as $row) {
        $token = amiga_player_opponents_country_token_from_field($row['opponent_country'] ?? '');
        if (!isset($buckets[$token])) {
            $buckets[$token] = amiga_player_opponents_country_empty_bucket($token);
        }
        $bucket = &$buckets[$token];

        $bucket['games'] += (int) $row['games'];
        $bucket['wins'] += (int) $row['wins'];
        $bucket['draws'] += (int) $row['draws'];
        $bucket['losses'] += (int) $row['losses'];
        $bucket['goals_for'] += (int) $row['goals_for'];
        $bucket['goals_against'] += (int) $row['goals_against'];
        $bucket['max_goals_for'] = max($bucket['max_goals_for'], (int) $row['max_goals_for']);
        $bucket['max_goals_against'] = max($bucket['max_goals_against'], (int) $row['max_goals_against']);
        $bucket['min_goals_for'] = $bucket['min_goals_for'] === 0
            ? (int) $row['min_goals_for']
            : min($bucket['min_goals_for'], (int) $row['min_goals_for']);
        $bucket['min_goals_against'] = $bucket['min_goals_against'] === 0
            ? (int) $row['min_goals_against']
            : min($bucket['min_goals_against'], (int) $row['min_goals_against']);
        $bucket['max_goal_sum'] = max($bucket['max_goal_sum'], (int) $row['max_goal_sum']);
        $bucket['min_goal_sum'] = $bucket['min_goal_sum'] === 0
            ? (int) $row['min_goal_sum']
            : min($bucket['min_goal_sum'], (int) $row['min_goal_sum']);
        $bucket['double_digits'] += (int) $row['double_digits'];
        $bucket['double_digits_conceded'] += (int) $row['double_digits_conceded'];
        $bucket['clean_sheets'] += (int) $row['clean_sheets'];
        $bucket['clean_sheets_conceded'] += (int) $row['clean_sheets_conceded'];

        foreach (['max_win_margin', 'max_loss_margin', 'max_draw_goals'] as $marginKey) {
            if (!array_key_exists($marginKey, $row) || $row[$marginKey] === null) {
                continue;
            }
            $value = (int) $row[$marginKey];
            if (!array_key_exists($marginKey, $bucket) || $bucket[$marginKey] === null) {
                $bucket[$marginKey] = $value;
            } else {
                $bucket[$marginKey] = max((int) $bucket[$marginKey], $value);
            }
        }
        unset($bucket);
    }

    $rows = array_values($buckets);
    usort(
        $rows,
        static function (array $a, array $b): int {
            $gamesCmp = (int) $b['games'] <=> (int) $a['games'];
            if ($gamesCmp !== 0) {
                return $gamesCmp;
            }

            return strcasecmp((string) $a['country_token'], (string) $b['country_token']);
        }
    );

    return $rows;
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_player_opponents_country_rows(mysqli $con, int $playerId, ?AmigaSnapshotContext $ctx = null): array
{
    if ($playerId < 1) {
        return [];
    }

    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();
    $pairRows = amiga_player_opponents_matchup_rows($con, $playerId, $ctx);
    $rows = amiga_player_opponents_country_rollup_from_pair_rows($pairRows);
    if ($rows === []) {
        return [];
    }

    require_once __DIR__ . '/amiga_player_opponents_country_perf_lib.php';
    $perfByToken = amiga_player_opponents_country_perf_ratings_batch($con, $playerId, $ctx);
    foreach ($rows as $index => $row) {
        $token = (string) $row['country_token'];
        $perf = $perfByToken[$token] ?? null;
        $rows[$index]['performance_rating'] = is_array($perf) ? ($perf['performance_rating'] ?? null) : null;
        $rows[$index]['performance_rating_vs_hero'] = is_array($perf) ? ($perf['performance_rating_vs_hero'] ?? null) : null;
    }

    return $rows;
}

/**
 * @return array<string, mixed>|null
 */
function amiga_player_opponents_country_bucket(
    mysqli $con,
    int $playerId,
    string $countryToken,
    ?AmigaSnapshotContext $ctx = null
): ?array {
    $countryToken = trim($countryToken);
    if ($countryToken === '') {
        return null;
    }

    foreach (amiga_player_opponents_country_rows($con, $playerId, $ctx) as $row) {
        if ((string) $row['country_token'] === $countryToken) {
            return $row;
        }
    }

    return null;
}
