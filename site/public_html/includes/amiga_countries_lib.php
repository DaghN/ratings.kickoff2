<?php
/**
 * Amiga Countries hub — player rows at cutoff + country roll-ups.
 *
 * @see docs/amiga-countries-hub-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_player_current_lib.php';
require_once __DIR__ . '/amiga_player_slice_lib.php';
require_once __DIR__ . '/amiga_lb_snapshot_lib.php';
require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/amiga_player_load.php';
require_once __DIR__ . '/k2_amiga_routes.php';

const AMIGA_COUNTRIES_UNKNOWN_TOKEN = 'Unknown';

/** Country roster hero scroll target — keep in sync with k2_amiga_country_roster_href(). */
const K2_AMIGA_COUNTRY_ROSTER_FRAGMENT = 'k2-country-roster';

function k2_amiga_country_roster_anchor_id(): string
{
    return K2_AMIGA_COUNTRY_ROSTER_FRAGMENT;
}

function k2_amiga_country_roster_anchor_hash(): string
{
    return '#' . k2_amiga_country_roster_anchor_id();
}

function k2_amiga_country_roster_anchor_markup(): string
{
    return '<div id="' . k2_amiga_country_roster_anchor_id() . '" class="k2-countries-scroll-anchor" tabindex="-1"></div>';
}

function amiga_countries_token_sql(string $playerAlias = 'p'): string
{
    return 'CASE WHEN TRIM(' . $playerAlias . '.country) IS NULL OR TRIM(' . $playerAlias . '.country) = \'\' '
        . 'THEN \'' . AMIGA_COUNTRIES_UNKNOWN_TOKEN . '\' ELSE TRIM(' . $playerAlias . '.country) END';
}

function k2_amiga_country_roster_href(string $countryToken, bool $scrollToHero = true): string
{
    // Country is an entity page (docs/navigation-model.md NM3): singular `country/`
    // namespace, not the plural `countries/` hub folder.
    $href = k2_amiga_route('amiga-country-roster', ['country' => $countryToken]);
    if ($scrollToHero) {
        $href .= k2_amiga_country_roster_anchor_hash();
    }

    return $href;
}

