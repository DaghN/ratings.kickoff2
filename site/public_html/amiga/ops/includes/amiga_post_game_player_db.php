<?php
/**
 * amiga_player_stats load/write + prior-game network sets for per-game post-game.
 */
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/ops/includes/post_game_constants.php';
require_once dirname(__DIR__, 3) . '/ops/includes/post_game_player_state.php';
require_once dirname(__DIR__, 3) . '/ops/includes/post_game_player_db.php';

function amiga_post_game_db_float(mixed $val, float $default = 0.0): float
{
    if ($val === null || $val === '') {
        return $default;
    }

    return (float) $val;
}

function amiga_post_game_db_int(mixed $val, int $default = 0): int
{
    if ($val === null || $val === '') {
        return $default;
    }

    return (int) $val;
}

/**
 * @return array{a: float, b: float}
 */
function amiga_post_game_load_player_ratings(mysqli $con, int $idA, int $idB): array
{
    $stmt = $con->prepare('SELECT player_id, Rating FROM amiga_player_stats WHERE player_id IN (?, ?)');
    if ($stmt === false) {
        throw new RuntimeException('prepare amiga_player_stats ratings: ' . $con->error);
    }
    $stmt->bind_param('ii', $idA, $idB);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute amiga_player_stats ratings: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $ratings = [];
    while ($row = $res->fetch_assoc()) {
        $ratings[(int) $row['player_id']] = amiga_post_game_db_float(
            $row['Rating'] ?? null,
            K2_POST_GAME_START_RATING
        );
    }
    $stmt->close();

    $start = K2_POST_GAME_START_RATING;

    return [
        'a' => $ratings[$idA] ?? $start,
        'b' => $ratings[$idB] ?? $start,
    ];
}

/**
 * Prior processed games for network sets (append-only v1: all rated games except $beforeGameId).
 *
 * @return array{
 *   _network_opponents: array<int, true>,
 *   _network_victims: array<int, true>,
 *   _network_culprits: array<int, true>,
 *   _network_dd_victims: array<int, true>,
 *   _network_dd_culprits: array<int, true>,
 *   _network_cs_victims: array<int, true>,
 *   _network_cs_culprits: array<int, true>
 * }
 */
function amiga_post_game_build_network_sets(mysqli $con, int $playerId, int $beforeGameId): array
{
    $sets = [
        '_network_opponents' => [],
        '_network_victims' => [],
        '_network_culprits' => [],
        '_network_dd_victims' => [],
        '_network_dd_culprits' => [],
        '_network_cs_victims' => [],
        '_network_cs_culprits' => [],
    ];

    $sql = 'SELECT g.id, g.player_a_id AS idA, g.player_b_id AS idB, '
        . 'gr.actual_score AS ActualScore, gr.dd_player_a AS DDPlayerA, gr.dd_player_b AS DDPlayerB, '
        . 'gr.cs_player_a AS CSPlayerA, gr.cs_player_b AS CSPlayerB '
        . 'FROM amiga_games g '
        . 'INNER JOIN amiga_game_ratings gr ON gr.game_id = g.id '
        . 'WHERE g.id <> ? AND (g.player_a_id = ? OR g.player_b_id = ?) '
        . 'ORDER BY g.id ASC';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare amiga network sets: ' . $con->error);
    }
    $stmt->bind_param('iii', $beforeGameId, $playerId, $playerId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute amiga network sets: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $idA = (int) $row['idA'];
        $idB = (int) $row['idB'];
        $score = (float) $row['ActualScore'];
        $ddA = (int) $row['DDPlayerA'];
        $ddB = (int) $row['DDPlayerB'];
        $csA = (int) $row['CSPlayerA'];
        $csB = (int) $row['CSPlayerB'];

        if ($idA === $playerId) {
            $opp = $idB;
            $playerScore = $score;
            $ddFor = $ddA === 1;
            $csFor = $csA === 1;
            $ddAgainst = $ddB === 1;
            $csAgainst = $csB === 1;
        } else {
            $opp = $idA;
            $playerScore = $score === 0.5 ? 0.5 : 1.0 - $score;
            $ddFor = $ddB === 1;
            $csFor = $csB === 1;
            $ddAgainst = $ddA === 1;
            $csAgainst = $csA === 1;
        }

        $sets['_network_opponents'][$opp] = true;
        if ($playerScore === 1.0) {
            $sets['_network_victims'][$opp] = true;
        } elseif ($playerScore === 0.0) {
            $sets['_network_culprits'][$opp] = true;
        }
        if ($ddFor) {
            $sets['_network_dd_victims'][$opp] = true;
        }
        if ($ddAgainst) {
            $sets['_network_dd_culprits'][$opp] = true;
        }
        if ($csFor) {
            $sets['_network_cs_victims'][$opp] = true;
        }
        if ($csAgainst) {
            $sets['_network_cs_culprits'][$opp] = true;
        }
    }
    $stmt->close();

    return $sets;
}

