<?php
/**
 * Amiga player chronologies — opponents first-meeting inventory (read-time spike).
 *
 * @see docs/player-profile-stat-links-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_amiga_routes.php';
require_once __DIR__ . '/amiga_db.php';
require_once __DIR__ . '/amiga_player_games_lib.php';
require_once __DIR__ . '/amiga_rated_game_row.php';
require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/amiga_player_load.php';

const AMIGA_PLAYER_CHRONOLOGY_KIND_OPPONENTS = 'opponents';
const AMIGA_PLAYER_CHRONOLOGY_KIND_VICTIMS = 'victims';
const AMIGA_PLAYER_CHRONOLOGY_KIND_DD_VICTIMS = 'dd_victims';
const AMIGA_PLAYER_CHRONOLOGY_KIND_CS_VICTIMS = 'cs_victims';
const AMIGA_PLAYER_CHRONOLOGY_KIND_MGC_VICTIMS = 'mgc_victims';
const AMIGA_PLAYER_CHRONOLOGY_KIND_BL_VICTIMS = 'bl_victims';
const AMIGA_PLAYER_CHRONOLOGY_KIND_MGS_CULPRITS = 'mgs_culprits';
const AMIGA_PLAYER_CHRONOLOGY_KIND_BW_CULPRITS = 'bw_culprits';
const AMIGA_PLAYER_CHRONOLOGY_SPOTLIGHT_FRAGMENT = 'k2-amiga-chronology-spotlight';

/** @return list<string> */
function amiga_player_chronology_valid_kinds(): array
{
    return [AMIGA_PLAYER_CHRONOLOGY_KIND_OPPONENTS, AMIGA_PLAYER_CHRONOLOGY_KIND_VICTIMS, AMIGA_PLAYER_CHRONOLOGY_KIND_DD_VICTIMS, AMIGA_PLAYER_CHRONOLOGY_KIND_CS_VICTIMS, AMIGA_PLAYER_CHRONOLOGY_KIND_MGC_VICTIMS, AMIGA_PLAYER_CHRONOLOGY_KIND_BL_VICTIMS, AMIGA_PLAYER_CHRONOLOGY_KIND_MGS_CULPRITS, AMIGA_PLAYER_CHRONOLOGY_KIND_BW_CULPRITS];
}

function amiga_player_chronology_parse_kind(?string $kind): string
{
    $kind = strtolower(trim((string) $kind));
    if (!in_array($kind, amiga_player_chronology_valid_kinds(), true)) {
        return AMIGA_PLAYER_CHRONOLOGY_KIND_OPPONENTS;
    }

    return $kind;
}

/** @return list<string> */
function amiga_player_chronology_valid_segments(): array
{
    return ['made-it', 'graphs'];
}

function amiga_player_chronology_parse_segment(?string $segment): string
{
    $segment = strtolower(trim((string) $segment));
    if (!in_array($segment, amiga_player_chronology_valid_segments(), true)) {
        return 'made-it';
    }

    return $segment;
}

function amiga_player_chronology_opponents_route_key(string $segment): string
{
    return $segment === 'graphs'
        ? 'amiga-player-chronologies-opponents-graphs'
        : 'amiga-player-chronologies-opponents-made-it';
}

function amiga_player_chronology_opponents_href(int $playerId, string $segment = 'made-it'): string
{
    if ($playerId < 1) {
        return '';
    }

    return k2_amiga_route(
        amiga_player_chronology_opponents_route_key(amiga_player_chronology_parse_segment($segment)),
        ['id' => $playerId],
    );
}

function amiga_player_chronology_spotlight_hash(): string
{
    return '#' . AMIGA_PLAYER_CHRONOLOGY_SPOTLIGHT_FRAGMENT;
}

/** Profile mosaic and other entry links — land on spotlight anchor above the card. */
function amiga_player_chronology_opponents_entry_href(int $playerId): string
{
    return amiga_player_chronology_entry_href($playerId, AMIGA_PLAYER_CHRONOLOGY_KIND_OPPONENTS);
}


function amiga_player_chronology_victims_route_key(string $segment): string
{
    return $segment === 'graphs'
        ? 'amiga-player-chronologies-victims-graphs'
        : 'amiga-player-chronologies-victims-made-it';
}

function amiga_player_chronology_victims_href(int $playerId, string $segment = 'made-it'): string
{
    if ($playerId < 1) {
        return '';
    }

    return k2_amiga_route(
        amiga_player_chronology_victims_route_key(amiga_player_chronology_parse_segment($segment)),
        ['id' => $playerId],
    );
}

function amiga_player_chronology_href(int $playerId, string $kind, string $segment = 'made-it'): string
{
    return match (amiga_player_chronology_parse_kind($kind)) {
        AMIGA_PLAYER_CHRONOLOGY_KIND_OPPONENTS => amiga_player_chronology_opponents_href($playerId, $segment),
        AMIGA_PLAYER_CHRONOLOGY_KIND_VICTIMS => amiga_player_chronology_victims_href($playerId, $segment),
        AMIGA_PLAYER_CHRONOLOGY_KIND_DD_VICTIMS => amiga_player_chronology_dd_victims_href($playerId, $segment),
        AMIGA_PLAYER_CHRONOLOGY_KIND_CS_VICTIMS => amiga_player_chronology_cs_victims_href($playerId, $segment),
        AMIGA_PLAYER_CHRONOLOGY_KIND_MGC_VICTIMS => amiga_player_chronology_mgc_victims_href($playerId, $segment),
        AMIGA_PLAYER_CHRONOLOGY_KIND_BL_VICTIMS => amiga_player_chronology_bl_victims_href($playerId, $segment),
        AMIGA_PLAYER_CHRONOLOGY_KIND_MGS_CULPRITS => amiga_player_chronology_mgs_culprits_href($playerId, $segment),
        AMIGA_PLAYER_CHRONOLOGY_KIND_BW_CULPRITS => amiga_player_chronology_bw_culprits_href($playerId, $segment),
        default => '',
    };
}