function amiga_countries_normalize_country_param(string $raw): string
{
    $raw = trim($raw);

    return $raw;
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_countries_player_rows(mysqli $con, AmigaSnapshotContext $ctx): array
{
    if (!$ctx->isActive()) {
        return amiga_countries_player_rows_present($con);
    }

    return amiga_countries_player_rows_at_cutoff($con, $ctx);
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_countries_player_rows_present(mysqli $con): array
{
    $tokenSql = amiga_countries_token_sql('p');
    $sliceKey = amiga_slice_key_world_cup();
    $sql = 'SELECT p.id AS player_id, p.name AS player_name, ' . $tokenSql . ' AS country_token, '
        . 'COALESCE(s.Rating, 0) AS rating, s.elo_rank, COALESCE(s.NumberGames, 0) AS number_games, '
        . 'COALESCE(wcs.tournaments_played, 0) AS wc_played, COALESCE(wcs.gold, 0) AS wc_gold, '
        . 'COALESCE(wcs.silver, 0) AS wc_silver, COALESCE(wcs.bronze, 0) AS wc_bronze, '
        . 's.last_tournament_id, s.last_event_date, lt.name AS last_tournament_name, lt.country AS last_tournament_country '
        . amiga_player_base_from_sql($con, 's') . ' '
        . 'LEFT JOIN amiga_player_slice_totals wcs ON wcs.player_id = p.id AND wcs.slice_key = ? '
        . 'LEFT JOIN tournaments lt ON lt.id = s.last_tournament_id '
        . 'WHERE s.NumberGames > 0 '
        . 'ORDER BY s.Rating DESC, p.id ASC';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare amiga_countries_player_rows_present: ' . $con->error);
    }
    $stmt->bind_param('s', $sliceKey);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute amiga_countries_player_rows_present: ' . $stmt->error);
    }
    $result = $stmt->get_result();
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = amiga_countries_normalize_player_row($row);
        }
        $result->free();
    }
    $stmt->close();

    return $rows;
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_countries_player_rows_at_cutoff(mysqli $con, AmigaSnapshotContext $ctx): array
{
    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return [];
    }

    $tokenSql = amiga_countries_token_sql('p');
    $sliceKey = amiga_slice_key_world_cup();
    $sliceJoin = amiga_slice_at_cutoff_join_sql();
    $sql = 'SELECT p.id AS player_id, p.name AS player_name, ' . $tokenSql . ' AS country_token, '
        . 'COALESCE(s.Rating, 0) AS rating, COALESCE(s.NumberGames, 0) AS number_games, '
        . 'COALESCE(wcs.tournaments_played, 0) AS wc_played, COALESCE(wcs.gold, 0) AS wc_gold, '
        . 'COALESCE(wcs.silver, 0) AS wc_silver, COALESCE(wcs.bronze, 0) AS wc_bronze, '
        . 's.tournament_id AS last_tournament_id, s.event_date AS last_event_date, lt.name AS last_tournament_name, lt.country AS last_tournament_country '
        . amiga_lb_snapshot_from_sql('s') . ' '
        . str_replace('t.player_id', 'p.id', $sliceJoin['sql']) . ' '
        . 'LEFT JOIN tournaments lt ON lt.id = s.tournament_id '
        . 'WHERE s.NumberGames > 0 '
        . 'ORDER BY s.Rating DESC, p.id ASC';

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare amiga_countries_player_rows_at_cutoff: ' . $con->error);
    }

    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tournamentId = $cutoff['tournament_id'];
    $stmt->bind_param(
        'sdissdi',
        $eventDate,
        $chrono,
        $tournamentId,
        $sliceKey,
        $eventDate,
        $chrono,
        $tournamentId
    );
    if (!$stmt->execute()) {
        throw new RuntimeException('execute amiga_countries_player_rows_at_cutoff: ' . $stmt->error);
    }
    $result = $stmt->get_result();
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = amiga_countries_normalize_player_row($row);
        }
        $result->free();
    }
    $stmt->close();

    return amiga_countries_attach_elo_ranks_at_cutoff($con, $ctx, $rows);
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function amiga_countries_normalize_player_row(array $row): array
{
    $row['player_id'] = (int) ($row['player_id'] ?? 0);
    $row['country_token'] = (string) ($row['country_token'] ?? '');
    $row['rating_sort'] = (float) ($row['rating'] ?? 0);
    $row['rating'] = (int) round($row['rating_sort']);
    $row['elo_rank'] = amiga_player_normalize_elo_rank($row['elo_rank'] ?? null);
    $row['number_games'] = (int) ($row['number_games'] ?? 0);
    $row['wc_played'] = (int) ($row['wc_played'] ?? 0);
    $row['wc_gold'] = (int) ($row['wc_gold'] ?? 0);
    $row['wc_silver'] = (int) ($row['wc_silver'] ?? 0);
    $row['wc_bronze'] = (int) ($row['wc_bronze'] ?? 0);
    $row['last_tournament_id'] = isset($row['last_tournament_id']) && $row['last_tournament_id'] !== null
        ? (int) $row['last_tournament_id'] : null;
    $row['last_event_date'] = isset($row['last_event_date']) && $row['last_event_date'] !== null
        ? (string) $row['last_event_date'] : null;
    $row['last_tournament_name'] = trim((string) ($row['last_tournament_name'] ?? ''));
    $row['last_tournament_country'] = trim((string) ($row['last_tournament_country'] ?? ''));

    return $row;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function amiga_countries_attach_elo_ranks_at_cutoff(mysqli $con, AmigaSnapshotContext $ctx, array $rows): array
{
    if ($rows === [] || !$ctx->isActive()) {
        return $rows;
    }

    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return $rows;
    }

    $rankByPlayer = [];
    $sql = 'SELECT r.player_id, r.elo_rank FROM (
        SELECT er.player_id, er.elo_rank,
            ROW_NUMBER() OVER (
                PARTITION BY er.player_id
                ORDER BY er.event_date DESC, er.event_chrono DESC, er.tournament_id DESC
            ) AS rn
        FROM amiga_player_elo_rank_at_event er
        WHERE (er.event_date, er.event_chrono, er.tournament_id) <= (?, ?, ?)
    ) r WHERE r.rn = 1';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        return $rows;
    }
    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tournamentId = $cutoff['tournament_id'];
    $stmt->bind_param('sdi', $eventDate, $chrono, $tournamentId);
    if (!$stmt->execute()) {
        $stmt->close();

        return $rows;
    }
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rankByPlayer[(int) $row['player_id']] = amiga_player_normalize_elo_rank($row['elo_rank'] ?? null);
        }
        $result->free();
    }
    $stmt->close();

    foreach ($rows as $i => $row) {
        $pid = (int) $row['player_id'];
        $rows[$i]['elo_rank'] = $rankByPlayer[$pid] ?? null;
    }

    return $rows;
}

