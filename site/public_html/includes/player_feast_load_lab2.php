<?php
/**
 * Profile feast data — LAB 2 fork (individual1-profile-lab2.php only).
 *
 * Reuses the production loader (player_feast_load_pm) then augments $pm with the
 * Profile content v1 reads Agent 2 needs for a Chronicle-first layout:
 *   - B06 win streak (already on $pm['winning_streak'])
 *   - B07/B08 play streaks (day + week, current + best)
 *   - C12 victims (already on $pm), P02 best year by wins, P05 distinct days played
 *   - M03 max rated victim, M08 favourite victim, M09 featured rival
 *   - MS01/MS02/MS04 milestone snippets, L01/L02/L04 league snippets
 *
 * Production player/profile.php is untouched. Extras live under $pm['lab2'].
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_feast_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_feast_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_milestones_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/league_standings.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_league_period_page.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_play_streaks.php';

/** M08 favourite victim needs a real rivalry, not a one-off. */
const K2_LAB2_FAVE_VICTIM_MIN_WINS = 3;

/**
 * Build the production $pm then attach the lab-2 extras.
 *
 * @return array<string, mixed>
 */
function player_feast_load_pm_lab2(mysqli $con, int $id): array
{
    $pm = player_feast_load_pm($con, $id);

    $pm['lab2'] = [
        'play_streaks' => pl2_load_play_streaks($con, $id),
        'best_year' => pl2_load_best_year($con, $id),
        'distinct_days' => pl2_load_distinct_days($con, $id),
        'max_rated_victim' => $pm['max_rated_victim'] ?? null,
        'favourite_victim' => pl2_load_favourite_victim($con, $id),
        'featured_rival' => pl2_load_featured_rival($con, $id),
        'milestones' => pl2_load_milestone_snippets($con, $id),
        'league' => pl2_load_league_snippets($con, $id),
    ];

    return $pm;
}

function pl2_table_ok(mysqli $con, string $table): bool
{
    if (function_exists('k2_status_table_exists')) {
        return k2_status_table_exists($con, $table);
    }
    $safe = mysqli_real_escape_string($con, $table);
    $res = @mysqli_query($con, "SHOW TABLES LIKE '$safe'");

    return $res !== false && mysqli_num_rows($res) > 0;
}

/**
 * B07/B08 — day + week play streaks (current run + personal best with date).
 *
 * @return array{day: array{current: int, best: int, best_date: string}, week: array{current: int, best: int, best_date: string}}
 */
function pl2_load_play_streaks(mysqli $con, int $id): array
{
    $out = [
        'day' => ['current' => 0, 'best' => 0, 'best_date' => ''],
        'week' => ['current' => 0, 'best' => 0, 'best_date' => ''],
    ];
    if (!pl2_table_ok($con, 'player_play_streaks')) {
        return $out;
    }
    try {
        $utcToday = k2_play_streak_utc_today($con);
    } catch (Throwable $e) {
        $utcToday = gmdate('Y-m-d');
    }
    foreach (['day', 'week'] as $type) {
        try {
            $row = k2_play_streak_load_row($con, $id, $type);
        } catch (Throwable $e) {
            $row = null;
        }
        if ($row === null) {
            continue;
        }
        $bestDate = (string) ($row['best_achieved_at'] ?? '');
        $out[$type] = [
            'current' => k2_play_streak_effective_current($row, $type, $utcToday),
            'best' => (int) ($row['best_streak'] ?? 0),
            'best_date' => $bestDate !== '' ? date('M Y', strtotime($bestDate) ?: time()) : '',
        ];
    }

    return $out;
}

/**
 * P02 — best calendar year by wins.
 *
 * @return array{year: int, wins: int}|null
 */
function pl2_load_best_year(mysqli $con, int $id): ?array
{
    if (!pl2_table_ok($con, 'player_period_league')) {
        return null;
    }
    $esc = (string) (int) $id;
    $res = @mysqli_query(
        $con,
        "SELECT period_start, wins FROM player_period_league "
        . "WHERE player_id = '$esc' AND period_type = 'year' AND wins > 0 "
        . "ORDER BY wins DESC, period_start ASC LIMIT 1"
    );
    if ($res === false || !($row = mysqli_fetch_assoc($res))) {
        return null;
    }

    return [
        'year' => (int) substr((string) $row['period_start'], 0, 4),
        'wins' => (int) $row['wins'],
    ];
}

