<?php
/**
 * k2-table.js + optional k2-table-scroll-mirror.js (overflow-driven top bar).
 *
 * Include in <head> after k2_head.php. Markup: k2_table_wrap_open(true) / k2_table_wrap_close().
 * Set $k2SortableTableScrollMirror = false before include to skip mirror (rare).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_table_helpers.php';

$k2SortableTableScrollMirror = $k2SortableTableScrollMirror ?? true;
k2_table_sortable_assets_enqueue($k2SortableTableScrollMirror);
