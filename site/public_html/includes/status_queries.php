<?php
/**
 * Status room data loaders (hub status.php v1).
 * Requires mysqli $con and playertable / resulttable / ratedresults / generalstatstable.
 */

declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_player_display_names.php';

function k2_status_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Days left in calendar month (server TZ), including today. Past months return 0. */
function k2_status_month_days_left(int $monthOffset = 0): int
{
    $monthOffset = max(-24, min(0, $monthOffset));
    $base = strtotime('first day of this month');
    if ($monthOffset !== 0) {
        $base = strtotime((string) $monthOffset . ' month', $base);
    }
    $today = strtotime('today');
    $monthEnd = strtotime(date('Y-m-t', $base));
    if ($today > $monthEnd) {
        return 0;
    }
    $monthStart = strtotime(date('Y-m-01', $base));
    if ($today < $monthStart) {
        return (int) date('t', $base);
    }

    return (int) floor(($monthEnd - $today) / 86400) + 1;
}

/** @param array{label: string, total_games: int, month_offset: int} $monthly */
function k2_status_league_meta_line(array $monthly): string
{
    $days = k2_status_month_days_left((int) ($monthly['month_offset'] ?? 0));
    $daysLabel = $days === 1 ? '1 day left' : $days . ' days left';

    return $monthly['label'] . ' · ' . (int) $monthly['total_games'] . ' rated games · ' . $daysLabel;
}

/** @return array{now: DateTimeImmutable, now_sql: string, source: string, timezone: string} */
function k2_status_server_clock(mysqli $con): array
{
    if (function_exists('k2_site_ensure_utc')) {
        k2_site_ensure_utc();
    }
    $utc = new DateTimeZone('UTC');
    $row = null;
    $r = mysqli_query($con, 'SELECT NOW() AS server_now, @@session.time_zone AS session_tz, @@system_time_zone AS system_tz');
    if ($r !== false) {
        $row = mysqli_fetch_assoc($r);
        mysqli_free_result($r);
    }

    $nowSql = $row && !empty($row['server_now'])
        ? (string) $row['server_now']
        : (new DateTimeImmutable('now', $utc))->format('Y-m-d H:i:s');
    $now = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $nowSql, $utc);
    if (!$now instanceof DateTimeImmutable) {
        $now = new DateTimeImmutable('now', $utc);
        $nowSql = $now->format('Y-m-d H:i:s');
    }

    $sessionTz = $row && isset($row['session_tz']) ? (string) $row['session_tz'] : date_default_timezone_get();
    $systemTz = $row && isset($row['system_tz']) ? (string) $row['system_tz'] : '';
    $tzLabel = $sessionTz === 'SYSTEM' && $systemTz !== '' ? 'SYSTEM/' . $systemTz : $sessionTz;

    return [
        'now' => $now,
        'now_sql' => $nowSql,
        'source' => $row ? 'mysql' : 'php',
        'timezone' => $tzLabel,
    ];
}

/**
 * Points day league for the same UTC calendar day one year before server now (C07).
 *
 * @param array{now_sql?: string}|null $serverClock from k2_status_load_room()['server_clock']
 */
function k2_status_on_this_day_last_year_href(?array $serverClock = null): string
{
    require_once __DIR__ . '/k2_league_period_page.php';
    if (function_exists('k2_site_ensure_utc')) {
        k2_site_ensure_utc();
    }
    $utc = new DateTimeZone('UTC');
    $nowSql = is_array($serverClock) ? trim((string) ($serverClock['now_sql'] ?? '')) : '';
    $now = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $nowSql, $utc);
    if (!$now instanceof DateTimeImmutable) {
        $now = new DateTimeImmutable('now', $utc);
    }

    return k2_league_period_href('points', 'day', $now->modify('-1 year')->format('Y-m-d'));
}

/** Unix epoch for league period end (UTC). */
function k2_status_league_end_epoch(array $league): int
{
    if (function_exists('k2_site_ensure_utc')) {
        k2_site_ensure_utc();
    }
    $end = (string) ($league['end'] ?? '');
    if ($end === '') {
        return 0;
    }
    $utc = new DateTimeZone('UTC');
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $end, $utc);
    if ($dt instanceof DateTimeImmutable) {
        return $dt->getTimestamp();
    }
    $endTs = strtotime($end);

    return $endTs === false ? 0 : (int) $endTs;
}

/**
 * Timing fields for Status league JSON APIs (visitor countdown + medals).
 *
 * @return array{end_epoch: int, server_now_epoch: int, show_medals: bool}
 */
function k2_status_league_timing_for_api(mysqli $con, ?array $league): array
{
    $clock = k2_status_server_clock($con);
    $serverNowEpoch = $clock['now']->getTimestamp();
    $endEpoch = $league !== null ? k2_status_league_end_epoch($league) : 0;

    return [
        'end_epoch' => $endEpoch,
        'server_now_epoch' => $serverNowEpoch,
        'show_medals' => $endEpoch > 0 && $endEpoch <= $serverNowEpoch,
    ];
}

/**
 * @return array{start: string, end: string, label: string}|null
 */
function k2_status_league_period_bounds(string $period, int $periodOffset, ?DateTimeImmutable $serverNow = null): ?array
{
    $utc = new DateTimeZone('UTC');
    $serverNow = $serverNow ?? new DateTimeImmutable('now', $utc);
    $periodOffset = max(-24, min(0, $periodOffset));
    $today = $serverNow->setTime(0, 0, 0);

    switch ($period) {
        case 'day':
            $start = $today->modify((string) $periodOffset . ' days');
            $end = $start->modify('+1 day');
            $label = k2_format_calendar_day_label($start->format('Y-m-d'));
            break;
        case 'week':
            $weekStart = $today->modify('-' . ((int) $today->format('N') - 1) . ' days');
            $start = $weekStart->modify((string) $periodOffset . ' weeks');
            $end = $start->modify('+1 week');
            $label = k2_format_calendar_week_label($start->format('Y-m-d'));
            break;
        case 'month':
            $monthStart = new DateTimeImmutable($serverNow->format('Y-m-01 00:00:00'));
            $start = $monthStart->modify((string) $periodOffset . ' months');
            $end = $start->modify('+1 month');
            $label = $start->format('F Y');
            break;
        case 'year':
            $yearStart = new DateTimeImmutable($serverNow->format('Y-01-01 00:00:00'));
            $start = $yearStart->modify((string) $periodOffset . ' years');
            $end = $start->modify('+1 year');
            $label = $start->format('Y');
            break;
        default:
            return null;
    }

    return [
        'start' => $start->format('Y-m-d H:i:s'),
        'end' => $end->format('Y-m-d H:i:s'),
        'label' => $label,
    ];
}

