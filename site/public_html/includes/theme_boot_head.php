<?php
/**
 * Apply saved tint before first paint.
 * Tint: six-hour schedule (visitor local time) unless user picked a pill (manual).
 * Loaded from includes/k2_head.php in <head> (after theme.css).
 */
$k2DocRoot = $_SERVER['DOCUMENT_ROOT'];
?>
<script type="text/javascript" src="js/k2-tint-schedule.js?v=<?php echo (int) @filemtime($k2DocRoot . '/js/k2-tint-schedule.js'); ?>"></script>
<script type="text/javascript">
(function () {
	var root = document.documentElement;
	var S = window.K2TintSchedule;
	var OPEN_KEY = 'k2-accent-palette-open';
	var LEGACY_HIDE_KEY = 'k2-accent-pills-hidden';

	function readLocal(key) {
		try {
			return localStorage.getItem(key);
		} catch (e) {
			return null;
		}
	}

	function readSession(key) {
		try {
			return sessionStorage.getItem(key);
		} catch (e) {
			return null;
		}
	}

	if (S) {
		S.applyAccentToRoot(root, S.resolveAccent());
	} else {
		root.setAttribute('data-k2-accent', 'amber');
	}

	/* Default: palette closed. OPEN_KEY "1" or legacy hide "0" = leave open for first paint */
	if (readSession(OPEN_KEY) === '1' || readSession(LEGACY_HIDE_KEY) === '0') {
		root.setAttribute('data-k2-accent-palette-open', '1');
	} else {
		root.setAttribute('data-k2-accent-palette-hidden', '1');
	}
})();
</script>
