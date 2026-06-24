<?php
/**
 * Online player wing — realm hub bar after site_header, before hero.
 * No hub tab is active on player pages. Tint picker: hub bar only (not player_nav.php).
 */
declare(strict_types=1);

$k2HubTabActive = $k2HubTabActive ?? '';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/hub_nav.php';