/** Segment pill label for day/week/month/year (Status leagues + League honours). */
function k2_status_period_segment_label(string $period): string
{
    return match ($period) {
        'day' => 'Daily',
        'week' => 'Weekly',
        'month' => 'Monthly',
        'year' => 'Year',
        default => ucfirst($period),
    };
}

function k2_status_league_title(string $period): string
{
    return match ($period) {
        'day' => 'Daily league',
        'week' => 'Weekly league',
        'year' => 'Year league',
        default => 'Monthly league',
    };
}

function k2_status_league_toggle_label(string $period, string $target): string
{
    if ($target === 'earlier') {
        return 'Earlier';
    }

    if ($target === 'prev') {
        return match ($period) {
            'day' => 'Yesterday',
            'week' => 'Last week',
            'year' => 'Last year',
            default => 'Last month',
        };
    }

    return match ($period) {
        'day' => 'Today',
        'week' => 'This week',
        'year' => 'This year',
        default => 'This month',
    };
}

function k2_status_league_end_includes_year(array $league): bool
{
    return ($league['period'] ?? '') === 'year';
}

function k2_status_league_end_label(array $league): string
{
    $ts = strtotime((string) ($league['end'] ?? ''));
    if ($ts === false) {
        return '';
    }

    return date(k2_status_league_end_includes_year($league) ? 'F j, Y, H:i' : 'F j, H:i', $ts);
}

/** @return array{date: string, time: string} */
function k2_status_league_end_label_parts(array $league): array
{
    $ts = strtotime((string) ($league['end'] ?? ''));
    if ($ts === false) {
        return ['date' => '', 'time' => ''];
    }

    return [
        'date' => date(k2_status_league_end_includes_year($league) ? 'F j, Y' : 'F j', $ts),
        'time' => date('H:i', $ts),
    ];
}

function k2_status_format_league_time_left(int $seconds): string
{
    if ($seconds <= 0) {
        return 'ended';
    }

    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $parts = [];
    if ($days > 0) {
        $parts[] = $days . ' ' . ($days === 1 ? 'day' : 'days');
        if ($hours > 0) {
            $parts[] = $hours . ' ' . ($hours === 1 ? 'hour' : 'hours');
        }

        return implode(' ', $parts);
    }
    if ($hours > 0) {
        $parts[] = $hours . ' ' . ($hours === 1 ? 'hour' : 'hours');
        if ($minutes > 0) {
            $parts[] = $minutes . ' ' . ($minutes === 1 ? 'minute' : 'minutes');
        }

        return implode(' ', $parts);
    }

    $minutes = max(1, $minutes);

    return $minutes . ' ' . ($minutes === 1 ? 'minute' : 'minutes');
}

function k2_status_league_meta_line_for_clock(array $league, DateTimeImmutable $serverNow): string
{
    $endTs = k2_status_league_end_epoch($league);
    $nowTs = $serverNow->getTimestamp();
    $totalGames = (int) ($league['total_games'] ?? 0);
    $gamesLabel = $totalGames === 1 ? 'rated game' : 'rated games';
    $endLabel = k2_status_league_end_label($league);
    $isLive = $endTs !== false && $endTs > $nowTs;
    $verb = $isLive ? 'ends' : 'ended';
    $periodLabel = trim((string) ($league['label'] ?? ''));
    $text = ($periodLabel !== '' ? $periodLabel . ' League' : '') . ' · ' . $totalGames . ' ' . $gamesLabel;
    if ($endLabel !== '') {
        $text .= ' · ' . $verb . ' ' . $endLabel . ' UTC';
    }
    if ($isLive) {
        $remaining = k2_status_format_league_time_left($endTs - $nowTs);
        $text .= $remaining === 'ended' ? ' · ' . $remaining : ' · ' . $remaining . ' left';
    }

    return $text;
}

/** Meta line for leagues block; period label + live countdown use {@see .blue}. */
function k2_status_league_meta_html_for_clock(array $league, DateTimeImmutable $serverNow): string
{
    $endTs = k2_status_league_end_epoch($league);
    $nowTs = $serverNow->getTimestamp();
    $totalGames = (int) ($league['total_games'] ?? 0);
    $gamesLabel = $totalGames === 1 ? 'rated game' : 'rated games';
    $endLabel = k2_status_league_end_label($league);
    $isLive = $endTs !== false && $endTs > $nowTs;
    $periodLabel = trim((string) ($league['label'] ?? ''));

    $text = '';
    if ($periodLabel !== '') {
        $text .= '<span class="blue">' . k2_status_h($periodLabel) . '</span> League';
    }
    $text .= ' · <span class="blue">'
        . number_format($totalGames) . '</span> ' . $gamesLabel;
    if ($endLabel !== '') {
        if ($isLive) {
            $text .= ' · ends ' . k2_status_h($endLabel) . ' UTC';
        } else {
            $endParts = k2_status_league_end_label_parts($league);
            $text .= ' · ended <span class="blue">' . k2_status_h($endParts['date']) . '</span>';
            if ($endParts['time'] !== '') {
                $text .= ', ' . k2_status_h($endParts['time']);
            }
            $text .= ' UTC';
        }
    }
    if ($isLive) {
        $remaining = k2_status_format_league_time_left($endTs - $nowTs);
        if ($remaining === 'ended') {
            $text .= ' · ' . k2_status_h($remaining);
        } else {
            $text .= ' · <span class="blue">' . k2_status_h($remaining) . ' left</span>';
        }
    }

    return $text;
}

