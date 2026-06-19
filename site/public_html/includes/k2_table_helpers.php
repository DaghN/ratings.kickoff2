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
