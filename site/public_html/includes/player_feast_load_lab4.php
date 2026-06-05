<?php
/**
 * Profile feast data — Agent 4 lab fork.
 *
 * Wraps production player_feast_load_pm() and bolts on the v1 facts the
 * production load does not expose (play streaks, milestone snippets, league
 * medals, matchup rival/victim, best-year ticker, distinct days, max-rated
 * victim). Lab only — production player/profile.php is untouched.
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_feast_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_play_streaks.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_milestones_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_league_period_page.php';

/**
 * M03 gate: celebrate the max-rated-victim card for non-elite players.
 * Top-of-ladder players already broadcast strength via the hero, so the card
 * earns its place for everyone ranked outside the top slice.
 */
const PLAYER_FEAST_LAB4_MAX_VICTIM_RANK_GATE = 10;

/**
 * @return array<string, mixed>
 */
function player_feast_load_lab4(mysqli $con, int $id): array
{
    $pm = player_feast_load_pm($con, $id);
    $pm['lab4'] = [
        'streak_day' => player_feast_lab4_play_streak($con, $id, 'day'),
        'streak_week' => player_feast_lab4_play_streak($con, $id, 'week'),
        'distinct_days' => player_feast_lab4_distinct_days($con, $id),
        'best_year' => player_feast_lab4_best_year($con, $id),
        'favourite_victim' => player_feast_lab4_favourite_victim($con, $id),
        'featured_rival' => player_feast_lab4_featured_rival($con, $id),
        'max_rated_victim' => player_feast_lab4_max_rated_victim($con, $id),
        'milestones' => player_feast_lab4_milestones($con, $id, (int) $pm['games']),
        'league' => player_feast_lab4_league($con, $id),
    ];

    return $pm;
}

/**
 * Read stored day/week play streak with the "alive" rule applied to current run.
 *
 * @return array{current: int, best: int, best_date: string, best_game_id: int, current_game_id: int}
 */
function player_feast_lab4_play_streak(mysqli $con, int $id, string $streakType): array
{
    $empty = ['current' => 0, 'best' => 0, 'best_date' => '', 'best_game_id' => 0, 'current_game_id' => 0];
    if (!k2_status_table_exists($con, 'player_play_streaks')) {
        return $empty;
    }
    try {
        $row = k2_play_streak_load_row($con, $id, $streakType);
        if ($row === null) {
            return $empty;
        }
        $utcToday = k2_play_streak_utc_today($con);
        $current = k2_play_streak_effective_current($row, $streakType, $utcToday);
    } catch (Throwable $e) {
        return $empty;
    }
    $bestDate = (string) ($row['best_achieved_at'] ?? '');

    return [
        'current' => $current,
        'best' => (int) ($row['best_streak'] ?? 0),
        'best_date' => $bestDate !== '' ? date('M Y', strtotime($bestDate) ?: time()) : '',
        'best_game_id' => (int) ($row['best_last_game_id'] ?? 0),
        'current_game_id' => (int) ($row['current_last_game_id'] ?? 0),
    ];
}

/** P05 — distinct UTC days with at least one rated game. */
function player_feast_lab4_distinct_days(mysqli $con, int $id): int
{
    if (!k2_status_table_exists($con, 'player_period_games')) {
        return 0;
    }
    $escId = (string) (int) $id;
    $res = k2_player_feast_query(
        $con,
        'lab4_distinct_days',
        "SELECT COUNT(*) AS c FROM player_period_games WHERE period_type='day' AND player_id='$escId'"
    );
    if (!$res || !($row = mysqli_fetch_row($res))) {
        return 0;
    }

    return (int) $row[0];
}

/**
 * P02 — best calendar year by wins (ticker).
 *
 * @return array{year: string, wins: int}|null
 */
function player_feast_lab4_best_year(mysqli $con, int $id): ?array
{
    if (!k2_status_table_exists($con, 'player_period_league')) {
        return null;
    }
    $escId = (string) (int) $id;
    $res = k2_player_feast_query(
        $con,
        'lab4_best_year',
        "SELECT period_start, wins FROM player_period_league "
        . "WHERE period_type='year' AND player_id='$escId' AND wins > 0 "
        . "ORDER BY wins DESC, period_start ASC LIMIT 1"
    );
    if (!$res || !($row = mysqli_fetch_assoc($res))) {
        return null;
    }

    return [
        'year' => (string) (int) substr((string) $row['period_start'], 0, 4),
        'wins' => (int) $row['wins'],
    ];
}

/**
 * M08 — favourite victim (opponent beaten most often).
 *
 * @return array{id: int, name: string, wins: int, games: int}|null
 */