function k2_status_human_ago(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '';
    }
    $diff = time() - $ts;
    if ($diff < 45) {
        return 'just now';
    }
    if ($diff < 3600) {
        $m = (int) floor($diff / 60);

        return $m . ' min ago';
    }
    if ($diff < 86400) {
        $h = (int) floor($diff / 3600);

        return $h . ' h ago';
    }
    if ($diff < 604800) {
        $d = (int) floor($diff / 86400);

        return $d . ' d ago';
    }

    return date('M j', $ts);
}

function k2_status_short_time(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }
    $ts = strtotime($datetime);

    return $ts === false ? '—' : date('D H:i', $ts);
}

/**
 * Weekday + clock for status recency rows — split columns so HH:MM aligns vertically.
 *
 * @param non-empty-string $wrapperClass
 */
function k2_status_day_clock_html(?string $datetime, string $wrapperClass = 'k2-status-recency-list__when'): string
{
    $classes = k2_status_h(trim($wrapperClass . ' k2-status-recency-list__when--day-clock'));
    if ($datetime === null || $datetime === '') {
        return '<span class="' . $classes . '"><span class="k2-status-recency-list__when-na">—</span></span>';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '<span class="' . $classes . '"><span class="k2-status-recency-list__when-na">—</span></span>';
    }

    return '<span class="' . $classes . '">'
        . '<span class="k2-status-recency-list__when-day">' . k2_status_h(date('D', $ts)) . '</span>'
        . '<span class="k2-status-recency-list__when-clock">' . k2_status_h(date('H:i', $ts)) . '</span>'
        . '</span>';
}

/** Join date for New players panel — space-padded day so comma/year align in the recency column. */
function k2_status_registration_date(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '—';
    }

    return date('M ', $ts) . sprintf('%2d', (int) date('j', $ts)) . date(', Y', $ts);
}

/** Scoreline for status lists — side wrappers enable per-goal live pulse in JS. */
function k2_status_score_html(int $goalsA, int $goalsB): string
{
    require_once __DIR__ . '/k2_rated_game_row.php';

    return '<span class="k2-status-score__goal" data-side="a">'
        . k2_rated_game_goal_cell_html($goalsA, $goalsB)
        . '</span>'
        . '<span class="k2-scoreline-sep" aria-hidden="true">–</span>'
        . '<span class="k2-status-score__goal" data-side="b">'
        . k2_rated_game_goal_cell_html($goalsB, $goalsA)
        . '</span>';
}

/** Time remaining in the current half (HalfCountdown ticks; 50 ticks per second; 5:00 per half). */
function k2_status_format_half_countdown(int $ticks): string
{
    if ($ticks <= 0) {
        return '—';
    }

    $seconds = (int) round($ticks / 50);
    $minutes = intdiv($seconds, 60);
    $secs = $seconds % 60;

    return $minutes . ':' . str_pad((string) $secs, 2, '0', STR_PAD_LEFT);
}

function k2_status_format_game_period(int $period): string
{
    return match ($period) {
        1 => '1st half',
        2 => '2nd half',
        default => '—',
    };
}

/** @return array{players: int, games: int, since_label: string}|null */
function k2_status_arc_ticker(mysqli $con, ?string &$error = null): ?array
{
    $error = null;
    $players = null;
    $games = null;
    $r = mysqli_query($con, 'SELECT GamesPlayed FROM generalstatstable WHERE id = 1 LIMIT 1');
    if ($r !== false) {
        $row = mysqli_fetch_assoc($r);
        mysqli_free_result($r);
        if ($row && $row['GamesPlayed'] !== null) {
            $games = (int) $row['GamesPlayed'];
        }
    }
    if ($games === null) {
        $r = mysqli_query($con, 'SELECT COUNT(*) AS c FROM ratedresults');
        if ($r === false) {
            $error = mysqli_error($con);

            return null;
        }
        $row = mysqli_fetch_assoc($r);
        mysqli_free_result($r);
        $games = (int) ($row['c'] ?? 0);
    }

    $r = mysqli_query($con, 'SELECT MIN(`Date`) AS first_game FROM ratedresults');
    if ($r === false) {
        $error = mysqli_error($con);

        return null;
    }
    $row = mysqli_fetch_assoc($r);
    mysqli_free_result($r);
    $first = $row['first_game'] ?? null;
    if ($first === null || $first === '') {
        $error = 'no rated games';

        return null;
    }
    $ts = strtotime((string) $first);

    $r = mysqli_query($con, 'SELECT COUNT(*) AS c FROM playertable WHERE NumberGames >= 1');
    if ($r === false) {
        $error = mysqli_error($con);

        return null;
    }
    $row = mysqli_fetch_assoc($r);
    mysqli_free_result($r);
    $players = (int) ($row['c'] ?? 0);

    return [
        'players' => $players,
        'games' => $games,
        'since_label' => $ts === false ? (string) $first : date('F j, Y', $ts),
    ];
}

