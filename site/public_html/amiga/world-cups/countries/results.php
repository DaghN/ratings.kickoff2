<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav_lib.php';

$k2AmigaWorldCupsHubView = 'countries';
$k2AmigaWorldCupsCountriesView = 'results';
$k2AmigaWorldCupsPageTitle = 'World Cups — Country stats — Results';
$k2AmigaWorldCupsChapterLede = 'World Cup match results and national depth metrics by country.';
$k2AmigaWorldCupsEnqueueTableJs = true;

include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_world_cups_hub_shell_start.inc.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_wc_countries_wing_body.inc.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_world_cups_hub_shell_end.inc.php';
