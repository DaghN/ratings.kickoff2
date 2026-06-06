<?php
/**
 * Amiga tournament standings read path (derived amiga_tournament_standings).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';

/**
 * @return array<string, mixed>|null
 */
function amiga_tournament_load(mysqli $con, int $tournamentId): ?array
{
    $stmt = mysqli_prepare(
        $con,
        'SELECT id, name, chrono, event_date, is_cup, country, equal_teams, player_count
         FROM tournaments WHERE id = ?'
    );
    if ($stmt === false) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $tournamentId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if ($res) {
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

/**
 * @return list<string>
 */
function amiga_tournament_list_scopes(mysqli $con, int $tournamentId, string $scopeType = 'overall'): array
{
    $stmt = mysqli_prepare(
        $con,
        'SELECT DISTINCT scope_key FROM amiga_tournament_standings
         WHERE tournament_id = ? AND scope_type = ?
         ORDER BY scope_key'
    );
    if ($stmt === false) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'is', $tournamentId, $scopeType);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $keys = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $keys[] = (string) $row['scope_key'];
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);
    return $keys;
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_tournament_standings_rows(
    mysqli $con,
    int $tournamentId,
    string $scopeType = 'overall',
    string $scopeKey = ''
): array {
    $sql = 'SELECT s.position, s.games, s.wins, s.draws, s.losses,
                   s.goals_for, s.goals_against, s.points,
                   p.id AS player_id, p.name AS player_name, p.country
            FROM amiga_tournament_standings s
            INNER JOIN amiga_players p ON p.id = s.player_id
            WHERE s.tournament_id = ? AND s.scope_type = ? AND s.scope_key = ?
            ORDER BY s.position ASC, s.points DESC, (s.goals_for - s.goals_against) DESC';
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'iss', $tournamentId, $scopeType, $scopeKey);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

/**
 * Recent tournaments for a player (overall scope, by event chrono desc).
 *
 * @return list<array<string, mixed>>
 */
function amiga_player_recent_tournaments(mysqli $con, int $playerId, int $limit = 5): array
{
    $limit = max(1, min(20, $limit));
    $sql = 'SELECT t.id, t.name, t.event_date, s.position, s.points, s.games
            FROM amiga_tournament_standings s
            INNER JOIN tournaments t ON t.id = s.tournament_id
            WHERE s.player_id = ? AND s.scope_type = \'overall\' AND s.scope_key = \'\'
            ORDER BY COALESCE(t.chrono, 999999) DESC, COALESCE(t.event_date, \'1970-01-01\') DESC
            LIMIT ' . (int) $limit;
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'i', $playerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function amiga_tournament_url(int $id, string $scopeType = 'overall', string $scopeKey = ''): string
{
    $params = ['id' => $id];
    if ($scopeType !== 'overall' || $scopeKey !== '') {
        $params['scope'] = $scopeType;
        if ($scopeKey !== '') {
            $params['scope_key'] = $scopeKey;
        }
    }
    return '/amiga/tournament.php?' . http_build_query($params);
}

function amiga_tournament_link(int $id, string $name): string
{
    return '<a href="' . htmlspecialchars(amiga_tournament_url($id), ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</a>';
}

/**
 * Candidate scope_key values for a game phase (mirrors Python phase normalizer).
 *
 * @return list<string>
 */
function amiga_tournament_phase_scope_candidates(string $phase): array
{
    $phase = trim($phase);
    if ($phase === '') {
        return [];
    }
    $candidates = [$phase];
    if (preg_match('/^(Round\s+\d+)\s+Group\s+([A-Z](?:\/[A-Z])?)$/i', $phase, $m) === 1) {
        $candidates[] = $m[1] . ' - Group ' . strtoupper($m[2]);
    }
    if (preg_match('/^(Silver Cup|Bronze Cup)\s+Group\s+([A-Z](?:\/[A-Z])?)$/i', $phase, $m) === 1) {
        $candidates[] = $m[1] . ' - Group ' . strtoupper($m[2]);
    }
    return array_values(array_unique($candidates));
}

/**
 * Resolve game phase to a standings scope present in DB, or null.
 *
 * @return array{scope_type: string, scope_key: string}|null
 */
function amiga_tournament_knockout_pair_scope_key(string $phase, int $playerAId, int $playerBId): string
{
    $phase = trim($phase);
    $lo = min($playerAId, $playerBId);
    $hi = max($playerAId, $playerBId);
    return $phase . '|' . $lo . '-' . $hi;
}

/**
 * @return array{scope_type: string, scope_key: string}|null
 */
function amiga_tournament_resolve_phase_scope(
    mysqli $con,
    int $tournamentId,
    string $phase,
    int $playerAId = 0,
    int $playerBId = 0
): ?array {
    $phase = trim($phase);
    if ($phase === '' || $tournamentId < 1) {
        return null;
    }

    if ($playerAId > 0 && $playerBId > 0) {
        $pairKey = amiga_tournament_knockout_pair_scope_key($phase, $playerAId, $playerBId);
        $stmt = mysqli_prepare(
            $con,
            'SELECT 1 FROM amiga_tournament_standings
             WHERE tournament_id = ? AND scope_type = \'knockout\' AND scope_key = ?
             LIMIT 1'
        );
        if ($stmt !== false) {
            mysqli_stmt_bind_param($stmt, 'is', $tournamentId, $pairKey);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $found = $res && mysqli_fetch_assoc($res);
            if ($res) {
                mysqli_free_result($res);
            }
            mysqli_stmt_close($stmt);
            if ($found) {
                return ['scope_type' => 'knockout', 'scope_key' => $pairKey];
            }
        }
    }

    foreach (amiga_tournament_phase_scope_candidates($phase) as $candidate) {
        $stmt = mysqli_prepare(
            $con,
            'SELECT 1 FROM amiga_tournament_standings
             WHERE tournament_id = ? AND scope_type = \'group\' AND scope_key = ?
             LIMIT 1'
        );
        if ($stmt === false) {
            continue;
        }
        mysqli_stmt_bind_param($stmt, 'is', $tournamentId, $candidate);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $found = $res && mysqli_fetch_assoc($res);
        if ($res) {
            mysqli_free_result($res);
        }
        mysqli_stmt_close($stmt);
        if ($found) {
            return ['scope_type' => 'group', 'scope_key' => $candidate];
        }
    }
    return null;
}

/**
 * Human label for a scope_key (knockout pairs show both players).
 */
function amiga_tournament_scope_label(mysqli $con, string $scopeKey): string
{
    if (preg_match('/^(.+)\|(\d+)-(\d+)$/', $scopeKey, $m) === 1) {
        $ids = [(int) $m[2], (int) $m[3]];
        $names = amiga_tournament_player_names($con, $ids);
        $n1 = $names[$ids[0]] ?? ('#' . $ids[0]);
        $n2 = $names[$ids[1]] ?? ('#' . $ids[1]);
        return $m[1] . ' — ' . $n1 . ' / ' . $n2;
    }
    return $scopeKey;
}

/**
 * @param list<int> $playerIds
 * @return array<int, string>
 */
function amiga_tournament_player_names(mysqli $con, array $playerIds): array
{
    $playerIds = array_values(array_unique(array_filter($playerIds, static fn (int $id): bool => $id > 0)));
    if ($playerIds === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($playerIds), '?'));
    $types = str_repeat('i', count($playerIds));
    $sql = 'SELECT id, name FROM amiga_players WHERE id IN (' . $placeholders . ')';
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, $types, ...$playerIds);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $out = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $out[(int) $row['id']] = (string) $row['name'];
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);
    return $out;
}

/** Link phase to group table or knockout pair for this fixture. */
function amiga_tournament_phase_link(
    mysqli $con,
    int $tournamentId,
    string $phase,
    int $playerAId = 0,
    int $playerBId = 0
): string {
    $phase = trim($phase);
    if ($phase === '') {
        return '';
    }
    $scope = amiga_tournament_resolve_phase_scope($con, $tournamentId, $phase, $playerAId, $playerBId);
    if ($scope === null) {
        return htmlspecialchars($phase, ENT_QUOTES, 'UTF-8');
    }
    $href = amiga_tournament_url($tournamentId, $scope['scope_type'], $scope['scope_key']);
    $title = $scope['scope_type'] === 'knockout' ? 'Elimination tie' : 'Group standings';
    return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($phase, ENT_QUOTES, 'UTF-8') . '</a>';
}

/**
 * Tournament catalog for index page (chrono desc).
 *
 * @return list<array<string, mixed>>
 */
function amiga_tournament_index_rows(mysqli $con, int $limit = 500, int $offset = 0): array
{
    $limit = max(1, min(1000, $limit));
    $offset = max(0, $offset);
    $sql = 'SELECT t.id, t.name, t.event_date, t.chrono, t.is_cup, t.country, t.player_count,
                   COUNT(DISTINCT g.id) AS game_count,
                   COUNT(DISTINCT s.player_id) AS standing_players,
                   COUNT(s.id) AS standing_rows
            FROM tournaments t
            LEFT JOIN amiga_games g ON g.tournament_id = t.id
            LEFT JOIN amiga_tournament_standings s ON s.tournament_id = t.id
            GROUP BY t.id, t.name, t.event_date, t.chrono, t.is_cup, t.country, t.player_count
            ORDER BY COALESCE(t.chrono, 999999) DESC, COALESCE(t.event_date, \'1970-01-01\') DESC, t.name ASC
            LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
    $res = mysqli_query($con, $sql);
    if ($res === false) {
        return [];
    }
    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }
    mysqli_free_result($res);
    return $rows;
}

function amiga_tournament_index_count(mysqli $con): int
{
    $res = mysqli_query($con, 'SELECT COUNT(*) AS n FROM tournaments');
    if ($res === false) {
        return 0;
    }
    $row = mysqli_fetch_assoc($res);
    mysqli_free_result($res);
    return (int) ($row['n'] ?? 0);
}

/**
 * Parse knockout scope_key into phase and canonical player id pair.
 *
 * @return array{phase: string, player_lo: int, player_hi: int}|null
 */
function amiga_tournament_parse_knockout_scope_key(string $scopeKey): ?array
{
    if (preg_match('/^(.+)\|(\d+)-(\d+)$/', $scopeKey, $m) !== 1) {
        return null;
    }
    return [
        'phase' => $m[1],
        'player_lo' => (int) $m[2],
        'player_hi' => (int) $m[3],
    ];
}

/**
 * Ground-truth legs for a knockout pair scope (ordered by source_scores_id).
 *
 * @return list<array<string, mixed>>
 */
function amiga_tournament_knockout_fixture_games(mysqli $con, int $tournamentId, string $scopeKey): array
{
    $parsed = amiga_tournament_parse_knockout_scope_key($scopeKey);
    if ($parsed === null || $tournamentId < 1) {
        return [];
    }
    $phase = $parsed['phase'];
    $lo = $parsed['player_lo'];
    $hi = $parsed['player_hi'];
    $sql = 'SELECT g.id, g.source_scores_id, g.game_date, g.player_a_id, g.player_b_id,
                   g.goals_a, g.goals_b, g.extra, g.phase,
                   pa.name AS player_a_name, pb.name AS player_b_name
            FROM amiga_games g
            INNER JOIN amiga_players pa ON pa.id = g.player_a_id
            INNER JOIN amiga_players pb ON pb.id = g.player_b_id
            WHERE g.tournament_id = ? AND g.phase = ?
              AND ((g.player_a_id = ? AND g.player_b_id = ?) OR (g.player_a_id = ? AND g.player_b_id = ?))
            ORDER BY g.source_scores_id ASC, g.id ASC';
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'isiiii', $tournamentId, $phase, $lo, $hi, $hi, $lo);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

/**
 * Resolve match winner for knockouts (regulation or Extra). Parity with parse_standings_winner in Python.
 */
function amiga_parse_standings_winner(
    int $goalsA,
    int $goalsB,
    ?string $extra,
    int $playerAId,
    int $playerBId
): ?int {
    if ($goalsA > $goalsB) {
        return $playerAId;
    }
    if ($goalsB > $goalsA) {
        return $playerBId;
    }
    if ($extra === null || trim($extra) === '') {
        return null;
    }
    $text = strtolower(trim($extra));
    $patterns = [
        '/\((\d+)\s*-\s*(\d+)\)\s*(\d+)\s*-\s*(\d+)\s*(?:p\.?k\.?|pen)/',
        '/\((\d+)\s*-\s*(\d+)\)\s*(\d+)\s*-\s*(\d+)/',
        '/(\d+)\s*-\s*(\d+)\s*pen/',
    ];
    foreach ($patterns as $pat) {
        if (preg_match($pat, $text, $m) !== 1) {
            continue;
        }
        if (count($m) >= 5) {
            $penA = (int) $m[3];
            $penB = (int) $m[4];
        } else {
            $penA = (int) $m[1];
            $penB = (int) $m[2];
        }
        if ($penA > $penB) {
            return $playerAId;
        }
        if ($penB > $penA) {
            return $playerBId;
        }
    }
    return null;
}

/**
 * Resolve knockout tie winner from fixture legs (aggregate GD/GF, then extra, then standings fallback).
 *
 * @param list<array<string, mixed>> $games
 * @param list<array<string, mixed>> $standingsRows
 * @return array{
 *   winner_id: int|null,
 *   loser_id: int|null,
 *   unresolved: bool,
 *   aggregate: array<int, array{goals_for: int, goals_against: int, goal_difference: int}>
 * }
 */
function amiga_tournament_knockout_resolve_winner(array $games, array $standingsRows): array
{
    /** @var array<int, array{goals_for: int, goals_against: int, goal_difference: int}> $aggregate */
    $aggregate = [];
    foreach ($games as $g) {
        $aId = (int) $g['player_a_id'];
        $bId = (int) $g['player_b_id'];
        $ga = (int) $g['goals_a'];
        $gb = (int) $g['goals_b'];
        if (!isset($aggregate[$aId])) {
            $aggregate[$aId] = ['goals_for' => 0, 'goals_against' => 0, 'goal_difference' => 0];
        }
        if (!isset($aggregate[$bId])) {
            $aggregate[$bId] = ['goals_for' => 0, 'goals_against' => 0, 'goal_difference' => 0];
        }
        $aggregate[$aId]['goals_for'] += $ga;
        $aggregate[$aId]['goals_against'] += $gb;
        $aggregate[$bId]['goals_for'] += $gb;
        $aggregate[$bId]['goals_against'] += $ga;
    }
    foreach ($aggregate as $pid => $st) {
        $aggregate[$pid]['goal_difference'] = $st['goals_for'] - $st['goals_against'];
    }

    $playerIds = array_keys($aggregate);
    if (count($playerIds) !== 2) {
        return [
            'winner_id' => null,
            'loser_id' => null,
            'unresolved' => true,
            'aggregate' => $aggregate,
        ];
    }

    $id1 = min($playerIds[0], $playerIds[1]);
    $id2 = max($playerIds[0], $playerIds[1]);
    $s1 = $aggregate[$id1];
    $s2 = $aggregate[$id2];

    $winnerId = null;
    if ($s1['goal_difference'] > $s2['goal_difference']) {
        $winnerId = $id1;
    } elseif ($s2['goal_difference'] > $s1['goal_difference']) {
        $winnerId = $id2;
    } elseif ($s1['goals_for'] > $s2['goals_for']) {
        $winnerId = $id1;
    } elseif ($s2['goals_for'] > $s1['goals_for']) {
        $winnerId = $id2;
    } else {
        foreach ($games as $g) {
            $extra = isset($g['extra']) ? (string) $g['extra'] : '';
            if (trim($extra) === '') {
                continue;
            }
            $wid = amiga_parse_standings_winner(
                (int) $g['goals_a'],
                (int) $g['goals_b'],
                $extra,
                (int) $g['player_a_id'],
                (int) $g['player_b_id']
            );
            if ($wid !== null) {
                $winnerId = $wid;
                break;
            }
        }
    }

    if ($winnerId === null && $standingsRows !== []) {
        usort(
            $standingsRows,
            static fn (array $a, array $b): int => (int) $a['position'] <=> (int) $b['position']
        );
        $winnerId = (int) $standingsRows[0]['player_id'];
    }

    $loserId = null;
    if ($winnerId !== null) {
        $loserId = $winnerId === $id1 ? $id2 : $id1;
    }

    return [
        'winner_id' => $winnerId,
        'loser_id' => $loserId,
        'unresolved' => $winnerId === null,
        'aggregate' => $aggregate,
    ];
}

/** Optional extra line for fixture score (e.g. penalties). */
function amiga_tournament_format_game_extra(?string $extra): string
{
    if ($extra === null || trim($extra) === '') {
        return '';
    }
    return ' <span style="color:var(--k2-text-secondary)">(' . k2_h(trim($extra)) . ')</span>';
}
