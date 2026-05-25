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
	try {
		var realm = localStorage.getItem('k2-realm');
		if (realm === 'online' || realm === 'amiga') {
			root.setAttribute('data-realm', realm);
		}
		var accent = sessionStorage.getItem('k2-accent-tune');
		if (accent && validAccents.indexOf(accent) !== -1) {
			root.setAttribute('data-k2-accent', accent);
		} else {
			root.setAttribute('data-k2-accent', 'amber');
		}
		/* Default hidden; sessionStorage "0" = user chose Show tint */
		if (sessionStorage.getItem('k2-accent-pills-hidden') !== '0') {
			root.setAttribute('data-k2-accent-pills-hidden', '1');
		}
	} catch (e) {
		/* ignore storage errors */
	}
})();
</script>
