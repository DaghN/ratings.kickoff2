<?php
/**
 * Amiga tournament standings read path (derived amiga_tournament_standings).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';

/** Lifecycle statuses shown on public tournament pages (index, detail, profile links). */
const AMIGA_TOURNAMENT_PUBLIC_LIFECYCLE_STATUSES = ['completed', 'archived'];

/**
 * Tournament IDs intentionally published on the public live view.
 * Add ids here when an event should appear on /amiga/live-tournaments.php.
 * Local overrides: $amigaPublicLiveTournamentIds in ko2amiga_config.local.php (gitignored).
 */
const AMIGA_PUBLIC_LIVE_TOURNAMENT_IDS = [];
const AMIGA_LIVE_TOURNAMENT_INDEX_ANCHOR_COL = 0;
const AMIGA_LIVE_TOURNAMENT_INDEX_DEFAULT_SORT_COL = 1;
const AMIGA_TOURNAMENT_STANDINGS_ANCHOR_COL = 1;
const AMIGA_TOURNAMENT_STANDINGS_DEFAULT_SORT_COL = 0;
const AMIGA_TOURNAMENT_GAMES_DEFAULT_SORT_COL = 0;

/** Prefixes in tournaments.format_overrides.generated_by for fixture-backed generated events. */
const AMIGA_FIXTURE_GENERATED_BY_PREFIXES = [
    'scripts.amiga.tournament_builder',
    'site.public_html.amiga.ops.fixtures',
];

/** Hash target on tournament detail pages — zero-height anchor flush above the hero title. */
const AMIGA_TOURNAMENT_PAGE_FRAGMENT = 'tournament';

function amiga_tournament_path_for_view(?string $view): string
{
    return match ($view) {
        'games' => '/amiga/tournament/games.php',
        'stages' => '/amiga/tournament/stages.php',
        'standings' => '/amiga/tournament/standings.php',
        'event-stats', null => '/amiga/tournament/event-stats.php',
        default => '/amiga/tournament/event-stats.php',
    };
}

function amiga_tournament_view_from_request(?string $path = null): ?string
{
    require_once __DIR__ . '/k2_table_helpers.php';
    require_once __DIR__ . '/amiga_snapshot_url.php';

    return match (k2_table_path_only($path ?? amiga_snapshot_request_path())) {
        '/amiga/tournament/event-stats.php' => 'event-stats',
        '/amiga/tournament/games.php' => 'games',
        '/amiga/tournament/stages.php' => 'stages',
        '/amiga/tournament/standings.php' => 'standings',
        default => null,
    };
}

function amiga_tournament_page_request_path(?string $path = null): bool
{
    require_once __DIR__ . '/k2_table_helpers.php';
    require_once __DIR__ . '/amiga_snapshot_url.php';
    $pathOnly = k2_table_path_only($path ?? amiga_snapshot_request_path());

    if ($pathOnly === '/amiga/tournament.php') {
        return true;
    }

    return str_starts_with($pathOnly, '/amiga/tournament/');
}

function amiga_tournament_id_from_request(): int
{
    return isset($_GET['id']) ? max(0, (int) $_GET['id']) : 0;
}

/** `as=event:ID` when tournament is in the realm event catalog (event ribbon on `tournament.php` — §5.1.1; not mode toggle — T19). */
function amiga_tournament_snapshot_as_param(mysqli $con, int $tournamentId): ?string
{
    if ($tournamentId < 1) {
        return null;
    }
    require_once __DIR__ . '/amiga_rating_history_lib.php';
    require_once __DIR__ . '/amiga_snapshot_context.php';

    $entry = amiga_rating_history_catalog_entry_by_key(
        amiga_rating_history_catalog_event($con),
        (string) $tournamentId,
    );
    if ($entry === null) {
        return null;
    }

    return amiga_snapshot_format_as_param('event', (string) $tournamentId);
}

/**
 * SQL fragment: tournaments visible on public pages.
 */
function amiga_tournament_public_visibility_where(string $tableAlias = 't'): string
{
    $statuses = array_map(
        static fn (string $s): string => "'" . str_replace("'", "''", $s) . "'",
        AMIGA_TOURNAMENT_PUBLIC_LIFECYCLE_STATUSES
    );

    return $tableAlias . '.lifecycle_status IN (' . implode(', ', $statuses) . ')';
}

function amiga_tournament_is_publicly_visible_lifecycle(string $lifecycleStatus): bool
{
    return in_array($lifecycleStatus, AMIGA_TOURNAMENT_PUBLIC_LIFECYCLE_STATUSES, true);
}

/**
 * @return list<int>
 */
