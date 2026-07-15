<?php
/**
 * Shared k2-table markup helpers (ranked leaderboards, history ladder, …).
 *
 * Before adding or refactoring a sortable table, read docs/k2-table-implementation-checklist.md
 * and copy the nearest reference implementation (do not bare k2_table_js_enqueue() on full pages).
 *
 * @see docs/k2-table-implementation-checklist.md
 * @see js/k2-table.js — `data-k2-quiet-default-sort-cols` (default load only).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';

/** Sortable ranked table bundle (cloak + calm stats); auto column widths. */
function k2_table_ranked_sortable_class(string $extra = '', bool $pending = true): string
{
    $class = 'k2-table k2-table--numeric-default k2-table--calm-stats ranked-pages-table';
    if ($pending) {
        $class .= ' ranked-table-pending';
    }
    $extra = trim($extra);

    return $extra !== '' ? $class . ' ' . $extra : $class;
}

/** Hub leaderboard wings: sortable bundle + Rank (col 1) / Player (col 2) min-widths. */
function k2_table_ranked_leaderboard_class(string $extra = '', bool $pending = true): string
{
    $class = k2_table_ranked_sortable_class('k2-table--hub-rank-player-cols', $pending);
    $extra = trim($extra);

    return $extra !== '' ? $class . ' ' . $extra : $class;
}

/**
 * Body cell classes for first paint: anchor column and/or default sort column.
 * When anchor and default sort are the same index, only anchor is applied (matches k2-table.js).
 */
function k2_table_body_td_classes(int $colIndex, int $anchorCol, int $defaultSortCol = -1, string $extraClass = ''): string
{
    $classes = [];
    foreach (preg_split('/\s+/', trim($extraClass)) ?: [] as $part) {
        if ($part !== '') {
            $classes[] = $part;
        }
    }
    if ($anchorCol >= 0 && $colIndex === $anchorCol) {
        $classes[] = 'k2-table-anchor-cell';
    } elseif ($defaultSortCol >= 0 && $colIndex === $defaultSortCol) {
        $classes[] = 'k2-table-col-sorted';
    }

    return implode(' ', $classes);
}

function k2_table_body_td_attr(int $colIndex, int $anchorCol, int $defaultSortCol = -1, string $extraClass = ''): string
{
    $class = k2_table_body_td_classes($colIndex, $anchorCol, $defaultSortCol, $extraClass);

    return $class === '' ? '' : ' class="' . k2_h($class) . '"';
}

/** Optional ranked-table sort from query string (`k2_sort` / `k2_dir`, 0-based column index). */
function k2_table_sort_query_params(): array
{
    if (!isset($_GET['k2_sort'])) {
        return [];
    }

    $sort = filter_var($_GET['k2_sort'], FILTER_VALIDATE_INT);
    if ($sort === false || $sort < 0) {
        return [];
    }

    $dir = isset($_GET['k2_dir']) && $_GET['k2_dir'] === 'asc' ? 'asc' : 'desc';

    return [
        'k2_sort' => (string) $sort,
        'k2_dir' => $dir,
    ];
}

/**
 * Scoped table sort params (`k2_sort`, `k2_dir`, `k2_sort_league-standings`, …).
 *
 * @return array<string, string>
 */
function k2_table_scoped_sort_query_params(): array
{
    /** @var array<string, string> $params */
    $params = [];
    foreach ($_GET as $name => $value) {
        if (!is_string($name) || $name === '' || is_array($value)) {
            continue;
        }
        if (!preg_match('/^k2_(sort|dir)(_|$)/', $name)) {
            continue;
        }
        if (str_starts_with($name, 'k2_sort')) {
            $sort = filter_var($value, FILTER_VALIDATE_INT);
            if ($sort === false || $sort < 0) {
                continue;
            }
            $params[$name] = (string) $sort;
            continue;
        }
        $params[$name] = $value === 'asc' ? 'asc' : 'desc';
    }

    return $params;
}