/** @return array{online: int, live_games: int, last_login: ?string, last_login_ago: string, games_played: ?int, goals_scored: ?int}|null */
function k2_status_pulse(mysqli $con, ?string &$error = null): ?array
{
    $error = null;
    $online = 0;
    $live = 0;
    $lastLogin = null;
    $gamesPlayed = null;
    $goalsScored = null;

    $r = mysqli_query($con, 'SELECT COUNT(*) AS c FROM playertable WHERE COALESCE(IsOnline, 0) <> 0');
    if ($r === false) {
        $error = mysqli_error($con);

        return null;
    }
    $row = mysqli_fetch_assoc($r);
    mysqli_free_result($r);
    $online = (int) ($row['c'] ?? 0);

    $r = mysqli_query(
        $con,
        'SELECT COUNT(*) AS c FROM resulttable WHERE HasStarted = 1 AND HasFinished = 0 AND Shelved = 0'
    );
    if ($r === false) {
        $error = mysqli_error($con);

        return null;
    }
    $row = mysqli_fetch_assoc($r);
    mysqli_free_result($r);
    $live = (int) ($row['c'] ?? 0);

    $r = mysqli_query($con, 'SELECT MAX(LastLogin) AS t FROM playertable');
    if ($r === false) {
        $error = mysqli_error($con);

        return null;
    }
    $row = mysqli_fetch_assoc($r);
    mysqli_free_result($r);
    $lastLogin = $row['t'] ?? null;

    $r = mysqli_query($con, 'SELECT GamesPlayed, GoalsScored FROM generalstatstable WHERE id = 1 LIMIT 1');
    if ($r !== false) {
        $row = mysqli_fetch_assoc($r);
        mysqli_free_result($r);
        if ($row) {
            $gamesPlayed = isset($row['GamesPlayed']) ? (int) $row['GamesPlayed'] : null;
            $goalsScored = isset($row['GoalsScored']) ? (int) $row['GoalsScored'] : null;
        }
    }

    return [
        'online' => $online,
        'live_games' => $live,
        'last_login' => $lastLogin,
        'last_login_ago' => k2_status_human_ago($lastLogin),
        'games_played' => $gamesPlayed,
        'goals_scored' => $goalsScored,
    ];
}

/** @return list<array{id: int, name: string, rating: int, last_game: string, games: int}>|null */
function k2_status_active_top_rated(mysqli $con, ?string &$error = null): ?array
{
    $error = null;
    $sql = 'SELECT ID, Name, Rating, LastGame, NumberGames FROM playertable '
        . 'WHERE NumberGames >= 1 AND LastGame >= DATE_SUB(NOW(), INTERVAL 12 MONTH) '
        . 'ORDER BY Rating DESC';
    $r = mysqli_query($con, $sql);
    if ($r === false) {
        $error = mysqli_error($con);

        return null;
    }
    $out = [];
    while ($row = mysqli_fetch_assoc($r)) {
        $out[] = [
            'id' => (int) $row['ID'],
            'name' => (string) $row['Name'],
            'rating' => k2_db_is_null($row['Rating']) ? 0 : (int) round((float) $row['Rating']),
            'last_game' => (string) $row['LastGame'],
            'games' => (int) ($row['NumberGames'] ?? 0),
        ];
    }
    mysqli_free_result($r);

    return $out;
}

function k2_status_table_exists(mysqli $con, string $tableName): bool
{
    static $exists = [];
    if (array_key_exists($tableName, $exists)) {
        return $exists[$tableName];
    }

    $stmt = mysqli_prepare(
        $con,
        'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );
    if ($stmt === false) {
        $exists[$tableName] = false;

        return false;
    }
    mysqli_stmt_bind_param($stmt, 's', $tableName);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        $exists[$tableName] = false;

        return false;
    }
    $r = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($r);
    mysqli_free_result($r);
    mysqli_stmt_close($stmt);
    $exists[$tableName] = (int) ($row['c'] ?? 0) > 0;

    return $exists[$tableName];
}

/**
 * @return array{label: string, start: string, end: string, rows: list<array>, total_games: int, month_offset: int}|null
 */
function k2_status_league_from_period_league(
    mysqli $con,
    string $periodType,
    string $periodStart,
    string $start,
    string $end,
    string $label,
    int $periodOffset,
    ?int $limit,
    ?string &$error = null
): ?array {
    $limitSql = $limit === null ? '' : ' LIMIT ' . max(1, (int) $limit);
    $sql = <<<'SQL'
SELECT
  l.player_id AS pid,
  COALESCE(p.Name, CONCAT('#', l.player_id)) AS pname,
  l.played,
  l.wins,
  l.draws,
  l.losses,
  l.goals_for AS gf,
  l.goals_against AS ga,
  l.goal_difference AS gd,
  l.points AS pts
FROM player_period_league l
LEFT JOIN playertable p ON p.ID = l.player_id
WHERE l.period_type = ? AND l.period_start = ?
ORDER BY l.points DESC, l.goal_difference DESC, l.goals_for DESC, pname ASC
SQL;
    $sql .= $limitSql;

    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        $error = mysqli_error($con);

        return null;
    }
    mysqli_stmt_bind_param($stmt, 'ss', $periodType, $periodStart);
    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);

        return null;
    }
    $r = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($r)) {
        $rows[] = [
            'id' => (int) $row['pid'],
            'name' => (string) $row['pname'],
            'played' => (int) $row['played'],
            'wins' => (int) $row['wins'],
            'draws' => (int) $row['draws'],
            'losses' => (int) $row['losses'],
            'gf' => (int) $row['gf'],
            'ga' => (int) $row['ga'],
            'gd' => (int) $row['gd'],
            'pts' => (int) $row['pts'],
        ];
    }
    mysqli_free_result($r);
    mysqli_stmt_close($stmt);

    if ($rows !== [] && !function_exists('k2_league_sort_rows')) {
        require_once __DIR__ . '/league_standings.php';
    }
    if ($rows !== [] && function_exists('k2_league_load_first_games')) {
        $firstGames = k2_league_load_first_games($con, $start, $end);
        foreach ($rows as &$row) {
            $row['player_id'] = (int) $row['id'];
        }
        unset($row);
        $rows = k2_league_attach_first_games($rows, $firstGames);
        $rows = k2_league_sort_rows('points', $rows);
    }

    $totalGames = 0;
    $countStmt = mysqli_prepare($con, 'SELECT COALESCE(SUM(played), 0) AS appearances FROM player_period_league WHERE period_type = ? AND period_start = ?');
    if ($countStmt !== false) {
        mysqli_stmt_bind_param($countStmt, 'ss', $periodType, $periodStart);
        if (mysqli_stmt_execute($countStmt)) {
            $cr = mysqli_stmt_get_result($countStmt);
            $crow = mysqli_fetch_assoc($cr);
            $totalGames = intdiv((int) ($crow['appearances'] ?? 0), 2);
            mysqli_free_result($cr);
        }
        mysqli_stmt_close($countStmt);
    }

    return [
        'label' => $label,
        'start' => $start,
        'end' => $end,
        'rows' => $rows,
        'total_games' => $totalGames,
        'month_offset' => $periodOffset,
    ];
}