function amiga_live_tournament_allowlist_ids(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $ids = array_values(array_filter(
        array_map('intval', AMIGA_PUBLIC_LIVE_TOURNAMENT_IDS),
        static fn (int $id): bool => $id > 0
    ));

    global $amigaPublicLiveTournamentIds;
    if (isset($amigaPublicLiveTournamentIds) && is_array($amigaPublicLiveTournamentIds)) {
        foreach ($amigaPublicLiveTournamentIds as $rawId) {
            $id = (int) $rawId;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
    }

    $cached = array_values(array_unique($ids));

    return $cached;
}

function amiga_live_tournament_is_allowlisted(int $tournamentId): bool
{
    return in_array($tournamentId, amiga_live_tournament_allowlist_ids(), true);
}

function amiga_live_tournament_fixture_generated_where(string $tableAlias = 't'): string
{
    $parts = [];
    foreach (AMIGA_FIXTURE_GENERATED_BY_PREFIXES as $prefix) {
        $escaped = str_replace("'", "''", $prefix);
        $parts[] = "COALESCE({$tableAlias}.format_overrides, '') LIKE '%{$escaped}%'";
    }

    return $tableAlias . '.source_id IS NULL AND (' . implode(' OR ', $parts) . ')';
}

function amiga_live_tournament_url(int $tournamentId): string
{
    return '/amiga/live-tournament.php?' . http_build_query(['id' => $tournamentId]);
}

function amiga_live_tournament_link(int $tournamentId, string $name): string
{
    $href = amiga_live_tournament_url($tournamentId);

    return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</a>';
}

/**
 * Public live index: allowlisted, running, fixture-backed generated tournaments only.
 *
 * @return list<array<string, mixed>>
 */
function amiga_live_tournament_index_rows(mysqli $con): array
{
    $allowlist = amiga_live_tournament_allowlist_ids();
    if ($allowlist === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($allowlist), '?'));
    $types = str_repeat('i', count($allowlist));
    $sql = 'SELECT t.id, t.name, t.event_date, t.country, t.lifecycle_status, t.started_at,
                   COUNT(DISTINCT f.id) AS fixture_count,
                   SUM(CASE WHEN f.status = \'played\' THEN 1 ELSE 0 END) AS played_count,
                   SUM(CASE WHEN f.status = \'scheduled\' THEN 1 ELSE 0 END) AS scheduled_count
            FROM tournaments t
            INNER JOIN tournament_stages s ON s.tournament_id = t.id
            LEFT JOIN tournament_fixtures f ON f.stage_id = s.id
            WHERE t.lifecycle_status = \'running\'
              AND ' . amiga_live_tournament_fixture_generated_where('t') . '
              AND t.id IN (' . $placeholders . ')
            GROUP BY t.id, t.name, t.event_date, t.country, t.lifecycle_status, t.started_at
            ORDER BY COALESCE(t.started_at, t.event_date, \'1970-01-01\') DESC, t.id DESC';

    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, $types, ...$allowlist);
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

function amiga_live_tournament_index_date_sort_value(array $row): string
{
    $sortDate = $row['started_at'] ?? $row['event_date'] ?? null;
    if ($sortDate === null || $sortDate === '') {
        return '0';
    }
    $ts = strtotime((string) $sortDate);

    return (string) ($ts !== false ? $ts : 0);
}

/**
 * Live tournament index table (/amiga/live-tournaments.php).
 *
 * @param list<array<string, mixed>> $rows from amiga_live_tournament_index_rows()
 */
function amiga_live_tournament_index_render_table(array $rows, bool $allowlistConfigured): void
{
    require_once __DIR__ . '/k2_table_helpers.php';
    $anchorCol = AMIGA_LIVE_TOURNAMENT_INDEX_ANCHOR_COL;
    $defaultSortCol = k2_table_default_sort_col_from_request(AMIGA_LIVE_TOURNAMENT_INDEX_DEFAULT_SORT_COL);
    $defaultSortDir = k2_table_default_sort_dir_from_request('desc');
    $tableClass = k2_table_ranked_sortable_class('k2-table--live-tournament-index');
    $skipInitialSort = $defaultSortCol === AMIGA_LIVE_TOURNAMENT_INDEX_DEFAULT_SORT_COL && $defaultSortDir === 'desc';
    ?>
<?php k2_table_wrap_open(true); ?>
<table class="<?php echo k2_h($tableClass); ?>" data-k2-table="sortable" data-k2-anchor-col="<?php echo $anchorCol; ?>" data-k2-default-sort="<?php echo $defaultSortCol; ?>" data-k2-default-direction="<?php echo k2_h($defaultSortDir); ?>"<?php echo $skipInitialSort ? ' data-k2-skip-initial-sort="1"' : ''; ?>>
<thead>
  <tr>
    <th<?php echo k2_table_sortable_th_attr(0, $defaultSortCol, $defaultSortDir, 'k2-table-cell--left'); ?> data-k2-sort="text">Tournament</th>
    <th<?php echo k2_table_sortable_th_attr(1, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number">Date</th>
    <th<?php echo k2_table_sortable_th_attr(2, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="text">Country</th>
    <th<?php echo k2_table_sortable_th_attr(3, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="text">Status</th>
    <th<?php echo k2_table_sortable_th_attr(4, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number">Played</th>
    <th<?php echo k2_table_sortable_th_attr(5, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number">Scheduled</th>
    <th<?php echo k2_table_sortable_th_attr(6, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number">Fixtures</th>
  </tr>
</thead>
<tbody>
<?php if ($rows === []) { ?>
  <tr>
    <td colspan="7" class="k2-table-cell--left" style="color:var(--k2-text-secondary)"><?php
        if (!$allowlistConfigured) {
            echo 'No live events are published for public viewing yet.';
        } else {
            echo 'No running live events match the current public allowlist.';
        }
    ?></td>
  </tr>
<?php } ?>
<?php foreach ($rows as $row) { ?>
  <tr>
    <td<?php echo k2_table_body_td_attr(0, $anchorCol, $defaultSortCol, 'k2-table-cell--left'); ?>><?php echo amiga_live_tournament_link((int) $row['id'], (string) $row['name']); ?></td>
    <td<?php echo k2_table_body_td_attr(1, $anchorCol, $defaultSortCol); ?> data-k2-sort-value="<?php echo amiga_live_tournament_index_date_sort_value($row); ?>"><?php echo $row['event_date'] !== null ? k2_h((string) $row['event_date']) : '—'; ?></td>
    <td<?php echo k2_table_body_td_attr(2, $anchorCol, $defaultSortCol); ?>><?php echo !empty($row['country']) ? k2_h((string) $row['country']) : '—'; ?></td>
    <td<?php echo k2_table_body_td_attr(3, $anchorCol, $defaultSortCol); ?>><span class="k2-amiga-tournament-badge"><?php echo k2_h((string) $row['lifecycle_status']); ?></span></td>
    <td<?php echo k2_table_body_td_attr(4, $anchorCol, $defaultSortCol); ?>><?php echo (int) ($row['played_count'] ?? 0); ?></td>
    <td<?php echo k2_table_body_td_attr(5, $anchorCol, $defaultSortCol); ?>><?php echo (int) ($row['scheduled_count'] ?? 0); ?></td>
    <td<?php echo k2_table_body_td_attr(6, $anchorCol, $defaultSortCol); ?>><?php echo (int) ($row['fixture_count'] ?? 0); ?></td>
  </tr>
<?php } ?>
</tbody>
</table>
<?php k2_table_wrap_close(); ?>
    <?php
}

/**
 * Load one public live tournament or null when not eligible.
 *
 * @return array<string, mixed>|null
 */
function amiga_live_tournament_load(mysqli $con, int $tournamentId): ?array
{
    if (!amiga_live_tournament_is_allowlisted($tournamentId)) {
        return null;
    }

    $sql = 'SELECT t.id, t.name, t.event_date, t.country, t.lifecycle_status, t.started_at, t.completed_at,
                   t.player_count, t.has_league, t.has_cup
            FROM tournaments t
            INNER JOIN tournament_stages s ON s.tournament_id = t.id
            WHERE t.id = ?
              AND t.lifecycle_status = \'running\'
              AND ' . amiga_live_tournament_fixture_generated_where('t') . '
            GROUP BY t.id, t.name, t.event_date, t.country, t.lifecycle_status, t.started_at, t.completed_at,
                     t.player_count, t.has_league, t.has_cup
            LIMIT 1';
    $stmt = mysqli_prepare($con, $sql);
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
 * Registered entrants, or stage players when entrants are empty.
 *
 * @return list<array<string, mixed>>
 */
function amiga_live_tournament_participants(mysqli $con, int $tournamentId): array
{
    $sql = 'SELECT e.player_id, e.seed_no, e.status, p.name AS player_name, p.country
            FROM tournament_entrants e
            INNER JOIN amiga_players p ON p.id = e.player_id
            WHERE e.tournament_id = ? AND e.status = \'registered\'
            ORDER BY COALESCE(e.seed_no, 9999) ASC, p.name ASC';
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'i', $tournamentId);
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
    if ($rows !== []) {
        return $rows;
    }

    $sql = 'SELECT DISTINCT sp.player_id, sp.seed_no, p.name AS player_name, p.country
            FROM tournament_stage_players sp
            INNER JOIN tournament_stages s ON s.id = sp.stage_id
            INNER JOIN amiga_players p ON p.id = sp.player_id
            WHERE s.tournament_id = ?
            ORDER BY COALESCE(sp.seed_no, 9999) ASC, p.name ASC';
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'i', $tournamentId);
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
 * Fixture schedule grouped by stage (stable order).
 *
 * @return list<array{stage: array<string, mixed>, fixtures: list<array<string, mixed>>}>
 */
function amiga_live_tournament_fixture_groups(mysqli $con, int $tournamentId): array
{
    $sql = 'SELECT f.id, f.fixture_key, f.leg_no, f.status, f.phase_label,
                   s.id AS stage_id, s.stage_key, s.name AS stage_name, s.stage_type, s.sequence_no,
                   f.player_a_id, f.player_b_id,
                   pa.name AS player_a_name, pb.name AS player_b_name,
                   g.id AS game_id, g.goals_a, g.goals_b, g.extra
            FROM tournament_fixtures f
            INNER JOIN tournament_stages s ON s.id = f.stage_id
            LEFT JOIN amiga_players pa ON pa.id = f.player_a_id
            LEFT JOIN amiga_players pb ON pb.id = f.player_b_id
            LEFT JOIN amiga_games g ON g.fixture_id = f.id
            WHERE s.tournament_id = ?
            ORDER BY s.sequence_no ASC, s.id ASC, f.id ASC';
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'i', $tournamentId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    /** @var array<int, array{stage: array<string, mixed>, fixtures: list<array<string, mixed>>}> $byStage */
    $byStage = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $stageId = (int) $row['stage_id'];
            if (!isset($byStage[$stageId])) {
                $byStage[$stageId] = [
                    'stage' => [
                        'id' => $stageId,
                        'stage_key' => (string) $row['stage_key'],
                        'name' => (string) $row['stage_name'],
                        'stage_type' => (string) $row['stage_type'],
                        'sequence_no' => (int) $row['sequence_no'],
                    ],
                    'fixtures' => [],
                ];
            }
            $byStage[$stageId]['fixtures'][] = $row;
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);

    return array_values($byStage);
}

function amiga_live_tournament_format_player_slot(
    ?int $playerId,
    ?string $playerName,
    string $placeholder = 'TBD'
): string {
    if ($playerId !== null && $playerId > 0 && $playerName !== null && $playerName !== '') {
        require_once __DIR__ . '/amiga_player_load.php';

        return k2_amiga_player_link($playerId, $playerName);
    }

    return '<span class="k2-amiga-live-view__placeholder">' . k2_h($placeholder) . '</span>';
}

/**
 * @return array<string, mixed>|null
 */
function amiga_tournament_load(mysqli $con, int $tournamentId, bool $publicOnly = true): ?array
{
    $visibility = $publicOnly ? ' AND ' . amiga_tournament_public_visibility_where('t') : '';
    $sql = 'SELECT t.id, t.name, t.chrono, t.event_date, t.is_cup, t.country, t.equal_teams, t.player_count,
                   t.lifecycle_status
            FROM tournaments t
            WHERE t.id = ?' . $visibility;
    $stmt = mysqli_prepare($con, $sql);
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
function amiga_tournament_list_scopes(mysqli $con, int $tournamentId, string $scopeType = 'league'): array
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
 * Labeled league phase keys (excludes implicit single-table ``scope_key = ''``).
 *
 * @return list<string>
 */
function amiga_tournament_list_league_labeled_scopes(mysqli $con, int $tournamentId): array
{
    $keys = amiga_tournament_list_scopes($con, $tournamentId, 'league');

    return array_values(array_filter($keys, static fn (string $key): bool => $key !== ''));
}

/**
 * Map legacy ``?scope=overall|group`` to canonical ``league`` request (policy S8).
 *
 * @return array{scope_type: string, scope_key: string, redirect: bool}
 */
function amiga_tournament_canonicalize_scope_request(string $scopeType, string $scopeKey): array
{
    if ($scopeType === 'overall') {
        return ['scope_type' => 'league', 'scope_key' => '', 'redirect' => true];
    }
    if ($scopeType === 'group') {
        return ['scope_type' => 'league', 'scope_key' => $scopeKey, 'redirect' => true];
    }
    if ($scopeType === 'placement') {
        return ['scope_type' => 'knockout', 'scope_key' => $scopeKey, 'redirect' => true];
    }
    if (!in_array($scopeType, ['league', 'knockout'], true)) {
        return ['scope_type' => 'league', 'scope_key' => '', 'redirect' => true];
    }

    return ['scope_type' => $scopeType, 'scope_key' => $scopeKey, 'redirect' => false];
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_tournament_standings_rows(
    mysqli $con,
    int $tournamentId,
    string $scopeType = 'league',
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
 * Recent tournaments for a player (participation table; event chrono desc).
 *
 * @return list<array<string, mixed>>
 */
function amiga_player_recent_tournaments(mysqli $con, int $playerId, int $limit = 5): array
{
    require_once __DIR__ . '/amiga_player_tournament_lib.php';

    return amiga_player_tournament_participation_recent($con, $playerId, $limit);
}

/**
 * Full tournament history for a player (participation table, all rows).
 *
 * @return list<array<string, mixed>>
 */
/**
 * @param 'all'|'world-cup' $eventFilter
 * @return list<array<string, mixed>>
 */
function amiga_player_all_tournaments(
    mysqli $con,
    int $playerId,
    string $eventFilter = 'all',
    string $country = ''
): array {
    require_once __DIR__ . '/amiga_player_tournament_lib.php';

    $rows = amiga_player_tournament_participation_all($con, $playerId);

    return amiga_player_tournament_participation_filter_events($rows, $eventFilter, $country);
}

function amiga_tournament_url(int $id, string $scopeType = 'league', string $scopeKey = '', ?string $view = null): string
{
    if ($scopeType === 'overall') {
        $scopeType = 'league';
        $scopeKey = '';
    } elseif ($scopeType === 'group') {
        $scopeType = 'league';
    }

    if ($view === null && ($scopeType === 'knockout' || ($scopeType === 'league' && $scopeKey !== ''))) {
        $view = 'standings';
    } elseif ($view === null) {
        $view = 'event-stats';
    }

    $params = ['id' => $id];
    if ($scopeType === 'knockout') {
        $params['scope'] = 'knockout';
        if ($scopeKey !== '') {
            $params['scope_key'] = $scopeKey;
        }
    } elseif ($scopeType === 'league' && $scopeKey !== '') {
        $params['scope'] = 'league';
        $params['scope_key'] = $scopeKey;
    }

    return amiga_tournament_path_for_view($view) . '?' . http_build_query($params);
}

/** Preserve active `as=` when building tournament page links (T16). */
function amiga_tournament_href(string $tournamentUrl): string
{
    require_once __DIR__ . '/amiga_snapshot_url.php';

    return amiga_url_with_context($tournamentUrl);
}

/**
 * Default Stages sub-view for World Cups (first group table, else league table, else bracket).
 *
 * @param list<string> $leagueLabeledScopes
 */
function amiga_tournament_stages_entry_url(
    int $id,
    bool $hasImplicitLeague,
    array $leagueLabeledScopes,
    bool $hasBracket,
): string {
    if ($leagueLabeledScopes !== []) {
        return amiga_tournament_url($id, 'league', $leagueLabeledScopes[0], 'stages');
    }
    if ($hasImplicitLeague) {
        return amiga_tournament_url($id, 'league', '', 'stages');
    }
    if ($hasBracket) {
        return amiga_tournament_url($id, 'league', '', 'stages');
    }

    return amiga_tournament_url($id, 'league', '', 'stages');
}

function amiga_tournament_event_stats_url(int $id): string
{
    return amiga_tournament_url($id, 'league', '', 'event-stats');
}

function amiga_tournament_games_url(int $id, int $playerFilter = 0): string
{
    $params = ['id' => $id];
    if ($playerFilter > 0) {
        $params['player'] = $playerFilter;
    }

    return amiga_tournament_path_for_view('games') . '?' . http_build_query($params);
}

/** Indexed lookup on ``amiga_games.tournament_id`` (`idx_amiga_games_tournament`). */
function amiga_tournament_game_count(mysqli $con, int $tournamentId): int
{
    if ($tournamentId < 1) {
        return 0;
    }
    $stmt = mysqli_prepare($con, 'SELECT COUNT(*) AS n FROM amiga_games WHERE tournament_id = ?');
    if ($stmt === false) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'i', $tournamentId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if ($res) {
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);

    return (int) ($row['n'] ?? 0);
}

/**
 * Participation roster for the player filter dropdown.
 *
 * @return list<array{player_id: int, player_name: string, games: int}>
 */
function amiga_tournament_game_player_choices(mysqli $con, int $tournamentId): array
{
    require_once __DIR__ . '/amiga_player_tournament_lib.php';
    $choices = [];
    foreach (amiga_tournament_participation_rows($con, $tournamentId) as $row) {
        $choices[] = [
            'player_id' => (int) ($row['player_id'] ?? 0),
            'player_name' => (string) ($row['player_name'] ?? ''),
            'games' => (int) ($row['games'] ?? 0),
        ];
    }

    usort(
        $choices,
        static fn(array $a, array $b): int => strcasecmp($a['player_name'], $b['player_name'])
    );

    return $choices;
}

/**
 * All rated games in one tournament (optional player filter).
 *
 * @return list<array<string, mixed>>
 */
function amiga_tournament_games_rows(mysqli $con, int $tournamentId, int $playerFilter = 0): array
{
    if ($tournamentId < 1) {
        return [];
    }

    require_once __DIR__ . '/amiga_db.php';

    $sql = 'SELECT g.id, g.source_scores_id, g.game_date, g.player_a_id, g.player_b_id,
                   g.goals_a, g.goals_b, g.extra, g.phase,
                   pa.name AS player_a_name, pb.name AS player_b_name
            FROM amiga_games g
            INNER JOIN amiga_players pa ON pa.id = g.player_a_id
            INNER JOIN amiga_players pb ON pb.id = g.player_b_id
            WHERE g.tournament_id = ?';
    $types = 'i';
    $params = [$tournamentId];
    if ($playerFilter > 0) {
        $sql .= ' AND (g.player_a_id = ? OR g.player_b_id = ?)';
        $types .= 'ii';
        $params[] = $playerFilter;
        $params[] = $playerFilter;
    }
    $sql .= ' ORDER BY ' . amiga_game_chronology_order_sql('ASC');

    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, $types, ...$params);
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

/** Standings/stages sub-nav link — folder path per mode (WC → stages.php, else standings.php). */
function amiga_tournament_standings_nav_url(
    int $id,
    string $scopeType = 'league',
    string $scopeKey = '',
    bool $isWorldCup = false,
): string {
    return amiga_tournament_url($id, $scopeType, $scopeKey, $isWorldCup ? 'stages' : 'standings');
}

/**
 * When time travel Event wing is active, keep `id` aligned with `as=event:{id}`.
 *
 * @param array<string, mixed> $query
 */
function amiga_tournament_apply_time_travel_event_id_redirect(array $query): void
{
    require_once __DIR__ . '/amiga_snapshot_url.php';

    if (!amiga_tournament_page_request_path()) {
        return;
    }

    $asRaw = isset($query['as']) ? trim((string) $query['as']) : '';
    if ($asRaw === '') {
        return;
    }

    $parsed = amiga_snapshot_parse_as_param($asRaw);
    if ($parsed === null || $parsed['wing'] !== 'event') {
        return;
    }

    $eventId = (int) $parsed['key'];
    if ($eventId < 1) {
        return;
    }

    $pageId = isset($query['id']) ? (int) $query['id'] : 0;
    if ($pageId === $eventId) {
        return;
    }

    $scopeType = isset($query['scope']) ? (string) $query['scope'] : 'league';
    $scopeKey = isset($query['scope_key']) ? (string) $query['scope_key'] : '';
    $view = amiga_tournament_view_from_request();
    if ($view === null) {
        $viewRaw = (string) ($query['view'] ?? '');
        $view = in_array($viewRaw, ['event-stats', 'standings', 'stages', 'games'], true) ? $viewRaw : 'event-stats';
    }

    header(
        'Location: ' . amiga_url_with_as_param(
            amiga_tournament_url($eventId, $scopeType, $scopeKey, $view),
            $asRaw,
        ),
        true,
        302,
    );
    exit;
}

/**
 * Legacy ``/amiga/tournament.php`` + ``?view=`` → foldered tournament paths (302).
 *
 * @param array<string, mixed> $query
 */
function amiga_tournament_legacy_view_redirect(array $query): void
{
    $id = isset($query['id']) ? max(0, (int) $query['id']) : 0;
    $scopeType = isset($query['scope']) ? (string) $query['scope'] : 'league';
    $scopeKey = isset($query['scope_key']) ? (string) $query['scope_key'] : '';
    $viewRaw = (string) ($query['view'] ?? '');
    $view = match ($viewRaw) {
        'event-stats', 'games', 'stages', 'standings' => $viewRaw,
        default => 'event-stats',
    };
    $carry = $query;
    unset($carry['view'], $carry['scope'], $carry['scope_key'], $carry['id']);
    $target = amiga_tournament_url($id, $scopeType, $scopeKey, $view);
    if ($carry !== []) {
        $target .= (str_contains($target, '?') ? '&' : '?') . http_build_query($carry);
    }
    header('Location: ' . amiga_tournament_href($target), true, 302);
    exit;
}

/**
 * 302 redirects before HTML — legacy scope canonicalization; WC/ordinary folder guard.
 *
 * @param array{scope_type: string, scope_key: string, redirect: bool} $canonicalScope
 */
function amiga_tournament_apply_entry_redirects(
    int $id,
    ?array $tournament,
    array $canonicalScope,
    string $pageView,
    array $query,
): void {
    $isWorldCup = $tournament !== null && amiga_tournament_is_world_cup($tournament);

    if ($canonicalScope['redirect'] && !in_array($pageView, ['event-stats', 'games'], true)) {
        header(
            'Location: ' . amiga_tournament_href(amiga_tournament_url(
                $id,
                $canonicalScope['scope_type'],
                $canonicalScope['scope_key'],
                $isWorldCup ? 'stages' : 'standings',
            )),
            true,
            302,
        );
        exit;
    }

    if ($isWorldCup && $pageView === 'standings') {
        header(
            'Location: ' . amiga_tournament_href(amiga_tournament_url(
                $id,
                $canonicalScope['scope_type'],
                $canonicalScope['scope_key'],
                'stages',
            )),
            true,
            302,
        );
        exit;
    }

    if (!$isWorldCup && $pageView === 'stages') {
        header(
            'Location: ' . amiga_tournament_href(amiga_tournament_url(
                $id,
                $canonicalScope['scope_type'],
                $canonicalScope['scope_key'],
                'standings',
            )),
            true,
            302,
        );
        exit;
    }
}

function amiga_tournament_link(int $id, string $name, string $fragment = AMIGA_TOURNAMENT_PAGE_FRAGMENT): string
{
    $href = amiga_tournament_href(amiga_tournament_event_stats_url($id));
    if ($fragment !== '') {
        $href .= '#' . ltrim($fragment, '#');
    }

    return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</a>';
}

function amiga_tournament_is_world_cup_by_name(string $name): bool
{
    return preg_match('/^World Cup\s+\S/i', trim($name)) === 1;
}

/** True when catalog name is a World Cup (e.g. World Cup XI). */
function amiga_tournament_is_world_cup(array $row): bool
{
    return amiga_tournament_is_world_cup_by_name((string) ($row['name'] ?? ''));
}

/**
 * Catalog format flags from import ground truth (non-exclusive).
 *
 * @return array{has_league: bool, has_cup: bool}
 */
function amiga_tournament_catalog_format_flags(array $row): array
{
    return [
        'has_league' => (int) ($row['has_league'] ?? 0) === 1,
        'has_cup' => (int) ($row['has_cup'] ?? 0) === 1,
    ];
}

/**
 * Index row format kind from catalog flags: league | cup | league_cup | ''.
 */
function amiga_tournament_index_format_kind(array $row): string
{
    $flags = amiga_tournament_catalog_format_flags($row);
    if ($flags['has_league'] && $flags['has_cup']) {
        return 'league_cup';
    }
    if ($flags['has_league']) {
        return 'league';
    }
    if ($flags['has_cup']) {
        return 'cup';
    }

    return '';
}

/** Display label for index format kind (uppercased by badge CSS). */
function amiga_tournament_index_format_label(string $kind): string
{
    return match ($kind) {
        'league_cup' => 'League + cup',
        'league' => 'League',
        'cup' => 'Cup',
        default => '—',
    };
}

/** Tournament index pill filter: '' | world-cup | league | cup | league-cup */
function amiga_tournament_index_matches_filter(array $row, string $filter): bool
{
    if ($filter === '') {
        return true;
    }
    if ($filter === 'world-cup') {
        return amiga_tournament_is_world_cup($row);
    }
    $flags = amiga_tournament_catalog_format_flags($row);
    if ($filter === 'league') {
        return $flags['has_league'] && !$flags['has_cup'];
    }
    if ($filter === 'cup') {
        return $flags['has_cup'] && !$flags['has_league'];
    }
    if ($filter === 'league-cup') {
        return $flags['has_league'] && $flags['has_cup'];
    }

    return true;
}

/** Filter pill href for /amiga/tournaments.php (carries active k2_sort when set). */
function amiga_tournament_index_filter_url(string $typeFilter = ''): string
{
    require_once __DIR__ . '/k2_table_helpers.php';
    $params = array_merge(
        $typeFilter !== '' ? ['type' => $typeFilter] : [],
        k2_table_sort_query_params(),
    );

    return $params === [] ? '/amiga/tournaments.php' : '/amiga/tournaments.php?' . http_build_query($params);
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
    if (preg_match('/^(Silver Cup|Bronze Cup|KOA Cup)\s+Group\s+([A-Z](?:\/[A-Z])?)$/i', $phase, $m) === 1) {
        $candidates[] = $m[1] . ' - Group ' . strtoupper($m[2]);
    }
    if (preg_match('/^(KOA Cup)\s*-\s*(Round\s+\d+)\s*-\s*Group\s+([A-Z](?:\/[A-Z])?)$/i', $phase, $m) === 1) {
        $candidates[] = $m[1] . ' - ' . $m[2] . ' - Group ' . strtoupper($m[3]);
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
             WHERE tournament_id = ? AND scope_type = \'league\' AND scope_key = ?
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
            return ['scope_type' => 'league', 'scope_key' => $candidate];
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

/**
 * @param list<int> $playerIds
 * @return array<int, string>
 */
function amiga_tournament_player_countries(mysqli $con, array $playerIds): array
{
    $playerIds = array_values(array_unique(array_filter($playerIds, static fn (int $id): bool => $id > 0)));
    if ($playerIds === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($playerIds), '?'));
    $types = str_repeat('i', count($playerIds));
    $sql = 'SELECT id, country FROM amiga_players WHERE id IN (' . $placeholders . ')';
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
            $out[(int) $row['id']] = (string) ($row['country'] ?? '');
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
    $title = $scope['scope_type'] === 'knockout' ? 'Elimination tie' : 'Phase standings';
    return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($phase, ENT_QUOTES, 'UTF-8') . '</a>';
}

/**
 * Tournament catalog for index page (chrono desc).
 *
 * @return list<array<string, mixed>>
 */
function amiga_tournament_index_rows(mysqli $con, int $limit = 0, int $offset = 0): array
{
    if ($limit < 1) {
        $limit = amiga_tournament_index_count($con);
    }
    $limit = max(1, min(5000, $limit));
    $offset = max(0, $offset);
    // Read stored catalog aggregates (amiga_tournament_catalog_stats) — not live scans on amiga_games.
    $sql = 'SELECT t.id, t.name, t.event_date, t.chrono, t.is_cup, t.has_league, t.has_cup, t.equal_teams, t.country, t.player_count,
                   t.lifecycle_status,
                   COALESCE(c.game_count, 0) AS game_count,
                   COALESCE(c.standing_players, 0) AS standing_players,
                   COALESCE(c.standing_rows, 0) AS standing_rows,
                   COALESCE(c.league_scopes, 0) AS league_scopes,
                   COALESCE(c.knockout_ties, 0) AS knockout_ties
            FROM tournaments t
            LEFT JOIN amiga_tournament_catalog_stats c ON c.tournament_id = t.id
            WHERE ' . amiga_tournament_public_visibility_where('t') . '
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
    $res = mysqli_query($con, 'SELECT COUNT(*) AS n FROM tournaments t WHERE ' . amiga_tournament_public_visibility_where('t'));
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
                   g.goals_a, g.goals_b, g.extra,
                   COALESCE(g.phase, f.phase_label, s.name, s.stage_key) AS phase,
                   pa.name AS player_a_name, pb.name AS player_b_name
            FROM amiga_games g
            LEFT JOIN tournament_fixtures f ON f.id = g.fixture_id
            LEFT JOIN tournament_stages s ON s.id = f.stage_id
            INNER JOIN amiga_players pa ON pa.id = g.player_a_id
            INNER JOIN amiga_players pb ON pb.id = g.player_b_id
            WHERE g.tournament_id = ?
              AND (
                g.phase = ?
                OR (g.fixture_id IS NOT NULL AND COALESCE(f.phase_label, s.name, s.stage_key) = ?)
              )
              AND ((g.player_a_id = ? AND g.player_b_id = ?) OR (g.player_a_id = ? AND g.player_b_id = ?))
            ORDER BY g.source_scores_id ASC, g.id ASC';
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'issiiii', $tournamentId, $phase, $phase, $lo, $hi, $hi, $lo);
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

/**
 * Knockout phase sort rank (parity with scripts/amiga/tournament_phases.py taxonomy).
 *
 * Inference rules (lower = earlier round, displayed left in main bracket):
 * - Round N / Round of 16 / Round of 32 → 100–199
 * - Quarter Finals → 200
 * - Semi Finals → 300
 * - Final → 400
 * - Nth Place Final → 500 + place number
 * - Places X-Y brackets → 600 + lower bound
 * - Unknown → 900
 */
function amiga_tournament_knockout_phase_rank(string $phase): int
{
    $label = trim($phase);
    $lower = strtolower($label);

    if (preg_match('/^round\s+of\s+32$/i', $label) === 1) {
        return 120;
    }
    if (preg_match('/^round\s+of\s+16$/i', $label) === 1) {
        return 140;
    }
    if (preg_match('/^round\s+(\d+)$/i', $label, $m) === 1) {
        return 100 + (int) $m[1];
    }
    if ($lower === 'quarter finals') {
        return 200;
    }
    if ($lower === 'semi finals') {
        return 300;
    }
    if ($lower === 'final') {
        return 400;
    }
    if (preg_match('/^(\d+)(?:st|nd|rd|th)\s+place\s+final$/i', $label, $m) === 1) {
        return 500 + (int) $m[1];
    }
    if (preg_match('/^places\s+(\d+)(?:-\d+)?$/i', $label, $m) === 1) {
        return 600 + (int) $m[1];
    }

    return 900;
}

/** main | placement_final | placement_bracket */
function amiga_tournament_knockout_phase_bucket(string $phase): string
{
    $rank = amiga_tournament_knockout_phase_rank($phase);
    if ($rank < 500) {
        return 'main';
    }
    if ($rank < 600) {
        return 'placement_final';
    }

    return 'placement_bracket';
}

/**
 * Tournament format label for index badges.
 *
 * @param list<string> $leagueLabeledScopes distinct non-empty league phase keys
 * @param list<string> $knockoutScopes
 */
function amiga_tournament_format_kind(array $tournament, array $leagueLabeledScopes, array $knockoutScopes): string
{
    if ($knockoutScopes !== [] || $leagueLabeledScopes !== []) {
        return 'cup';
    }
    if ((int) ($tournament['is_cup'] ?? 0) === 1) {
        return 'cup';
    }

    return 'league';
}

/**
 * Build bracket layout from knockout scope keys (read-path only; no advancement graph).
 *
 * @param list<string> $scopeKeys
 * @return array{
 *   main: list<array{phase: string, rank: int, ties: list<array<string, mixed>>}>,
 *   placement_final: list<array{phase: string, rank: int, ties: list<array<string, mixed>>}>,
 *   placement_bracket: list<array{phase: string, rank: int, ties: list<array<string, mixed>>}>
 * }
 */
function amiga_tournament_knockout_bracket_data(mysqli $con, int $tournamentId, array $scopeKeys): array
{
    $buckets = [
        'main' => [],
        'placement_final' => [],
        'placement_bracket' => [],
    ];
    if ($scopeKeys === [] || $tournamentId < 1) {
        return $buckets;
    }

    /** @var array<string, list<array<string, mixed>>> $byPhase */
    $byPhase = [];
    $allPlayerIds = [];

    foreach ($scopeKeys as $scopeKey) {
        $parsed = amiga_tournament_parse_knockout_scope_key($scopeKey);
        if ($parsed === null) {
            continue;
        }
        $phase = $parsed['phase'];
        $playerLo = $parsed['player_lo'];
        $playerHi = $parsed['player_hi'];
        $allPlayerIds[] = $playerLo;
        $allPlayerIds[] = $playerHi;

        $games = amiga_tournament_knockout_fixture_games($con, $tournamentId, $scopeKey);
        $standings = amiga_tournament_standings_rows($con, $tournamentId, 'knockout', $scopeKey);
        $resolved = amiga_tournament_knockout_resolve_winner($games, $standings);

        $winnerId = $resolved['winner_id'];
        $loserId = $resolved['loser_id'];
        $scoreLabel = '';
        if ($resolved['unresolved']) {
            $scoreLabel = 'Tie unresolved';
        } elseif ($winnerId !== null && $loserId !== null) {
            $wAgg = $resolved['aggregate'][$winnerId] ?? null;
            $lAgg = $resolved['aggregate'][$loserId] ?? null;
            if ($wAgg !== null && $lAgg !== null) {
                $scoreLabel = (int) $wAgg['goals_for'] . '–' . (int) $lAgg['goals_for'];
            }
        }

        if (!isset($byPhase[$phase])) {
            $byPhase[$phase] = [];
        }
        $byPhase[$phase][] = [
            'scope_key' => $scopeKey,
            'player_lo' => $playerLo,
            'player_hi' => $playerHi,
            'winner_id' => $winnerId,
            'unresolved' => $resolved['unresolved'],
            'score' => $scoreLabel,
            'url' => amiga_tournament_url($tournamentId, 'knockout', $scopeKey),
        ];
    }

    $names = amiga_tournament_player_names($con, $allPlayerIds);

    foreach ($byPhase as $phase => &$ties) {
        usort(
            $ties,
            static fn (array $a, array $b): int => ($a['player_lo'] <=> $b['player_lo'])
                ?: ($a['player_hi'] <=> $b['player_hi'])
        );
        foreach ($ties as &$tie) {
            $lo = (int) $tie['player_lo'];
            $hi = (int) $tie['player_hi'];
            $tie['player_a_id'] = $lo;
            $tie['player_b_id'] = $hi;
            $tie['player_a_name'] = $names[$lo] ?? ('#' . $lo);
            $tie['player_b_name'] = $names[$hi] ?? ('#' . $hi);
        }
        unset($tie);

        $round = [
            'phase' => $phase,
            'rank' => amiga_tournament_knockout_phase_rank($phase),
            'ties' => $ties,
        ];
        $bucket = amiga_tournament_knockout_phase_bucket($phase);
        $buckets[$bucket][] = $round;
    }
    unset($ties);

    foreach (['main', 'placement_final', 'placement_bracket'] as $bucket) {
        usort(
            $buckets[$bucket],
            static fn (array $a, array $b): int => ($a['rank'] <=> $b['rank'])
                ?: strcmp($a['phase'], $b['phase'])
        );
    }

    return $buckets;
}

/**
 * Show Phase column only when at least one game has a phase label (groups, knockouts, etc.).
 *
 * @param list<array<string, mixed>> $rows
 */
function amiga_tournament_games_show_phase_column(array $rows): bool
{
    foreach ($rows as $row) {
        if (trim((string) ($row['phase'] ?? '')) !== '') {
            return true;
        }
    }

    return false;
}

/**
 * Phase / knockout standings table on tournament.php.
 *
 * @param list<array<string, mixed>> $rows from amiga_tournament_standings_rows()
 */
function amiga_tournament_render_standings_table(array $rows, bool $isKnockoutView): void
{
    require_once __DIR__ . '/k2_table_helpers.php';
    $anchorCol = AMIGA_TOURNAMENT_STANDINGS_ANCHOR_COL;
    $defaultSortCol = k2_table_default_sort_col_from_request(AMIGA_TOURNAMENT_STANDINGS_DEFAULT_SORT_COL);
    $defaultSortDir = k2_table_default_sort_dir_from_request('asc');
    $tableClass = k2_table_ranked_sortable_class('k2-table--tournament-standings');
    $skipInitialSort = $defaultSortCol === AMIGA_TOURNAMENT_STANDINGS_DEFAULT_SORT_COL
        && $defaultSortDir === 'asc'
        && k2_table_sort_query_params() === [];
    $colCount = $isKnockoutView ? 9 : 10;
    ?>
<?php k2_table_wrap_open(true); ?>
<table class="<?php echo k2_h($tableClass); ?>" data-k2-table="sortable" data-k2-anchor-col="<?php echo $anchorCol; ?>" data-k2-default-sort="<?php echo $defaultSortCol; ?>" data-k2-default-direction="<?php echo k2_h($defaultSortDir); ?>"<?php echo $skipInitialSort ? ' data-k2-skip-initial-sort="1"' : ''; ?>>
<thead>
    <tr>
        <th<?php echo k2_table_sortable_th_attr(0, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number"><?php echo $isKnockoutView ? ' ' : 'Pos'; ?></th>
        <th<?php echo k2_table_sortable_th_attr(1, $defaultSortCol, $defaultSortDir, 'k2-table-cell--left'); ?> data-k2-sort="text">Player</th>
        <th<?php echo k2_table_sortable_th_attr(2, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number">G</th>
        <th<?php echo k2_table_sortable_th_attr(3, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number">W</th>
        <th<?php echo k2_table_sortable_th_attr(4, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number">D</th>
        <th<?php echo k2_table_sortable_th_attr(5, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number">L</th>
        <th<?php echo k2_table_sortable_th_attr(6, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number">GF</th>
        <th<?php echo k2_table_sortable_th_attr(7, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number">GA</th>
        <th<?php echo k2_table_sortable_th_attr(8, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number">GD</th>
        <?php if (!$isKnockoutView) { ?>
        <th<?php echo k2_table_sortable_th_attr(9, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number">Pts</th>
        <?php } ?>
    </tr>
</thead>
<tbody class="black">
<?php if ($rows === []) { ?>
    <tr>
        <td colspan="<?php echo $colCount; ?>" class="k2-table-cell--left" style="color:var(--k2-text-secondary)">No standings rows for this scope.</td>
    </tr>
<?php } ?>
<?php foreach ($rows as $row) {
    $gd = (int) $row['goals_for'] - (int) $row['goals_against'];
    $posLabel = $isKnockoutView
        ? ((int) $row['position'] === 1 ? 'W' : 'L')
        : (string) (int) $row['position'];
    ?>
    <tr>
        <td<?php echo k2_table_body_td_attr(0, $anchorCol, $defaultSortCol); ?>><?php echo k2_h($posLabel); ?></td>
        <td<?php echo k2_table_body_td_attr(1, $anchorCol, $defaultSortCol, 'k2-table-cell--left'); ?>><?php
            echo k2_amiga_player_link((int) $row['player_id'], (string) $row['player_name']);
        ?></td>
        <td<?php echo k2_table_body_td_attr(2, $anchorCol, $defaultSortCol); ?>><?php echo (int) $row['games']; ?></td>
        <td<?php echo k2_table_body_td_attr(3, $anchorCol, $defaultSortCol); ?>><?php echo (int) $row['wins']; ?></td>
        <td<?php echo k2_table_body_td_attr(4, $anchorCol, $defaultSortCol); ?>><?php echo (int) $row['draws']; ?></td>
        <td<?php echo k2_table_body_td_attr(5, $anchorCol, $defaultSortCol); ?>><?php echo (int) $row['losses']; ?></td>
        <td<?php echo k2_table_body_td_attr(6, $anchorCol, $defaultSortCol); ?>><?php echo (int) $row['goals_for']; ?></td>
        <td<?php echo k2_table_body_td_attr(7, $anchorCol, $defaultSortCol); ?>><?php echo (int) $row['goals_against']; ?></td>
        <td<?php echo k2_table_body_td_attr(8, $anchorCol, $defaultSortCol); ?>><?php echo $gd > 0 ? '+' . $gd : (string) $gd; ?></td>
        <?php if (!$isKnockoutView) { ?>
        <td<?php echo k2_table_body_td_attr(9, $anchorCol, $defaultSortCol); ?>><?php echo (int) $row['points']; ?></td>
        <?php } ?>
    </tr>
<?php } ?>
</tbody>
</table>
<?php k2_table_wrap_close(); ?>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows from amiga_tournament_games_rows()
 */
function amiga_tournament_render_games_table(array $rows): void
{
    require_once __DIR__ . '/k2_table_helpers.php';
    $showPhaseColumn = amiga_tournament_games_show_phase_column($rows);
    $colCount = $showPhaseColumn ? 5 : 4;
    $anchorCol = $showPhaseColumn ? 2 : 1;
    $defaultSortCol = k2_table_default_sort_col_from_request(AMIGA_TOURNAMENT_GAMES_DEFAULT_SORT_COL);
    $defaultSortDir = k2_table_default_sort_dir_from_request('asc');
    $tableClass = k2_table_ranked_sortable_class('k2-table--tournament-games');
    $skipInitialSort = $defaultSortCol === AMIGA_TOURNAMENT_GAMES_DEFAULT_SORT_COL
        && $defaultSortDir === 'asc'
        && k2_table_sort_query_params() === [];
    ?>
<?php k2_table_wrap_open(true); ?>
<table class="<?php echo k2_h($tableClass); ?>" data-k2-table="sortable" data-k2-anchor-col="<?php echo $anchorCol; ?>" data-k2-default-sort="<?php echo $defaultSortCol; ?>" data-k2-default-direction="<?php echo k2_h($defaultSortDir); ?>"<?php echo $skipInitialSort ? ' data-k2-skip-initial-sort="1"' : ''; ?>>
	<thead>
		<tr>
			<th<?php echo k2_table_sortable_th_attr(0, $defaultSortCol, $defaultSortDir, 'k2-table-cell--left'); ?> data-k2-sort="number">#</th>
			<?php if ($showPhaseColumn) { ?>
			<th<?php echo k2_table_sortable_th_attr(1, $defaultSortCol, $defaultSortDir, 'k2-table-cell--left'); ?> data-k2-sort="text">Phase</th>
			<?php } ?>
			<th<?php echo k2_table_sortable_th_attr($showPhaseColumn ? 2 : 1, $defaultSortCol, $defaultSortDir, 'k2-table-cell--left'); ?> data-k2-sort="text">Player A</th>
			<th<?php echo k2_table_sortable_th_attr($showPhaseColumn ? 3 : 2, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="text">Score</th>
			<th<?php echo k2_table_sortable_th_attr($showPhaseColumn ? 4 : 3, $defaultSortCol, $defaultSortDir, 'k2-table-cell--left'); ?> data-k2-sort="text">Player B</th>
		</tr>
	</thead>
	<tbody class="black">
	<?php if ($rows === []) { ?>
		<tr>
			<td colspan="<?php echo (int) $colCount; ?>" class="k2-table-cell--left" style="color:var(--k2-text-secondary)">No games match this filter.</td>
		</tr>
	<?php } ?>
	<?php foreach ($rows as $idx => $row) {
        $phase = trim((string) ($row['phase'] ?? ''));
        $scoreCol = $showPhaseColumn ? 3 : 2;
        $playerBCol = $showPhaseColumn ? 4 : 3;
        ?>
		<tr>
			<td<?php echo k2_table_body_td_attr(0, $anchorCol, $defaultSortCol, 'k2-table-cell--left'); ?>><?php echo (int) ($idx + 1); ?></td>
			<?php if ($showPhaseColumn) { ?>
			<td<?php echo k2_table_body_td_attr(1, $anchorCol, $defaultSortCol, 'k2-table-cell--left'); ?>><?php echo k2_h($phase); ?></td>
			<?php } ?>
			<td<?php echo k2_table_body_td_attr($anchorCol, $anchorCol, $defaultSortCol, 'k2-table-cell--left'); ?>><?php
                echo k2_amiga_player_link((int) $row['player_a_id'], (string) $row['player_a_name']);
            ?></td>
			<td<?php echo k2_table_body_td_attr($scoreCol, $anchorCol, $defaultSortCol); ?>><?php
                echo (int) $row['goals_a'] . ' – ' . (int) $row['goals_b'];
                echo amiga_tournament_format_game_extra(isset($row['extra']) ? (string) $row['extra'] : null);
            ?></td>
			<td<?php echo k2_table_body_td_attr($playerBCol, $anchorCol, $defaultSortCol, 'k2-table-cell--left'); ?>><?php
                echo k2_amiga_player_link((int) $row['player_b_id'], (string) $row['player_b_name']);
            ?></td>
		</tr>
	<?php } ?>
	</tbody>
</table>
<?php k2_table_wrap_close(); ?>
    <?php
}
