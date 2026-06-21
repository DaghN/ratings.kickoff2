<?php
/**
 * Shared k2-table markup helpers (ranked leaderboards, history ladder, …).
 *
 * @see js/k2-table.js — client sort re-applies the same body cell classes.
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';

/** Table class bundle for full-page ranked leaderboards (cloak + calm stats). */
function k2_table_ranked_leaderboard_class(string $extra = '', bool $pending = true): string
{
    $class = 'k2-table k2-table--numeric-default k2-table--calm-stats ranked-pages-table';
    if ($pending) {
        $class .= ' ranked-table-pending';
    }
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
