<?php
/**
 * Profile feast data — lab Agent 1 (extends production load with v1 fields).
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_feast_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_play_streaks.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/league_standings.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_milestones_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/status_queries.php';

/** Show M03 max-rated-victim card when current ladder rank is weaker than this (higher rank number). */
const PLAYER_FEAST_LAB1_M03_MAX_RANK = 50;

/** Minimum wins vs one opponent before M08 favourite-victim line. */
const PLAYER_FEAST_LAB1_M08_MIN_WINS = 5;

/**
 * @return array<string, mixed>
 */
function player_feast_load_pm_lab1(mysqli $con, int $id): array
{
    $pm = player_feast_load_pm($con, $id);
    $escId = (string) (int) $id;

    $rowResult = k2_player_feast_query($con, 'lab_playertable_row', "SELECT * FROM playertable WHERE id = '$escId' LIMIT 1");
    $row = $rowResult ? mysqli_fetch_assoc($rowResult) : null;
    if ($row === null) {
        return $pm;
    }

    $pm['highest_rated_victim_game_id'] = (int) ($row['HighestRatedVictimGameID'] ?? 0);
    $pm['play_streak'] = player_feast_lab1_load_play_streak($con, $id);
    $pm['honours'] = player_feast_lab1_load_honours($con, $id, $pm);
    $pm['best_year'] = player_feast_lab1_load_best_year($con, $id);
    $pm['distinct_days_played'] = player_feast_lab1_load_distinct_days($con, $id);
    $pm['max_rated_victim'] = player_feast_lab1_load_max_rated_victim(
        $con,
        $id,
        $pm['highest_rated_victim_game_id']
    );
    $pm['favourite_victim'] = player_feast_lab1_load_favourite_victim($con, $id);
    $pm['featured_rival'] = player_feast_lab1_load_featured_rival($con, $id);
    $pm['show_m03_card'] = player_feast_lab1_should_show_m03($pm);

    return $pm;
}

/**
 * B07/B08: one narrative per load — day vs week via player_id % 2; current run or historical best.
 *
 * @return array{show: bool, line: string, streak_lb_href: ?string}
 */
function player_feast_lab1_load_play_streak(mysqli $con, int $playerId): array
{
    $empty = ['show' => false, 'line' => '', 'streak_lb_href' => null];
    if (!k2_status_table_exists($con, 'player_play_streaks')) {
        return $empty;
    }

    $useWeek = ($playerId % 2) === 1;
    $streakType = $useWeek ? 'week' : 'day';
    $unit = $useWeek ? 'week' : 'day';
    $unitPlural = $useWeek ? 'weeks' : 'days';

    try {
        $utcToday = k2_play_streak_utc_today($con);
        $row = k2_play_streak_load_row($con, $playerId, $streakType) ?? k2_play_streak_default_row($playerId, $streakType);
    } catch (Throwable $e) {
        return $empty;
    }

    $current = k2_play_streak_effective_current($row, $streakType, $utcToday);
    $best = (int) ($row['best_streak'] ?? 0);
    $bestAt = (string) ($row['best_achieved_at'] ?? '');

    $line = '';
    $highlightBest = false;

    if ($current >= 3) {
        $line = 'On a ' . $current . '-' . $unit . ' rated play streak right now.';
    } elseif ($best >= 3 && $bestAt !== '') {
        $endTs = strtotime($bestAt) ?: 0;
        $when = $endTs ? date('M Y', $endTs) : '';
        $line = 'Best run: ' . $best . ' ' . $unitPlural . ' in a row'
            . ($when !== '' ? ' — peaked ' . $when : '') . '.';
        $highlightBest = true;
    } else {
        return $empty;
    }

    $streakLbHref = null;
    if ($highlightBest && $best >= 20) {
        $sort = $useWeek ? 11 : 10;
        $streakLbHref = 'ranked4.php?sort=' . $sort . '&dir=desc';
    }

    return [
        'show' => true,
        'line' => $line,
        'streak_lb_href' => $streakLbHref,
    ];
}

/**
 * @param array<string, mixed> $pm
 * @return array<string, mixed>
 */
