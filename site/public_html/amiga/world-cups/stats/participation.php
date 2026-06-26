<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav_lib.php';

$k2AmigaWorldCupsHubView = 'stats';
$k2AmigaWorldCupsStatsView = 'participation';
$k2AmigaWorldCupsPageTitle = 'World Cups — Tournament stats — Participation';
$k2AmigaWorldCupsEnqueueTableJs = true;

include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_world_cups_hub_shell_start.inc.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_world_cup_stats_wing_body.inc.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_world_cups_hub_shell_end.inc.php';
