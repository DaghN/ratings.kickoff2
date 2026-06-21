<?php
/**
 * Player-wing Event time travel — chevrons step participated tournaments (T18).
 *
 * @see docs/amiga-time-travel-policy.md T18
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_table_helpers.php';
require_once __DIR__ . '/amiga_snapshot_url.php';
require_once __DIR__ . '/amiga_rating_history_lib.php';

function amiga_player_event_stepper_applies(?string $path = null, ?int $playerId = null): bool
{
    $path ??= amiga_snapshot_request_path();
    if (!str_contains(k2_table_path_only($path), '/amiga/player/')) {
        return false;
    }

    $playerId ??= isset($_GET['id']) ? max(0, (int) $_GET['id']) : 0;

    return $playerId > 0;
}

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
 * @param list<array<string, mixed>> $realmCatalog chrono-asc event catalog
 * @return array{prev_key: string|null, next_key: string|null}
 */
function amiga_player_event_wing_step_keys(
    mysqli $con,
    int $playerId,
    array $realmCatalog,
    string $currentKey
): array {
    $realmPosition = amiga_rating_history_catalog_position($realmCatalog, $currentKey);
    $entry = $realmPosition['entry'];
    if ($entry === null) {
        return ['prev_key' => null, 'next_key' => null];
    }

    $participated = amiga_player_participated_event_keys($con, $playerId);
    if ($participated === []) {
        return [
            'prev_key' => $realmPosition['prev_key'],
            'next_key' => $realmPosition['next_key'],
        ];
    }

    $keyIndex = [];
    foreach ($realmCatalog as $i => $catalogEntry) {
        $keyIndex[(string) $catalogEntry['key']] = $i;
    }

    $currentIdx = $keyIndex[$currentKey] ?? null;
    if ($currentIdx === null) {
        return [
            'prev_key' => $realmPosition['prev_key'],
            'next_key' => $realmPosition['next_key'],
        ];
    }

    $participatedIndices = [];
    foreach ($participated as $key) {
        if (isset($keyIndex[$key])) {
            $participatedIndices[] = $keyIndex[$key];
        }
    }
    sort($participatedIndices);

    $nextKey = null;
    foreach ($participatedIndices as $participatedIdx) {
        if ($participatedIdx > $currentIdx) {
            $nextKey = (string) $realmCatalog[$participatedIdx]['key'];
            break;
        }
    }

    $prevParticipatedIdx = null;
    foreach ($participatedIndices as $participatedIdx) {
        if ($participatedIdx < $currentIdx) {
            $prevParticipatedIdx = $participatedIdx;
        }
    }

    if ($prevParticipatedIdx !== null) {
        $prevKey = (string) $realmCatalog[$prevParticipatedIdx]['key'];
    } elseif ($currentIdx > 0) {
        $prevKey = (string) $realmCatalog[$currentIdx - 1]['key'];
    } else {
        $prevKey = null;
    }

    return ['prev_key' => $prevKey, 'next_key' => $nextKey];
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