/**
 * Fallback when player_period_league is absent: scan ratedresults for the period bounds.
 *
 * @return array{label: string, start: string, end: string, rows: list<array>, total_games: int, month_offset: int}|null
 */
function k2_status_league_from_ratedresults(
    mysqli $con,
    string $start,
    string $end,
    string $label,
    int $periodOffset,
    ?int $limit,
    ?string &$error = null
): ?array {
    $limitSql = $limit === null ? '' : ' LIMIT ' . max(1, (int) $limit);

    $sql = <<<'SQL'
SELECT
  sides.pid,
  COALESCE(p.Name, CONCAT('#', sides.pid)) AS pname,
  COUNT(*) AS played,
  SUM(sides.w) AS wins,
  SUM(sides.d) AS draws,
  SUM(sides.l) AS losses,
  SUM(sides.gf) AS gf,
  SUM(sides.ga) AS ga,
  SUM(sides.pts) AS pts
FROM (
  SELECT
    idA AS pid,
    CASE WHEN ActualScore = 1 THEN 1 ELSE 0 END AS w,
    CASE WHEN ActualScore = 0.5 THEN 1 ELSE 0 END AS d,
    CASE WHEN ActualScore = 0 THEN 1 ELSE 0 END AS l,
    GoalsA AS gf,
    GoalsB AS ga,
    CASE WHEN ActualScore = 1 THEN 3 WHEN ActualScore = 0.5 THEN 1 ELSE 0 END AS pts
  FROM ratedresults
  WHERE `Date` >= ? AND `Date` < ?
  UNION ALL
  SELECT
    idB AS pid,
    CASE WHEN ActualScore = 0 THEN 1 ELSE 0 END AS w,
    CASE WHEN ActualScore = 0.5 THEN 1 ELSE 0 END AS d,
    CASE WHEN ActualScore = 1 THEN 1 ELSE 0 END AS l,
    GoalsB AS gf,
    GoalsA AS ga,
    CASE WHEN ActualScore = 0 THEN 3 WHEN ActualScore = 0.5 THEN 1 ELSE 0 END AS pts
  FROM ratedresults
  WHERE `Date` >= ? AND `Date` < ?
) AS sides
LEFT JOIN playertable p ON p.ID = sides.pid
GROUP BY sides.pid
ORDER BY pts DESC, (SUM(sides.gf) - SUM(sides.ga)) DESC, SUM(sides.gf) DESC, pname ASC
SQL;
    $sql .= $limitSql;

    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        $error = mysqli_error($con);

        return null;
    }
    mysqli_stmt_bind_param($stmt, 'ssss', $start, $end, $start, $end);
    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);

        return null;
    }
    $r = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($r)) {
        $gf = (int) $row['gf'];
        $ga = (int) $row['ga'];
        $rows[] = [
            'id' => (int) $row['pid'],
            'name' => (string) $row['pname'],
            'played' => (int) $row['played'],
            'wins' => (int) $row['wins'],
            'draws' => (int) $row['draws'],
            'losses' => (int) $row['losses'],
            'gf' => $gf,
            'ga' => $ga,
            'gd' => $gf - $ga,
            'pts' => (int) $row['pts'],
        ];
    }
    mysqli_free_result($r);
    mysqli_stmt_close($stmt);

    $totalGames = 0;
    $countStmt = mysqli_prepare(
        $con,
        'SELECT COUNT(*) AS c FROM ratedresults WHERE `Date` >= ? AND `Date` < ?'
    );
    if ($countStmt !== false) {
        mysqli_stmt_bind_param($countStmt, 'ss', $start, $end);
        if (mysqli_stmt_execute($countStmt)) {
            $cr = mysqli_stmt_get_result($countStmt);
            $crow = mysqli_fetch_assoc($cr);
            $totalGames = (int) ($crow['c'] ?? 0);
            mysqli_free_result($cr);
        }
        mysqli_stmt_close($countStmt);
    }

    return [
        'label' => $label,
        'start' => $start,
        'end' => $end,
        'rows' => $rows,
        'total_games' => $totalGames,
        'month_offset' => $periodOffset,
    ];
}

/**
 * @return array{label: string, start: string, end: string, rows: list<array>, total_games: int, month_offset: int, period: string, period_offset: int}|null
 */
function k2_status_league(
    mysqli $con,
    string $period,
    ?int $limit = null,
    int $periodOffset = 0,
    ?DateTimeImmutable $serverNow = null,
    ?string &$error = null
): ?array {
    $error = null;
    if (!in_array($period, ['day', 'week', 'month', 'year'], true)) {
        $error = 'invalid_period';

        return null;
    }

    $bounds = k2_status_league_period_bounds($period, $periodOffset, $serverNow);
    if ($bounds === null) {
        $error = 'invalid_period';

        return null;
    }

    if (k2_status_table_exists($con, 'player_period_league')) {
        $periodStart = substr($bounds['start'], 0, 10);
        $league = k2_status_league_from_period_league(
            $con,
            $period,
            $periodStart,
            $bounds['start'],
            $bounds['end'],
            $bounds['label'],
            $periodOffset,
            $limit,
            $error
        );
    } else {
        $league = k2_status_league_from_ratedresults(
            $con,
            $bounds['start'],
            $bounds['end'],
            $bounds['label'],
            $periodOffset,
            $limit,
            $error
        );
    }

    if ($league === null) {
        return null;
    }

    $league['period'] = $period;
    $league['period_offset'] = $periodOffset;

    return $league;
}