function player_feast_lab4_favourite_victim(mysqli $con, int $id): ?array
{
    if (!k2_status_table_exists($con, 'player_matchup_summary')) {
        return null;
    }
    $escId = (string) (int) $id;
    $res = k2_player_feast_query(
        $con,
        'lab4_favourite_victim',
        "SELECT m.opponent_id, m.wins, m.games, p.Name AS opp_name "
        . "FROM player_matchup_summary m INNER JOIN playertable p ON p.ID = m.opponent_id "
        . "WHERE m.player_id='$escId' AND m.wins > 0 "
        . "ORDER BY m.wins DESC, m.games ASC, m.opponent_id ASC LIMIT 1"
    );
    if (!$res || !($row = mysqli_fetch_assoc($res))) {
        return null;
    }

    return [
        'id' => (int) $row['opponent_id'],
        'name' => (string) $row['opp_name'],
        'wins' => (int) $row['wins'],
        'games' => (int) $row['games'],
    ];
}

/**
 * M09 — featured rival (most-played opponent) with the W-D-L line.
 *
 * @return array{id: int, name: string, wins: int, draws: int, losses: int, games: int}|null
 */
function player_feast_lab4_featured_rival(mysqli $con, int $id): ?array
{
    if (!k2_status_table_exists($con, 'player_matchup_summary')) {
        return null;
    }
    $escId = (string) (int) $id;
    $res = k2_player_feast_query(
        $con,
        'lab4_featured_rival',
        "SELECT m.opponent_id, m.games, m.wins, m.draws, m.losses, p.Name AS opp_name "
        . "FROM player_matchup_summary m INNER JOIN playertable p ON p.ID = m.opponent_id "
        . "WHERE m.player_id='$escId' "
        . "ORDER BY m.games DESC, m.wins DESC, m.opponent_id ASC LIMIT 1"
    );
    if (!$res || !($row = mysqli_fetch_assoc($res))) {
        return null;
    }

    return [
        'id' => (int) $row['opponent_id'],
        'name' => (string) $row['opp_name'],
        'wins' => (int) $row['wins'],
        'draws' => (int) $row['draws'],
        'losses' => (int) $row['losses'],
        'games' => (int) $row['games'],
    ];
}

/**
 * M03 — max-rated-victim game card (rank-gated, non-elite celebration).
 *
 * @return array<string, mixed>|null parsed game row + victim rating + show flag
 */
function player_feast_lab4_max_rated_victim(mysqli $con, int $id): ?array
{
    $escId = (string) (int) $id;
    $res = k2_player_feast_query(
        $con,
        'lab4_max_victim_meta',
        "SELECT HighestRatedVictimGameID AS gid, HighestRatedVictim AS vrating FROM playertable WHERE id='$escId' LIMIT 1"
    );
    $meta = $res ? mysqli_fetch_assoc($res) : null;
    if ($meta === null) {
        return null;
    }
    $gid = (int) $meta['gid'];
    if ($gid <= 0) {
        return null;
    }
    $gRes = k2_player_feast_query(
        $con,
        'lab4_max_victim_game',
        "SELECT id, Date, idA, idB, NameA, NameB, GoalsA, GoalsB, ActualScore, AdjustmentA, AdjustmentB "
        . "FROM ratedresults WHERE id = $gid LIMIT 1"
    );
    $gRow = $gRes ? mysqli_fetch_assoc($gRes) : null;
    if ($gRow === null) {
        return null;
    }
    $parsed = pm_parse_highlight_row($gRow, $id);
    $parsed['victim_rating'] = (!k2_db_is_null($meta['vrating']) && (float) $meta['vrating'] > 0)
        ? (int) round((float) $meta['vrating']) : null;

    return $parsed;
}

/** Whether M03 should surface for this player (non-elite celebration). */
function player_feast_lab4_show_max_victim(array $pm): bool
{
    if (empty($pm['lab4']['max_rated_victim'])) {
        return false;
    }
    $rank = (int) ($pm['rank'] ?? 0);

    return $rank <= 0 || $rank > PLAYER_FEAST_LAB4_MAX_VICTIM_RANK_GATE;
}

/**
 * Milestone snippets — latest unlock, holo/amber rarity, last-12mo cadence, league-tied card.
 *
 * @return array{ready: bool, latest: ?array, holo: int, amber: int, unlocks_12mo: int, league_card: ?array}
 */