function player_feast_lab1_load_honours(mysqli $con, int $playerId, array $pm): array
{
    $honours = [
        'show_strip' => false,
        'latest_milestone' => null,
        'holo_count' => 0,
        'amber_count' => 0,
        'signature_label' => '',
        'unlocks_12mo' => 0,
        'league_milestone' => null,
        'latest_medal' => null,
        'career_medals' => null,
        'league_wins' => 0,
        'garden_href' => 'individual_milestones.php?id=' . $playerId,
        'honours_href' => 'ranked9.php?cup=overall',
    ];

    $games = (int) ($pm['games'] ?? 0);
    if ($games < 1) {
        return $honours;
    }

    if (k2_milestone_tables_ready($con)) {
        $counts = k2_milestone_player_counts($con, $playerId);
        if ($counts !== null) {
            $honours['holo_count'] = (int) $counts['legendary'];
            $honours['amber_count'] = (int) $counts['accomplished'];
            $honours['signature_label'] = $honours['holo_count'] > 0
                ? $honours['holo_count'] . ' holo unlock' . ($honours['holo_count'] === 1 ? '' : 's')
                : ($honours['amber_count'] > 0
                    ? $honours['amber_count'] . ' amber-tier unlock' . ($honours['amber_count'] === 1 ? '' : 's')
                    : '');
        }

        $honours['latest_milestone'] = player_feast_lab1_load_latest_milestone($con, $playerId);
        $honours['unlocks_12mo'] = player_feast_lab1_load_unlocks_12mo($con, $playerId);
        $honours['league_milestone'] = player_feast_lab1_load_league_milestone_card($con, $playerId);
    }

    if (k2_league_table_exists($con, 'player_league_award')) {
        $honours['latest_medal'] = player_feast_lab1_load_latest_medal($con, $playerId);
    }
    if (k2_league_table_exists($con, 'player_league_totals')) {
        $totals = player_feast_lab1_load_league_totals($con, $playerId);
        if ($totals !== null) {
            $honours['career_medals'] = $totals;
            $honours['league_wins'] = (int) $totals['wins'];
        }
    }

    $honours['show_strip'] = $honours['latest_milestone'] !== null
        || $honours['signature_label'] !== ''
        || $honours['unlocks_12mo'] > 0
        || $honours['league_milestone'] !== null
        || $honours['latest_medal'] !== null
        || ($honours['career_medals'] !== null && (int) $honours['career_medals']['podiums'] > 0)
        || $honours['league_wins'] > 0;

    return $honours;
}

/**
 * @return ?array{milestone_key: string, display_name: string, achieved_label: string, detail_href: string, tier_band: string}
 */
function player_feast_lab1_load_latest_milestone(mysqli $con, int $playerId): ?array
{
    $pid = (int) $playerId;
    $sql = "
        SELECT pm.milestone_key, pm.achieved_at, d.display_name, d.tier_band
        FROM player_milestones pm
        INNER JOIN milestone_definitions d ON d.milestone_key = pm.milestone_key
        WHERE pm.player_id = $pid
        ORDER BY pm.achieved_at DESC
        LIMIT 1
    ";
    $result = k2_player_feast_query($con, 'lab_latest_milestone', $sql);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    if ($row === null || ($row['achieved_at'] ?? '') === '') {
        return null;
    }

    $mKey = (string) $row['milestone_key'];

    return [
        'milestone_key' => $mKey,
        'display_name' => k2_milestone_strip_markdown((string) $row['display_name']),
        'achieved_label' => k2_milestone_format_utc((string) $row['achieved_at']),
        'detail_href' => k2_milestone_detail_href($mKey),
        'tier_band' => (string) $row['tier_band'],
    ];
}

function player_feast_lab1_load_unlocks_12mo(mysqli $con, int $playerId): int
{
    $pid = (int) $playerId;
    $sql = "SELECT COUNT(*) AS c FROM player_milestones
        WHERE player_id = $pid AND achieved_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 12 MONTH)";
    $result = k2_player_feast_query($con, 'lab_unlocks_12mo', $sql);
    $row = $result ? mysqli_fetch_assoc($result) : null;

    return $row ? (int) $row['c'] : 0;
}

/**
 * @return ?array{milestone_key: string, display_name: string, achieved_label: string, detail_href: string, league_label: string}
 */
