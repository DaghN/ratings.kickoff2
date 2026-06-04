<?php
/**
 * League standings sort, awards persistence, and rebuild (rules: docs/leagues-rules-spec.md).
 */
declare(strict_types=1);

require_once __DIR__ . '/status_queries.php';
require_once __DIR__ . '/period_activity_leaderboard_query.php';

/** @var list<string> */
const K2_LEAGUE_PERIOD_TYPES = ['day', 'week', 'month', 'year'];

/** @var list<string> */
const K2_LEAGUE_KINDS = ['points', 'activity'];

/** @var array<string, int> */
const K2_LEAGUE_WIN_MILESTONE_THRESHOLDS = [
    'league_wins_10' => 10,
    'league_wins_50' => 50,
    'league_wins_100' => 100,
    'league_wins_500' => 500,
];

function k2_league_table_exists(mysqli $con, string $table): bool
{
    return k2_status_table_exists($con, $table);
}

function k2_league_key_from_period_start(string $periodType, string $periodStart): ?string
{
    return match ($periodType) {
        'day', 'week' => $periodStart,
        'month' => substr($periodStart, 0, 7),
        'year' => substr($periodStart, 0, 4),
        default => null,
    };
}

/**
 * @return array{start: string, end: string, label: string}|null
 */
function k2_league_bounds_for_start(string $periodType, string $periodStart): ?array
{
    $key = k2_league_key_from_period_start($periodType, $periodStart);
    if ($key === null) {
        return null;
    }

    return k2_status_bounds_from_period_key($periodType, $key);
}

function k2_league_period_end_datetime(string $periodType, string $periodStart): ?string
{
    $bounds = k2_league_bounds_for_start($periodType, $periodStart);

    return $bounds['end'] ?? null;
}

function k2_league_period_is_closed(string $periodType, string $periodStart, ?DateTimeImmutable $asOf = null): bool
{
    $end = k2_league_period_end_datetime($periodType, $periodStart);
    if ($end === null) {
        return false;
    }
    $asOf = $asOf ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

    return $asOf >= new DateTimeImmutable($end, new DateTimeZone('UTC'));
}

/**
 * @return array<int, array{first_game_id: int, first_game_side: string}>
 */
function k2_league_load_first_games(
    mysqli $con,
    string $periodStart,
    string $periodEnd,
    ?DateTimeImmutable $maxGameDate = null
): array {
    $maxClause = '';
    $types = 'ssss';
    $params = [$periodStart, $periodEnd, $periodStart, $periodEnd];
    if ($maxGameDate !== null) {
        $maxClause = ' AND `Date` <= ?';
        $maxStr = $maxGameDate->format('Y-m-d H:i:s');
        // Per UNION branch: start, end, max — not start, end, start, end, max, max.
        $types = 'ssssss';
        $params = [$periodStart, $periodEnd, $maxStr, $periodStart, $periodEnd, $maxStr];
    }

    $sql = <<<SQL
SELECT player_id, game_id, side FROM (
  SELECT idA AS player_id, id AS game_id, 'A' AS side,
         ROW_NUMBER() OVER (PARTITION BY idA ORDER BY `Date` ASC, id ASC) AS rn
  FROM ratedresults
  WHERE `Date` >= ? AND `Date` < ? AND NewRatingA IS NOT NULL{$maxClause}
  UNION ALL
  SELECT idB AS player_id, id AS game_id, 'B' AS side,
         ROW_NUMBER() OVER (PARTITION BY idB ORDER BY `Date` ASC, id ASC) AS rn
  FROM ratedresults
  WHERE `Date` >= ? AND `Date` < ? AND NewRatingA IS NOT NULL{$maxClause}
) ranked
WHERE rn = 1
SQL;

    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);

        return [];
    }
    $res = mysqli_stmt_get_result($stmt);
    $map = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $pid = (int) $row['player_id'];
        $map[$pid] = [
            'first_game_id' => (int) $row['game_id'],
            'first_game_side' => (string) $row['side'] === 'B' ? 'B' : 'A',
        ];
    }
    mysqli_free_result($res);
    mysqli_stmt_close($stmt);

    return $map;
}

/**
 * @param array<int, array{first_game_id: int, first_game_side: string}> $firstGames
 */