/**
 * Activity picker key aligned with league period bounds.
 *
 * @param array{start: string, end: string, label: string} $bounds
 */
function k2_status_period_activity_key_from_bounds(string $period, array $bounds): ?string
{
    $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $bounds['start']);
    if (!$start) {
        return null;
    }

    return match ($period) {
        'day', 'week' => $start->format('Y-m-d'),
        'month' => $start->format('Y-m'),
        'year' => $start->format('Y'),
        default => null,
    };
}

/**
 * @return array{start: string, end: string, label: string}|null
 */
function k2_status_bounds_from_period_key(string $period, string $key): ?array
{
    if (!function_exists('k2_period_activity_normalize_key')) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/period_activity_leaderboard_query.php';
    }
    if (function_exists('k2_site_ensure_utc')) {
        k2_site_ensure_utc();
    }
    $utc = new DateTimeZone('UTC');

    $normalized = k2_period_activity_normalize_key($period, $key);
    if ($normalized === null) {
        return null;
    }

    switch ($period) {
        case 'day':
            $start = new DateTimeImmutable($normalized . ' 00:00:00', $utc);
            $end = $start->modify('+1 day');
            $label = k2_format_calendar_day_label($normalized);
            break;
        case 'week':
            $start = new DateTimeImmutable($normalized . ' 00:00:00', $utc);
            $end = $start->modify('+1 week');
            $label = k2_format_calendar_week_label($start->format('Y-m-d'));
            break;
        case 'month':
            $start = new DateTimeImmutable($normalized . '-01 00:00:00', $utc);
            $end = $start->modify('+1 month');
            $label = $start->format('F Y');
            break;
        case 'year':
            $start = new DateTimeImmutable($normalized . '-01-01 00:00:00', $utc);
            $end = $start->modify('+1 year');
            $label = $normalized;
            break;
        default:
            return null;
    }

    return [
        'start' => $start->format('Y-m-d H:i:s'),
        'end' => $end->format('Y-m-d H:i:s'),
        'label' => $label,
    ];
}

/**
 * Points league for an explicit calendar key (archive / API).
 *
 * @return array{label: string, start: string, end: string, rows: list<array>, total_games: int, period: string}|null
 */
function k2_status_league_for_key(
    mysqli $con,
    string $period,
    string $key,
    ?int $limit = null,
    ?string &$error = null
): ?array {
    $error = null;
    if (!in_array($period, ['day', 'week', 'month', 'year'], true)) {
        $error = 'invalid_period';

        return null;
    }

    $bounds = k2_status_bounds_from_period_key($period, $key);
    if ($bounds === null) {
        $error = 'invalid_key';

        return null;
    }

    $periodStart = substr($bounds['start'], 0, 10);

    if (k2_status_table_exists($con, 'player_period_league')) {
        $league = k2_status_league_from_period_league(
            $con,
            $period,
            $periodStart,
            $bounds['start'],
            $bounds['end'],
            $bounds['label'],
            0,
            $limit,
            $error
        );
    } else {
        $league = k2_status_league_from_ratedresults(
            $con,
            $bounds['start'],
            $bounds['end'],
            $bounds['label'],
            0,
            $limit,
            $error
        );
    }

    if ($league === null) {
        return null;
    }

    $league['period'] = $period;
    $league['period_offset'] = 0;
    $league['activity_key'] = k2_status_period_activity_key_from_bounds($period, $bounds);

    return $league;
}

/**
 * @return array{
 *   default_period: string,
 *   activity_limit: int,
 *   periods: array<string, array<string, array<string, mixed>>>,
 *   current_keys: array<string, string>,
 *   day_min: string,
 *   day_max: string,
 *   first_rated_day: string,
 *   week_choices: list<string>,
 *   month_choices: list<string>,
 *   year_choices: list<string>
 * }
 */
function k2_status_build_period_competitions(mysqli $con, DateTimeImmutable $serverNow): array
{
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/period_activity_leaderboard_query.php';

    $periods = [];
    $currentKeys = [];
    $choicesErr = null;

    foreach (['day', 'week', 'month', 'year'] as $period) {
        $bounds = k2_status_league_period_bounds($period, 0, $serverNow);
        $key = $bounds !== null ? k2_status_period_activity_key_from_bounds($period, $bounds) : null;
        $currentKeys[$period] = $key ?? '';

        // Year tiebreaker window is expensive (~300 ms × 2). Initial HTML only renders Week;
        // status-period-competitions.js prewarms year via JSON APIs after load.
        if ($period === 'year') {
            $periods[$period] = [
                'points' => null,
                'points_error' => null,
                'activity' => [
                    'entries' => [],
                    'total_games' => 0,
                    'key' => $key ?? '',
                    'label' => $key !== null ? k2_format_period_activity_label($period, $key) : '',
                    'error' => null,
                ],
                'day_games' => [],
                'day_games_error' => null,
            ];
            continue;
        }

        $pointsError = null;
        $points = k2_status_league($con, $period, null, 0, $serverNow, $pointsError);

        $activityError = null;
        $entries = $key !== null
            ? k2_period_activity_leaderboard_entries($con, $period, $key, 0, $activityError)
            : [];

        $totalGames = $key !== null ? k2_period_activity_total_games($con, $period, $key) : 0;

        $dayGames = [];
        $dayGamesError = null;
        if ($period === 'day' && $key !== null) {
            $dayGames = k2_status_rated_games_for_calendar_day($con, $key, $dayGamesError) ?? [];
        }

        $periods[$period] = [
            'points' => $points,
            'points_error' => $pointsError,
            'activity' => [
                'entries' => $entries,
                'total_games' => $totalGames,
                'key' => $key ?? '',
                'label' => $key !== null ? k2_format_period_activity_label($period, $key) : '',
                'error' => $activityError,
            ],
            'day_games' => $dayGames,
            'day_games_error' => $dayGamesError,
        ];
    }

    $dayBounds = k2_period_activity_day_bounds($con, $choicesErr);
    $today = $serverNow->format('Y-m-d');
    $firstRatedDay = $today;
    $rFirst = mysqli_query($con, 'SELECT MIN(DATE(`Date`)) AS d FROM ratedresults');
    if ($rFirst !== false) {
        $rowFirst = mysqli_fetch_assoc($rFirst);
        mysqli_free_result($rFirst);
        if (!empty($rowFirst['d'])) {
            $firstRatedDay = (string) $rowFirst['d'];
        }
    }
    $dayMin = $dayBounds['min'] ?? $today;
    $dayMax = $dayBounds['max'] ?? $today;
    if ($dayMin === $dayMax || $dayBounds['min'] === null) {
        $dayMin = $firstRatedDay;
    }
    if ($dayMax < $today) {
        $dayMax = $today;
    }
    if ($dayMin < $firstRatedDay) {
        $dayMin = $firstRatedDay;
    }
    $weekChoices = k2_period_activity_available_keys($con, 'week', $choicesErr);
    $monthChoices = k2_period_activity_available_keys($con, 'month', $choicesErr);
    $yearChoices = k2_period_activity_available_keys($con, 'year', $choicesErr);

    foreach (['week', 'month', 'year'] as $p) {
        $ck = $currentKeys[$p];
        if ($ck !== '' && $p === 'week' && !in_array($ck, $weekChoices, true)) {
            array_unshift($weekChoices, $ck);
        }
        if ($ck !== '' && $p === 'month' && !in_array($ck, $monthChoices, true)) {
            array_unshift($monthChoices, $ck);
        }
        if ($ck !== '' && $p === 'year' && !in_array($ck, $yearChoices, true)) {
            array_unshift($yearChoices, $ck);
        }
    }

    return [
        'default_period' => 'week',
        'activity_limit' => 0,
        'periods' => $periods,
        'current_keys' => $currentKeys,
        'day_min' => $dayMin,
        'day_max' => $dayMax,
        'first_rated_day' => $firstRatedDay,
        'week_choices' => $weekChoices,
        'month_choices' => $monthChoices,
        'year_choices' => $yearChoices,
    ];
}

