<?php
/**
 * Legacy Activity URL — 302 to the Activity hub default wing (Growth).
 * Preserves the query string (as=, as_with, chart params).
 *
 * @see docs/amiga-activity-charts-policy.md §3.2
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_amiga_routes.php';

k2_amiga_legacy_redirect('/amiga/activity/growth.php');