function k2_league_attach_first_games(array $rows, array $firstGames): array
{
    foreach ($rows as &$row) {
        $pid = (int) ($row['player_id'] ?? $row['id'] ?? 0);
        $fg = $firstGames[$pid] ?? null;
        $row['first_game_id'] = $fg['first_game_id'] ?? 0;
        $row['first_game_side'] = $fg['first_game_side'] ?? 'A';
        // Sort tie-break only — never persist (see write_instance_awards).
        $row['first_game_sort_key'] = $fg['first_game_id'] ?? PHP_INT_MAX;
    }
    unset($row);

    return $rows;
}

function k2_league_compare_points(array $a, array $b): int
{
    $fields = [
        ['pts', 'desc'],
        ['gd', 'desc'],
        ['gf', 'desc'],
        ['played', 'desc'],
        ['first_game_sort_key', 'asc'],
    ];
    foreach ($fields as [$key, $dir]) {
        $av = (int) ($a[$key] ?? 0);
        $bv = (int) ($b[$key] ?? 0);
        if ($av !== $bv) {
            return $dir === 'desc' ? $bv <=> $av : $av <=> $bv;
        }
    }
    if ((int) ($a['first_game_sort_key'] ?? $a['first_game_id'] ?? 0)
        === (int) ($b['first_game_sort_key'] ?? $b['first_game_id'] ?? 0)) {
        $aB = ($a['first_game_side'] ?? 'A') === 'B';
        $bB = ($b['first_game_side'] ?? 'A') === 'B';
        if ($aB !== $bB) {
            return $aB ? -1 : 1;
        }
    }

    $nameA = (string) ($a['name'] ?? $a['pname'] ?? '');
    $nameB = (string) ($b['name'] ?? $b['pname'] ?? '');

    return $nameA <=> $nameB;
}

function k2_league_compare_activity(array $a, array $b): int
{
    $av = (int) ($a['games'] ?? 0);
    $bv = (int) ($b['games'] ?? 0);
    if ($av !== $bv) {
        return $bv <=> $av;
    }
    $ag = (int) ($a['first_game_id'] ?? PHP_INT_MAX);
    $bg = (int) ($b['first_game_id'] ?? PHP_INT_MAX);
    if ($ag !== $bg) {
        return $ag <=> $bg;
    }
    if ($ag === $bg && $ag !== PHP_INT_MAX) {
        $aB = ($a['first_game_side'] ?? 'A') === 'B';
        $bB = ($b['first_game_side'] ?? 'A') === 'B';
        if ($aB !== $bB) {
            return $aB ? -1 : 1;
        }
    }

    $nameA = (string) ($a['player_name'] ?? $a['name'] ?? '');
    $nameB = (string) ($b['player_name'] ?? $b['name'] ?? '');

    return $nameA <=> $nameB;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function k2_league_sort_rows(string $leagueKind, array $rows): array
{
    if ($rows === []) {
        return [];
    }
    usort(
        $rows,
        $leagueKind === 'points' ? 'k2_league_compare_points' : 'k2_league_compare_activity'
    );

    return $rows;
}

/**
 * @param list<array<string, mixed>> $sorted
 * @return list<array<string, mixed>>
 */
function k2_league_apply_ranks(array $sorted): array
{
    $rank = 0;
    foreach ($sorted as &$row) {
        ++$rank;
        $row['rank'] = $rank;
    }
    unset($row);

    return $sorted;
}

/**
 * @return list<array{
 *   player_id: int,
 *   finish_rank: int,
 *   medal: string,
 *   is_winner: int,
 *   points: ?int,
 *   goal_difference: ?int,
 *   goals_for: ?int,
 *   played: ?int,
 *   games: ?int,
 *   first_game_id: int,
 *   first_game_side: string
 * }>
 */
function k2_league_podium_from_sorted(string $leagueKind, array $sorted): array
{
    $medals = [1 => 'gold', 2 => 'silver', 3 => 'bronze'];
    $out = [];
    $rank = 0;
    foreach ($sorted as $row) {
        ++$rank;
        if ($rank > 3) {
            break;
        }
        $pid = (int) ($row['player_id'] ?? $row['id'] ?? 0);
        $out[] = [
            'player_id' => $pid,
            'finish_rank' => $rank,
            'medal' => $medals[$rank],
            'is_winner' => $rank === 1 ? 1 : 0,
            'points' => $leagueKind === 'points' ? (int) ($row['pts'] ?? 0) : null,
            'goal_difference' => $leagueKind === 'points' ? (int) ($row['gd'] ?? 0) : null,
            'goals_for' => $leagueKind === 'points' ? (int) ($row['gf'] ?? 0) : null,
            'played' => $leagueKind === 'points' ? (int) ($row['played'] ?? 0) : null,
            'games' => $leagueKind === 'activity' ? (int) ($row['games'] ?? 0) : null,
            'first_game_id' => (int) ($row['first_game_id'] ?? 0),
            'first_game_side' => (string) ($row['first_game_side'] ?? 'A'),
        ];
    }

    return $out;
}

/**
 * @return list<array<string, mixed>>
 */
function k2_league_load_points_rows(mysqli $con, string $periodType, string $periodStart): array
{
    $sql = <<<'SQL'
SELECT
  l.player_id AS id,
  l.player_id,
  COALESCE(p.Name, CONCAT('#', l.player_id)) AS name,
  l.played,
  l.points AS pts,
  l.goal_difference AS gd,
  l.goals_for AS gf
FROM player_period_league l
LEFT JOIN playertable p ON p.ID = l.player_id
WHERE l.period_type = ? AND l.period_start = ?
SQL;
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'ss', $periodType, $periodStart);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);

        return [];
    }
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = [
            'id' => (int) $row['id'],
            'player_id' => (int) $row['player_id'],
            'name' => (string) $row['name'],
            'played' => (int) $row['played'],
            'pts' => (int) $row['pts'],
            'gd' => (int) $row['gd'],
            'gf' => (int) $row['gf'],
        ];
    }
    mysqli_free_result($res);
    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * @return list<array<string, mixed>>
 */
