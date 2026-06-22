<?php
/**
 * Realm switcher state — Online hub vs Amiga 500 ladder home URLs.
 * Plain anchor navigation; does not change tint (docs/tint-vs-realm.md).
 *
 * Optional before include: $k2CurrentRealm — 'online' | 'amiga'
 * Sets $k2RealmChoices and $k2RealmHomeHref; markup in realm_switcher_nav.php.
 */
if (!isset($k2CurrentRealm)) {
	$script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
	$k2CurrentRealm = (strpos($script, '/amiga/') !== false) ? 'amiga' : 'online';
}

if (!isset($k2RealmChoices)) {
	$k2RealmChoices = [
		'online' => ['href' => '/status.php', 'label' => 'Online'],
		'amiga' => ['href' => '/amiga/news.php', 'label' => 'Amiga 500'],
	];
}

if ($k2CurrentRealm === 'amiga') {
	require_once __DIR__ . '/amiga_hub_nav_lib.php';
	$amigaHomeHref = amiga_realm_home_href();
	$k2RealmChoices['amiga']['href'] = $amigaHomeHref;
}

$k2RealmHomeHref = $k2RealmChoices[$k2CurrentRealm]['href'] ?? $k2RealmChoices['online']['href'];