/** P05 — lifetime distinct UTC days with at least one rated game. */
function pl2_load_distinct_days(mysqli $con, int $id): int
{
    if (!pl2_table_ok($con, 'player_period_games')) {
        return 0;
    }
    $esc = (string) (int) $id;
    $res = @mysqli_query(
        $con,
        "SELECT COUNT(*) AS c FROM player_period_games "
        . "WHERE player_id = '$esc' AND period_type = 'day'"
    );
    if ($res === false || !($row = mysqli_fetch_assoc($res))) {
        return 0;
    }

    return (int) $row['c'];
}

/**
 * M08 — favourite victim: opponent beaten most often (min wins threshold).
 *
 * @return array{opponent_id: int, opponent_name: string, wins: int, games: int}|null
 */
function pl2_load_favourite_victim(mysqli $con, int $id): ?array
{
    if (!pl2_table_ok($con, 'player_matchup_summary')) {
        return null;
    }
    $esc = (string) (int) $id;
    $res = @mysqli_query(
        $con,
        "SELECT m.opponent_id, p.Name AS opponent_name, m.wins, m.games "
        . "FROM player_matchup_summary m INNER JOIN playertable p ON p.id = m.opponent_id "
        . "WHERE m.player_id = '$esc' AND m.wins >= " . K2_LAB2_FAVE_VICTIM_MIN_WINS . " "
        . "ORDER BY m.wins DESC, m.games ASC, p.Name ASC LIMIT 1"
    );
    if ($res === false || !($row = mysqli_fetch_assoc($res))) {
        return null;
    }

    return [
        'opponent_id' => (int) $row['opponent_id'],
        'opponent_name' => (string) $row['opponent_name'],
        'wins' => (int) $row['wins'],
        'games' => (int) $row['games'],
    ];
}

/**
 * M09 — featured rival: most-played opponent (matches the auto-selected #1 in charts).
 *
 * @return array{opponent_id: int, opponent_name: string, wins: int, draws: int, losses: int, games: int}|null
 */
function pl2_load_featured_rival(mysqli $con, int $id): ?array
{
    if (!pl2_table_ok($con, 'player_matchup_summary')) {
        return null;
    }
    $esc = (string) (int) $id;
    $res = @mysqli_query(
        $con,
        "SELECT m.opponent_id, p.Name AS opponent_name, m.wins, m.draws, m.losses, m.games "
        . "FROM player_matchup_summary m INNER JOIN playertable p ON p.id = m.opponent_id "
        . "WHERE m.player_id = '$esc' "
        . "ORDER BY m.games DESC, p.Name ASC LIMIT 1"
    );
    if ($res === false || !($row = mysqli_fetch_assoc($res))) {
        return null;
    }

    return [
        'opponent_id' => (int) $row['opponent_id'],
        'opponent_name' => (string) $row['opponent_name'],
        'wins' => (int) $row['wins'],
        'draws' => (int) $row['draws'],
        'losses' => (int) $row['losses'],
        'games' => (int) $row['games'],
    ];
}

/**
 * MS01/MS02/MS04 — milestone snippets for the Honours band.
 *
 * @return array{total: int, holo: int, amber: int, last12: int, latest: ?array<string, mixed>}
 */
