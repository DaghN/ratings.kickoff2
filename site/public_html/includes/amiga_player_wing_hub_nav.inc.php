<?php
/**
 * Amiga player wing — realm hub bar after site_header, before hero.
 * No hub tab is active on player pages. Tint picker: hub bar only (not amiga_player_nav.php).
 */
declare(strict_types=1);

$k2AmigaHubTabActive = $k2AmigaHubTabActive ?? '';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';
