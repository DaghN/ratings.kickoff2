<?php
declare(strict_types=1);

$k2AmigaActivityWingView = 'geography';
$k2AmigaActivityGeographyView = 'nations';
$k2AmigaActivityPageTitle = 'Activity — Geography — Nationalities';

include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_activity_hub_shell_start.inc.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_activity_geography_selector.inc.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_activity_geography_nations_panels.inc.php';
?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_activity_hub_shell_end.inc.php'; ?>
