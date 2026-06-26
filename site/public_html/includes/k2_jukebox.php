<?php
/**
 * Site jukebox launcher — floating FAB that opens the standalone player window
 * (/jukebox.php) so audio plays gaplessly across full-page navigation.
 * Player UI + audio live in the popup window; wiring in js/k2-jukebox-launcher.js.
 */
declare(strict_types=1);
?>
<div class="k2-jukebox k2-jukebox--launcher" id="k2-jukebox-root" data-k2-jukebox aria-live="polite">
	<button
		type="button"
		class="k2-jukebox__toggle"
		data-k2-jukebox-launch
		title="Open Amiga jukebox"
		aria-label="Open Amiga jukebox"
	>
		<svg class="k2-jukebox__toggle-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
			<rect x="5" y="4" width="14" height="16" rx="2" fill="none" stroke="currentColor" stroke-width="1.5"/>
			<rect x="8" y="7" width="8" height="5" rx="1" fill="currentColor" opacity="0.35"/>
			<line class="k2-jukebox__toggle-led" x1="8" y1="15" x2="16" y2="15"/>
			<line class="k2-jukebox__toggle-led" x1="8" y1="17.5" x2="13" y2="17.5"/>
		</svg>
	</button>
</div>