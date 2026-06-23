<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav_lib.php';

$k2AmigaWorldCupsHubView = 'events';
$k2AmigaWorldCupsPageTitle = 'World Cups';
$k2AmigaWorldCupsChapterLede = 'Every World Cup in the realm — links go to each event\'s tournament page.';

include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_world_cups_hub_shell_start.inc.php';
?>
	<p class="k2-amiga-world-cups-placeholder" style="margin:0 1.25rem 1.25rem;color:var(--k2-text-secondary)">Event list coming soon.</p>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_world_cups_hub_shell_end.inc.php'; ?>