function k2_table_path_only(string $path): string
{
    $qPos = strpos($path, '?');

    return $qPos !== false ? substr($path, 0, $qPos) : $path;
}

/**
 * Carry active table sort onto same-path navigation (time-travel chevrons, etc.).
 *
 * @param array<string, scalar|null> $query
 * @return array<string, scalar|null>
 */
function k2_table_merge_sort_query_for_path(string $targetPath, array $query): array
{
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (!is_string($currentPath) || $currentPath === '') {
        return $query;
    }
    if (k2_table_path_only($targetPath) !== $currentPath) {
        return $query;
    }

    return array_merge($query, k2_table_sort_query_params());
}

/** Default sort column for SSR when URL carries k2_sort on this request. */
function k2_table_default_sort_col_from_request(int $fallback): int
{
    $params = k2_table_sort_query_params();
    if ($params === []) {
        return $fallback;
    }

    return (int) $params['k2_sort'];
}

/** Default sort direction for SSR when URL carries k2_dir on this request. */
function k2_table_default_sort_dir_from_request(string $fallback = 'desc'): string
{
    $params = k2_table_sort_query_params();

    return $params === [] ? $fallback : $params['k2_dir'];
}

/**
 * Sortable header classes for first paint (matches k2-table.js after init).
 *
 * @param 'asc'|'desc' $defaultDir
 */
function k2_table_sortable_th_classes(int $colIndex, int $defaultSortCol, string $defaultDir = 'desc', string $extraClass = ''): string
{
    $classes = [];
    foreach (preg_split('/\s+/', trim($extraClass)) ?: [] as $part) {
        if ($part !== '') {
            $classes[] = $part;
        }
    }
    $classes[] = 'k2-table-sortable';
    if ($colIndex === $defaultSortCol) {
        $classes[] = $defaultDir === 'asc' ? 'k2-table-sorted-asc' : 'k2-table-sorted-desc';
    }

    return implode(' ', $classes);
}

/**
 * Sortable <th> attributes for SSR (class, aria-sort, tabindex).
 *
 * @param 'asc'|'desc' $defaultDir
 */
function k2_table_sortable_th_attr(int $colIndex, int $defaultSortCol, string $defaultDir = 'desc', string $extraClass = ''): string
{
    $class = k2_table_sortable_th_classes($colIndex, $defaultSortCol, $defaultDir, $extraClass);
    $isActive = $colIndex === $defaultSortCol;
    $aria = $isActive ? ($defaultDir === 'desc' ? 'descending' : 'ascending') : 'none';

    return ' class="' . k2_h($class) . '" aria-sort="' . $aria . '" tabindex="0"';
}

/** Whether {@see k2_table_js_enqueue()} already emitted the script tag this request. */
function k2_table_js_is_enqueued(): bool
{
    return !empty($GLOBALS['_k2_table_js_enqueued']);
}

/** Mark k2-table.js as loaded (e.g. when a page enqueues it in head before site_header). */
function k2_table_js_mark_enqueued(): void
{
    $GLOBALS['_k2_table_js_enqueued'] = true;
}