function amiga_player_chronology_entry_href(int $playerId, string $kind): string
{
    $href = amiga_player_chronology_href($playerId, $kind);
    if ($href === '') {
        return '';
    }

    return $href . amiga_player_chronology_spotlight_hash();
}

function amiga_player_chronology_victims_entry_href(int $playerId): string
{
    return amiga_player_chronology_entry_href($playerId, AMIGA_PLAYER_CHRONOLOGY_KIND_VICTIMS);
}


function amiga_player_chronology_dd_victims_route_key(string $segment): string
{
    return $segment === 'graphs'
        ? 'amiga-player-chronologies-dd-victims-graphs'
        : 'amiga-player-chronologies-dd-victims-made-it';
}

function amiga_player_chronology_dd_victims_href(int $playerId, string $segment = 'made-it'): string
{
    if ($playerId < 1) {
        return '';
    }

    return k2_amiga_route(
        amiga_player_chronology_dd_victims_route_key(amiga_player_chronology_parse_segment($segment)),
        ['id' => $playerId],
    );
}

function amiga_player_chronology_dd_victims_entry_href(int $playerId): string
{
    return amiga_player_chronology_entry_href($playerId, AMIGA_PLAYER_CHRONOLOGY_KIND_DD_VICTIMS);
}

function amiga_player_chronology_cs_victims_route_key(string $segment): string
{
    return $segment === 'graphs'
        ? 'amiga-player-chronologies-cs-victims-graphs'
        : 'amiga-player-chronologies-cs-victims-made-it';
}

function amiga_player_chronology_cs_victims_href(int $playerId, string $segment = 'made-it'): string
{
    if ($playerId < 1) {
        return '';
    }

    return k2_amiga_route(
        amiga_player_chronology_cs_victims_route_key(amiga_player_chronology_parse_segment($segment)),
        ['id' => $playerId],
    );
}

function amiga_player_chronology_cs_victims_entry_href(int $playerId): string
{
    return amiga_player_chronology_entry_href($playerId, AMIGA_PLAYER_CHRONOLOGY_KIND_CS_VICTIMS);
}

function amiga_player_chronology_mgc_victims_route_key(string $segment): string
{
    return $segment === 'graphs'
        ? 'amiga-player-chronologies-mgc-victims-graphs'
        : 'amiga-player-chronologies-mgc-victims-made-it';
}

function amiga_player_chronology_mgc_victims_href(int $playerId, string $segment = 'made-it'): string
{
    if ($playerId < 1) {
        return '';
    }

    return k2_amiga_route(
        amiga_player_chronology_mgc_victims_route_key(amiga_player_chronology_parse_segment($segment)),
        ['id' => $playerId],
    );
}

function amiga_player_chronology_mgc_victims_entry_href(int $playerId): string
{
    return amiga_player_chronology_entry_href($playerId, AMIGA_PLAYER_CHRONOLOGY_KIND_MGC_VICTIMS);
}

function amiga_player_chronology_bl_victims_route_key(string $segment): string
{
    return $segment === 'graphs'
        ? 'amiga-player-chronologies-bl-victims-graphs'
        : 'amiga-player-chronologies-bl-victims-made-it';
}

function amiga_player_chronology_bl_victims_href(int $playerId, string $segment = 'made-it'): string
{
    if ($playerId < 1) {
        return '';
    }

    return k2_amiga_route(
        amiga_player_chronology_bl_victims_route_key(amiga_player_chronology_parse_segment($segment)),
        ['id' => $playerId],
    );
}

function amiga_player_chronology_bl_victims_entry_href(int $playerId): string
{
    return amiga_player_chronology_entry_href($playerId, AMIGA_PLAYER_CHRONOLOGY_KIND_BL_VICTIMS);
}

function amiga_player_chronology_mgs_culprits_route_key(string $segment): string
{
    return $segment === 'graphs'
        ? 'amiga-player-chronologies-mgs-culprits-graphs'
        : 'amiga-player-chronologies-mgs-culprits-made-it';
}

function amiga_player_chronology_mgs_culprits_href(int $playerId, string $segment = 'made-it'): string
{
    if ($playerId < 1) {
        return '';
    }

    return k2_amiga_route(
        amiga_player_chronology_mgs_culprits_route_key(amiga_player_chronology_parse_segment($segment)),
        ['id' => $playerId],
    );
}

function amiga_player_chronology_mgs_culprits_entry_href(int $playerId): string
{
    return amiga_player_chronology_entry_href($playerId, AMIGA_PLAYER_CHRONOLOGY_KIND_MGS_CULPRITS);
}

function amiga_player_chronology_bw_culprits_route_key(string $segment): string
{
    return $segment === 'graphs'
        ? 'amiga-player-chronologies-bw-culprits-graphs'
        : 'amiga-player-chronologies-bw-culprits-made-it';
}