function k2_league_load_activity_rows(mysqli $con, string $periodType, string $periodStart): array
{
    $sql = <<<'SQL'
SELECT g.player_id, p.Name AS player_name, g.games
FROM player_period_games g
INNER JOIN playertable p ON p.ID = g.player_id
WHERE g.period_type = ? AND g.period_start = ?
SQL;
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'ss', $periodType, $periodStart);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);

        return [];
    }
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = [
            'player_id' => (int) $row['player_id'],
            'player_name' => (string) $row['player_name'],
            'games' => (int) $row['games'],
        ];
    }
    mysqli_free_result($res);
    mysqli_stmt_close($stmt);

    return $rows;
}

function k2_league_rated_games_for_period(mysqli $con, string $periodType, string $periodStart): int
{
    if (k2_league_table_exists($con, 'server_period_game_totals')) {
        $stmt = mysqli_prepare(
            $con,
            'SELECT rated_games FROM server_period_game_totals WHERE period_type = ? AND period_start = ?'
        );
        if ($stmt !== false) {
            mysqli_stmt_bind_param($stmt, 'ss', $periodType, $periodStart);
            if (mysqli_stmt_execute($stmt)) {
                $res = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($res);
                mysqli_free_result($res);
                mysqli_stmt_close($stmt);
                if ($row !== null) {
                    return (int) $row['rated_games'];
                }
            } else {
                mysqli_stmt_close($stmt);
            }
        }
    }

    $stmt = mysqli_prepare(
        $con,
        'SELECT COALESCE(SUM(played), 0) AS appearances FROM player_period_league WHERE period_type = ? AND period_start = ?'
    );
    if ($stmt === false) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'ss', $periodType, $periodStart);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);

        return 0;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_free_result($res);
    mysqli_stmt_close($stmt);

    return intdiv((int) ($row['appearances'] ?? 0), 2);
}

/**
 * @return list<array{period_type: string, period_start: string}>
 */
function k2_league_list_period_instances(mysqli $con): array
{
    $sql = <<<'SQL'
SELECT period_type, period_start FROM player_period_league
UNION
SELECT period_type, period_start FROM player_period_games
ORDER BY period_type, period_start
SQL;
    $res = mysqli_query($con, $sql);
    if ($res === false) {
        return [];
    }
    $out = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $out[] = [
            'period_type' => (string) $row['period_type'],
            'period_start' => (string) $row['period_start'],
        ];
    }
    mysqli_free_result($res);

    return $out;
}