/**
 * @param array<string, mixed> $row amiga_player_stats fetch_assoc
 * @return array<string, mixed>
 */
function amiga_post_game_player_state_from_db_row(array $row): array
{
    $row['ID'] = (int) $row['player_id'];

    return k2_post_game_player_state_from_db_row($row);
}

/**
 * @return array<string, mixed>
 */
function amiga_post_game_player_load(mysqli $con, int $playerId, int $beforeGameId): array
{
    $stmt = $con->prepare('SELECT * FROM amiga_player_stats WHERE player_id = ? LIMIT 1');
    if ($stmt === false) {
        throw new RuntimeException('prepare amiga_player_stats load: ' . $con->error);
    }
    $stmt->bind_param('i', $playerId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute amiga_player_stats load: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : false;
    $stmt->close();
    if ($row === false || $row === null) {
        $st = k2_post_game_player_state_new();
    } else {
        $st = amiga_post_game_player_state_from_db_row($row);
    }
    $network = amiga_post_game_build_network_sets($con, $playerId, $beforeGameId);
    foreach ($network as $key => $set) {
        $st[$key] = $set;
        $countKey = match ($key) {
            '_network_opponents' => 'different_opponents',
            '_network_victims' => 'different_victims',
            '_network_culprits' => 'different_culprits',
            '_network_dd_victims' => 'double_digits_victims',
            '_network_dd_culprits' => 'double_digits_culprits',
            '_network_cs_victims' => 'clean_sheets_victims',
            '_network_cs_culprits' => 'clean_sheets_culprits',
            default => null,
        };
        if ($countKey !== null) {
            $st[$countKey] = count($set);
        }
    }

    return $st;
}

/**
 * @param array<string, mixed> $dbRow from k2_post_game_player_to_db_row (ID key)
 */
function amiga_post_game_player_write(mysqli $con, array $dbRow): void
{
    $playerId = (int) $dbRow['ID'];
    unset($dbRow['ID']);
    $cols = array_keys($dbRow);
    $colList = implode(', ', array_map(static fn (string $c): string => "`{$c}`", $cols));
    $placeholders = implode(', ', array_fill(0, count($cols), '?'));
    $updates = implode(', ', array_map(static fn (string $c): string => "`{$c}` = VALUES(`{$c}`)", $cols));
    $sql = "INSERT INTO amiga_player_stats (player_id, {$colList}) VALUES (?, {$placeholders}) "
        . "ON DUPLICATE KEY UPDATE {$updates}";

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare amiga_player_stats write: ' . $con->error);
    }

    $types = 'i';
    $values = [$playerId];
    foreach ($dbRow as $val) {
        if ($val === null) {
            $types .= 's';
            $values[] = null;
        } elseif (is_int($val)) {
            $types .= 'i';
            $values[] = $val;
        } elseif (is_float($val)) {
            $types .= 'd';
            $values[] = $val;
        } else {
            $types .= 's';
            $values[] = (string) $val;
        }
    }

    $bind = [$types];
    foreach ($values as $i => $v) {
        $bind[] = &$values[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute amiga_player_stats write player_id=' . $playerId . ': ' . $stmt->error);
    }
    $stmt->close();
}