function pl2_load_milestone_snippets(mysqli $con, int $id): array
{
    $out = ['total' => 0, 'holo' => 0, 'amber' => 0, 'last12' => 0, 'latest' => null];
    if (!k2_milestone_tables_ready($con)) {
        return $out;
    }

    $counts = k2_milestone_player_counts($con, $id);
    if ($counts !== null) {
        $out['total'] = (int) $counts['total'];
        $out['holo'] = (int) $counts['legendary'];
        $out['amber'] = (int) $counts['accomplished'];
    }

    $esc = (string) (int) $id;
    $res = @mysqli_query(
        $con,
        "SELECT COUNT(*) AS c FROM player_milestones "
        . "WHERE player_id = '$esc' AND achieved_at >= (UTC_TIMESTAMP() - INTERVAL 12 MONTH)"
    );
    if ($res !== false && ($row = mysqli_fetch_assoc($res))) {
        $out['last12'] = (int) $row['c'];
    }

    $latestRes = @mysqli_query(
        $con,
        "SELECT pm.milestone_key, pm.achieved_at, pm.source_kind, pm.source_game_id, "
        . "pm.source_league_kind, pm.source_period_type, pm.source_period_start, "
        . "d.display_name, d.tier_band, d.chart_token "
        . "FROM player_milestones pm "
        . "INNER JOIN milestone_definitions d ON d.milestone_key = pm.milestone_key "
        . "WHERE pm.player_id = '$esc' "
        . "ORDER BY pm.achieved_at DESC, pm.milestone_key ASC LIMIT 1"
    );
    if ($latestRes !== false && ($row = mysqli_fetch_assoc($latestRes))) {
        $key = (string) $row['milestone_key'];
        $out['latest'] = [
            'key' => $key,
            'name' => k2_milestone_strip_markdown((string) $row['display_name']),
            'date' => k2_milestone_format_utc((string) $row['achieved_at']),
            'token' => (string) $row['chart_token'],
            'href' => k2_milestone_detail_href($key),
            'source_html' => k2_milestone_source_link_html($row),
        ];
    }

    return $out;
}

/**
 * L01/L02/L04 — league snippets for the Honours band.
 *
 * @return array{has_any: bool, gold: int, silver: int, bronze: int, wins: int, podiums: int, latest: ?array<string, mixed>, top_slice: ?array<string, mixed>}
 */
function pl2_load_league_snippets(mysqli $con, int $id): array
{
    $out = [
        'has_any' => false,
        'gold' => 0,
        'silver' => 0,
        'bronze' => 0,
        'wins' => 0,
        'podiums' => 0,
        'latest' => null,
        'top_slice' => null,
    ];
    if (!pl2_table_ok($con, 'player_league_award')) {
        return $out;
    }
    $esc = (string) (int) $id;

    if (pl2_table_ok($con, 'player_league_totals')) {
        $res = @mysqli_query(
            $con,
            "SELECT wins, podiums, gold, silver, bronze FROM player_league_totals WHERE player_id = '$esc' LIMIT 1"
        );
        if ($res !== false && ($row = mysqli_fetch_assoc($res))) {
            $out['wins'] = (int) $row['wins'];
            $out['podiums'] = (int) $row['podiums'];
            $out['gold'] = (int) $row['gold'];
            $out['silver'] = (int) $row['silver'];
            $out['bronze'] = (int) $row['bronze'];
        }
    }

    $latestRes = @mysqli_query(
        $con,
        "SELECT league_kind, period_type, period_start, period_end, medal, finish_rank "
        . "FROM player_league_award WHERE player_id = '$esc' "
        . "ORDER BY period_end DESC, finish_rank ASC LIMIT 1"
    );
    if ($latestRes !== false && ($row = mysqli_fetch_assoc($latestRes))) {
        $out['has_any'] = true;
        $cup = (string) $row['league_kind'];
        $cupNorm = $cup === 'activity' ? 'activity' : 'points';
        $period = (string) $row['period_type'];
        $start = (string) $row['period_start'];
        $out['latest'] = [
            'medal' => (string) $row['medal'],
            'finish_rank' => (int) $row['finish_rank'],
            'label' => k2_league_period_short_label($cupNorm, $period, $start),
            'href' => k2_league_period_href($cupNorm, $period, $start),
            'date' => date('M Y', strtotime((string) $row['period_end']) ?: time()),
        ];
    }

    if (($out['gold'] + $out['silver'] + $out['bronze']) > 0) {
        $out['has_any'] = true;
    }

    // L03-style strongest slice (shown only as a one-line flavour beside totals).
    $slices = k2_league_player_slice_totals($con, $id);
    $best = null;
    foreach ($slices as $slice) {
        if ($best === null || $slice['gold'] > $best['gold']) {
            $best = $slice;
        }
    }
    if ($best !== null && $best['gold'] > 0) {
        $grain = ucfirst((string) $best['period_type']);
        $kind = $best['league_kind'] === 'activity' ? 'Activity' : 'Points';
        $out['top_slice'] = [
            'gold' => (int) $best['gold'],
            'label' => $grain . ' ' . $kind,
        ];
    }

    return $out;
}
