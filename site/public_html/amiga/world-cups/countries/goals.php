<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav_lib.php';

$k2AmigaWorldCupsHubView = 'countries';
$k2AmigaWorldCupsCountriesView = 'goals';
$k2AmigaWorldCupsPageTitle = 'World Cups — Country stats — Goals';
$k2AmigaWorldCupsChapterLede = 'World Cup goals scored and conceded by national teams.';
$k2AmigaWorldCupsEnqueueTableJs = true;

include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_world_cups_hub_shell_start.inc.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_wc_countries_wing_body.inc.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_world_cups_hub_shell_end.inc.php';