/** Emit k2-table.js once per request (sortable tables + data-k2-help tooltips). */
function k2_table_js_enqueue(): void
{
    if (k2_table_js_is_enqueued()) {
        return;
    }

    k2_table_js_mark_enqueued();
    $v = (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js');
    echo '<script type="text/javascript" src="/js/k2-table.js?v=' . $v . '" defer="defer"></script>';
}

/** Whether {@see k2_table_scroll_mirror_enqueue()} already emitted the script tag this request. */
function k2_table_scroll_mirror_is_enqueued(): bool
{
    return !empty($GLOBALS['_k2_table_scroll_mirror_enqueued']);
}

/** Emit k2-table-scroll-mirror.js once per request (overflow-driven top scrollbar). */
function k2_table_scroll_mirror_enqueue(): void
{
    if (k2_table_scroll_mirror_is_enqueued()) {
        return;
    }

    $GLOBALS['_k2_table_scroll_mirror_enqueued'] = true;
    $v = (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table-scroll-mirror.js');
    echo '<script type="text/javascript" src="/js/k2-table-scroll-mirror.js?v=' . $v . '" defer="defer"></script>';
}

/** k2-table.js plus optional scroll mirror (markup: {@see k2_table_wrap_open()}).
 *  Head assets: {@see includes/k2_sortable_table_assets_head.inc.php} or online LB {@see includes/k2_lb_sortable_table_head.inc.php}. */
function k2_table_sortable_assets_enqueue(bool $scrollMirror = false): void
{
    k2_table_js_enqueue();
    if ($scrollMirror) {
        k2_table_scroll_mirror_enqueue();
    }
}

/** Open a .k2-table-wrap panel; pass true when scroll mirror may be needed on overflow. */
function k2_table_wrap_open(bool $scrollMirror = false): void
{
    if ($scrollMirror) {
        echo '<div class="k2-table-wrap" data-k2-scroll-mirror>';
        return;
    }

    echo '<div class="k2-table-wrap">';
}

function k2_table_wrap_close(): void
{
    echo '</div>';
}

/** Hub leaderboard wings: Player link anchor is always col 2 (0=#, 1=Player, 2=ELO or hero stat). */
const K2_LB_TABLE_ANCHOR_COL = 2;

/**
 * @return array{anchor: int, sort_col: int, sort_dir: string}
 */
function k2_lb_table_sort_state(int $defaultSortCol, int $anchorCol = K2_LB_TABLE_ANCHOR_COL): array
{
    return [
        'anchor' => $anchorCol,
        'sort_col' => k2_table_default_sort_col_from_request($defaultSortCol),
        'sort_dir' => k2_table_default_sort_dir_from_request('desc'),
    ];
}

/** Hub LB sortable <th> attrs (wraps k2_table_sortable_th_attr). */
function k2_lb_th(int $colIndex, array $sort, string $extraClass = ''): string
{
    return k2_table_sortable_th_attr($colIndex, $sort['sort_col'], $sort['sort_dir'], $extraClass);
}

/** Career Elo column <th> attrs — centered header (see k2_lb_elo_column_help_attrs for tooltip). */
function k2_lb_th_elo(int $colIndex, array $sort): string
{
    return k2_lb_th($colIndex, $sort, 'k2-table-cell--center');
}

/** @deprecated Legacy flag-only column header — use inline compositors per k2-table-entity-links-policy.md */
function k2_lb_th_country(int $colIndex, array $sort): string
{
    return k2_lb_th($colIndex, $sort, 'k2-table-cell--center');
}

/** Rating change (Δ) column <th> attrs — centered flat-top Δ styling via k2-table-col-delta. */
function k2_lb_th_delta(int $colIndex, array $sort): string
{
    return k2_lb_th($colIndex, $sort, 'k2-table-cell--center k2-table-col-delta');
}

/** Hub LB body <td> attrs (wraps k2_table_body_td_attr). */
function k2_lb_td(int $colIndex, array $sort, string $extraClass = ''): string
{
    return k2_table_body_td_attr($colIndex, $sort['anchor'], $sort['sort_col'], $extraClass);
}

/** data-k2-skip-initial-sort when URL has no k2_sort and defaults match SQL order. */
function k2_table_skip_initial_sort_attr(int $defaultSortCol, string $defaultDir = 'desc'): string
{
    if (k2_table_sort_query_params() !== []) {
        return '';
    }
    $sortCol = k2_table_default_sort_col_from_request($defaultSortCol);
    $sortDir = k2_table_default_sort_dir_from_request($defaultDir);
    if ($sortCol === $defaultSortCol && $sortDir === $defaultDir) {
        return ' data-k2-skip-initial-sort="1"';
    }

    return '';
}

/**
 * ORDER BY clause (no "ORDER BY" prefix) from URL/default LB sort + per-page column map.
 *
 * When the URL carries k2_sort and the column is mapped, SSR owns row order for that request.
 *
 * @param array{sort_col: int, sort_dir: string} $lbSort
 * @param array<int, string> $columnExprs col index => SQL expression without direction
 * @return array{order_clause: string, ssr_applied_url_sort: bool}
 */
function k2_lb_sql_order_from_sort(array $lbSort, array $columnExprs, string $defaultOrderClause): array
{
    $urlSort = k2_table_sort_query_params();
    $col = $lbSort['sort_col'];
    if ($urlSort === [] || !isset($columnExprs[$col])) {
        return [
            'order_clause' => $defaultOrderClause,
            'ssr_applied_url_sort' => false,
        ];
    }

    $dir = strtolower($lbSort['sort_dir']) === 'asc' ? 'ASC' : 'DESC';

    return [
        'order_clause' => $columnExprs[$col] . ' ' . $dir . ', ' . $defaultOrderClause,
        'ssr_applied_url_sort' => true,
    ];
}

/**
 * Skip k2-table.js tbody reorder when SSR already applied the URL sort (header/body emphasis via $lbSort).
 */
function k2_lb_table_skip_initial_sort_attr_for_ssr(
    array $lbSort,
    int $defaultSortCol,
    string $defaultDir,
    bool $ssrAppliedUrlSort
): string {
    if (k2_table_sort_query_params() !== []) {
        return $ssrAppliedUrlSort ? ' data-k2-skip-initial-sort="1"' : '';
    }

    return k2_table_skip_initial_sort_attr($defaultSortCol, $defaultDir);
}

/** True when ranked client-sort table has no `k2_sort` / `k2_dir` in the request (default sort view). */
function k2_table_is_default_client_sort_view(): bool
{
    return k2_table_sort_query_params() === [];
}

/** True when server-sort page has no user `sort` query override. */
function k2_table_is_default_server_sort_view(string $sortQueryKey = 'sort'): bool
{
    if (!isset($_GET[$sortQueryKey])) {
        return true;
    }

    $raw = $_GET[$sortQueryKey];

    return !is_string($raw) || trim($raw) === '';
}

/**
 * Body emphasis column for quiet-date cells — returns -1 to skip `k2-table-col-sorted` on default load only.
 *
 * @param list<int> $quietDateCols
 */
function k2_table_sort_col_for_emphasis(
    int $colIndex,
    int $activeSortCol,
    array $quietDateCols,
    bool $isDefaultSortView
): int {
    if ($isDefaultSortView
        && in_array($colIndex, $quietDateCols, true)
        && $colIndex === $activeSortCol
    ) {
        return -1;
    }

    return $activeSortCol;
}

/**
 * `data-k2-quiet-default-sort-cols` attribute for client-sort tables (quiet body on default load only).
 *
 * @param list<int> $indices
 */
function k2_table_quiet_default_sort_col_attr(array $indices): string
{
    if ($indices === []) {
        return '';
    }

    $parts = [];
    foreach ($indices as $index) {
        $parts[] = (string) (int) $index;
    }

    return ' data-k2-quiet-default-sort-cols="' . k2_h(implode(',', $parts)) . '"';
}

/** Append `k2-table-col-quiet-date` when this date cell is quietly sorted on default load. */
function k2_table_quiet_date_cell_class(
    int $colIndex,
    int $activeSortCol,
    int $tableDefaultSortCol,
    bool $isDefaultSortView,
    string $extraClass = ''
): string {
    $classes = trim($extraClass);
    if ($isDefaultSortView && $colIndex === $activeSortCol && $activeSortCol === $tableDefaultSortCol) {
        $classes = trim($classes . ' k2-table-col-quiet-date');
    }

    return $classes;
}
