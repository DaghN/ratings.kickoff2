<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav_lib.php';

$k2AmigaWorldCupsHubView = 'players';
$k2AmigaWorldCupsPlayersView = 'results';
$k2AmigaWorldCupsPageTitle = 'World Cups — Player stats — Results';
$k2AmigaWorldCupsChapterLede = 'World Cup match results (3 / 1 / 0) summed over each player\'s WC career.';
$k2AmigaWorldCupsEnqueueTableJs = true;

include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_world_cups_hub_shell_start.inc.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_wc_players_wing_body.inc.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_world_cups_hub_shell_end.inc.php';
