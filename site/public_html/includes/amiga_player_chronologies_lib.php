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
const AMIGA_PLAYER_CHRONOLOGY_SPOTLIGHT_FRAGMENT = 'k2-amiga-chronology-spotlight';

/** @return list<string> */
function amiga_player_chronology_valid_kinds(): array
{
    return [AMIGA_PLAYER_CHRONOLOGY_KIND_OPPONENTS];
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
    $href = amiga_player_chronology_opponents_href($playerId);
    if ($href === '') {
        return '';
    }

    return $href . amiga_player_chronology_spotlight_hash();
}

function amiga_player_chronology_kind_label(string $kind): string
{
    return match (amiga_player_chronology_parse_kind($kind)) {
        AMIGA_PLAYER_CHRONOLOGY_KIND_OPPONENTS => 'Opponents',
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
        default => '',
    };
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