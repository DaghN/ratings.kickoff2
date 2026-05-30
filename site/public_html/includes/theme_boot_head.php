<?php
/**
 * Apply saved realm / tint before first paint.
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

	var realm = readLocal('k2-realm');
	if (realm === 'online' || realm === 'amiga') {
		root.setAttribute('data-realm', realm);
	}

	if (S) {
		S.applyAccentToRoot(root, S.resolveAccent());
	} else {
		root.setAttribute('data-k2-accent', 'amber');
	}

	/* Default hidden; sessionStorage "0" = user chose Show tint */
	if (readSession('k2-accent-pills-hidden') !== '0') {
		root.setAttribute('data-k2-accent-pills-hidden', '1');
	}
})();
</script>