/**
 * @return list<array<string, mixed>>
 */
function k2_league_build_sorted_standings(
    mysqli $con,
    string $leagueKind,
    string $periodType,
    string $periodStart,
    ?DateTimeImmutable $asOf = null
): array {
    $bounds = k2_league_bounds_for_start($periodType, $periodStart);
    if ($bounds === null) {
        return [];
    }
    $firstGames = k2_league_load_first_games($con, $bounds['start'], $bounds['end'], $asOf);
    $rows = $leagueKind === 'points'
        ? k2_league_load_points_rows($con, $periodType, $periodStart)
        : k2_league_load_activity_rows($con, $periodType, $periodStart);
    if ($rows === []) {
        return [];
    }
    $rows = k2_league_attach_first_games($rows, $firstGames);

    return k2_league_apply_ranks(k2_league_sort_rows($leagueKind, $rows));
}

function k2_league_delete_instance_awards(
    mysqli $con,
    string $leagueKind,
    string $periodType,
    string $periodStart
): void {
    $stmt = mysqli_prepare(
        $con,
        'DELETE FROM player_league_award WHERE league_kind = ? AND period_type = ? AND period_start = ?'
    );
    if ($stmt === false) {
        return;
    }
    mysqli_stmt_bind_param($stmt, 'sss', $leagueKind, $periodType, $periodStart);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * @param list<array<string, mixed>> $podium
 */
function k2_league_write_instance_awards(
    mysqli $con,
    string $leagueKind,
    string $periodType,
    string $periodStart,
    string $periodEnd,
    array $podium
): int {
    $sql = <<<'SQL'
INSERT INTO player_league_award (
  player_id, league_kind, period_type, period_start, period_end,
  finish_rank, medal, is_winner,
  points, goal_difference, goals_for, played, games,
  first_game_id, first_game_side
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
SQL;
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return 0;
    }
    $written = 0;
    foreach ($podium as $row) {
        $playerId = (int) $row['player_id'];
        $finishRank = (int) $row['finish_rank'];
        $medal = (string) $row['medal'];
        $isWinner = (int) $row['is_winner'];
        $points = $row['points'];
        $gd = $row['goal_difference'];
        $gf = $row['goals_for'];
        $played = $row['played'];
        $games = $row['games'];
        $firstGameId = (int) ($row['first_game_id'] ?? 0);
        if ($firstGameId <= 0) {
            continue;
        }
        $firstSide = (string) $row['first_game_side'];
        $bindTypes = 'i' . 'ssss' . 'i' . 's' . 'i' . 'iiiii' . 'i' . 's';
        mysqli_stmt_bind_param(
            $stmt,
            $bindTypes,
            $playerId,
            $leagueKind,
            $periodType,
            $periodStart,
            $periodEnd,
            $finishRank,
            $medal,
            $isWinner,
            $points,
            $gd,
            $gf,
            $played,
            $games,
            $firstGameId,
            $firstSide
        );
        if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
            ++$written;
        }
    }
    mysqli_stmt_close($stmt);

    return $written;
}

