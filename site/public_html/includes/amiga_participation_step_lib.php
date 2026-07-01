<?php
/**
 * Amiga realm — player participation keys for with-player stepper filters.
 *
 * @see docs/with-player-stepper-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_rating_history_lib.php';
require_once __DIR__ . '/amiga_player_current_lib.php';

/**
 * Tournament event keys (chrono) where the player has rated games.
 *
 * @return list<string>
 */
function amiga_player_participated_event_keys(mysqli $con, int $playerId): array
{
    static $cache = [];

    if ($playerId < 1) {
        return [];
    }
    if (isset($cache[$playerId])) {
        return $cache[$playerId];
    }

    $sql = 'SELECT tournament_id FROM amiga_player_event_snapshots
        WHERE player_id = ? AND NumberGames > 0
        ORDER BY event_date ASC, event_chrono ASC, tournament_id ASC';
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $playerId);
    if (!$stmt->execute()) {
        $stmt->close();

        return [];
    }
    $res = $stmt->get_result();
    $keys = [];
    while ($row = $res->fetch_assoc()) {
        $tid = (int) ($row['tournament_id'] ?? 0);
        if ($tid > 0) {
            $keys[] = (string) $tid;
        }
    }
    if ($res) {
        $res->free();
    }
    $stmt->close();

    $cache[$playerId] = $keys;

    return $keys;
}

/**
 * @return array<string, true>
 */
function amiga_player_participated_event_key_set(mysqli $con, int $playerId): array
{
    $set = [];
    foreach (amiga_player_participated_event_keys($con, $playerId) as $key) {
        $set[$key] = true;
    }

    return $set;
}

/**
 * Calendar period keys (year or YYYY-MM month) where the player has rated games.
 *
 * @return list<string>
 */
function amiga_player_participated_period_keys(mysqli $con, int $playerId, string $wing): array
{
    static $cache = [];

    if ($playerId < 1) {
        return [];
    }

    $wing = amiga_rating_history_normalize_wing($wing);
    $prefixLen = match ($wing) {
        'year' => 4,
        'month' => 7,
        default => 0,
    };
    if ($prefixLen === 0) {
        return [];
    }

    $cacheKey = $wing . ':' . $playerId;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $sql = 'SELECT DISTINCT SUBSTRING(event_date, 1, ?) AS period_key '
        . 'FROM amiga_player_event_snapshots '
        . 'WHERE player_id = ? AND NumberGames > 0 '
        . 'AND event_date IS NOT NULL AND CHAR_LENGTH(event_date) >= ? '
        . 'ORDER BY period_key ASC';
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        $cache[$cacheKey] = [];

        return $cache[$cacheKey];
    }
    $stmt->bind_param('iii', $prefixLen, $playerId, $prefixLen);
    if (!$stmt->execute()) {
        $stmt->close();
        $cache[$cacheKey] = [];

        return $cache[$cacheKey];
    }
    $res = $stmt->get_result();
    $keys = [];
    while ($row = $res->fetch_assoc()) {
        $key = trim((string) ($row['period_key'] ?? ''));
        if ($key !== '') {
            $keys[] = $key;
        }
    }
    if ($res) {
        $res->free();
    }
    $stmt->close();

    $cache[$cacheKey] = $keys;

    return $keys;
}

/**
 * Participation key-set for the active TT ribbon wing catalog.
 *
 * @return array<string, true>
 */
function amiga_player_participated_wing_key_set(mysqli $con, int $playerId, string $wing): array
{
    $wing = amiga_rating_history_normalize_wing($wing);
    $keys = match ($wing) {
        'event' => amiga_player_participated_event_keys($con, $playerId),
        'year', 'month' => amiga_player_participated_period_keys($con, $playerId, $wing),
        default => [],
    };

    $set = [];
    foreach ($keys as $key) {
        $set[$key] = true;
    }

    return $set;
}

/**
 * @return list<array{id: int, name: string}>
 */
function amiga_participation_eligible_players(mysqli $con): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $careerTable = amiga_player_career_table($con);
    $sql = 'SELECT p.id AS id, p.name AS name '
        . 'FROM amiga_players p INNER JOIN `' . $careerTable . '` s ON s.player_id = p.id '
        . 'WHERE s.NumberGames > 0 AND p.name IS NOT NULL AND TRIM(p.name) <> \'\' '
        . 'ORDER BY p.name ASC, p.id ASC';
    $res = $con->query($sql);
    if (!$res) {
        $cache = [];

        return $cache;
    }

    $players = [];
    while ($row = $res->fetch_assoc()) {
        $players[] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
        ];
    }
    $res->free();
    $cache = $players;

    return $players;
}

/**
 * Resolve active with-player filter for TT ribbon (`as_with=`).
 *
 * Unknown id or player with no rated games → filter off (silent).
 */
function amiga_as_with_active_player_id(mysqli $con): int
{
    require_once __DIR__ . '/amiga_as_with_url.php';

    $playerId = amiga_as_with_from_request();
    if ($playerId < 1) {
        return 0;
    }

    if (amiga_player_participated_event_keys($con, $playerId) === []) {
        return 0;
    }

    return $playerId;
}

/**
 * @param list<array<string, mixed>> $catalog chrono-asc wing catalog
 * @param array<string, true> $participatedSet
 * @return array{prev_key: string|null, next_key: string|null}
 */
function k2_participation_step_keys(array $catalog, string $currentKey, array $participatedSet): array
{
    if ($participatedSet === [] || $catalog === []) {
        return ['prev_key' => null, 'next_key' => null];
    }

    $position = amiga_rating_history_catalog_position($catalog, $currentKey);
    if ($position['entry'] === null) {
        return ['prev_key' => null, 'next_key' => null];
    }

    $keyIndex = [];
    foreach ($catalog as $i => $catalogEntry) {
        $keyIndex[(string) $catalogEntry['key']] = $i;
    }

    $currentIdx = $keyIndex[$currentKey] ?? null;
    if ($currentIdx === null) {
        return ['prev_key' => null, 'next_key' => null];
    }

    $participatedIndices = [];
    foreach ($participatedSet as $key => $_) {
        if (isset($keyIndex[$key])) {
            $participatedIndices[] = $keyIndex[$key];
        }
    }
    sort($participatedIndices);

    $nextKey = null;
    foreach ($participatedIndices as $idx) {
        if ($idx > $currentIdx) {
            $nextKey = (string) $catalog[$idx]['key'];
            break;
        }
    }

    $prevKey = null;
    foreach ($participatedIndices as $idx) {
        if ($idx < $currentIdx) {
            $prevKey = (string) $catalog[$idx]['key'];
        }
    }

    return ['prev_key' => $prevKey, 'next_key' => $nextKey];
}

/**
 * When current key is off the participation filter, nearest snap target:
 * prefer previous eligible (back in chrono), else next.
 *
 * @param list<array{key: string}> $catalog
 * @param array<string, true> $participatedSet
 */
function k2_participation_snap_target_key(array $catalog, string $currentKey, array $participatedSet): ?string
{
    if ($participatedSet === [] || $catalog === []) {
        return null;
    }
    if (isset($participatedSet[$currentKey])) {
        return null;
    }

    $steps = k2_participation_step_keys($catalog, $currentKey, $participatedSet);
    $prev = $steps['prev_key'];
    if ($prev !== null && $prev !== '') {
        return $prev;
    }
    $next = $steps['next_key'];
    if ($next !== null && $next !== '') {
        return $next;
    }

    return null;
}