<?php
/**
 * Amiga tournament standings read path (derived amiga_tournament_standings).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';

/** Lifecycle statuses shown on public tournament pages (index, detail, profile links). */
const AMIGA_TOURNAMENT_PUBLIC_LIFECYCLE_STATUSES = ['completed', 'archived'];

/**
 * Lifecycle for the public live hub (/amiga/live-tournaments.php) while a community event is in progress.
 * Start tournament in ops ⇒ running ⇒ visible here when live_visible (default on) until finalize + completed.
 * Hide from Live sets format_overrides.live_visible=0 without changing lifecycle (OW2/OW4).
 */
const AMIGA_LIVE_TOURNAMENT_PUBLIC_LIFECYCLE_STATUS = 'running';
const AMIGA_LIVE_TOURNAMENT_INDEX_ANCHOR_COL = 0;
const AMIGA_LIVE_TOURNAMENT_INDEX_DEFAULT_SORT_COL = 1;
/** Date col — quiet body on default load only (no game ID). */
const AMIGA_LIVE_TOURNAMENT_INDEX_QUIET_DATE_COL = 1;
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

/** First KOA World Cup (2001, Dartford) — tournaments index chapter lede link target. */
const AMIGA_FIRST_WORLD_CUP_TOURNAMENT_ID = 26;

/** World Cup XIV (2014, Copenhagen) — Activity Texture wing lede milestone. */
const AMIGA_WORLD_CUP_XIV_COPENHAGEN_TOURNAMENT_ID = 577;

const AMIGA_TOURNAMENT_VIDEOS_PATH_GAMES = '/amiga/tournament/videos/games.php';
const AMIGA_TOURNAMENT_VIDEOS_PATH_ATMOSPHERE = '/amiga/tournament/videos/atmosphere.php';

function amiga_tournament_videos_path_for_mode(string $mode): string
{
    return $mode === 'atmosphere'
        ? AMIGA_TOURNAMENT_VIDEOS_PATH_ATMOSPHERE
        : AMIGA_TOURNAMENT_VIDEOS_PATH_GAMES;
}

function amiga_tournament_videos_mode_from_request(?string $path = null): string
{
    require_once __DIR__ . '/k2_table_helpers.php';
    require_once __DIR__ . '/amiga_snapshot_url.php';

    $pathOnly = k2_table_path_only($path ?? amiga_snapshot_request_path());
    if ($pathOnly === AMIGA_TOURNAMENT_VIDEOS_PATH_ATMOSPHERE) {
        return 'atmosphere';
    }

    return 'games';
}

function amiga_tournament_videos_resolve_mode(
    string $requestedMode,
    bool $hasAtmosphereWing,
    bool $hasGamesWing = true,
): string {
    if ($requestedMode === 'atmosphere' && $hasAtmosphereWing) {
        return 'atmosphere';
    }
    if ($requestedMode === 'games' && $hasGamesWing) {
        return 'games';
    }
    if (!$hasGamesWing && $hasAtmosphereWing) {
        return 'atmosphere';
    }

    return 'games';
}

/**
 * @param array<string, mixed> $query
 */
function amiga_tournament_videos_apply_mode_redirect(
    int $id,
    string $requestedMode,
    bool $hasAtmosphereWing,
    bool $hasGamesWing,
    array $query,
): void {
    $resolvedMode = amiga_tournament_videos_resolve_mode($requestedMode, $hasAtmosphereWing, $hasGamesWing);
    if ($resolvedMode === $requestedMode) {
        return;
    }

    $v = isset($query['v']) ? trim((string) $query['v']) : '';
    $game = isset($query['game']) ? max(0, (int) $query['game']) : 0;
    $startSec = isset($query['t']) ? max(0, (int) $query['t']) : 0;

    header(
        'Location: ' . amiga_tournament_href(amiga_tournament_videos_url(
            $id,
            $resolvedMode,
            $v !== '' ? $v : null,
            $game > 0 ? $game : null,
            $startSec > 0 ? $startSec : null,
        )),
        true,
        302,
    );
    exit;
}

/**
 * 302 when the requested folder mode is unavailable — must run before HTML output.
 *
 * @param array<string, mixed> $query
 */
function amiga_tournament_videos_apply_mode_redirect_from_db(
    mysqli $con,
    int $id,
    string $requestedMode,
    array $query,
): void {
    require_once __DIR__ . '/amiga_tournament_videos_lib.php';
    if (!amiga_tournament_has_videos($id)) {
        return;
    }
    $wings = amiga_tournament_videos_wings_for_id($con, $id);
    amiga_tournament_videos_apply_mode_redirect(
        $id,
        $requestedMode,
        $wings['has_atmosphere_wing'],
        $wings['has_games_wing'],
        $query,
    );
}

/** 302 legacy `/amiga/tournament/videos.php` (+ optional `wing=extras`) to folder modes. */
function amiga_tournament_videos_legacy_redirect(): void
{
    $query = $_GET;
    $id = isset($query['id']) ? max(0, (int) $query['id']) : 0;
    $wing = isset($query['wing']) ? trim((string) $query['wing']) : '';
    $mode = in_array($wing, ['extras', 'atmosphere'], true) ? 'atmosphere' : 'games';
    $v = isset($query['v']) ? trim((string) $query['v']) : '';
    $game = isset($query['game']) ? max(0, (int) $query['game']) : 0;
    $startSec = isset($query['t']) ? max(0, (int) $query['t']) : 0;

    header(
        'Location: ' . amiga_tournament_href(amiga_tournament_videos_url(
            $id,
            $mode,
            $v !== '' ? $v : null,
            $game > 0 ? $game : null,
            $startSec > 0 ? $startSec : null,
        )),
        true,
        302,
    );
    exit;
}

