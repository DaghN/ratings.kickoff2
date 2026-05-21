<?php
/**
 * Apply saved realm / accent tune before first paint (prevents amber flash on navigation).
 * Loaded from includes/k2_head.php in <head> (after theme.css).
 */
?>
<script type="text/javascript">
(function () {
	var root = document.documentElement;
	var validAccents = ['chrome', 'signal', 'lagoon', 'phosphor', 'pulse', 'holo', 'ember'];
	try {
		var realm = localStorage.getItem('k2-realm');
		if (realm === 'online' || realm === 'amiga') {
			root.setAttribute('data-realm', realm);
		}
		var accent = sessionStorage.getItem('k2-accent-tune');
		if (accent && validAccents.indexOf(accent) !== -1) {
			root.setAttribute('data-k2-accent', accent);
		}
	} catch (e) {
		/* ignore storage errors */
	}
})();
</script>
