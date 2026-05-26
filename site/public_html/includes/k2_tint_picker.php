<?php
/**
 * Shared tint picker — pills + Show/Hide tint toggle.
 * Requires realm-switch.js (site header) for pill clicks.
 */
if (!isset($k2AccentPills)) {
	include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_accent_pills.inc.php';
}
?>
			<nav class="k2-accent-pills" aria-label="Tint">
<?php foreach ($k2AccentPills as $accentId => $pill) { ?>
				<button type="button" class="k2-accent-pills__btn" data-k2-accent="<?php echo $accentId; ?>"><?php echo $pill['label']; ?></button>
<?php } ?>
			</nav>
			<button type="button" class="k2-accent-pills-toggle" aria-pressed="false">Hide tint</button>