function amiga_player_chronology_bw_culprits_href(int $playerId, string $segment = 'made-it'): string
{
    if ($playerId < 1) {
        return '';
    }

    return k2_amiga_route(
        amiga_player_chronology_bw_culprits_route_key(amiga_player_chronology_parse_segment($segment)),
        ['id' => $playerId],
    );
}

function amiga_player_chronology_bw_culprits_entry_href(int $playerId): string
{
    return amiga_player_chronology_entry_href($playerId, AMIGA_PLAYER_CHRONOLOGY_KIND_BW_CULPRITS);
}
function amiga_player_chronology_kind_label(string $kind): string
{
    return match (amiga_player_chronology_parse_kind($kind)) {
        AMIGA_PLAYER_CHRONOLOGY_KIND_OPPONENTS => 'Opponents',
        AMIGA_PLAYER_CHRONOLOGY_KIND_VICTIMS => 'Victims',
        AMIGA_PLAYER_CHRONOLOGY_KIND_DD_VICTIMS => 'DD Victims',
        AMIGA_PLAYER_CHRONOLOGY_KIND_CS_VICTIMS => 'CS Victims',
        AMIGA_PLAYER_CHRONOLOGY_KIND_MGC_VICTIMS => 'MGC Victims',
        AMIGA_PLAYER_CHRONOLOGY_KIND_BL_VICTIMS => 'BL Victims',
        AMIGA_PLAYER_CHRONOLOGY_KIND_MGS_CULPRITS => 'MGS Culprits',
        AMIGA_PLAYER_CHRONOLOGY_KIND_BW_CULPRITS => 'BW Culprits',
        default => 'Chronology',
    };
}

function amiga_player_chronology_kind_rule_html(int $playerId, string $playerName, string $kind): string
{
    $nameEsc = k2_h($playerName);
    $profileHref = $playerId > 0 ? k2_amiga_player_profile_href($playerId) : '';
    $nameHtml = $profileHref !== ''
        ? '<a class="k2-link-star" href="' . k2_h($profileHref) . '">' . $nameEsc . '</a>'
        : $nameEsc;

    return match (amiga_player_chronology_parse_kind($kind)) {
        AMIGA_PLAYER_CHRONOLOGY_KIND_OPPONENTS => 'Players that ' . $nameHtml . ' has faced',
        AMIGA_PLAYER_CHRONOLOGY_KIND_VICTIMS => 'Players that ' . $nameHtml . ' has beaten at least once',
        AMIGA_PLAYER_CHRONOLOGY_KIND_DD_VICTIMS => 'Players that ' . $nameHtml . ' has scored 10 or more against at least once',
        AMIGA_PLAYER_CHRONOLOGY_KIND_CS_VICTIMS => 'Players that ' . $nameHtml . ' has shut out at least once',
        AMIGA_PLAYER_CHRONOLOGY_KIND_MGC_VICTIMS => 'Players whose most conceded goals game was against ' . $nameHtml,
        AMIGA_PLAYER_CHRONOLOGY_KIND_BL_VICTIMS => 'Players whose biggest loss game was against ' . $nameHtml,
        AMIGA_PLAYER_CHRONOLOGY_KIND_MGS_CULPRITS => 'Culprits whose most scored goals game was against ' . $nameHtml,
        AMIGA_PLAYER_CHRONOLOGY_KIND_BW_CULPRITS => 'Culprits whose biggest win game was against ' . $nameHtml,
        default => '',
    };
}

/**
 * Hero win predicate for rated games (ActualScore).
 */
function amiga_player_chronology_hero_win_sql(int $playerId, string $alias = 'r'): string
{
    $pid = (int) $playerId;
    $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
    if ($a === '') {
        $a = 'r';
    }

    return " AND (({$a}.idA = {$pid} AND ABS({$a}.ActualScore - 1.0) < 0.001)"
        . " OR ({$a}.idB = {$pid} AND ABS({$a}.ActualScore) < 0.001))";
}


/**
 * Hero goals-for minimum (e.g. double digits: 10).
 */
function amiga_player_chronology_hero_gf_min_sql(int $playerId, int $minGoals, string $alias = 'r'): string
{
    $pid = (int) $playerId;
    $min = max(0, (int) $minGoals);
    $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
    if ($a === '') {
        $a = 'r';
    }

    return " AND (CASE WHEN {$a}.idA = {$pid} THEN {$a}.GoalsA ELSE {$a}.GoalsB END >= {$min})";
}

/**
 * Hero goals-against maximum (e.g. clean sheet: 0).
 */
function amiga_player_chronology_hero_ga_max_sql(int $playerId, int $maxGoals, string $alias = 'r'): string
{
    $pid = (int) $playerId;
    $max = max(0, (int) $maxGoals);
    $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
    if ($a === '') {
        $a = 'r';
    }

    return " AND (CASE WHEN {$a}.idA = {$pid} THEN {$a}.GoalsB ELSE {$a}.GoalsA END <= {$max})";
}
/**
 * First rated meeting per opponent through cutoff (tournament chronology).
 *
 * @return list<array<string, mixed>>
 */
