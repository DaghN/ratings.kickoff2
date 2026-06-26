<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav_lib.php';

$k2AmigaWorldCupsHubView = 'chronology';
$k2AmigaWorldCupsPageTitle = 'World Cups — Chronology';
$k2AmigaWorldCupsEnqueueTableJs = true;

include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_world_cups_hub_shell_start.inc.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_world_cups_events_body.inc.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_world_cups_hub_shell_end.inc.php';
