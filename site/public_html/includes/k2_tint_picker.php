<?php
/**
 * Shared tint picker — Tint disclosure + swatch choices.
 * Requires realm-switch.js for swatch clicks and schedule/manual override.
 */
if (!isset($k2AccentPills)) {
	include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_accent_pills.inc.php';
}
static $k2TintMenuInstance = 0;
$k2TintMenuInstance++;
$k2TintMenuChoicesId = 'k2-tint-menu-choices-' . $k2TintMenuInstance;
?>
			<nav class="k2-tint-menu" aria-label="Tint">
				<button
					type="button"
					class="k2-tint-menu__toggle"
					aria-expanded="false"
					aria-controls="<?php echo $k2TintMenuChoicesId; ?>"
				>
					<span class="k2-tint-menu__toggle-label">Tint</span>
				</button>
				<div id="<?php echo $k2TintMenuChoicesId; ?>" class="k2-tint-menu__choices" role="group" aria-label="Tint choices">
<?php foreach ($k2AccentPills as $accentId => $pill) { ?>
					<button
						type="button"
						class="k2-tint-menu__choice"
						data-k2-accent="<?php echo htmlspecialchars($accentId, ENT_QUOTES, 'UTF-8'); ?>"
					><?php echo htmlspecialchars($pill['label'], ENT_QUOTES, 'UTF-8'); ?></button>
<?php } ?>
				</div>
			</nav>