function amiga_player_chronology_opponents_load(
    mysqli $con,
    int $playerId,
    ?AmigaSnapshotContext $ctx = null,
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

    $sql = 'SELECT numbered.* FROM ('
        . 'SELECT ranked.*, '
        . 'ROW_NUMBER() OVER (ORDER BY ranked.tournament_event_date ASC, ranked.tournament_chrono ASC, ranked.tournament_id ASC, ranked.id ASC) AS unlock_rank '
        . 'FROM ('
        . 'SELECT inner_r.*, '
        . "CASE WHEN inner_r.idA = {$pid} THEN inner_r.idB ELSE inner_r.idA END AS opponent_id, "
        . "CASE WHEN inner_r.idA = {$pid} THEN inner_r.NameB ELSE inner_r.NameA END AS opponent_name, "
        . "CASE WHEN inner_r.idA = {$pid} THEN inner_r.country_b ELSE inner_r.country_a END AS opponent_country, "
        . 'ROW_NUMBER() OVER ('
        . "PARTITION BY CASE WHEN inner_r.idA = {$pid} THEN inner_r.idB ELSE inner_r.idA END "
        . 'ORDER BY inner_r.tournament_event_date ASC, inner_r.tournament_chrono ASC, inner_r.tournament_id ASC, inner_r.id ASC'
        . ') AS meeting_rn '
        . 'FROM (SELECT r.* ' . $fromSql . ' WHERE 1=1' . $cutoffSql . ') inner_r'
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
 * First rated win per victim through cutoff (tournament chronology).
 *
 * @return list<array<string, mixed>>
 */
function amiga_player_chronology_victims_load(
    mysqli $con,
    int $playerId,
    ?AmigaSnapshotContext $ctx = null,
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
    $winSql = amiga_player_chronology_hero_win_sql($playerId, 'r');

    $sql = 'SELECT numbered.* FROM ('
        . 'SELECT ranked.*, '
        . 'ROW_NUMBER() OVER (ORDER BY ranked.tournament_event_date ASC, ranked.tournament_chrono ASC, ranked.tournament_id ASC, ranked.id ASC) AS unlock_rank '
        . 'FROM ('
        . 'SELECT inner_r.*, '
        . "CASE WHEN inner_r.idA = {$pid} THEN inner_r.idB ELSE inner_r.idA END AS opponent_id, "
        . "CASE WHEN inner_r.idA = {$pid} THEN inner_r.NameB ELSE inner_r.NameA END AS opponent_name, "
        . "CASE WHEN inner_r.idA = {$pid} THEN inner_r.country_b ELSE inner_r.country_a END AS opponent_country, "
        . 'ROW_NUMBER() OVER ('
        . "PARTITION BY CASE WHEN inner_r.idA = {$pid} THEN inner_r.idB ELSE inner_r.idA END "
        . 'ORDER BY inner_r.tournament_event_date ASC, inner_r.tournament_chrono ASC, inner_r.tournament_id ASC, inner_r.id ASC'
        . ') AS meeting_rn '
        . 'FROM (SELECT r.* ' . $fromSql . ' WHERE 1=1' . $cutoffSql . $winSql . ') inner_r'
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
 * First rated double-digit game (hero GF >= 10) per victim through cutoff.
 *
 * @return list<array<string, mixed>>
 */
function amiga_player_chronology_dd_victims_load(
    mysqli $con,
    int $playerId,
    ?AmigaSnapshotContext $ctx = null,
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
    $ddSql = amiga_player_chronology_hero_gf_min_sql($playerId, 10, 'r');

    $sql = 'SELECT numbered.* FROM ('
        . 'SELECT ranked.*, '
        . 'ROW_NUMBER() OVER (ORDER BY ranked.tournament_event_date ASC, ranked.tournament_chrono ASC, ranked.tournament_id ASC, ranked.id ASC) AS unlock_rank '
        . 'FROM ('
        . 'SELECT inner_r.*, '
        . "CASE WHEN inner_r.idA = {$pid} THEN inner_r.idB ELSE inner_r.idA END AS opponent_id, "
        . "CASE WHEN inner_r.idA = {$pid} THEN inner_r.NameB ELSE inner_r.NameA END AS opponent_name, "
        . "CASE WHEN inner_r.idA = {$pid} THEN inner_r.country_b ELSE inner_r.country_a END AS opponent_country, "
        . 'ROW_NUMBER() OVER ('
        . "PARTITION BY CASE WHEN inner_r.idA = {$pid} THEN inner_r.idB ELSE inner_r.idA END "
        . 'ORDER BY inner_r.tournament_event_date ASC, inner_r.tournament_chrono ASC, inner_r.tournament_id ASC, inner_r.id ASC'
        . ') AS meeting_rn '
        . 'FROM (SELECT r.* ' . $fromSql . ' WHERE 1=1' . $cutoffSql . $ddSql . ') inner_r'
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
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function amiga_player_chronology_dd_victims_chart_payload(
    mysqli $con,
    int $playerId,
    array $rows,
    string $playerName,
): array {
    $payload = amiga_player_chronology_opponents_chart_payload($con, $playerId, $rows, $playerName);
    $payload['kind_label'] = 'DD Victims';

    return $payload;
}

/**
 * First rated clean-sheet game (hero GA = 0) per victim through cutoff.
 *
 * @return list<array<string, mixed>>
 */
function amiga_player_chronology_cs_victims_load(
    mysqli $con,
    int $playerId,
    ?AmigaSnapshotContext $ctx = null,
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
    $csSql = amiga_player_chronology_hero_ga_max_sql($playerId, 0, 'r');

    $sql = 'SELECT numbered.* FROM ('
        . 'SELECT ranked.*, '
        . 'ROW_NUMBER() OVER (ORDER BY ranked.tournament_event_date ASC, ranked.tournament_chrono ASC, ranked.tournament_id ASC, ranked.id ASC) AS unlock_rank '
        . 'FROM ('
        . 'SELECT inner_r.*, '
        . "CASE WHEN inner_r.idA = {$pid} THEN inner_r.idB ELSE inner_r.idA END AS opponent_id, "
        . "CASE WHEN inner_r.idA = {$pid} THEN inner_r.NameB ELSE inner_r.NameA END AS opponent_name, "
        . "CASE WHEN inner_r.idA = {$pid} THEN inner_r.country_b ELSE inner_r.country_a END AS opponent_country, "
        . 'ROW_NUMBER() OVER ('
        . "PARTITION BY CASE WHEN inner_r.idA = {$pid} THEN inner_r.idB ELSE inner_r.idA END "
        . 'ORDER BY inner_r.tournament_event_date ASC, inner_r.tournament_chrono ASC, inner_r.tournament_id ASC, inner_r.id ASC'
        . ') AS meeting_rn '
        . 'FROM (SELECT r.* ' . $fromSql . ' WHERE 1=1' . $cutoffSql . $csSql . ') inner_r'
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
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function amiga_player_chronology_cs_victims_chart_payload(
    mysqli $con,
    int $playerId,
    array $rows,
    string $playerName,
): array {
    $payload = amiga_player_chronology_opponents_chart_payload($con, $playerId, $rows, $playerName);
    $payload['kind_label'] = 'CS Victims';

    return $payload;
}
/**
 * @param array<string, mixed> $row
 */
function amiga_player_chronology_opponents_first_met_sort_value(array $row): string
{
    $date = trim((string) ($row['tournament_event_date'] ?? ''));
    if ($date === '' || k2_db_is_null($date)) {
        $date = substr((string) ($row['Date'] ?? ''), 0, 10);
    }
    $chrono = (int) ($row['tournament_chrono'] ?? 0);
    $tid = (int) ($row['tournament_id'] ?? 0);
    $gid = (int) ($row['id'] ?? 0);

    return sprintf('%s|%08d|%08d|%08d', $date !== '' ? $date : '0000-00-00', $chrono, $tid, $gid);
}

/**
 * @param array<string, mixed> $row
 */
function amiga_player_chronology_opponents_first_met_label(array $row): string
{
    $dateRaw = (string) ($row['Date'] ?? '');
    if ($dateRaw === '') {
        return '—';
    }

    return amiga_player_game_date_html($dateRaw);
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function amiga_player_chronology_opponents_chart_payload(
    mysqli $con,
    int $playerId,
    array $rows,
    string $playerName,
): array {
    $yearCounts = [];
    $points = [];
    $cumulative = 0;

    $chartRows = $rows;
    usort($chartRows, static function (array $a, array $b): int {
        return strcmp(
            amiga_player_chronology_opponents_first_met_sort_value($a),
            amiga_player_chronology_opponents_first_met_sort_value($b),
        );
    });

    foreach ($chartRows as $row) {
        $date = trim((string) ($row['tournament_event_date'] ?? ''));
        if ($date === '' || k2_db_is_null($date)) {
            $date = substr((string) ($row['Date'] ?? ''), 0, 10);
        }
        if ($date === '') {
            continue;
        }
        $year = (int) substr($date, 0, 4);
        if ($year > 0) {
            $yearCounts[$year] = ($yearCounts[$year] ?? 0) + 1;
        }
        ++$cumulative;
        $points[] = [
            'date' => $date,
            'cumulative' => $cumulative,
        ];
    }

    $firstYear = null;
    $firstRated = amiga_player_chronology_amiga_first_rated_year($con);
    if ($chartRows !== []) {
        $firstDate = trim((string) ($chartRows[0]['tournament_event_date'] ?? ''));
        if ($firstDate === '' || k2_db_is_null($firstDate)) {
            $firstDate = substr((string) ($chartRows[0]['Date'] ?? ''), 0, 10);
        }
        if ($firstDate !== '') {
            $firstYear = (int) substr($firstDate, 0, 4);
        }
    }
    if ($firstYear === null && $firstRated !== null) {
        $firstYear = $firstRated;
    }

    $currentYear = (int) gmdate('Y');
    $years = [];
    if ($firstYear !== null) {
        for ($y = $firstYear; $y <= $currentYear; ++$y) {
            $years[] = [
                'year' => $y,
                'unlocks' => (int) ($yearCounts[$y] ?? 0),
            ];
        }
    }

    return [
        'player_id' => $playerId,
        'player_name' => $playerName,
        'kind_label' => 'Opponents',
        'total_unlocks' => count($rows),
        'first_year' => $firstYear,
        'years' => $years,
        'cumulative_points' => $points,
    ];
}


/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function amiga_player_chronology_victims_chart_payload(
    mysqli $con,
    int $playerId,
    array $rows,
    string $playerName,
): array {
    $payload = amiga_player_chronology_opponents_chart_payload($con, $playerId, $rows, $playerName);
    $payload['kind_label'] = 'Victims';

    return $payload;
}

/**
 * Victims at cutoff whose personal MGC culprit pointer names $heroId.
 *
 * @return string SQL subquery aliased as snap with columns player_id, game_id
 */
function amiga_player_chronology_mgc_victim_snapshots_sql(
    int $heroId,
    ?AmigaSnapshotContext $ctx,
    string &$types,
    array &$params,
): string {
    $pid = (int) $heroId;
    if ($ctx !== null && $ctx->isActive()) {
        $cutoff = $ctx->cutoff();
        if ($cutoff !== null) {
            $types .= 'sdii';
            $params[] = $cutoff['event_date'];
            $params[] = $cutoff['chrono'];
            $params[] = $cutoff['tournament_id'];
            $params[] = $pid;

            return '(SELECT x.player_id, x.MostGoalsConcededGameID AS game_id FROM ('
                . 'SELECT snap.player_id, snap.MostGoalsConcededCulpritID, snap.MostGoalsConcededGameID,'
                . 'ROW_NUMBER() OVER (PARTITION BY snap.player_id ORDER BY snap.event_date DESC, snap.event_chrono DESC, snap.tournament_id DESC) AS rn '
                . 'FROM amiga_player_event_snapshots snap '
                . 'WHERE (snap.event_date, snap.event_chrono, snap.tournament_id) <= (?, ?, ?)'
                . ') x WHERE x.rn = 1 AND x.MostGoalsConcededCulpritID = ? '
                . 'AND x.MostGoalsConcededGameID IS NOT NULL AND x.MostGoalsConcededGameID > 0) snap';
        }
    }

    $types .= 'i';
    $params[] = $pid;

    return '(SELECT s.player_id, s.MostGoalsConcededGameID AS game_id '
        . 'FROM amiga_player_current s '
        . 'WHERE s.MostGoalsConcededCulpritID = ? '
        . 'AND s.MostGoalsConcededGameID IS NOT NULL AND s.MostGoalsConcededGameID > 0) snap';
}

/**
 * Current MGC victims at cutoff — inverse pointer inventory (stored snapshot truth).
 *
 * @return list<array<string, mixed>>
 */
function amiga_player_chronology_mgc_victims_load(
    mysqli $con,
    int $playerId,
    ?AmigaSnapshotContext $ctx = null,
): array {
    if ($playerId < 1) {
        return [];
    }

    $ctx ??= amiga_snapshot_context_peek();
    $pid = (int) $playerId;
    $types = '';
    $params = [];
    $snapSql = amiga_player_chronology_mgc_victim_snapshots_sql($pid, $ctx, $types, $params);
    $cutoffSql = amiga_snapshot_rated_game_cutoff_and_sql($ctx, $types, $params);
    $fromSql = amiga_rated_games_from_sql(null);

    $sql = 'SELECT numbered.* FROM ('
        . 'SELECT ranked.*, '
        . 'ROW_NUMBER() OVER (ORDER BY ranked.tournament_event_date ASC, ranked.tournament_chrono ASC, ranked.tournament_id ASC, ranked.id ASC) AS unlock_rank '
        . 'FROM ('
        . 'SELECT r.*, p.id AS opponent_id, p.name AS opponent_name, p.country AS opponent_country '
        . 'FROM ' . $snapSql . ' '
        . 'INNER JOIN amiga_players p ON p.id = snap.player_id '
        . 'INNER JOIN (SELECT r.* ' . $fromSql . ' WHERE 1=1' . $cutoffSql . ') r ON r.id = snap.game_id '
        . 'WHERE (r.idA = ' . $pid . ' OR r.idB = ' . $pid . ')'
        . ') ranked'
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
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function amiga_player_chronology_mgc_victims_chart_payload(
    mysqli $con,
    int $playerId,
    array $rows,
    string $playerName,
): array {
    $payload = amiga_player_chronology_opponents_chart_payload($con, $playerId, $rows, $playerName);
    $payload['kind_label'] = 'MGC Victims';

    return $payload;
}

/**
 * Victims at cutoff whose personal BL culprit pointer names $heroId.
 *
 * @return string SQL subquery aliased as snap with columns player_id, game_id
 */
function amiga_player_chronology_bl_victim_snapshots_sql(
    int $heroId,
    ?AmigaSnapshotContext $ctx,
    string &$types,
    array &$params,
): string {
    $pid = (int) $heroId;
    if ($ctx !== null && $ctx->isActive()) {
        $cutoff = $ctx->cutoff();
        if ($cutoff !== null) {
            $types .= 'sdii';
            $params[] = $cutoff['event_date'];
            $params[] = $cutoff['chrono'];
            $params[] = $cutoff['tournament_id'];
            $params[] = $pid;

            return '(SELECT x.player_id, x.BiggestLossGameID AS game_id FROM ('
                . 'SELECT snap.player_id, snap.BiggestLossCulpritID, snap.BiggestLossGameID,'
                . 'ROW_NUMBER() OVER (PARTITION BY snap.player_id ORDER BY snap.event_date DESC, snap.event_chrono DESC, snap.tournament_id DESC) AS rn '
                . 'FROM amiga_player_event_snapshots snap '
                . 'WHERE (snap.event_date, snap.event_chrono, snap.tournament_id) <= (?, ?, ?)'
                . ') x WHERE x.rn = 1 AND x.BiggestLossCulpritID = ? '
                . 'AND x.BiggestLossGameID IS NOT NULL AND x.BiggestLossGameID > 0) snap';
        }
    }

    $types .= 'i';
    $params[] = $pid;

    return '(SELECT s.player_id, s.BiggestLossGameID AS game_id '
        . 'FROM amiga_player_current s '
        . 'WHERE s.BiggestLossCulpritID = ? '
        . 'AND s.BiggestLossGameID IS NOT NULL AND s.BiggestLossGameID > 0) snap';
}

/**
 * Current BL victims at cutoff — inverse pointer inventory (stored snapshot truth).
 *
 * @return list<array<string, mixed>>
 */
function amiga_player_chronology_bl_victims_load(
    mysqli $con,
    int $playerId,
    ?AmigaSnapshotContext $ctx = null,
): array {
    if ($playerId < 1) {
        return [];
    }

    $ctx ??= amiga_snapshot_context_peek();
    $pid = (int) $playerId;
    $types = '';
    $params = [];
    $snapSql = amiga_player_chronology_bl_victim_snapshots_sql($pid, $ctx, $types, $params);
    $cutoffSql = amiga_snapshot_rated_game_cutoff_and_sql($ctx, $types, $params);
    $fromSql = amiga_rated_games_from_sql(null);

    $sql = 'SELECT numbered.* FROM ('
        . 'SELECT ranked.*, '
        . 'ROW_NUMBER() OVER (ORDER BY ranked.tournament_event_date ASC, ranked.tournament_chrono ASC, ranked.tournament_id ASC, ranked.id ASC) AS unlock_rank '
        . 'FROM ('
        . 'SELECT r.*, p.id AS opponent_id, p.name AS opponent_name, p.country AS opponent_country '
        . 'FROM ' . $snapSql . ' '
        . 'INNER JOIN amiga_players p ON p.id = snap.player_id '
        . 'INNER JOIN (SELECT r.* ' . $fromSql . ' WHERE 1=1' . $cutoffSql . ') r ON r.id = snap.game_id '
        . 'WHERE (r.idA = ' . $pid . ' OR r.idB = ' . $pid . ')'
        . ') ranked'
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
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function amiga_player_chronology_bl_victims_chart_payload(
    mysqli $con,
    int $playerId,
    array $rows,
    string $playerName,
): array {
    $payload = amiga_player_chronology_opponents_chart_payload($con, $playerId, $rows, $playerName);
    $payload['kind_label'] = 'BL Victims';

    return $payload;
}

/**
 * Culprits at cutoff whose personal MGS victim pointer names $heroId.
 *
 * @return string SQL subquery aliased as snap with columns player_id, game_id
 */
function amiga_player_chronology_mgs_culprit_snapshots_sql(
    int $heroId,
    ?AmigaSnapshotContext $ctx,
    string &$types,
    array &$params,
): string {
    $pid = (int) $heroId;
    if ($ctx !== null && $ctx->isActive()) {
        $cutoff = $ctx->cutoff();
        if ($cutoff !== null) {
            $types .= 'sdii';
            $params[] = $cutoff['event_date'];
            $params[] = $cutoff['chrono'];
            $params[] = $cutoff['tournament_id'];
            $params[] = $pid;

            return '(SELECT x.player_id, x.MostGoalsScoredGameID AS game_id FROM ('
                . 'SELECT snap.player_id, snap.MostGoalsScoredVictimID, snap.MostGoalsScoredGameID,'
                . 'ROW_NUMBER() OVER (PARTITION BY snap.player_id ORDER BY snap.event_date DESC, snap.event_chrono DESC, snap.tournament_id DESC) AS rn '
                . 'FROM amiga_player_event_snapshots snap '
                . 'WHERE (snap.event_date, snap.event_chrono, snap.tournament_id) <= (?, ?, ?)'
                . ') x WHERE x.rn = 1 AND x.MostGoalsScoredVictimID = ? '
                . 'AND x.MostGoalsScoredGameID IS NOT NULL AND x.MostGoalsScoredGameID > 0) snap';
        }
    }

    $types .= 'i';
    $params[] = $pid;

    return '(SELECT s.player_id, s.MostGoalsScoredGameID AS game_id '
        . 'FROM amiga_player_current s '
        . 'WHERE s.MostGoalsScoredVictimID = ? '
        . 'AND s.MostGoalsScoredGameID IS NOT NULL AND s.MostGoalsScoredGameID > 0) snap';
}

/**
 * Current MGS culprits at cutoff — inverse victim-pointer inventory (stored snapshot truth).
 *
 * @return list<array<string, mixed>>
 */
function amiga_player_chronology_mgs_culprits_load(
    mysqli $con,
    int $playerId,
    ?AmigaSnapshotContext $ctx = null,
): array {
    if ($playerId < 1) {
        return [];
    }

    $ctx ??= amiga_snapshot_context_peek();
    $pid = (int) $playerId;
    $types = '';
    $params = [];
    $snapSql = amiga_player_chronology_mgs_culprit_snapshots_sql($pid, $ctx, $types, $params);
    $cutoffSql = amiga_snapshot_rated_game_cutoff_and_sql($ctx, $types, $params);
    $fromSql = amiga_rated_games_from_sql(null);

    $sql = 'SELECT numbered.* FROM ('
        . 'SELECT ranked.*, '
        . 'ROW_NUMBER() OVER (ORDER BY ranked.tournament_event_date ASC, ranked.tournament_chrono ASC, ranked.tournament_id ASC, ranked.id ASC) AS unlock_rank '
        . 'FROM ('
        . 'SELECT r.*, p.id AS opponent_id, p.name AS opponent_name, p.country AS opponent_country '
        . 'FROM ' . $snapSql . ' '
        . 'INNER JOIN amiga_players p ON p.id = snap.player_id '
        . 'INNER JOIN (SELECT r.* ' . $fromSql . ' WHERE 1=1' . $cutoffSql . ') r ON r.id = snap.game_id '
        . 'WHERE (r.idA = ' . $pid . ' OR r.idB = ' . $pid . ')'
        . ') ranked'
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
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function amiga_player_chronology_mgs_culprits_chart_payload(
    mysqli $con,
    int $playerId,
    array $rows,
    string $playerName,
): array {
    $payload = amiga_player_chronology_opponents_chart_payload($con, $playerId, $rows, $playerName);
    $payload['kind_label'] = 'MGS Culprits';

    return $payload;
}

/**
 * Culprits at cutoff whose personal BW victim pointer names $heroId.
 *
 * @return string SQL subquery aliased as snap with columns player_id, game_id
 */
function amiga_player_chronology_bw_culprit_snapshots_sql(
    int $heroId,
    ?AmigaSnapshotContext $ctx,
    string &$types,
    array &$params,
): string {
    $pid = (int) $heroId;
    if ($ctx !== null && $ctx->isActive()) {
        $cutoff = $ctx->cutoff();
        if ($cutoff !== null) {
            $types .= 'sdii';
            $params[] = $cutoff['event_date'];
            $params[] = $cutoff['chrono'];
            $params[] = $cutoff['tournament_id'];
            $params[] = $pid;

            return '(SELECT x.player_id, x.BiggestWinGameID AS game_id FROM ('
                . 'SELECT snap.player_id, snap.BiggestWinVictimID, snap.BiggestWinGameID,'
                . 'ROW_NUMBER() OVER (PARTITION BY snap.player_id ORDER BY snap.event_date DESC, snap.event_chrono DESC, snap.tournament_id DESC) AS rn '
                . 'FROM amiga_player_event_snapshots snap '
                . 'WHERE (snap.event_date, snap.event_chrono, snap.tournament_id) <= (?, ?, ?)'
                . ') x WHERE x.rn = 1 AND x.BiggestWinVictimID = ? '
                . 'AND x.BiggestWinGameID IS NOT NULL AND x.BiggestWinGameID > 0) snap';
        }
    }

    $types .= 'i';
    $params[] = $pid;

    return '(SELECT s.player_id, s.BiggestWinGameID AS game_id '
        . 'FROM amiga_player_current s '
        . 'WHERE s.BiggestWinVictimID = ? '
        . 'AND s.BiggestWinGameID IS NOT NULL AND s.BiggestWinGameID > 0) snap';
}

/**
 * Current BW culprits at cutoff — inverse victim-pointer inventory (stored snapshot truth).
 *
 * @return list<array<string, mixed>>
 */
function amiga_player_chronology_bw_culprits_load(
    mysqli $con,
    int $playerId,
    ?AmigaSnapshotContext $ctx = null,
): array {
    if ($playerId < 1) {
        return [];
    }

    $ctx ??= amiga_snapshot_context_peek();
    $pid = (int) $playerId;
    $types = '';
    $params = [];
    $snapSql = amiga_player_chronology_bw_culprit_snapshots_sql($pid, $ctx, $types, $params);
    $cutoffSql = amiga_snapshot_rated_game_cutoff_and_sql($ctx, $types, $params);
    $fromSql = amiga_rated_games_from_sql(null);

    $sql = 'SELECT numbered.* FROM ('
        . 'SELECT ranked.*, '
        . 'ROW_NUMBER() OVER (ORDER BY ranked.tournament_event_date ASC, ranked.tournament_chrono ASC, ranked.tournament_id ASC, ranked.id ASC) AS unlock_rank '
        . 'FROM ('
        . 'SELECT r.*, p.id AS opponent_id, p.name AS opponent_name, p.country AS opponent_country '
        . 'FROM ' . $snapSql . ' '
        . 'INNER JOIN amiga_players p ON p.id = snap.player_id '
        . 'INNER JOIN (SELECT r.* ' . $fromSql . ' WHERE 1=1' . $cutoffSql . ') r ON r.id = snap.game_id '
        . 'WHERE (r.idA = ' . $pid . ' OR r.idB = ' . $pid . ')'
        . ') ranked'
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
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function amiga_player_chronology_bw_culprits_chart_payload(
    mysqli $con,
    int $playerId,
    array $rows,
    string $playerName,
): array {
    $payload = amiga_player_chronology_opponents_chart_payload($con, $playerId, $rows, $playerName);
    $payload['kind_label'] = 'BW Culprits';

    return $payload;
}
function amiga_player_chronology_amiga_first_rated_year(mysqli $con): ?int
{
    $res = $con->query(
        'SELECT MIN(t.event_date) AS first_date FROM amiga_games g '
        . 'INNER JOIN tournaments t ON t.id = g.tournament_id '
        . 'WHERE t.event_date IS NOT NULL'
    );
    if (!$res) {
        return null;
    }
    $row = mysqli_fetch_assoc($res);
    mysqli_free_result($res);
    if ($row === null || k2_db_is_null($row['first_date'] ?? null)) {
        return null;
    }

    return (int) substr((string) $row['first_date'], 0, 4);
}