<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav_lib.php';

$k2AmigaWorldCupsHubView = 'players';
$k2AmigaWorldCupsPlayersView = 'opponents';
$k2AmigaWcPlayersView = 'opponents';
$k2AmigaWorldCupsPageTitle = 'World Cups — Player stats — Opponents';
$k2AmigaWorldCupsChapterLede = 'Geography and opponent network across every player\'s World Cup career.';
$k2AmigaWorldCupsEnqueueTableJs = true;

include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_world_cups_hub_shell_start.inc.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_wc_players_wing_body.inc.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_world_cups_hub_shell_end.inc.php';
