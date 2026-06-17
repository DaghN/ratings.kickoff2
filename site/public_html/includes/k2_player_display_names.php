<?php
/**
 * Resolve player display names from playertable (canonical), not ratedresults snapshots.
 *
 * Policy: site UI always shows the player's current registered name. Snapshot names in
 * ratedresults remain ground truth at insert time; use only as fallback when playertable
 * row is missing.
 */
declare(strict_types=1);

/**
 * @param array<int, string> $nameMap
 */
function k2_player_display_name(array $nameMap, int $playerId, ?string $snapshotFallback = null): string
{
    if ($playerId > 0 && isset($nameMap[$playerId]) && $nameMap[$playerId] !== '') {
        return $nameMap[$playerId];
    }
    if ($snapshotFallback !== null && $snapshotFallback !== '') {
        return $snapshotFallback;
    }

    return $playerId > 0 ? '#' . $playerId : '';
}

/**
 * @param list<int> $playerIds
 * @return array<int, string>
 */
function k2_player_display_names_load(mysqli $con, array $playerIds): array
{
    $ids = [];
    foreach ($playerIds as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $ids[$id] = true;
        }
    }
    if ($ids === []) {
        return [];
    }

    $idList = implode(',', array_keys($ids));
    $res = $con->query('SELECT ID, Name FROM playertable WHERE ID IN (' . $idList . ')');
    if ($res === false) {
        return [];
    }

    $map = [];
    while ($row = $res->fetch_assoc()) {
        $map[(int) $row['ID']] = (string) $row['Name'];
    }
    $res->free();

    return $map;
}

/**
 * @param array<string, mixed> $row
 * @return list<int>
 */
function k2_rated_game_row_player_ids(array $row): array
{
    $ids = [];
    foreach (['idA', 'idB', 'IDA', 'IDB'] as $key) {
        if (isset($row[$key]) && (int) $row[$key] > 0) {
            $ids[] = (int) $row[$key];
        }
    }

    return array_values(array_unique($ids));
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<int>
 */
function k2_rated_game_rows_collect_player_ids(array $rows): array
{
    $ids = [];
    foreach ($rows as $row) {
        foreach (k2_rated_game_row_player_ids($row) as $id) {
            $ids[$id] = true;
        }
    }

    return array_keys($ids);
}

/**
 * @param array<string, mixed> $row
 * @param array<int, string> $nameMap
 * @return array<string, mixed>
 */
function k2_rated_game_apply_display_names(array $row, array $nameMap): array
{
    $idA = (int) ($row['idA'] ?? $row['IDA'] ?? 0);
    $idB = (int) ($row['idB'] ?? $row['IDB'] ?? 0);
    $snapA = (string) ($row['NameA'] ?? $row['nameA'] ?? '');
    $snapB = (string) ($row['NameB'] ?? $row['nameB'] ?? '');

    if ($idA > 0) {
        $resolved = k2_player_display_name($nameMap, $idA, $snapA);
        $row['NameA'] = $resolved;
        $row['nameA'] = $resolved;
    }
    if ($idB > 0) {
        $resolved = k2_player_display_name($nameMap, $idB, $snapB);
        $row['NameB'] = $resolved;
        $row['nameB'] = $resolved;
    }

    return $row;
}

/**
 * @param list<array<string, mixed>> $rows
 * @param array<int, string> $nameMap
 * @return list<array<string, mixed>>
 */
function k2_rated_games_apply_display_names(array $rows, array $nameMap): array
{
    $out = [];
    foreach ($rows as $row) {
        $out[] = k2_rated_game_apply_display_names($row, $nameMap);
    }

    return $out;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<int, string>
 */
function k2_player_display_names_for_rated_rows(mysqli $con, array $rows): array
{
    return k2_player_display_names_load($con, k2_rated_game_rows_collect_player_ids($rows));
}

/**
 * @param array<string, mixed> $row
 * @return array<int, string>
 */
function k2_player_display_names_for_game_row(mysqli $con, array $row): array
{
    return k2_player_display_names_load($con, k2_rated_game_row_player_ids($row));
}

/** Live opponent list from ratedresults — group by ID, label from playertable. */
function k2_player_opponents_grouped_from_ratedresults_sql(): string
{
    return 'SELECT s.opponent_id, COALESCE(p.Name, CONCAT(\'#\', s.opponent_id)) AS opponent_name, COUNT(*) AS games '
        . 'FROM ('
        . 'SELECT idB AS opponent_id FROM ratedresults WHERE idA = ? '
        . 'UNION ALL '
        . 'SELECT idA AS opponent_id FROM ratedresults WHERE idB = ?'
        . ') AS s '
        . 'LEFT JOIN playertable p ON p.ID = s.opponent_id '
        . 'GROUP BY s.opponent_id '
        . 'ORDER BY games DESC, opponent_name ASC';
}

/**
 * Patch generalstatstable-style *Name fields from paired *ID columns.
 *
 * @param array<string, mixed> $row
 */
function k2_player_display_names_patch_id_name_fields(array &$row, array $nameMap): void
{
    foreach ($row as $key => $val) {
        if (!is_string($key) || !preg_match('/^(.+)Name(A?)$/', $key, $m)) {
            continue;
        }
        $idKey = $m[1] . 'ID' . $m[2];
        if (!array_key_exists($idKey, $row)) {
            continue;
        }
        $playerId = (int) $row[$idKey];
        if ($playerId < 1) {
            continue;
        }
        $fallback = is_scalar($val) ? (string) $val : '';
        $row[$key] = k2_player_display_name($nameMap, $playerId, $fallback);
    }
}

/**
 * @param array<string, mixed> $row
 * @return list<int>
 */
function k2_player_display_names_collect_ids_from_row(array $row): array
{
    $ids = [];
    foreach ($row as $key => $val) {
        if (!is_string($key) || !preg_match('/ID(A?)$/', $key)) {
            continue;
        }
        $id = (int) $val;
        if ($id > 0) {
            $ids[$id] = true;
        }
    }

    return array_keys($ids);
}

/**
 * @param array<string, mixed> $records generalstatstable row
 */
function k2_player_display_names_patch_hof_records(mysqli $con, array &$records): void
{
    $nameMap = k2_player_display_names_load($con, k2_player_display_names_collect_ids_from_row($records));
    k2_player_display_names_patch_id_name_fields($records, $nameMap);
}

/**
 * @param array<int, string> $nameMap
 */
function k2_rated_game_row_resolve(mysqli $con, ?array $row): ?array
{
    if ($row === null) {
        return null;
    }

    $nameMap = k2_player_display_names_for_game_row($con, $row);

    return k2_rated_game_apply_display_names($row, $nameMap);
}
