<?php
/**
 * Realm switcher markup — requires realm_switcher.php first.
 */
?>
<nav class="k2-realm-switch" data-k2-carry-scroll aria-label="Kick Off 2 realm">
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
