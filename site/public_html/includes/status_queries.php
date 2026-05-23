<?php
/**
 * Status room data loaders (hub status.php v1).
 * Requires mysqli $con and playertable / resulttable / ratedresults / generalstatstable.
 */

declare(strict_types=1);

function k2_status_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
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

/** @return array{online: int, live_games: int, last_login: ?string, last_login_ago: string, games_played: ?int, goals_scored: ?int}|null */
function k2_status_pulse(mysqli $con, ?string &$error = null): ?array
{
    $error = null;
    $online = 0;
    $live = 0;
    $lastLogin = null;
    $gamesPlayed = null;
    $goalsScored = null;

    $r = mysqli_query($con, 'SELECT COUNT(*) AS c FROM playertable WHERE Display = 1 AND IsOnline = 1');
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

    $r = mysqli_query($con, 'SELECT MAX(LastLogin) AS t FROM playertable WHERE Display = 1');
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
function k2_status_active_top_rated(mysqli $con, int $limit = 20, ?string &$error = null): ?array
{
    $error = null;
    $limit = max(1, min(50, $limit));
    $sql = 'SELECT ID, Name, Rating, LastGame, NumberGames FROM playertable '
        . 'WHERE Display = 1 AND LastGame >= DATE_SUB(NOW(), INTERVAL 12 MONTH) '
        . 'ORDER BY Rating DESC LIMIT ' . $limit;
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
            'rating' => (int) round((float) $row['Rating']),
            'last_game' => (string) $row['LastGame'],
            'games' => (int) ($row['NumberGames'] ?? 0),
        ];
    }
    mysqli_free_result($r);

    return $out;
}

/**
 * @param int $monthOffset 0 = current calendar month, -1 = previous month (server TZ)
 *
 * @return array{label: string, start: string, end: string, rows: list<array>, total_games: int, month_offset: int}|null
 */
function k2_status_monthly_league(mysqli $con, int $limit = 20, int $monthOffset = 0, ?string &$error = null): ?array
{
    $error = null;
    $limit = max(1, min(50, $limit));
    $monthOffset = max(-24, min(0, $monthOffset));
    $base = strtotime('first day of this month');
    if ($monthOffset !== 0) {
        $base = strtotime((string) $monthOffset . ' month', $base);
    }
    $start = date('Y-m-01 00:00:00', $base);
    $end = date('Y-m-01 00:00:00', strtotime('+1 month', $base));
    $label = date('F Y', $base);

    $sql = <<<'SQL'
SELECT
  pid,
  MAX(pname) AS pname,
  COUNT(*) AS played,
  SUM(w) AS wins,
  SUM(d) AS draws,
  SUM(l) AS losses,
  SUM(gf) AS gf,
  SUM(ga) AS ga,
  SUM(pts) AS pts
FROM (
  SELECT
    idA AS pid,
    NameA AS pname,
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
    NameB AS pname,
    CASE WHEN ActualScore = 0 THEN 1 ELSE 0 END AS w,
    CASE WHEN ActualScore = 0.5 THEN 1 ELSE 0 END AS d,
    CASE WHEN ActualScore = 1 THEN 1 ELSE 0 END AS l,
    GoalsB AS gf,
    GoalsA AS ga,
    CASE WHEN ActualScore = 0 THEN 3 WHEN ActualScore = 0.5 THEN 1 ELSE 0 END AS pts
  FROM ratedresults
  WHERE `Date` >= ? AND `Date` < ?
) AS sides
GROUP BY pid
ORDER BY pts DESC, (SUM(gf) - SUM(ga)) DESC, SUM(gf) DESC, pname ASC
LIMIT ?
SQL;

    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        $error = mysqli_error($con);

        return null;
    }
    mysqli_stmt_bind_param($stmt, 'ssssi', $start, $end, $start, $end, $limit);
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
        'month_offset' => $monthOffset,
    ];
}

/** @return list<array{id: int, name: string}>|null */
function k2_status_online_players(mysqli $con, ?string &$error = null): ?array
{
    $error = null;
    $r = mysqli_query(
        $con,
        'SELECT ID, Name FROM playertable WHERE Display = 1 AND IsOnline = 1 ORDER BY Name ASC'
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

/** @return list<array{game_id: int, id_a: int, id_b: int, name_a: string, name_b: string, score_a: int, score_b: int, period: int, start: string}>|null */
function k2_status_live_games(mysqli $con, int $limit = 10, ?string &$error = null): ?array
{
    $error = null;
    $limit = max(1, min(30, $limit));
    $sql = 'SELECT GameID, HostID, SlaveID, NameA, NameB, ScoreA, ScoreB, GamePeriod, StartTime FROM resulttable '
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
    $sql = 'SELECT ID, Name, LastLogin FROM playertable WHERE Display = 1 '
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
    $sql = 'SELECT ID, Name, JoinDate FROM playertable WHERE Display = 1 '
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
    $out = [];
    while ($row = mysqli_fetch_assoc($r)) {
        $out[] = [
            'id' => (int) $row['id'],
            'id_a' => (int) $row['idA'],
            'id_b' => (int) $row['idB'],
            'name_a' => (string) $row['NameA'],
            'name_b' => (string) $row['NameB'],
            'goals_a' => (int) $row['GoalsA'],
            'goals_b' => (int) $row['GoalsB'],
            'at' => (string) $row['Date'],
        ];
    }
    mysqli_free_result($r);

    return $out;
}

/** @param string $leagueMonth `current` or `prev` */
function k2_status_load_room(mysqli $con, string $leagueMonth = 'current', ?string &$error = null): ?array
{
    $error = null;
    $err = null;
    $leagueMonth = ($leagueMonth === 'prev') ? 'prev' : 'current';
    $monthOffset = $leagueMonth === 'prev' ? -1 : 0;

    $pulse = k2_status_pulse($con, $err);
    if ($pulse === null) {
        $error = $err;

        return null;
    }

    $panelErr = null;
    $activeTop = k2_status_active_top_rated($con, 20, $panelErr);
    $activeTopError = $panelErr;

    $panelErr = null;
    $monthly = k2_status_monthly_league($con, 20, $monthOffset, $panelErr);
    $monthlyError = $panelErr;

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

    return [
        'pulse' => $pulse,
        'league_month' => $leagueMonth,
        'active_top' => $activeTop ?? [],
        'active_top_error' => $activeTopError,
        'monthly' => $monthly,
        'monthly_error' => $monthlyError,
        'online' => $online,
        'live_games' => $liveGames,
        'logins' => $logins,
        'registrations' => $registrations,
        'recent_games' => $recentGames,
    ];
}