function k2_league_upsert_period_header(
    mysqli $con,
    string $leagueKind,
    string $periodType,
    string $periodStart,
    string $periodEnd,
    int $ratedGames,
    ?DateTimeImmutable $finalizedAt
): void {
    $finalized = $finalizedAt?->format('Y-m-d H:i:s');
    $sql = <<<'SQL'
INSERT INTO league_period (
  league_kind, period_type, period_start, period_end, rated_games, finalized_at
) VALUES (?, ?, ?, ?, ?, ?)
ON DUPLICATE KEY UPDATE
  period_end = VALUES(period_end),
  rated_games = VALUES(rated_games),
  finalized_at = VALUES(finalized_at)
SQL;
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return;
    }
    mysqli_stmt_bind_param(
        $stmt,
        'ssssis',
        $leagueKind,
        $periodType,
        $periodStart,
        $periodEnd,
        $ratedGames,
        $finalized
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function k2_league_rebuild_totals(mysqli $con): void
{
    mysqli_query($con, 'TRUNCATE TABLE player_league_totals');
    $sql = <<<'SQL'
INSERT INTO player_league_totals (player_id, wins, podiums, gold, silver, bronze)
SELECT
  player_id,
  SUM(is_winner) AS wins,
  COUNT(*) AS podiums,
  SUM(medal = 'gold') AS gold,
  SUM(medal = 'silver') AS silver,
  SUM(medal = 'bronze') AS bronze
FROM player_league_award
GROUP BY player_id
SQL;
    mysqli_query($con, $sql);
}

function k2_league_rebuild_slice_totals(mysqli $con): void
{
    if (!k2_league_table_exists($con, 'player_league_slice_totals')) {
        return;
    }

    mysqli_query($con, 'TRUNCATE TABLE player_league_slice_totals');

    if (!k2_league_table_exists($con, 'player_league_award')) {
        return;
    }

    $sql = <<<'SQL'
INSERT INTO player_league_slice_totals (player_id, league_kind, period_type, gold, silver, bronze, podiums)
SELECT
  player_id,
  league_kind,
  period_type,
  SUM(medal = 'gold') AS gold,
  SUM(medal = 'silver') AS silver,
  SUM(medal = 'bronze') AS bronze,
  COUNT(*) AS podiums
FROM player_league_award
GROUP BY player_id, league_kind, period_type
SQL;
    mysqli_query($con, $sql);
}

/**
 * Career medal breakdown by league kind + period (profile / APIs).
 *
 * @return list<array{league_kind: string, period_type: string, gold: int, silver: int, bronze: int, podiums: int}>
 */
function k2_league_player_slice_totals(mysqli $con, int $playerId): array
{
    if ($playerId <= 0 || !k2_league_table_exists($con, 'player_league_slice_totals')) {
        return [];
    }

    $stmt = mysqli_prepare(
        $con,
        'SELECT league_kind, period_type, gold, silver, bronze, podiums '
        . 'FROM player_league_slice_totals WHERE player_id = ? ORDER BY period_type, league_kind'
    );
    if ($stmt === false) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'i', $playerId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);

        return [];
    }
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = [
                'league_kind' => (string) $row['league_kind'],
                'period_type' => (string) $row['period_type'],
                'gold' => (int) $row['gold'],
                'silver' => (int) $row['silver'],
                'bronze' => (int) $row['bronze'],
                'podiums' => (int) $row['podiums'],
            ];
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);

    return $rows;
}

/** Rebuild career + slice aggregate tables from awards. */
function k2_league_rebuild_player_aggregates(mysqli $con): void
{
    k2_league_rebuild_totals($con);
    k2_league_rebuild_slice_totals($con);
}