/**
 * @param list<array<string, mixed>> $playerRows
 * @return list<array<string, mixed>>
 */
function amiga_countries_index_rows(array $playerRows): array
{
    /** @var array<string, array<string, mixed>> $byToken */
    $byToken = [];
    foreach ($playerRows as $row) {
        $token = (string) $row['country_token'];
        if (!isset($byToken[$token])) {
            $byToken[$token] = [
                'country_token' => $token,
                'players' => 0,
                'games' => 0,
                'wc_entries' => 0,
                'wc_gold' => 0,
                'wc_silver' => 0,
                'wc_bronze' => 0,
            ];
        }
        $byToken[$token]['players']++;
        $byToken[$token]['games'] += (int) $row['number_games'];
        $byToken[$token]['wc_entries'] += (int) $row['wc_played'];
        $byToken[$token]['wc_gold'] += (int) $row['wc_gold'];
        $byToken[$token]['wc_silver'] += (int) $row['wc_silver'];
        $byToken[$token]['wc_bronze'] += (int) $row['wc_bronze'];
    }

    $indexRows = [];
    foreach ($byToken as $row) {
        $players = (int) $row['players'];
        $row['games_per_player'] = $players > 0 ? round($row['games'] / $players, 1) : 0.0;
        $indexRows[] = $row;
    }

    usort($indexRows, static function (array $a, array $b): int {
        $cmp = $b['players'] <=> $a['players'];
        if ($cmp !== 0) {
            return $cmp;
        }

        return strcmp((string) $a['country_token'], (string) $b['country_token']);
    });

    return $indexRows;
}

/**
 * @param list<array<string, mixed>> $playerRows
 * @return list<array<string, mixed>>
 */
function amiga_countries_roster_rows(array $playerRows, string $countryToken): array
{
    $countryToken = trim($countryToken);
    $rows = [];
    foreach ($playerRows as $row) {
        if ((string) $row['country_token'] === $countryToken) {
            $rows[] = $row;
        }
    }

    usort($rows, static function (array $a, array $b): int {
        $cmp = $b['rating_sort'] <=> $a['rating_sort'];
        if ($cmp !== 0) {
            return $cmp;
        }

        return $a['player_id'] <=> $b['player_id'];
    });

    return $rows;
}

/**
 * @param list<array<string, mixed>> $indexRows
 * @return ?array<string, mixed>
 */
function amiga_countries_index_row_for_token(array $indexRows, string $countryToken): ?array
{
    foreach ($indexRows as $row) {
        if ((string) $row['country_token'] === $countryToken) {
            return $row;
        }
    }

    return null;
}

/**
 * @return array<string, int> country_token => rated player count at snapshot
 */
function amiga_countries_player_counts_by_token(mysqli $con, ?AmigaSnapshotContext $ctx = null): array
{
    static $cache = [];
    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();
    if ($ctx->isActive()) {
        $cutoff = $ctx->cutoff();
        $cacheKey = $cutoff === null
            ? 'at:empty'
            : 'at:' . (int) ($cutoff['tournament_id'] ?? 0) . ':' . (string) ($cutoff['event_date'] ?? '') . ':' . (string) ($cutoff['chrono'] ?? '');
    } else {
        $cacheKey = 'present';
    }
    if (!isset($cache[$cacheKey])) {
        $map = [];
        foreach (amiga_countries_index_rows(amiga_countries_player_rows($con, $ctx)) as $row) {
            $map[(string) $row['country_token']] = (int) $row['players'];
        }
        $cache[$cacheKey] = $map;
    }

    return $cache[$cacheKey];
}

function amiga_countries_player_count(mysqli $con, string $countryToken, ?AmigaSnapshotContext $ctx = null): int
{
    $countryToken = amiga_countries_normalize_country_param($countryToken);
    if ($countryToken === '') {
        return 0;
    }
    $map = amiga_countries_player_counts_by_token($con, $ctx);

    return $map[$countryToken] ?? 0;
}

function amiga_countries_wc_stats_href_for_token(string $countryToken): string
{
    return k2_amiga_route('amiga-world-cups-countries-honours');
}