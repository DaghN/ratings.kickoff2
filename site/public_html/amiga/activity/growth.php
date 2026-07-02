<?php
declare(strict_types=1);

$k2AmigaActivityWingView = 'growth';
$k2AmigaActivityPageTitle = 'Activity — Growth';

include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_activity_hub_shell_start.inc.php';
$k2AmigaActivitySummaryHideLede = true;
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_activity_summary.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_activity_growth_panels.inc.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_activity_hub_shell_end.inc.php';