/** @return list<array{id: int, name: string}>|null */
function k2_status_online_players(mysqli $con, ?string &$error = null): ?array
{
    $error = null;
    $r = mysqli_query(
        $con,
        'SELECT ID, Name FROM playertable WHERE COALESCE(IsOnline, 0) <> 0 ORDER BY LastLogin ASC, ID ASC'
    );
    if ($r === false) {
        $error = mysqli_error($con);

        return null;
    }
    $out = [];
    while ($row = mysqli_fetch_assoc($r)) {
        $out[] = ['id' => (int) $row['ID'], 'name' => (string) $row['Name']];
    }
    mysqli_free_result($r);

    return $out;
}

/** @return list<array{game_id: int, id_a: int, id_b: int, name_a: string, name_b: string, score_a: int, score_b: int, period: int, half_countdown: int, start: string}>|null */
function k2_status_live_games(mysqli $con, int $limit = 10, ?string &$error = null): ?array
{
    $error = null;
    $limit = max(1, min(30, $limit));
    $sql = 'SELECT GameID, HostID, SlaveID, NameA, NameB, ScoreA, ScoreB, GamePeriod, HalfCountdown, StartTime FROM resulttable '
        . 'WHERE HasStarted = 1 AND HasFinished = 0 AND Shelved = 0 '
        . 'ORDER BY StartTime DESC LIMIT ' . $limit;
    $r = mysqli_query($con, $sql);
    if ($r === false) {
        $error = mysqli_error($con);

        return null;
    }
    $out = [];
    while ($row = mysqli_fetch_assoc($r)) {
        $out[] = [
            'game_id' => (int) $row['GameID'],
            'id_a' => (int) $row['HostID'],
            'id_b' => (int) $row['SlaveID'],
            'name_a' => (string) $row['NameA'],
            'name_b' => (string) $row['NameB'],
            'score_a' => (int) $row['ScoreA'],
            'score_b' => (int) $row['ScoreB'],
            'period' => (int) $row['GamePeriod'],
            'half_countdown' => (int) $row['HalfCountdown'],
            'start' => (string) ($row['StartTime'] ?? ''),
        ];
    }
    mysqli_free_result($r);

    return $out;
}

/** @return list<array{id: int, name: string, at: string}>|null */
function k2_status_recent_logins(mysqli $con, int $limit = 10, ?string &$error = null): ?array
{
    $error = null;
    $limit = max(1, min(30, $limit));
    // Lobby recency — not ladder eligibility; do not gate by Display (see Online now panel).
    $sql = 'SELECT ID, Name, LastLogin FROM playertable '
        . 'ORDER BY LastLogin DESC LIMIT ' . $limit;
    $r = mysqli_query($con, $sql);
    if ($r === false) {
        $error = mysqli_error($con);

        return null;
    }
    $out = [];
    while ($row = mysqli_fetch_assoc($r)) {
        $out[] = [
            'id' => (int) $row['ID'],
            'name' => (string) $row['Name'],
            'at' => (string) $row['LastLogin'],
        ];
    }
    mysqli_free_result($r);

    return $out;
}

/** @return list<array{id: int, name: string, joined: string}>|null */
function k2_status_recent_registrations(mysqli $con, int $limit = 10, ?string &$error = null): ?array
{
    $error = null;
    $limit = max(1, min(30, $limit));
    // Lobby recency — not ladder eligibility; do not gate by Display (see Online now panel).
    $sql = 'SELECT ID, Name, JoinDate FROM playertable '
        . 'ORDER BY JoinDate DESC LIMIT ' . $limit;
    $r = mysqli_query($con, $sql);
    if ($r === false) {
        $error = mysqli_error($con);

        return null;
    }
    $out = [];
    while ($row = mysqli_fetch_assoc($r)) {
        $out[] = [
            'id' => (int) $row['ID'],
            'name' => (string) $row['Name'],
            'joined' => (string) $row['JoinDate'],
        ];
    }
    mysqli_free_result($r);

    return $out;
}