function amiga_tournament_path_for_view(?string $view): string
{
    return match ($view) {
        'games' => '/amiga/tournament/games.php',
        'videos' => AMIGA_TOURNAMENT_VIDEOS_PATH_GAMES,
        'stages', 'standings' => '/amiga/tournament/stages.php',
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
        '/amiga/tournament/videos.php', AMIGA_TOURNAMENT_VIDEOS_PATH_GAMES, AMIGA_TOURNAMENT_VIDEOS_PATH_ATMOSPHERE => 'videos',
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
 * SQL fragment: fixture-backed generated tournaments eligible for the public live hub.
 */
function amiga_live_tournament_fixture_generated_where(string $tableAlias = 't'): string
{
    $parts = [];
    foreach (AMIGA_FIXTURE_GENERATED_BY_PREFIXES as $prefix) {
        $escaped = str_replace("'", "''", $prefix);
        $parts[] = "COALESCE({$tableAlias}.format_overrides, '') LIKE '%{$escaped}%'";
    }

    return $tableAlias . '.source_id IS NULL AND (' . implode(' OR ', $parts) . ')';
}

/**
 * SQL fragment: spectator Live visibility (OW2/OW4).
 * Missing or non-JSON overrides ⇒ visible (default on). Explicit live_visible=0 ⇒ hidden.
 */
function amiga_live_tournament_live_visible_where(string $tableAlias = 't'): string
{
    $ov = "COALESCE({$tableAlias}.format_overrides, '{}')";

    return '('
        . "JSON_VALID({$ov}) = 0"
        . " OR JSON_EXTRACT({$ov}, '$.live_visible') IS NULL"
        . " OR CAST(JSON_UNQUOTE(JSON_EXTRACT({$ov}, '$.live_visible')) AS UNSIGNED) = 1"
        . ')';
}

/**
 * SQL fragment: public live hub eligibility (running + Live-visible generated events).
 */
function amiga_live_tournament_public_eligibility_where(string $tableAlias = 't'): string
{
    return $tableAlias . '.lifecycle_status = \''
        . AMIGA_LIVE_TOURNAMENT_PUBLIC_LIFECYCLE_STATUS
        . '\' AND '
        . amiga_live_tournament_fixture_generated_where($tableAlias)
        . ' AND '
        . amiga_live_tournament_live_visible_where($tableAlias);
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
 * Public live index: running, fixture-backed generated tournaments (no config allowlist).
 *
 * @return list<array<string, mixed>>
 */
function amiga_live_tournament_index_rows(mysqli $con): array
{
    $sql = 'SELECT t.id, t.name, t.event_date, t.country, t.lifecycle_status, t.started_at,
                   COUNT(DISTINCT f.id) AS fixture_count,
                   SUM(CASE WHEN f.status = \'played\' THEN 1 ELSE 0 END) AS played_count,
                   SUM(CASE WHEN f.status = \'scheduled\' THEN 1 ELSE 0 END) AS scheduled_count
            FROM tournaments t
            INNER JOIN tournament_stages s ON s.tournament_id = t.id
            LEFT JOIN tournament_fixtures f ON f.stage_id = s.id
            WHERE ' . amiga_live_tournament_public_eligibility_where('t') . '
            GROUP BY t.id, t.name, t.event_date, t.country, t.lifecycle_status, t.started_at
            ORDER BY COALESCE(t.started_at, t.event_date, \'1970-01-01\') DESC, t.id DESC';

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
function amiga_live_tournament_index_render_table(array $rows): void
{
    require_once __DIR__ . '/k2_table_helpers.php';
    $anchorCol = AMIGA_LIVE_TOURNAMENT_INDEX_ANCHOR_COL;
    $defaultSortCol = k2_table_default_sort_col_from_request(AMIGA_LIVE_TOURNAMENT_INDEX_DEFAULT_SORT_COL);
    $defaultSortDir = k2_table_default_sort_dir_from_request('desc');
    $isDefaultSortView = k2_table_is_default_client_sort_view();
    $dateEmphasisCol = k2_table_sort_col_for_emphasis(
        AMIGA_LIVE_TOURNAMENT_INDEX_QUIET_DATE_COL,
        $defaultSortCol,
        [AMIGA_LIVE_TOURNAMENT_INDEX_QUIET_DATE_COL],
        $isDefaultSortView
    );
    $dateCellClass = k2_table_quiet_date_cell_class(
        AMIGA_LIVE_TOURNAMENT_INDEX_QUIET_DATE_COL,
        $defaultSortCol,
        AMIGA_LIVE_TOURNAMENT_INDEX_DEFAULT_SORT_COL,
        $isDefaultSortView,
        ''
    );
    $tableClass = k2_table_ranked_sortable_class('k2-table--live-tournament-index');
    $skipInitialSort = $defaultSortCol === AMIGA_LIVE_TOURNAMENT_INDEX_DEFAULT_SORT_COL && $defaultSortDir === 'desc';
    ?>
<?php k2_table_wrap_open(true); ?>
<table class="<?php echo k2_h($tableClass); ?>" data-k2-table="sortable" data-k2-anchor-col="<?php echo $anchorCol; ?>" data-k2-default-sort="<?php echo $defaultSortCol; ?>" data-k2-default-direction="<?php echo k2_h($defaultSortDir); ?>"<?php echo k2_table_quiet_default_sort_col_attr([AMIGA_LIVE_TOURNAMENT_INDEX_QUIET_DATE_COL]); ?><?php echo $skipInitialSort ? ' data-k2-skip-initial-sort="1"' : ''; ?>>
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
    <td colspan="7" class="k2-table-cell--left" style="color:var(--k2-text-secondary)">No live tournaments in progress right now.</td>
  </tr>
<?php } ?>
<?php foreach ($rows as $row) { ?>
  <tr>
    <td<?php echo k2_table_body_td_attr(0, $anchorCol, $defaultSortCol, 'k2-table-cell--left'); ?>><?php echo amiga_live_tournament_link((int) $row['id'], (string) $row['name']); ?></td>
    <td<?php echo k2_table_body_td_attr(1, $anchorCol, $dateEmphasisCol, $dateCellClass); ?> data-k2-sort-value="<?php echo amiga_live_tournament_index_date_sort_value($row); ?>"><?php echo $row['event_date'] !== null ? k2_h((string) $row['event_date']) : '—'; ?></td>
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
    $sql = 'SELECT t.id, t.name, t.event_date, t.country, t.lifecycle_status, t.started_at, t.completed_at,
                   t.player_count, t.has_league, t.has_cup
            FROM tournaments t
            INNER JOIN tournament_stages s ON s.tournament_id = t.id
            WHERE t.id = ?
              AND ' . amiga_live_tournament_public_eligibility_where('t') . '
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
                   f.goals_a, f.goals_b, f.extra,
                   g.id AS game_id
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

function amiga_tournament_index_cutoff_cache_key(?AmigaSnapshotContext $ctx): string
{
    require_once __DIR__ . '/amiga_snapshot_context.php';

    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();
    if (!$ctx->isActive()) {
        return 'present';
    }
    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return 'present';
    }

    return $cutoff['event_date'] . '|' . (string) $cutoff['chrono'] . '|' . (int) $cutoff['tournament_id'];
}

/**
 * Full at-cutoff tournament catalog (request-scoped cache).
 *
 * @return list<array<string, mixed>>
 */
function amiga_tournament_index_cached_all_rows(mysqli $con, ?AmigaSnapshotContext $ctx = null): array
{
    require_once __DIR__ . '/amiga_snapshot_context.php';

    static $cache = [];
    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();
    $cacheKey = amiga_tournament_index_cutoff_cache_key($ctx);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $types = '';
    $params = [];
    $cutoffSql = amiga_snapshot_tournament_cutoff_and_sql($ctx, $types, $params);
    $sql = 'SELECT t.id, t.name, t.event_date, t.chrono, t.is_cup, t.has_league, t.has_cup, t.is_world_cup, t.equal_teams, t.country, t.player_count,
                   t.lifecycle_status,
                   COALESCE(c.game_count, 0) AS game_count,
                   COALESCE(c.standing_players, 0) AS standing_players,
                   COALESCE(c.standing_rows, 0) AS standing_rows,
                   COALESCE(c.league_scopes, 0) AS league_scopes,
                   COALESCE(c.knockout_ties, 0) AS knockout_ties,
                   COALESCE(c.has_perfect_participant, 0) AS has_perfect_participant,
                   wp.id AS winner_player_id,
                   wp.name AS winner_name,
                   wp.country AS winner_country
            FROM tournaments t
            LEFT JOIN amiga_tournament_catalog_stats c ON c.tournament_id = t.id
            LEFT JOIN (
                SELECT tournament_id, MIN(player_id) AS player_id
                FROM amiga_player_event_snapshots
                WHERE is_winner = 1
                GROUP BY tournament_id
            ) win ON win.tournament_id = t.id
            LEFT JOIN amiga_players wp ON wp.id = win.player_id
            WHERE ' . amiga_tournament_public_visibility_where('t') . $cutoffSql . '
            ORDER BY COALESCE(t.chrono, 999999) DESC, COALESCE(t.event_date, \'1970-01-01\') DESC, t.name ASC';
    if ($types === '') {
        $res = mysqli_query($con, $sql);
        if ($res === false) {
            $cache[$cacheKey] = [];

            return $cache[$cacheKey];
        }
    } else {
        $stmt = mysqli_prepare($con, $sql);
        if ($stmt === false) {
            $cache[$cacheKey] = [];

            return $cache[$cacheKey];
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res === false) {
            mysqli_stmt_close($stmt);
            $cache[$cacheKey] = [];

            return $cache[$cacheKey];
        }
    }
    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }
    mysqli_free_result($res);
    if (isset($stmt)) {
        mysqli_stmt_close($stmt);
    }
    $cache[$cacheKey] = $rows;

    return $cache[$cacheKey];
}

/**
 * @return array<string, mixed>|null
 */
function amiga_tournament_load(mysqli $con, int $tournamentId, bool $publicOnly = true): ?array
{
    static $cache = [];
    $cacheKey = $tournamentId . '|' . ($publicOnly ? '1' : '0');
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $visibility = $publicOnly ? ' AND ' . amiga_tournament_public_visibility_where('t') : '';
    $sql = 'SELECT t.id, t.name, t.chrono, t.event_date, t.is_cup, t.country, t.equal_teams, t.player_count,
                   t.has_league, t.has_cup, t.is_world_cup, t.lifecycle_status
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
    $cache[$cacheKey] = $row ?: null;

    return $cache[$cacheKey];
}

/** Whether the event has any public participation rows (Event stats tab). */
function amiga_tournament_has_participation(mysqli $con, int $tournamentId): bool
{
    static $cache = [];
    if ($tournamentId < 1) {
        return false;
    }
    if (isset($cache[$tournamentId])) {
        return $cache[$tournamentId];
    }
    $sql = 'SELECT 1
            FROM amiga_player_event_snapshots s
            INNER JOIN tournaments t ON t.id = s.tournament_id
            WHERE s.tournament_id = ?
              AND ' . amiga_tournament_public_visibility_where('t') . '
            LIMIT 1';
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'i', $tournamentId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $has = $res !== false && mysqli_fetch_assoc($res) !== null;
    if ($res) {
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);
    $cache[$tournamentId] = $has;

    return $has;
}

/** Whether implicit league table rows exist (scope_key = ''). */
function amiga_tournament_has_implicit_league_table(mysqli $con, int $tournamentId): bool
{
    static $cache = [];
    if ($tournamentId < 1) {
        return false;
    }
    if (isset($cache[$tournamentId])) {
        return $cache[$tournamentId];
    }
    $stmt = mysqli_prepare(
        $con,
        'SELECT 1 FROM amiga_tournament_standings
         WHERE tournament_id = ? AND scope_type = \'league\' AND scope_key = \'\'
         LIMIT 1',
    );
    if ($stmt === false) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'i', $tournamentId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $has = $res !== false && mysqli_fetch_assoc($res) !== null;
    if ($res) {
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);
    $cache[$tournamentId] = $has;

    return $has;
}

/**
 * @return list<string>
 */
function amiga_tournament_list_scopes(mysqli $con, int $tournamentId, string $scopeType = 'league'): array
{
    require_once __DIR__ . '/amiga_running_tournament_lib.php';
    if (amiga_running_tournament_broadcast_mode($con, $tournamentId)) {
        return amiga_running_tournament_list_scopes($con, $tournamentId, $scopeType);
    }

    static $cache = [];
    $cacheKey = $tournamentId . '|' . $scopeType;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

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
    $cache[$cacheKey] = $keys;

    return $cache[$cacheKey];
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
    require_once __DIR__ . '/amiga_running_tournament_lib.php';
    if (amiga_running_tournament_broadcast_mode($con, $tournamentId)) {
        return amiga_running_tournament_standings_scope_rows($con, $tournamentId, $scopeType, $scopeKey);
    }

    static $cache = [];
    $cacheKey = $tournamentId . '|' . $scopeType . '|' . $scopeKey;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $sql = 'SELECT s.position, s.games, s.wins, s.draws, s.losses,
                   s.goals_for, s.goals_against, s.points,
                   s.stage_id,
                   st.stage_key, st.name AS stage_name, st.stage_type,
                   p.id AS player_id, p.name AS player_name, p.country
            FROM amiga_tournament_standings s
            INNER JOIN amiga_players p ON p.id = s.player_id
            LEFT JOIN tournament_stages st ON st.id = s.stage_id
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
    $cache[$cacheKey] = $rows;

    return $cache[$cacheKey];
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
        $view = 'stages';
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

/**
 * Preserve active `as=` when building tournament page links (T16).
 *
 * Carries the current time-travel lens only — does not rewrite `as=` to match
 * the linked tournament id (snapshot changes belong in the TT picker).
 */
function amiga_tournament_href(string $tournamentUrl): string
{
    require_once __DIR__ . '/amiga_snapshot_url.php';
    require_once __DIR__ . '/amiga_id_with_url.php';
    require_once __DIR__ . '/amiga_id_country_url.php';
    require_once __DIR__ . '/amiga_id_wc_url.php';

    return amiga_id_wc_append_to_path(
        amiga_id_country_append_to_path(
            amiga_id_with_append_to_path(amiga_url_with_context($tournamentUrl)),
        ),
    );
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

function amiga_tournament_videos_url(
    int $id,
    string $mode = 'games',
    ?string $youtubeId = null,
    ?int $gameId = null,
    ?int $startSec = null,
    bool $withPlayerHash = false,
): string {
    $params = ['id' => $id];
    if ($youtubeId !== null && $youtubeId !== '') {
        $yt = preg_replace('/[^A-Za-z0-9_-]/', '', $youtubeId) ?? '';
        if ($yt !== '') {
            $params['v'] = $yt;
        }
    }
    if ($gameId !== null && $gameId > 0) {
        $params['game'] = $gameId;
    }
    if ($startSec !== null && $startSec > 0) {
        $params['t'] = $startSec;
    }
    $url = amiga_tournament_videos_path_for_mode($mode) . '?' . http_build_query($params);
    if ($withPlayerHash) {
        $url .= '#k2-tournament-video-player';
    }

    return $url;
}

/**
 * Product participant count for tournament hero / summary (not Access catalog witness).
 *
 * Live ops: registered tournament_entrants (or stage players when entrants empty).
 * Historical: standing_players → event snapshots → distinct game participants.
 */
function amiga_tournament_participant_count(mysqli $con, int $tournamentId): int
{
    static $cache = [];
    if ($tournamentId < 1) {
        return 0;
    }
    if (isset($cache[$tournamentId])) {
        return $cache[$tournamentId];
    }

    $liveParticipants = amiga_live_tournament_participants($con, $tournamentId);
    if ($liveParticipants !== []) {
        $cache[$tournamentId] = count($liveParticipants);

        return $cache[$tournamentId];
    }

    $stmt = mysqli_prepare(
        $con,
        'SELECT COALESCE(c.standing_players, 0) AS standing_players,
                COALESCE(c.standing_rows, 0) AS standing_rows
         FROM amiga_tournament_catalog_stats c
         WHERE c.tournament_id = ?'
    );
    if ($stmt !== false) {
        mysqli_stmt_bind_param($stmt, 'i', $tournamentId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        if ($res) {
            mysqli_free_result($res);
        }
        mysqli_stmt_close($stmt);
        if (is_array($row) && (int) ($row['standing_rows'] ?? 0) > 0) {
            $cache[$tournamentId] = (int) ($row['standing_players'] ?? 0);

            return $cache[$tournamentId];
        }
    }

    $stmt = mysqli_prepare(
        $con,
        'SELECT COUNT(*) AS n FROM amiga_player_event_snapshots WHERE tournament_id = ?'
    );
    if ($stmt !== false) {
        mysqli_stmt_bind_param($stmt, 'i', $tournamentId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        if ($res) {
            mysqli_free_result($res);
        }
        mysqli_stmt_close($stmt);
        $snapshotCount = (int) ($row['n'] ?? 0);
        if ($snapshotCount > 0) {
            $cache[$tournamentId] = $snapshotCount;

            return $cache[$tournamentId];
        }
    }

    $stmt = mysqli_prepare(
        $con,
        'SELECT COUNT(DISTINCT player_id) AS n
         FROM (
             SELECT player_a_id AS player_id FROM amiga_games WHERE tournament_id = ?
             UNION
             SELECT player_b_id AS player_id FROM amiga_games WHERE tournament_id = ?
         ) g'
    );
    if ($stmt === false) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $tournamentId, $tournamentId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if ($res) {
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);

    $cache[$tournamentId] = (int) ($row['n'] ?? 0);

    return $cache[$tournamentId];
}

/** Indexed lookup — official games, or played fixtures while running (RTB broadcast). */
function amiga_tournament_game_count(mysqli $con, int $tournamentId): int
{
    static $cache = [];
    if ($tournamentId < 1) {
        return 0;
    }
    if (isset($cache[$tournamentId])) {
        return $cache[$tournamentId];
    }
    require_once __DIR__ . '/amiga_running_tournament_lib.php';
    if (amiga_running_tournament_broadcast_mode($con, $tournamentId)) {
        $cache[$tournamentId] = amiga_running_tournament_count_played_fixtures($con, $tournamentId);
        return $cache[$tournamentId];
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

    $cache[$tournamentId] = (int) ($row['n'] ?? 0);

    return $cache[$tournamentId];
}

/**
 * Tournament champion from stored participation (`is_winner = 1`).
 *
 * @return array{player_id: int, player_name: string, player_country: string}|null
 */
function amiga_tournament_winner(mysqli $con, int $tournamentId): ?array
{
    static $cache = [];
    if ($tournamentId < 1) {
        return null;
    }
    if (array_key_exists($tournamentId, $cache)) {
        return $cache[$tournamentId];
    }
    $sql = 'SELECT p.id AS player_id, p.name AS player_name, COALESCE(p.country, \'\') AS player_country
            FROM amiga_player_event_snapshots s
            INNER JOIN amiga_players p ON p.id = s.player_id
            WHERE s.tournament_id = ? AND s.is_winner = 1
            ORDER BY s.player_id ASC
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
    if ($row === null) {
        $cache[$tournamentId] = null;

        return null;
    }
    $playerId = (int) ($row['player_id'] ?? 0);
    $playerName = trim((string) ($row['player_name'] ?? ''));
    if ($playerId < 1 || $playerName === '') {
        $cache[$tournamentId] = null;

        return null;
    }

    $cache[$tournamentId] = [
        'player_id' => $playerId,
        'player_name' => $playerName,
        'player_country' => trim((string) ($row['player_country'] ?? '')),
    ];

    return $cache[$tournamentId];
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
    static $cache = [];
    if ($tournamentId < 1) {
        return [];
    }
    $cacheKey = $tournamentId . '|' . $playerFilter;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    require_once __DIR__ . '/amiga_db.php';

    // Rich ratedresults-shaped source (Elo, expected score, adjustments, player nationalities)
    // — same view that backs amiga/game.php and amiga/player/games.php.
    $sql = 'SELECT r.id, r.`Date`, r.idA, r.NameA, r.idB, r.NameB, r.phase, '
        . amiga_rated_games_phase_link_cols_sql() . ',
                   r.GoalsA, r.GoalsB, r.RatingA, r.RatingB, r.RatingDifference,
                   r.ExpectedScoreA, r.ExpectedScoreB, r.ActualScore, r.AdjustmentA, r.AdjustmentB,
                   r.NewRatingA, r.NewRatingB, r.SumOfGoals, r.GoalDifference,
                   r.country_a, r.country_b '
        . amiga_rated_games_from_sql()
        . ' WHERE r.tournament_id = ?';
    $types = 'i';
    $params = [$tournamentId];
    if ($playerFilter > 0) {
        $sql .= ' AND (r.idA = ? OR r.idB = ?)';
        $types .= 'ii';
        $params[] = $playerFilter;
        $params[] = $playerFilter;
    }
    $sql .= ' ORDER BY r.id DESC';

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

    $cache[$cacheKey] = $rows;

    return $cache[$cacheKey];
}

/** Standings/stages sub-nav link — always `stages.php` (WC and ordinary events). */
function amiga_tournament_standings_nav_url(
    int $id,
    string $scopeType = 'league',
    string $scopeKey = '',
    bool $isWorldCup = false,
): string {
    unset($isWorldCup);

    return amiga_tournament_url($id, $scopeType, $scopeKey, 'stages');
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
        'event-stats', 'games', 'stages', 'standings', 'videos' => $viewRaw === 'standings' ? 'stages' : $viewRaw,
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
    unset($tournament, $query);

    if ($pageView === 'standings') {
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

    if ($canonicalScope['redirect'] && !in_array($pageView, ['event-stats', 'games', 'videos'], true)) {
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
}

function amiga_tournament_link(int $id, string $name, string $fragment = AMIGA_TOURNAMENT_PAGE_FRAGMENT): string
{
    $href = amiga_tournament_href(amiga_tournament_event_stats_url($id));
    if ($fragment !== '') {
        $href .= '#' . ltrim($fragment, '#');
    }

    return '<a class="k2-link-star" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</a>';
}

function amiga_tournament_index_chapter_lede_html(int $tournamentCount): string
{
    $wcLink = amiga_tournament_link(AMIGA_FIRST_WORLD_CUP_TOURNAMENT_ID, 'World Cup in Dartford in 2001');
    $countHtml = '<span class="blue">' . number_format($tournamentCount) . '</span>';

    return 'Since the first ' . $wcLink . ', a total of ' . $countHtml
        . ' official tournaments have been played. Links to them all are provided below, with various filter options.';
}

function amiga_tournament_is_world_cup_by_name(string $name): bool
{
    return preg_match('/^World Cup\s+\S/i', trim($name)) === 1;
}

/**
 * Bidirectional create/save guard: checkbox must match World Cup name shape (WC11).
 */
function amiga_tournament_validate_is_world_cup_correspondence(string $name, bool $isWorldCup): void
{
    $nameMatches = amiga_tournament_is_world_cup_by_name($name);
    if ($isWorldCup === $nameMatches) {
        return;
    }
    if ($isWorldCup) {
        throw new RuntimeException(
            'World Cup event requires a name matching "World Cup …" (with text after Cup).'
        );
    }
    throw new RuntimeException(
        'This name is reserved for World Cup events — tick "World Cup event" or choose a different name.'
    );
}

/** True when catalog row is a World Cup (stored flag when present). */
function amiga_tournament_is_world_cup(array $row): bool
{
    if (array_key_exists('is_world_cup', $row)) {
        return (int) ($row['is_world_cup'] ?? 0) === 1;
    }

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

/** Tournament index format pill filter: '' | league | cup | league-cup */
function amiga_tournament_index_matches_filter(array $row, string $filter): bool
{
    if ($filter === '') {
        return true;
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

/** Tournament index World Cup pill filter: '' | world-cup | not-world-cup */
function amiga_tournament_index_matches_wc_filter(array $row, string $wcFilter): bool
{
    if ($wcFilter === '') {
        return true;
    }
    $isWorldCup = amiga_tournament_is_world_cup($row);
    if ($wcFilter === 'world-cup') {
        return $isWorldCup;
    }
    if ($wcFilter === 'not-world-cup') {
        return !$isWorldCup;
    }

    return true;
}

/** Calendar year from tournament catalog row (0 when unknown). */
function amiga_tournament_index_event_year(array $row): int
{
    $eventDate = $row['event_date'] ?? null;
    if ($eventDate === null || $eventDate === '') {
        return 0;
    }
    $ts = strtotime((string) $eventDate);

    return $ts !== false ? (int) date('Y', $ts) : 0;
}

/** Tournament index host-country filter: '' = all. */
function amiga_tournament_index_matches_country_filter(array $row, string $countryFilter): bool
{
    if ($countryFilter === '') {
        return true;
    }

    return trim((string) ($row['country'] ?? '')) === $countryFilter;
}

/** Tournament index calendar-year filter: 0 = all. */
function amiga_tournament_index_matches_year_filter(array $row, int $yearFilter): bool
{
    if ($yearFilter < 1) {
        return true;
    }

    return amiga_tournament_index_event_year($row) === $yearFilter;
}

/** Tournament index winner filter: 0 = all. */
function amiga_tournament_index_matches_winner_filter(array $row, int $winnerFilter): bool
{
    if ($winnerFilter < 1) {
        return true;
    }

    return (int) ($row['winner_player_id'] ?? 0) === $winnerFilter;
}

/** Tournament index winning-country filter: '' = all. */
function amiga_tournament_index_matches_winner_country_filter(array $row, string $winnerCountryFilter): bool
{
    if ($winnerCountryFilter === '') {
        return true;
    }

    return trim((string) ($row['winner_country'] ?? '')) === $winnerCountryFilter;
}

/**
 * Apply index filters; omit one dimension for faceted listbox counts.
 *
 * @return list<array<string, mixed>>
 */
function amiga_tournament_index_filter_rows(
    array $rows,
    string $wcFilter,
    string $typeFilter,
    string $videosFilter,
    string $countryFilter = '',
    int $yearFilter = 0,
    bool $omitCountry = false,
    bool $omitYear = false,
    string $perfectFilter = '',
    int $winnerFilter = 0,
    string $winnerCountryFilter = '',
    bool $omitWinner = false,
    bool $omitWinnerCountry = false,
): array {
    return array_values(array_filter(
        $rows,
        static function (array $row) use ($wcFilter, $typeFilter, $videosFilter, $countryFilter, $yearFilter, $omitCountry, $omitYear, $perfectFilter, $winnerFilter, $winnerCountryFilter, $omitWinner, $omitWinnerCountry): bool {
            if (!amiga_tournament_index_matches_wc_filter($row, $wcFilter)) {
                return false;
            }
            if (!amiga_tournament_index_matches_filter($row, $typeFilter)) {
                return false;
            }
            if ($videosFilter === 'with-videos' && !amiga_tournament_has_videos((int) ($row['id'] ?? 0))) {
                return false;
            }
            if ($perfectFilter === 'with-participant' && (int) ($row['has_perfect_participant'] ?? 0) !== 1) {
                return false;
            }
            if (!$omitCountry && !amiga_tournament_index_matches_country_filter($row, $countryFilter)) {
                return false;
            }
            if (!$omitYear && !amiga_tournament_index_matches_year_filter($row, $yearFilter)) {
                return false;
            }
            if (!$omitWinner && !amiga_tournament_index_matches_winner_filter($row, $winnerFilter)) {
                return false;
            }
            if (!$omitWinnerCountry && !amiga_tournament_index_matches_winner_country_filter($row, $winnerCountryFilter)) {
                return false;
            }

            return true;
        }
    ));
}

/**
 * @return array<string, int> country name => tournament count
 */
function amiga_tournament_index_country_counts(array $rows): array
{
    $counts = [];
    foreach ($rows as $row) {
        $country = trim((string) ($row['country'] ?? ''));
        if ($country === '') {
            continue;
        }
        $counts[$country] = ($counts[$country] ?? 0) + 1;
    }
    ksort($counts, SORT_STRING);

    return $counts;
}

/**
 * @return array<int, int> year => tournament count
 */
function amiga_tournament_index_year_counts(array $rows): array
{
    $counts = [];
    foreach ($rows as $row) {
        $year = amiga_tournament_index_event_year($row);
        if ($year < 1) {
            continue;
        }
        $counts[$year] = ($counts[$year] ?? 0) + 1;
    }
    krsort($counts, SORT_NUMERIC);

    return $counts;
}

/**
 * @param array<string, int> $counts
 * @return array<string, int>
 */
function amiga_tournament_index_inject_selected_country(array $counts, string $country): array
{
    $country = trim($country);
    if ($country === '' || isset($counts[$country])) {
        return $counts;
    }
    $counts[$country] = 0;
    ksort($counts, SORT_STRING);

    return $counts;
}

/**
 * @param array<int, int> $counts
 * @return array<int, int>
 */
function amiga_tournament_index_inject_selected_year(array $counts, int $year): array
{
    if ($year < 1 || isset($counts[$year])) {
        return $counts;
    }
    $counts[$year] = 0;
    krsort($counts, SORT_NUMERIC);

    return $counts;
}

/**
 * @param array<string, int> $counts
 * @return list<array{value: string, label: string, meta: string, flag_html?: string}>
 */
function amiga_tournament_index_country_listbox_choices(array $counts): array
{
    require_once __DIR__ . '/k2_amiga_country_flag.php';
    $choices = [['value' => '', 'label' => '', 'meta' => '']];
    foreach ($counts as $country => $count) {
        $choice = [
            'value' => $country,
            'label' => $country,
            'meta' => (string) (int) $count,
        ];
        $flagHtml = k2_amiga_country_flag_img($country, ['decorative' => true]);
        if ($flagHtml !== '') {
            $choice['flag_html'] = $flagHtml;
        }
        $choices[] = $choice;
    }

    return $choices;
}

/**
 * @param array<int, int> $counts
 * @return list<array{value: string, label: string, meta: string}>
 */
function amiga_tournament_index_year_listbox_choices(array $counts, string $idleValue = '0'): array
{
    $choices = [['value' => $idleValue, 'label' => '', 'meta' => '']];
    foreach ($counts as $year => $count) {
        $choices[] = [
            'value' => (string) (int) $year,
            'label' => (string) (int) $year,
            'meta' => (string) (int) $count,
        ];
    }

    return $choices;
}

/**
 * @return array<int, array{name: string, count: int}> player id => winner facet row
 */
function amiga_tournament_index_winner_counts(array $rows): array
{
    $counts = [];
    foreach ($rows as $row) {
        $winnerId = (int) ($row['winner_player_id'] ?? 0);
        if ($winnerId < 1) {
            continue;
        }
        $name = trim((string) ($row['winner_name'] ?? ''));
        if ($name === '') {
            continue;
        }
        if (!isset($counts[$winnerId])) {
            $counts[$winnerId] = ['name' => $name, 'count' => 0];
        }
        $counts[$winnerId]['count']++;
    }
    uasort(
        $counts,
        static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']),
    );

    return $counts;
}

/**
 * @return array<string, int> winning country name => tournament count
 */
function amiga_tournament_index_winner_country_counts(array $rows): array
{
    $counts = [];
    foreach ($rows as $row) {
        $country = trim((string) ($row['winner_country'] ?? ''));
        if ($country === '') {
            continue;
        }
        $counts[$country] = ($counts[$country] ?? 0) + 1;
    }
    ksort($counts, SORT_STRING);

    return $counts;
}

/**
 * @param array<int, array{name: string, count: int}> $counts
 * @return array<int, array{name: string, count: int}>
 */
function amiga_tournament_index_inject_selected_winner(array $counts, int $winnerId, string $winnerName = ''): array
{
    if ($winnerId < 1 || isset($counts[$winnerId])) {
        return $counts;
    }
    $counts[$winnerId] = [
        'name' => $winnerName !== '' ? $winnerName : ('Player #' . $winnerId),
        'count' => 0,
    ];
    uasort(
        $counts,
        static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']),
    );

    return $counts;
}

/**
 * @param array<string, int> $counts
 * @return array<string, int>
 */
function amiga_tournament_index_inject_selected_winner_country(array $counts, string $country): array
{
    return amiga_tournament_index_inject_selected_country($counts, $country);
}

/**
 * @param array<int, array{name: string, count: int}> $counts
 * @return list<array{value: string, label: string, meta: string}>
 */
function amiga_tournament_index_winner_listbox_choices(array $counts): array
{
    $choices = [['value' => '0', 'label' => '', 'meta' => '']];
    foreach ($counts as $winnerId => $row) {
        $choices[] = [
            'value' => (string) (int) $winnerId,
            'label' => (string) $row['name'],
            'meta' => (string) (int) $row['count'],
        ];
    }

    return $choices;
}

/**
 * @param array<string, int> $counts
 * @return list<array{value: string, label: string, meta: string}>
 */
function amiga_tournament_index_winner_country_listbox_choices(array $counts): array
{
    return amiga_tournament_index_country_listbox_choices($counts);
}

/**
 * Plain-language count line for the filtered tournament catalog list.
 */
function amiga_tournament_index_list_summary(
    int $count,
    bool $hasAnyTournaments,
    string $wcFilter = '',
    string $typeFilter = '',
    string $videosFilter = '',
    string $countryFilter = '',
    int $yearFilter = 0,
    string $perfectFilter = '',
    int $winnerFilter = 0,
    string $winnerCountryFilter = '',
    string $winnerName = '',
): string {
    if ($count === 0) {
        if (!$hasAnyTournaments) {
            return 'No tournaments on record yet.';
        }

        return 'No tournaments match these filters.';
    }

    $word = $count === 1 ? 'tournament' : 'tournaments';
    $n = number_format($count);

    $preNoun = [];
    if ($wcFilter === 'world-cup') {
        $preNoun[] = 'World Cup';
    } elseif ($wcFilter === 'not-world-cup') {
        $preNoun[] = 'non-World Cup';
    }
    if ($typeFilter === 'league') {
        $preNoun[] = 'league';
    } elseif ($typeFilter === 'cup') {
        $preNoun[] = 'cup';
    } elseif ($typeFilter === 'league-cup') {
        $preNoun[] = 'league + cup';
    }

    $postNoun = [];
    if ($videosFilter === 'with-videos') {
        $postNoun[] = 'with videos';
    }
    if ($perfectFilter === 'with-participant') {
        $postNoun[] = 'with a perfect run';
    }

    $suffix = '';
    if ($countryFilter !== '') {
        $suffix .= ' in ' . $countryFilter;
    }
    if ($yearFilter > 0) {
        $suffix .= ' in ' . $yearFilter;
    }
    if ($winnerFilter > 0) {
        $suffix .= ' won by ' . ($winnerName !== '' ? $winnerName : ('player #' . $winnerFilter));
    }
    if ($winnerCountryFilter !== '') {
        $suffix .= ' with winner from ' . $winnerCountryFilter;
    }

    $hasFilters = $preNoun !== [] || $postNoun !== [] || $suffix !== '';
    if (!$hasFilters) {
        return $n . ' ' . $word . ' in total.';
    }

    $phrase = $n . ' ';
    if ($preNoun !== []) {
        $phrase .= implode(' ', $preNoun) . ' ';
    }
    $phrase .= $word;
    if ($postNoun !== []) {
        $phrase .= ' ' . implode(' ', $postNoun);
    }

    return $phrase . $suffix . '.';
}

/** True when any catalog index filter param is active. */
function amiga_tournament_index_filters_active(
    string $wcFilter,
    string $typeFilter,
    string $videosFilter,
    string $countryFilter,
    int $yearFilter,
    string $perfectFilter = '',
    int $winnerFilter = 0,
    string $winnerCountryFilter = '',
): bool {
    return $wcFilter !== ''
        || $typeFilter !== ''
        || $videosFilter !== ''
        || $perfectFilter !== ''
        || $countryFilter !== ''
        || $yearFilter > 0
        || $winnerFilter > 0
        || $winnerCountryFilter !== '';
}

function amiga_tournament_index_reset_url(): string
{
    require_once __DIR__ . '/k2_amiga_routes.php';

    return k2_amiga_route('amiga-tournaments');
}

/** Filter pill href for /amiga/tournaments.php (carries active k2_sort and `as=` when set). */
function amiga_tournament_index_filter_url(
    string $typeFilter = '',
    string $videosFilter = '',
    string $wcFilter = '',
    string $countryFilter = '',
    int $yearFilter = 0,
    string $perfectFilter = '',
    int $winnerFilter = 0,
    string $winnerCountryFilter = '',
): string {
    require_once __DIR__ . '/k2_table_helpers.php';
    require_once __DIR__ . '/k2_amiga_routes.php';
    $params = array_merge(
        in_array($wcFilter, ['world-cup', 'not-world-cup'], true) ? ['wc' => $wcFilter] : [],
        $typeFilter !== '' ? ['type' => $typeFilter] : [],
        $videosFilter === 'with-videos' ? ['videos' => 'with-videos'] : [],
        $perfectFilter === 'with-participant' ? ['perfect' => 'with-participant'] : [],
        $countryFilter !== '' ? ['country' => $countryFilter] : [],
        $yearFilter > 0 ? ['year' => (string) $yearFilter] : [],
        $winnerFilter > 0 ? ['winner' => (string) $winnerFilter] : [],
        $winnerCountryFilter !== '' ? ['winner_country' => $winnerCountryFilter] : [],
        k2_table_sort_query_params(),
    );

    return k2_amiga_route('amiga-tournaments', $params);
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
    $help = $scope['scope_type'] === 'knockout' ? 'Elimination tie' : 'Phase standings';
    return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" data-k2-help="' . htmlspecialchars($help, ENT_QUOTES, 'UTF-8') . '" data-k2-tooltip-hide-title="1">'
        . htmlspecialchars($phase, ENT_QUOTES, 'UTF-8') . '</a>';
}

/**
 * Resolve standings scope for a game row — prefer `stage_id`, else L3 witness phase.
 *
 * @return array{scope_type: string, scope_key: string}|null
 */
function amiga_tournament_resolve_game_phase_scope(
    mysqli $con,
    int $tournamentId,
    int $stageId,
    string $witnessPhase,
    int $playerAId = 0,
    int $playerBId = 0,
): ?array {
    if ($tournamentId < 1) {
        return null;
    }
    if ($stageId > 0) {
        $stmt = mysqli_prepare(
            $con,
            'SELECT scope_type, scope_key FROM amiga_tournament_standings
             WHERE tournament_id = ? AND stage_id = ?
             LIMIT 1'
        );
        if ($stmt !== false) {
            mysqli_stmt_bind_param($stmt, 'ii', $tournamentId, $stageId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : false;
            if ($res) {
                mysqli_free_result($res);
            }
            mysqli_stmt_close($stmt);
            if (is_array($row)) {
                return [
                    'scope_type' => (string) $row['scope_type'],
                    'scope_key' => (string) $row['scope_key'],
                ];
            }
        }
    }

    $witnessPhase = trim($witnessPhase);
    if ($witnessPhase === '') {
        return null;
    }

    return amiga_tournament_resolve_phase_scope($con, $tournamentId, $witnessPhase, $playerAId, $playerBId);
}

/**
 * Tournament catalog for index page (chrono desc).
 *
 * @return list<array<string, mixed>>
 */
function amiga_tournament_index_rows(
    mysqli $con,
    int $limit = 0,
    int $offset = 0,
    ?AmigaSnapshotContext $ctx = null,
): array {
    require_once __DIR__ . '/amiga_snapshot_context.php';

    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();
    $offset = max(0, $offset);
    $allRows = amiga_tournament_index_cached_all_rows($con, $ctx);
    if ($limit < 1) {
        if ($offset === 0) {
            return $allRows;
        }
        $limit = count($allRows);
    }
    $limit = max(1, min(5000, $limit));

    return array_slice($allRows, $offset, $limit);
}

function amiga_tournament_index_count(mysqli $con, ?AmigaSnapshotContext $ctx = null): int
{
    require_once __DIR__ . '/amiga_snapshot_context.php';

    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();

    static $cache = [];
    $cacheKey = amiga_tournament_index_cutoff_cache_key($ctx);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $types = '';
    $params = [];
    $cutoffSql = amiga_snapshot_tournament_cutoff_and_sql($ctx, $types, $params);
    $sql = 'SELECT COUNT(*) AS n FROM tournaments t WHERE '
        . amiga_tournament_public_visibility_where('t') . $cutoffSql;
    if ($types === '') {
        $res = mysqli_query($con, $sql);
        if ($res === false) {
            return $cache[$cacheKey] = 0;
        }
        $row = mysqli_fetch_assoc($res);
        mysqli_free_result($res);

        return $cache[$cacheKey] = (int) ($row['n'] ?? 0);
    }

    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return $cache[$cacheKey] = 0;
    }
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : false;
    if ($res) {
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);

    return $cache[$cacheKey] = $row !== false ? (int) ($row['n'] ?? 0) : 0;
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
    require_once __DIR__ . '/amiga_running_tournament_lib.php';
    if (amiga_running_tournament_broadcast_mode($con, $tournamentId)) {
        return amiga_running_tournament_knockout_fixture_games($con, $tournamentId, $scopeKey);
    }

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
    static $cache = [];
    $empty = [
        'main' => [],
        'placement_final' => [],
        'placement_bracket' => [],
    ];
    if ($scopeKeys === [] || $tournamentId < 1) {
        return $empty;
    }
    if (isset($cache[$tournamentId])) {
        return $cache[$tournamentId];
    }

    $buckets = [
        'main' => [],
        'placement_final' => [],
        'placement_bracket' => [],
    ];

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

    $cache[$tournamentId] = $buckets;

    return $cache[$tournamentId];
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
 * Player flags in games tables — always on when `country_a` / `country_b` are set.
 *
 * @param list<array<string, mixed>> $rows unused; kept for call-site stability
 */
function amiga_tournament_games_show_flags(array $rows): bool
{
    return true;
}

/**
 * Phase / knockout standings table on tournament.php.
 *
 * @param list<array<string, mixed>> $rows from amiga_tournament_standings_rows()
 */
function amiga_tournament_render_standings_table(array $rows, bool $isKnockoutView): void
{
    require_once __DIR__ . '/k2_table_helpers.php';
    require_once __DIR__ . '/k2_amiga_country_flag.php';
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
            echo k2_amiga_lb_player_cell(
                (int) $row['player_id'],
                (string) $row['player_name'],
                trim((string) ($row['country'] ?? ''))
            );
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
function amiga_tournament_render_games_table(array $rows, ?mysqli $con = null): void
{
    require_once __DIR__ . '/k2_table_helpers.php';
    require_once __DIR__ . '/k2_rated_game_row.php';
    require_once __DIR__ . '/k2_player_game_row.php';
    require_once __DIR__ . '/amiga_rated_game_row.php';
    require_once __DIR__ . '/k2_amiga_country_flag.php';
    require_once __DIR__ . '/amiga_player_load.php';

    $showPhase = amiga_tournament_games_show_phase_column($rows);
    $showFlags = amiga_tournament_games_show_flags($rows);

    // Column index plan. Flags live inside the player cells, so they add no columns;
    // the layout stays index-stable whether or not flags are shown.
    $col = 0;
    $idCol = $col++;
    $phaseCol = $showPhase ? $col++ : -1;
    $teamACol = $col++;
    $goalsACol = $col++;
    $goalsBCol = $col++;
    $teamBCol = $col++;
    $gdCol = $col++;
    $sumCol = $col++;
    $tsCol = $col++;
    $ratingACol = $col++;
    $ratingBCol = $col++;
    $eloDiffCol = $col++;
    $favEsCol = $col++;
    $adjWinCol = $col++;
    $adjLoseCol = $col++;
    $colCount = $col;

    $anchorCol = $idCol;
    $defaultSortCol = k2_table_default_sort_col_from_request(AMIGA_TOURNAMENT_GAMES_DEFAULT_SORT_COL);
    $defaultSortDir = k2_table_default_sort_dir_from_request('desc');
    $tableClass = k2_table_ranked_sortable_class('k2-table--tournament-games');
    // No date/order column — rows arrive in game-id descending order; preserve that on first paint.
    $skipInitialSort = $defaultSortCol === AMIGA_TOURNAMENT_GAMES_DEFAULT_SORT_COL
        && $defaultSortDir === 'desc'
        && k2_table_sort_query_params() === [];
    ?>
<?php k2_table_wrap_open(true); ?>
<table class="<?php echo k2_h($tableClass); ?>" data-k2-table="sortable" data-k2-anchor-col="<?php echo $anchorCol; ?>" data-k2-default-sort="<?php echo $defaultSortCol; ?>" data-k2-default-direction="<?php echo k2_h($defaultSortDir); ?>"<?php echo $skipInitialSort ? ' data-k2-skip-initial-sort="1"' : ''; ?>>
	<thead>
		<tr>
			<th<?php echo k2_table_sortable_th_attr($idCol, $defaultSortCol, $defaultSortDir, 'k2-table-cell--left'); ?> data-k2-sort="number">ID</th>
			<?php if ($showPhase) { ?>
			<th<?php echo k2_table_sortable_th_attr($phaseCol, $defaultSortCol, $defaultSortDir, 'k2-table-cell--left'); ?> data-k2-sort="text">Phase</th>
			<?php } ?>
			<th<?php echo k2_table_sortable_th_attr($teamACol, $defaultSortCol, $defaultSortDir, 'k2-table-cell--right'); ?> data-k2-sort="text">Player A</th>
			<th<?php echo k2_table_sortable_th_attr($goalsACol, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number">A</th>
			<th<?php echo k2_table_sortable_th_attr($goalsBCol, $defaultSortCol, $defaultSortDir, 'k2-table-cell--left'); ?> data-k2-sort="number">B</th>
			<th<?php echo k2_table_sortable_th_attr($teamBCol, $defaultSortCol, $defaultSortDir, 'k2-table-cell--left'); ?> data-k2-sort="text">Player B</th>
			<th<?php echo k2_table_sortable_th_attr($gdCol, $defaultSortCol, $defaultSortDir, 'k2-table-cell--pad-left-md'); ?> data-k2-sort="number" data-k2-tooltip-label="Goal difference" data-k2-help="Absolute goal margin in the game. A 7-4 result has GD 3.">GD</th>
			<th<?php echo k2_table_sortable_th_attr($sumCol, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-tooltip-label="Goal sum" data-k2-help="Total goals scored by both players. A 7-4 result has Sum 11.">Sum</th>
			<th<?php echo k2_table_sortable_th_attr($tsCol, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-tooltip-label="Top score" data-k2-help="Top score — the most goals either player scored in this game (e.g. 10 in 10–2).">TS</th>
			<th<?php echo k2_table_sortable_th_attr($ratingACol, $defaultSortCol, $defaultSortDir, 'k2-table-cell--pad-left-md'); ?> data-k2-sort="number" data-k2-help="Player A's Elo rating before this game.">Rating A</th>
			<th<?php echo k2_table_sortable_th_attr($ratingBCol, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Player B's Elo rating before this game.">Rating B</th>
			<th<?php echo k2_table_sortable_th_attr($eloDiffCol, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Absolute pre-game Elo rating difference between the two players. Larger gaps mean a stronger favorite.">Elo Diff</th>
			<th<?php echo k2_table_sortable_th_attr($favEsCol, $defaultSortCol, $defaultSortDir, 'k2-table-cell--pad-right-xs'); ?> data-k2-sort="number" data-k2-tooltip-label="Favorite expected score" data-k2-help="Elo maps the rating difference to an expected score for the favorite:&#10;&#10;ES = 1 / (1 + 10^(-diff/400))&#10;&#10;Examples:&#10;&#10;0 -> 0.50&#10;100 -> 0.64&#10;200 -> 0.76&#10;300 -> 0.85&#10;400 -> 0.91&#10;&#10;The actual score will be one of win = 1, draw = 0.5, loss = 0.">Fav ES</th>
			<th<?php echo k2_table_sortable_th_attr($adjWinCol, $defaultSortCol, $defaultSortDir, 'k2-table-cell--left'); ?> data-k2-sort="number" data-k2-tooltip-label="Adjustment" data-k2-help="The expected score and actual score are used to calculate the rating change:&#10;&#10;Rating change = 32 * (actual score - expected score)&#10;&#10;Example:&#10;&#10;200 Elo difference -> expected score 0.76 ->&#10;&#10;A win would gain 7.7 rating points.&#10;A draw would lose 8.3 rating points.&#10;A loss would lose 24.3 rating points.&#10;&#10;A favorite's expected win gives a small rating gain; an underdog win beats expectation a lot and gains more. The two players win or lose the opposite amount.">Adjustment</th>
			<th class="k2-table-cell--left"><span class="visually-hidden">Adjustment lost</span></th>
		</tr>
	</thead>
	<tbody class="black">
	<?php if ($rows === []) { ?>
		<tr>
			<td colspan="<?php echo (int) $colCount; ?>" class="k2-table-cell--left" style="color:var(--k2-text-secondary)">No games match this filter.</td>
		</tr>
	<?php } ?>
	<?php foreach ($rows as $row) {
        $processed = k2_rated_game_is_processed($row);
        $game = k2_player_game_normalize_row($row);
        $phaseCell = amiga_rated_game_phase_cell($row, $con);
        $countryA = trim((string) ($row['country_a'] ?? ''));
        $countryB = trim((string) ($row['country_b'] ?? ''));
        $dash = k2_fmt_dash();

        $goalsA = (int) $game['GoalsA'];
        $goalsB = (int) $game['GoalsB'];
        if ($processed) {
            $aWin = k2_rated_game_is_a_win($game);
            $bWin = k2_rated_game_is_b_win($game);
        } else {
            $aWin = $goalsA > $goalsB;
            $bWin = $goalsB > $goalsA;
        }

        $goalDiff = $processed ? (int) $game['GoalDifference'] : abs($goalsA - $goalsB);
        $sumGoals = $processed ? (int) $game['SumOfGoals'] : $goalsA + $goalsB;
        $topScore = max($goalsA, $goalsB);

        if ($processed) {
            $esCell = k2_rated_game_es_winner_html($game);
            $favEs = k2_rated_game_favorite_expected_score($game);
            $winnerAdj = k2_game_rating_adjustment_pick($game, 'winner');
            $loserAdj = k2_game_rating_adjustment_pick($game, 'loser');
            $adjWinCell = amiga_rated_game_adjustment_html($game, 'winner');
            $adjLoseCell = amiga_rated_game_adjustment_html($game, 'loser');
            $ratingACell = (string) (int) round((float) $game['RatingA']);
            $ratingBCell = (string) (int) round((float) $game['RatingB']);
            $eloDiffCell = number_format(abs((float) ($row['RatingDifference'] ?? 0)), 0);
        } else {
            $esCell = $dash;
            $favEs = -1.0;
            $winnerAdj = ['adj' => 0.0];
            $loserAdj = ['adj' => 0.0];
            $adjWinCell = $dash;
            $adjLoseCell = $dash;
            $ratingACell = $dash;
            $ratingBCell = $dash;
            $eloDiffCell = $dash;
        }

        $flagA = $showFlags && $countryA !== '' ? k2_amiga_country_flag_link($countryA) : '';
        $flagB = $showFlags && $countryB !== '' ? k2_amiga_country_flag_link($countryB) : '';
        $teamACell = '<span class="k2-amiga-tgame-side k2-amiga-tgame-side--a">' . $flagA
            . k2_amiga_player_link((int) $game['idA'], (string) $game['NameA']) . '</span>';
        $teamBCell = '<span class="k2-amiga-tgame-side k2-amiga-tgame-side--b">'
            . k2_amiga_player_link((int) $game['idB'], (string) $game['NameB']) . $flagB . '</span>';

        $goalsAClass = $aWin ? 'k2-amiga-tgame-goal--win' : '';
        $goalsBClass = 'k2-table-cell--left' . ($bWin ? ' k2-amiga-tgame-goal--win' : '');
        $goalsACell = $aWin ? '<span class="blue">' . $goalsA . '</span>' : (string) $goalsA;
        $goalsBCell = $bWin ? '<span class="blue">' . $goalsB . '</span>' : (string) $goalsB;
        ?>
		<tr data-k2-sort-tie-value="<?php echo (int) $game['id']; ?>">
			<td<?php echo k2_table_body_td_attr($idCol, $anchorCol, $defaultSortCol, 'k2-table-cell--left'); ?>><?php echo amiga_rated_game_id_html((int) $game['id']); ?></td>
			<?php if ($showPhase) { ?>
			<td<?php echo k2_table_body_td_attr($phaseCol, $anchorCol, $defaultSortCol, 'k2-table-cell--left'); ?>><?php echo $phaseCell; ?></td>
			<?php } ?>
			<td<?php echo k2_table_body_td_attr($teamACol, $anchorCol, $defaultSortCol, 'k2-table-cell--right k2-amiga-tgame-team k2-amiga-tgame-team--a'); ?>><?php echo $teamACell; ?></td>
			<td<?php echo k2_table_body_td_attr($goalsACol, $anchorCol, $defaultSortCol, $goalsAClass); ?>><?php echo $goalsACell; ?></td>
			<td<?php echo k2_table_body_td_attr($goalsBCol, $anchorCol, $defaultSortCol, $goalsBClass); ?>><?php echo $goalsBCell; ?></td>
			<td<?php echo k2_table_body_td_attr($teamBCol, $anchorCol, $defaultSortCol, 'k2-table-cell--left k2-amiga-tgame-team k2-amiga-tgame-team--b'); ?>><?php echo $teamBCell; ?></td>
			<td<?php echo k2_table_body_td_attr($gdCol, $anchorCol, $defaultSortCol, 'k2-table-cell--pad-left-md'); ?> data-k2-sort-value="<?php echo $goalDiff; ?>"><?php echo $goalDiff; ?></td>
			<td<?php echo k2_table_body_td_attr($sumCol, $anchorCol, $defaultSortCol); ?>><?php echo $sumGoals; ?></td>
			<td<?php echo k2_table_body_td_attr($tsCol, $anchorCol, $defaultSortCol); ?> data-k2-sort-value="<?php echo $topScore; ?>"><?php echo $topScore; ?></td>
			<td<?php echo k2_table_body_td_attr($ratingACol, $anchorCol, $defaultSortCol, 'k2-table-cell--pad-left-md'); ?>><?php echo $ratingACell; ?></td>
			<td<?php echo k2_table_body_td_attr($ratingBCol, $anchorCol, $defaultSortCol); ?>><?php echo $ratingBCell; ?></td>
			<td<?php echo k2_table_body_td_attr($eloDiffCol, $anchorCol, $defaultSortCol); ?>><?php echo $eloDiffCell; ?></td>
			<td<?php echo k2_table_body_td_attr($favEsCol, $anchorCol, $defaultSortCol, 'k2-table-cell--pad-right-xs'); ?> data-k2-sort-value="<?php echo $favEs; ?>"><?php echo $esCell; ?></td>
			<td<?php echo k2_table_body_td_attr($adjWinCol, $anchorCol, $defaultSortCol, 'k2-table-cell--left'); ?> data-k2-sort-value="<?php echo (float) $winnerAdj['adj']; ?>"><?php echo $adjWinCell; ?></td>
			<td<?php echo k2_table_body_td_attr($adjLoseCol, $anchorCol, $defaultSortCol, 'k2-table-cell--left'); ?> data-k2-sort-value="<?php echo (float) $loserAdj['adj']; ?>"><?php echo $adjLoseCell; ?></td>
		</tr>
	<?php } ?>
	</tbody>
</table>
<?php k2_table_wrap_close(); ?>
    <?php
}
