<?php
/**
 * Apply saved realm / tint before first paint.
 * Tint default is amber; sets data-k2-accent="amber" when nothing saved (amber pill shows active).
 * Loaded from includes/k2_head.php in <head> (after theme.css).
 */
?>
<script type="text/javascript">
(function () {
	var root = document.documentElement;
	var validAccents = ['amber', 'pitch', 'chrome', 'holo'];

	function isValidAccent(accent) {
		return accent && validAccents.indexOf(accent) !== -1;
	}

	function readLocal(key) {
		try {
			return localStorage.getItem(key);
		} catch (e) {
			return null;
		}
	}

	function writeLocal(key, value) {
		try {
			localStorage.setItem(key, value);
		} catch (e) {
			/* ignore storage errors */
		}
	}

	function readSession(key) {
		try {
			return sessionStorage.getItem(key);
		} catch (e) {
			return null;
		}
	}

	function removeSession(key) {
		try {
			sessionStorage.removeItem(key);
		} catch (e) {
			/* ignore storage errors */
		}
	}

	function savedAccent() {
		var accent = readLocal('k2-accent-tune');
		if (isValidAccent(accent)) {
			return accent;
		}

		/* Migrate old session-only tint choices without losing the current tab. */
		accent = readSession('k2-accent-tune');
		if (isValidAccent(accent)) {
			writeLocal('k2-accent-tune', accent);
			removeSession('k2-accent-tune');
			return accent;
		}

		return 'amber';
	}

	var realm = readLocal('k2-realm');
	if (realm === 'online' || realm === 'amiga') {
		root.setAttribute('data-realm', realm);
	}
	root.setAttribute('data-k2-accent', savedAccent());
	/* Default hidden; sessionStorage "0" = user chose Show tint */
	if (readSession('k2-accent-pills-hidden') !== '0') {
		root.setAttribute('data-k2-accent-pills-hidden', '1');
	}
})();
</script>