function k2_status_rated_games_to_list_items(array $rows): array
{
    if ($rows === []) {
        return [];
    }

    return array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'id_a' => (int) $row['idA'],
            'id_b' => (int) $row['idB'],
            'name_a' => (string) $row['NameA'],
            'name_b' => (string) $row['NameB'],
            'goals_a' => (int) $row['GoalsA'],
            'goals_b' => (int) $row['GoalsB'],
            'at' => (string) $row['Date'],
        ];
    }, $rows);
}

/**
 * Rated games on one UTC calendar day (Status Daily tab list).
 *
 * @return list<array{id: int, name_a: string, name_b: string, goals_a: int, goals_b: int, at: string, id_a: int, id_b: int}>|null
 */
function k2_status_rated_games_for_calendar_day(mysqli $con, string $dayYmd, ?string &$error = null): ?array
{
    $error = null;
    $bounds = k2_status_bounds_from_period_key('day', $dayYmd);
    if ($bounds === null) {
        $error = 'invalid_day';

        return null;
    }

    $sql = 'SELECT id, idA, idB, NameA, NameB, GoalsA, GoalsB, `Date` FROM ratedresults '
        . 'WHERE `Date` >= ? AND `Date` < ? ORDER BY `Date` DESC';
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        $error = mysqli_error($con);

        return null;
    }
    mysqli_stmt_bind_param($stmt, 'ss', $bounds['start'], $bounds['end']);
    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);

        return null;
    }
    $r = mysqli_stmt_get_result($stmt);
    if ($r === false) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);

        return null;
    }
    $raw = [];
    while ($row = mysqli_fetch_assoc($r)) {
        $raw[] = $row;
    }
    mysqli_free_result($r);
    mysqli_stmt_close($stmt);

    $nameMap = k2_player_display_names_for_rated_rows($con, $raw);

    return k2_status_rated_games_to_list_items(k2_rated_games_apply_display_names($raw, $nameMap));
}

/** @return list<array{id: int, name_a: string, name_b: string, goals_a: int, goals_b: int, at: string, id_a: int, id_b: int}>|null */
function k2_status_recent_rated_games(mysqli $con, int $limit = 10, ?string &$error = null): ?array
{
    $error = null;
    $limit = max(1, min(30, $limit));
    $sql = 'SELECT id, idA, idB, NameA, NameB, GoalsA, GoalsB, `Date` FROM ratedresults '
        . 'ORDER BY `Date` DESC LIMIT ' . $limit;
    $r = mysqli_query($con, $sql);
    if ($r === false) {
        $error = mysqli_error($con);

        return null;
    }
    $raw = [];
    while ($row = mysqli_fetch_assoc($r)) {
        $raw[] = $row;
    }
    mysqli_free_result($r);

    $nameMap = k2_player_display_names_for_rated_rows($con, $raw);

    return k2_status_rated_games_to_list_items(k2_rated_games_apply_display_names($raw, $nameMap));
}

function k2_status_load_room(mysqli $con, ?string &$error = null): ?array
{
    $error = null;
    $err = null;

    $serverClock = k2_status_server_clock($con);
    $serverNow = $serverClock['now'];

    $arc = k2_status_arc_ticker($con, $err);
    if ($arc === null) {
        $error = $err;

        return null;
    }

    $panelErr = null;
    $activeTop = k2_status_active_top_rated($con, $panelErr);
    $activeTopError = $panelErr;

    $periodCompetitions = k2_status_build_period_competitions($con, $serverNow);

    $leagues = [];
    foreach (['day', 'week', 'month', 'year'] as $period) {
        $bundle = is_array($periodCompetitions['periods'][$period] ?? null) ? $periodCompetitions['periods'][$period] : [];
        $leagues[$period] = [
            'current' => $bundle['points'] ?? null,
            'current_error' => $bundle['points_error'] ?? null,
            'prev' => null,
            'prev_error' => null,
        ];
    }
    $monthlyCurrent = $leagues['month']['current'] ?? null;
    $monthlyPrev = null;
    $monthlyCurrentError = $leagues['month']['current_error'] ?? null;
    $monthlyPrevError = null;

    $panelErr = null;
    $online = k2_status_online_players($con, $panelErr) ?? [];

    $panelErr = null;
    $liveGames = k2_status_live_games($con, 10, $panelErr) ?? [];

    $panelErr = null;
    $logins = k2_status_recent_logins($con, 10, $panelErr) ?? [];

    $panelErr = null;
    $registrations = k2_status_recent_registrations($con, 10, $panelErr) ?? [];

    $panelErr = null;
    $recentGames = k2_status_recent_rated_games($con, 10, $panelErr) ?? [];

    require_once __DIR__ . '/status_room_pulse.php';
    $weekKey = '';
    if (is_array($periodCompetitions['current_keys'] ?? null)) {
        $weekKey = (string) ($periodCompetitions['current_keys']['week'] ?? '');
    }
    $pulse = k2_status_pulse_signals_for_page($con, 'week', $weekKey);

    return [
        'server_clock' => [
            'now_sql' => $serverClock['now_sql'],
            'now_epoch' => $serverNow->getTimestamp(),
            'source' => $serverClock['source'],
            'timezone' => $serverClock['timezone'],
        ],
        'arc' => $arc,
        'active_top' => $activeTop ?? [],
        'active_top_error' => $activeTopError,
        'leagues' => $leagues,
        'period_competitions' => $periodCompetitions,
        'monthly_current' => $monthlyCurrent,
        'monthly_current_error' => $monthlyCurrentError,
        'monthly_prev' => $monthlyPrev,
        'monthly_prev_error' => $monthlyPrevError,
        'online' => $online,
        'live_games' => $liveGames,
        'logins' => $logins,
        'registrations' => $registrations,
        'recent_games' => $recentGames,
        'pulse' => $pulse,
    ];
}
