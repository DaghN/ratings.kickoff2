<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav_lib.php';

$k2AmigaWorldCupsHubView = 'players';
$k2AmigaWorldCupsPlayersView = 'dds';
$k2AmigaWcPlayersView = 'dds';
$k2AmigaWorldCupsPageTitle = 'World Cups — Player stats — DDs & CSs';
$k2AmigaWorldCupsChapterLede = 'Double digits and clean sheets across every player\'s World Cup career.';
$k2AmigaWorldCupsEnqueueTableJs = true;

include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_world_cups_hub_shell_start.inc.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_wc_players_wing_body.inc.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_world_cups_hub_shell_end.inc.php';
