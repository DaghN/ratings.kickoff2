<?php
/**
 * Realm switcher — Online hub vs Amiga 500 ladder.
 * Plain anchor navigation; does not change tint (docs/tint-vs-realm.md).
 *
 * Optional before include: $k2CurrentRealm — 'online' | 'amiga'
 */
if (!isset($k2CurrentRealm)) {
	$script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
	$k2CurrentRealm = (strpos($script, '/amiga/') !== false) ? 'amiga' : 'online';
}

$k2RealmChoices = [
	'online' => ['href' => '/status.php', 'label' => 'Online'],
	'amiga' => ['href' => '/amiga/rating.php', 'label' => 'Amiga 500'],
];
?>
<nav class="k2-realm-switch" aria-label="Kick Off 2 realm">
	<div class="k2-realm-switch__track" role="group" aria-label="Realm">
<?php foreach ($k2RealmChoices as $realmId => $choice) {
	$isActive = $k2CurrentRealm === $realmId;
	$hrefEsc = htmlspecialchars($choice['href'], ENT_QUOTES, 'UTF-8');
	$labelEsc = htmlspecialchars($choice['label'], ENT_QUOTES, 'UTF-8');
	$activeClass = $isActive ? ' is-active' : '';
	$ariaCurrent = $isActive ? ' aria-current="page"' : '';
?>
		<a href="<?php echo $hrefEsc; ?>" class="k2-realm-switch__btn<?php echo $activeClass; ?>"<?php echo $ariaCurrent; ?>><?php echo $labelEsc; ?></a>
<?php } ?>
	</div>
</nav>
