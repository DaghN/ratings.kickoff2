<?php
/**
 * Amiga player chronologies — country unlock inventories (host + opponent nationality).
 */
declare(strict_types=1);

/**
 * First host-country unlock per country (event grain — participation with games > 0).
 *
 * @return list<array<string, mixed>>
 */
function amiga_player_chronology_host_countries_load(
    mysqli $con,
    int $playerId,
    ?AmigaSnapshotContext $ctx = null,
): array {
    if ($playerId < 1) {
        return [];
    }

    require_once __DIR__ . '/amiga_player_tournament_lib.php';

    $ctx ??= amiga_snapshot_context_peek();
    $types = 'i';
    $params = [$playerId];
    $cutoffSql = '';
    if ($ctx !== null && $ctx->isActive()) {
        $cutoff = $ctx->cutoff();
        if ($cutoff !== null) {
            $cutoffSql = amiga_snapshot_event_tuple_cutoff_and_sql(
                $cutoff,
                $types,
                $params,
                'p.event_date',
                'p.event_chrono',
                'p.tournament_id',
            );
        }
    }

    $vis = amiga_tournament_public_visibility_where('t');
    $sql = 'SELECT numbered.* FROM ('
        . 'SELECT ranked.*, '
        . 'ROW_NUMBER() OVER (ORDER BY ranked.tournament_event_date ASC, ranked.tournament_chrono ASC, ranked.tournament_id ASC) AS unlock_rank '
        . 'FROM ('
        . 'SELECT '
        . 'p.tournament_id, '
        . 'p.tournament_name, '
        . 'p.event_date AS tournament_event_date, '
        . 'p.event_date AS `Date`, '
        . 'p.event_chrono AS tournament_chrono, '
        . 'TRIM(p.country) AS country_token, '
        . 'TRIM(p.country) AS tournament_country, '
        . 'p.games, '
        . 'ROW_NUMBER() OVER ('
        . 'PARTITION BY TRIM(p.country) '
        . 'ORDER BY p.event_date ASC, p.event_chrono ASC, p.tournament_id ASC'
        . ') AS meeting_rn '
        . 'FROM amiga_player_event_snapshots p '
        . 'INNER JOIN tournaments t ON t.id = p.tournament_id '
        . 'WHERE p.player_id = ? '
        . 'AND p.games > 0 '
        . "AND TRIM(IFNULL(p.country, '')) <> '' "
        . 'AND ' . $vis
        . $cutoffSql
        . ') ranked WHERE ranked.meeting_rn = 1'
        . ') numbered '
        . 'ORDER BY numbered.tournament_event_date DESC, numbered.tournament_chrono DESC, numbered.tournament_id DESC';

    $rows = amiga_games_query_all($con, $sql, $types, $params);
    foreach ($rows as &$row) {
        $row['unlock_rank'] = (int) ($row['unlock_rank'] ?? 0);
        $row['first_met_sort'] = amiga_player_chronology_opponents_first_met_sort_value($row);
        $row['first_met_label'] = amiga_player_chronology_opponents_first_met_label($row);
    }
    unset($row);

    return $rows;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function amiga_player_chronology_host_countries_chart_payload(
    mysqli $con,
    int $playerId,
    array $rows,
    string $playerName,
): array {
    $payload = amiga_player_chronology_opponents_chart_payload($con, $playerId, $rows, $playerName);
    $payload['kind_label'] = 'Host countries';

    return $payload;
}

/**
 * Shared first-game-per-opponent-country loader.
 *
 * @return list<array<string, mixed>>
 */
function amiga_player_chronology_opponent_countries_load(
    mysqli $con,
    int $playerId,
    string $extraFilterSql,
    ?AmigaSnapshotContext $ctx = null,
    bool $worldCupOnly = false,
): array {
    if ($playerId < 1) {
        return [];
    }

    $ctx ??= amiga_snapshot_context_peek();
    $pid = (int) $playerId;
    $types = '';
    $params = [];
    $cutoffSql = amiga_snapshot_rated_game_cutoff_and_sql($ctx, $types, $params);
    $fromSql = amiga_rated_games_from_sql($playerId);
    $countrySql = amiga_player_chronology_opponent_country_nonempty_sql($playerId, 'r');
    $wcSql = $worldCupOnly ? ' AND ' . amiga_games_world_cup_flag_sql('r.is_world_cup') : '';

    $sql = 'SELECT numbered.* FROM ('
        . 'SELECT ranked.*, '
        . 'ROW_NUMBER() OVER (ORDER BY ranked.tournament_event_date ASC, ranked.tournament_chrono ASC, ranked.tournament_id ASC, ranked.id ASC) AS unlock_rank '
        . 'FROM ('
        . 'SELECT inner_r.*, '
        . "CASE WHEN inner_r.idA = {$pid} THEN inner_r.idB ELSE inner_r.idA END AS opponent_id, "
        . "CASE WHEN inner_r.idA = {$pid} THEN inner_r.NameB ELSE inner_r.NameA END AS opponent_name, "
        . "TRIM(CASE WHEN inner_r.idA = {$pid} THEN inner_r.country_b ELSE inner_r.country_a END) AS country_token, "
        . "TRIM(CASE WHEN inner_r.idA = {$pid} THEN inner_r.country_b ELSE inner_r.country_a END) AS opponent_country, "
        . 'ROW_NUMBER() OVER ('
        . "PARTITION BY TRIM(CASE WHEN inner_r.idA = {$pid} THEN inner_r.country_b ELSE inner_r.country_a END) "
        . 'ORDER BY inner_r.tournament_event_date ASC, inner_r.tournament_chrono ASC, inner_r.tournament_id ASC, inner_r.id ASC'
        . ') AS meeting_rn '
        . 'FROM (SELECT r.* ' . $fromSql . ' WHERE 1=1' . $cutoffSql . $countrySql . $extraFilterSql . $wcSql . ') inner_r'
        . ') ranked WHERE ranked.meeting_rn = 1'
        . ') numbered '
        . 'ORDER BY numbered.tournament_event_date DESC, numbered.tournament_chrono DESC, numbered.tournament_id DESC, numbered.id DESC';

    $rows = amiga_games_query_all($con, $sql, $types, $params);
    foreach ($rows as &$row) {
        $row['unlock_rank'] = (int) ($row['unlock_rank'] ?? 0);
        $row['first_met_sort'] = amiga_player_chronology_opponents_first_met_sort_value($row);
        $row['first_met_label'] = amiga_player_chronology_opponents_first_met_label($row);
    }
    unset($row);

    return $rows;
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_player_chronology_countries_faced_load(
    mysqli $con,
    int $playerId,
    ?AmigaSnapshotContext $ctx = null,
    bool $worldCupOnly = false,
): array {
    return amiga_player_chronology_opponent_countries_load($con, $playerId, '', $ctx, $worldCupOnly);
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function amiga_player_chronology_countries_faced_chart_payload(
    mysqli $con,
    int $playerId,
    array $rows,
    string $playerName,
): array {
    $payload = amiga_player_chronology_opponents_chart_payload($con, $playerId, $rows, $playerName);
    $payload['kind_label'] = 'Countries faced';

    return $payload;
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_player_chronology_countries_beaten_load(
    mysqli $con,
    int $playerId,
    ?AmigaSnapshotContext $ctx = null,
    bool $worldCupOnly = false,
): array {
    return amiga_player_chronology_opponent_countries_load(
        $con,
        $playerId,
        amiga_player_chronology_hero_goals_win_sql($playerId, 'r'),
        $ctx,
        $worldCupOnly,
    );
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function amiga_player_chronology_countries_beaten_chart_payload(
    mysqli $con,
    int $playerId,
    array $rows,
    string $playerName,
): array {
    $payload = amiga_player_chronology_opponents_chart_payload($con, $playerId, $rows, $playerName);
    $payload['kind_label'] = 'Countries beaten';

    return $payload;
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_player_chronology_countries_beaten_by_load(
    mysqli $con,
    int $playerId,
    ?AmigaSnapshotContext $ctx = null,
    bool $worldCupOnly = false,
): array {
    return amiga_player_chronology_opponent_countries_load(
        $con,
        $playerId,
        amiga_player_chronology_hero_goals_loss_sql($playerId, 'r'),
        $ctx,
        $worldCupOnly,
    );
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function amiga_player_chronology_countries_beaten_by_chart_payload(
    mysqli $con,
    int $playerId,
    array $rows,
    string $playerName,
): array {
    $payload = amiga_player_chronology_opponents_chart_payload($con, $playerId, $rows, $playerName);
    $payload['kind_label'] = 'Countries beaten by';

    return $payload;
}