function player_feast_lab1_load_league_milestone_card(mysqli $con, int $playerId): ?array
{
    $pid = (int) $playerId;
    $sql = "
        SELECT pm.milestone_key, pm.achieved_at, pm.source_league_kind, pm.source_period_type,
               d.display_name, d.tier_band
        FROM player_milestones pm
        INNER JOIN milestone_definitions d ON d.milestone_key = pm.milestone_key
        WHERE pm.player_id = $pid AND pm.source_kind = 'league'
        ORDER BY pm.achieved_at DESC
        LIMIT 1
    ";
    $result = k2_player_feast_query($con, 'lab_league_milestone', $sql);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    if ($row === null) {
        return null;
    }

    $kind = (string) ($row['source_league_kind'] ?? 'points');
    $period = (string) ($row['source_period_type'] ?? 'day');
    $cupLabel = $kind === 'activity' ? 'Activity' : 'Points';
    $grainLabel = k2_status_period_segment_label($period);

    return [
        'milestone_key' => (string) $row['milestone_key'],
        'display_name' => k2_milestone_strip_markdown((string) $row['display_name']),
        'achieved_label' => k2_milestone_format_utc((string) $row['achieved_at']),
        'detail_href' => k2_milestone_detail_href((string) $row['milestone_key']),
        'league_label' => $grainLabel . ' ' . $cupLabel,
    ];
}

/**
 * @return ?array{medal: string, medal_label: string, league_label: string, period_label: string}
 */
function player_feast_lab1_load_latest_medal(mysqli $con, int $playerId): ?array
{
    $stmt = mysqli_prepare(
        $con,
        'SELECT medal, league_kind, period_type, period_start, period_end '
        . 'FROM player_league_award WHERE player_id = ? ORDER BY period_end DESC LIMIT 1'
    );
    if ($stmt === false) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $playerId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);

        return null;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if ($row === null) {
        return null;
    }

    $medal = (string) $row['medal'];
    $kind = (string) $row['league_kind'];
    $period = (string) $row['period_type'];
    $cupLabel = $kind === 'activity' ? 'Activity' : 'Points';
    $grainLabel = k2_status_period_segment_label($period);
    $periodLabel = player_feast_lab1_format_period_label($period, (string) $row['period_start']);

    return [
        'medal' => $medal,
        'medal_label' => ucfirst($medal),
        'league_label' => $grainLabel . ' ' . $cupLabel,
        'period_label' => (string) $periodLabel,
    ];
}

/**
 * @return ?array{gold: int, silver: int, bronze: int, podiums: int, wins: int}
 */
function player_feast_lab1_load_league_totals(mysqli $con, int $playerId): ?array
{
    $stmt = mysqli_prepare(
        $con,
        'SELECT wins, podiums, gold, silver, bronze FROM player_league_totals WHERE player_id = ? LIMIT 1'
    );
    if ($stmt === false) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $playerId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);

        return null;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if ($row === null) {
        return null;
    }

    return [
        'gold' => (int) $row['gold'],
        'silver' => (int) $row['silver'],
        'bronze' => (int) $row['bronze'],
        'podiums' => (int) $row['podiums'],
        'wins' => (int) $row['wins'],
    ];
}

/**
 * @return ?array{year: int, wins: int}
 */
function player_feast_lab1_load_best_year(mysqli $con, int $playerId): ?array
{
    if (!k2_status_table_exists($con, 'player_period_league')) {
        return null;
    }
    $stmt = mysqli_prepare(
        $con,
        "SELECT period_start, wins FROM player_period_league
         WHERE player_id = ? AND period_type = 'year' AND wins > 0
         ORDER BY wins DESC, period_start DESC LIMIT 1"
    );
    if ($stmt === false) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $playerId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);

        return null;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if ($row === null || (int) $row['wins'] < 1) {
        return null;
    }

    return [
        'year' => (int) substr((string) $row['period_start'], 0, 4),
        'wins' => (int) $row['wins'],
    ];
}

function player_feast_lab1_load_distinct_days(mysqli $con, int $playerId): int
{
    if (!k2_status_table_exists($con, 'player_period_games')) {
        return 0;
    }
    $pid = (int) $playerId;
    $sql = "SELECT COUNT(*) AS c FROM player_period_games
        WHERE player_id = $pid AND period_type = 'day' AND games > 0";
    $result = k2_player_feast_query($con, 'lab_distinct_days', $sql);
    $row = $result ? mysqli_fetch_assoc($result) : null;

    return $row ? (int) $row['c'] : 0;
}