function k2_league_sync_win_milestones(mysqli $con): void
{
    if (!k2_league_table_exists($con, 'player_milestones')) {
        return;
    }

    foreach (K2_LEAGUE_WIN_MILESTONE_THRESHOLDS as $milestoneKey => $threshold) {
        $sql = <<<'SQL'
INSERT INTO player_milestones (player_id, milestone_key, achieved_at, value)
SELECT w.player_id, ?, w.period_end, ?
FROM (
  SELECT player_id, period_end,
         ROW_NUMBER() OVER (PARTITION BY player_id ORDER BY period_end ASC, period_start ASC, league_kind ASC) AS win_num
  FROM player_league_award
  WHERE is_winner = 1
) w
LEFT JOIN player_milestones m
  ON m.player_id = w.player_id AND m.milestone_key = ?
WHERE w.win_num = ?
  AND m.player_id IS NULL
SQL;
        $stmt = mysqli_prepare($con, $sql);
        if ($stmt === false) {
            continue;
        }
        mysqli_stmt_bind_param($stmt, 'sisi', $milestoneKey, $threshold, $milestoneKey, $threshold);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Finalize one league instance if closed.
 * Returns true only when every podium medal row was persisted (strict — no empty finalized_at).
 */
function k2_league_finalize_instance(
    mysqli $con,
    string $leagueKind,
    string $periodType,
    string $periodStart,
    ?DateTimeImmutable $asOf = null,
    bool $force = false
): bool {
    if (!k2_league_table_exists($con, 'player_league_award')) {
        return false;
    }
    if (!$force && !k2_league_period_is_closed($periodType, $periodStart, $asOf)) {
        return false;
    }
    $periodEnd = k2_league_period_end_datetime($periodType, $periodStart);
    if ($periodEnd === null) {
        return false;
    }
    $sorted = k2_league_build_sorted_standings($con, $leagueKind, $periodType, $periodStart, $asOf);
    if ($sorted === []) {
        return false;
    }
    $podium = k2_league_podium_from_sorted($leagueKind, $sorted);
    if ($podium === []) {
        return false;
    }

    k2_league_delete_instance_awards($con, $leagueKind, $periodType, $periodStart);
    $written = k2_league_write_instance_awards(
        $con,
        $leagueKind,
        $periodType,
        $periodStart,
        $periodEnd,
        $podium
    );
    $podiumCount = count($podium);
    if ($written === 0 || $written < $podiumCount) {
        if (function_exists('k2_ops_log')) {
            k2_ops_log(
                'league_finalize_skip awards_not_written kind=' . $leagueKind
                . ' period=' . $periodType
                . ' start=' . $periodStart
                . ' written=' . $written
                . ' podium=' . $podiumCount
            );
        }

        return false;
    }

    $ratedGames = k2_league_rated_games_for_period($con, $periodType, $periodStart);
    $finalizedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    k2_league_upsert_period_header(
        $con,
        $leagueKind,
        $periodType,
        $periodStart,
        $periodEnd,
        $ratedGames,
        $finalizedAt
    );

    return true;
}

/**
 * Full rebuild of all closed league awards (REP-012).
 *
 * @return array{instances: int, awards: int}
 */
function k2_league_rebuild_all_awards(mysqli $con, ?DateTimeImmutable $asOf = null): array
{
    if (!k2_league_table_exists($con, 'player_league_award')) {
        return ['instances' => 0, 'awards' => 0];
    }

    $asOf = $asOf ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    mysqli_query($con, 'TRUNCATE TABLE player_league_award');
    mysqli_query($con, 'TRUNCATE TABLE league_period');

    $instances = 0;
    foreach (k2_league_list_period_instances($con) as $inst) {
        $periodType = $inst['period_type'];
        $periodStart = $inst['period_start'];
        if (!k2_league_period_is_closed($periodType, $periodStart, $asOf)) {
            continue;
        }
        foreach (K2_LEAGUE_KINDS as $leagueKind) {
            if (k2_league_finalize_instance($con, $leagueKind, $periodType, $periodStart, $asOf, true)) {
                ++$instances;
            }
        }
    }

    k2_league_rebuild_player_aggregates($con);
    k2_league_sync_win_milestones($con);

    $countRes = mysqli_query($con, 'SELECT COUNT(*) AS c FROM player_league_award');
    $awards = 0;
    if ($countRes !== false) {
        $row = mysqli_fetch_assoc($countRes);
        $awards = (int) ($row['c'] ?? 0);
        mysqli_free_result($countRes);
    }

    return ['instances' => $instances, 'awards' => $awards];
}

/**
 * Finalize all closed instances not yet in league_period (daily job).
 *
 * @return array{finalized: int}
 */
function k2_league_finalize_due_periods(mysqli $con, ?DateTimeImmutable $asOf = null): array
{
    $asOf = $asOf ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $finalized = 0;

    foreach (k2_league_list_period_instances($con) as $inst) {
        $periodType = $inst['period_type'];
        $periodStart = $inst['period_start'];
        if (!k2_league_period_is_closed($periodType, $periodStart, $asOf)) {
            continue;
        }
        foreach (K2_LEAGUE_KINDS as $leagueKind) {
            $check = mysqli_prepare(
                $con,
                'SELECT finalized_at FROM league_period WHERE league_kind = ? AND period_type = ? AND period_start = ?'
            );
            if ($check === false) {
                continue;
            }
            mysqli_stmt_bind_param($check, 'sss', $leagueKind, $periodType, $periodStart);
            mysqli_stmt_execute($check);
            $res = mysqli_stmt_get_result($check);
            $row = mysqli_fetch_assoc($res);
            mysqli_free_result($res);
            mysqli_stmt_close($check);
            if ($row !== null && $row['finalized_at'] !== null) {
                continue;
            }
            if (k2_league_finalize_instance($con, $leagueKind, $periodType, $periodStart, $asOf, true)) {
                ++$finalized;
            }
        }
    }

    if ($finalized > 0) {
        k2_league_rebuild_player_aggregates($con);
        k2_league_sync_win_milestones($con);
    }

    return ['finalized' => $finalized];
}