function player_feast_lab4_milestones(mysqli $con, int $id, int $games): array
{
    $out = ['ready' => false, 'latest' => null, 'holo' => 0, 'amber' => 0, 'unlocks_12mo' => 0, 'league_card' => null];
    if ($games < 1 || !k2_milestone_tables_ready($con)) {
        return $out;
    }
    $out['ready'] = true;
    $pid = (int) $id;

    $latestRes = mysqli_query(
        $con,
        "SELECT pm.milestone_key, pm.achieved_at, d.display_name, d.chart_token "
        . "FROM player_milestones pm INNER JOIN milestone_definitions d ON d.milestone_key = pm.milestone_key "
        . "WHERE pm.player_id = $pid ORDER BY pm.achieved_at DESC, pm.milestone_key ASC LIMIT 1"
    );
    if ($latestRes && ($row = mysqli_fetch_assoc($latestRes))) {
        $key = (string) $row['milestone_key'];
        $out['latest'] = [
            'key' => $key,
            'name' => k2_milestone_strip_markdown((string) $row['display_name']),
            'token' => (string) $row['chart_token'],
            'date' => date('M Y', strtotime((string) $row['achieved_at']) ?: time()),
            'href' => k2_milestone_detail_href($key),
        ];
    }

    $tokenRes = mysqli_query(
        $con,
        "SELECT d.chart_token AS token, COUNT(*) AS c "
        . "FROM player_milestones pm INNER JOIN milestone_definitions d ON d.milestone_key = pm.milestone_key "
        . "WHERE pm.player_id = $pid GROUP BY d.chart_token"
    );
    if ($tokenRes) {
        while ($row = mysqli_fetch_assoc($tokenRes)) {
            if ((string) $row['token'] === 'holo') {
                $out['holo'] = (int) $row['c'];
            } elseif ((string) $row['token'] === 'amber') {
                $out['amber'] = (int) $row['c'];
            }
        }
    }

    $recentRes = mysqli_query(
        $con,
        "SELECT COUNT(*) AS c FROM player_milestones "
        . "WHERE player_id = $pid AND achieved_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 12 MONTH)"
    );
    if ($recentRes && ($row = mysqli_fetch_assoc($recentRes))) {
        $out['unlocks_12mo'] = (int) $row['c'];
    }

    $leagueRes = mysqli_query(
        $con,
        "SELECT pm.milestone_key, pm.achieved_at, d.display_name, d.chart_token "
        . "FROM player_milestones pm INNER JOIN milestone_definitions d ON d.milestone_key = pm.milestone_key "
        . "WHERE pm.player_id = $pid AND pm.source_kind = 'league' "
        . "ORDER BY pm.achieved_at DESC, pm.milestone_key ASC LIMIT 1"
    );
    if ($leagueRes && ($row = mysqli_fetch_assoc($leagueRes))) {
        $key = (string) $row['milestone_key'];
        $out['league_card'] = [
            'key' => $key,
            'name' => k2_milestone_strip_markdown((string) $row['display_name']),
            'token' => (string) $row['chart_token'],
            'date' => date('M Y', strtotime((string) $row['achieved_at']) ?: time()),
            'href' => k2_milestone_detail_href($key),
        ];
    }

    return $out;
}

/**
 * League snippets — latest medal (bling) + career podium totals + wins.
 *
 * @return array{ready: bool, latest: ?array, wins: int, gold: int, silver: int, bronze: int, podiums: int}
 */
function player_feast_lab4_league(mysqli $con, int $id): array
{
    $out = ['ready' => false, 'latest' => null, 'wins' => 0, 'gold' => 0, 'silver' => 0, 'bronze' => 0, 'podiums' => 0];
    if (!k2_status_table_exists($con, 'player_league_award')) {
        return $out;
    }
    $pid = (int) $id;

    $latestRes = mysqli_query(
        $con,
        "SELECT league_kind, period_type, period_start, period_end, medal, finish_rank "
        . "FROM player_league_award WHERE player_id = $pid "
        . "ORDER BY period_end DESC, finish_rank ASC LIMIT 1"
    );
    if ($latestRes && ($row = mysqli_fetch_assoc($latestRes))) {
        $out['ready'] = true;
        $kind = (string) $row['league_kind'];
        $period = (string) $row['period_type'];
        $start = (string) $row['period_start'];
        $out['latest'] = [
            'medal' => (string) $row['medal'],
            'kind' => $kind,
            'period_type' => $period,
            'period_start' => $start,
            'label' => player_feast_lab4_league_label($kind, $period, $start),
            'href' => k2_league_period_href($kind === 'activity' ? 'activity' : 'points', $period, $start),
        ];
    }

    if (k2_status_table_exists($con, 'player_league_totals')) {
        $totRes = mysqli_query(
            $con,
            "SELECT wins, podiums, gold, silver, bronze FROM player_league_totals WHERE player_id = $pid LIMIT 1"
        );
        if ($totRes && ($row = mysqli_fetch_assoc($totRes))) {
            $out['ready'] = true;
            $out['wins'] = (int) $row['wins'];
            $out['podiums'] = (int) $row['podiums'];
            $out['gold'] = (int) $row['gold'];
            $out['silver'] = (int) $row['silver'];
            $out['bronze'] = (int) $row['bronze'];
        }
    }

    return $out;
}

/** Human medal context, e.g. "Weekly activity league · May 2026". */
function player_feast_lab4_league_label(string $kind, string $period, string $periodStart): string
{
    $cup = $kind === 'activity' ? 'activity' : 'points';
    $short = k2_league_period_short_label($cup, $period, $periodStart);
    if ($short !== '') {
        return ucfirst($short);
    }
    $grainNames = ['day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly', 'year' => 'Yearly'];
    $grain = $grainNames[$period] ?? ucfirst($period);

    return $grain . ' ' . $cup . ' league';
}

/** Pretty medal label for chips. */
function player_feast_lab4_medal_label(string $medal): string
{
    return ucfirst($medal);
}