/**
 * @return ?array<string, mixed>
 */
function player_feast_lab1_load_max_rated_victim(mysqli $con, int $playerId, int $gameId): ?array
{
    if ($gameId <= 0) {
        return null;
    }
    $gRes = k2_player_feast_query(
        $con,
        'lab_max_rated_victim',
        "SELECT id, Date, idA, idB, NameA, NameB, GoalsA, GoalsB, ActualScore, AdjustmentA, AdjustmentB, RatingA, RatingB "
        . "FROM ratedresults WHERE id = $gameId LIMIT 1"
    );
    $gRow = $gRes ? mysqli_fetch_assoc($gRes) : null;
    if ($gRow === null) {
        return null;
    }
    $parsed = pm_parse_highlight_row($gRow, $playerId);
    $isA = (int) pm_row_col($gRow, 'idA') === $playerId;
    $oppRating = $isA ? (int) round((float) pm_row_col($gRow, 'RatingB')) : (int) round((float) pm_row_col($gRow, 'RatingA'));

    return array_merge($parsed, [
        'game_id' => $gameId,
        'opponent_rating' => $oppRating,
    ]);
}

/**
 * @return ?array{opponent_id: int, opponent_name: string, wins: int, games: int}
 */
function player_feast_lab1_load_favourite_victim(mysqli $con, int $playerId): ?array
{
    if (!k2_status_table_exists($con, 'player_matchup_summary')) {
        return null;
    }
    $stmt = mysqli_prepare(
        $con,
        'SELECT m.opponent_id, COALESCE(p.Name, CONCAT(\'#\', m.opponent_id)) AS opponent_name, m.wins, m.games '
        . 'FROM player_matchup_summary m '
        . 'LEFT JOIN playertable p ON p.ID = m.opponent_id '
        . 'WHERE m.player_id = ? AND m.wins >= ? '
        . 'ORDER BY m.wins DESC, m.games DESC LIMIT 1'
    );
    if ($stmt === false) {
        return null;
    }
    $minWins = PLAYER_FEAST_LAB1_M08_MIN_WINS;
    mysqli_stmt_bind_param($stmt, 'ii', $playerId, $minWins);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);

        return null;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if ($row === null) {
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
 * @return ?array{opponent_id: int, opponent_name: string, wins: int, draws: int, losses: int, games: int}
 */
function player_feast_lab1_load_featured_rival(mysqli $con, int $playerId): ?array
{
    if (!k2_status_table_exists($con, 'player_matchup_summary')) {
        return null;
    }
    $stmt = mysqli_prepare(
        $con,
        'SELECT m.opponent_id, COALESCE(p.Name, CONCAT(\'#\', m.opponent_id)) AS opponent_name, '
        . 'm.games, m.wins, m.draws, m.losses '
        . 'FROM player_matchup_summary m '
        . 'LEFT JOIN playertable p ON p.ID = m.opponent_id '
        . 'WHERE m.player_id = ? '
        . 'ORDER BY m.games DESC, opponent_name ASC LIMIT 1'
    );
    if ($stmt === false) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $playerId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);

        return null;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if ($row === null || (int) $row['games'] < 1) {
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
 * @param array<string, mixed> $pm
 */
function player_feast_lab1_should_show_m03(array $pm): bool
{
    if (empty($pm['max_rated_victim'])) {
        return false;
    }
    if (empty($pm['display'])) {
        return true;
    }

    return (int) ($pm['rank'] ?? 999) > PLAYER_FEAST_LAB1_M03_MAX_RANK;
}

function player_feast_lab1_format_period_label(string $periodType, string $periodStart): string
{
    $periodStart = trim($periodStart);
    if ($periodStart === '') {
        return '';
    }
    try {
        $start = new DateTimeImmutable($periodStart, new DateTimeZone('UTC'));
    } catch (Exception $e) {
        return $periodStart;
    }

    return match ($periodType) {
        'day' => $start->format('M j, Y'),
        'week' => 'Week of ' . $start->format('M j, Y'),
        'month' => $start->format('F Y'),
        'year' => $start->format('Y'),
        default => $start->format('M Y'),
    };
}

/** @param array<string, mixed> $pm */
function player_feast_expose_hero_vars_lab1(array $pm): void
{
    player_feast_expose_hero_vars($pm);
}
