<?php
/**
 * Ranked leaderboards (ranked1-5, ranked7): mark document so CSS can hide tables until table JS
 * finishes (class ranked-table-pending on the table). Without JS, html never gets ranked-js and
 * tables stay visible. Fallback timer clears pending if init never runs (e.g. script error).
 */
?>
<script type="text/javascript">
(function () {
	document.documentElement.className += " ranked-js";
	setTimeout(function () {
		var tables = document.getElementsByTagName("TABLE"), i, el;
		for (i = 0; i < tables.length; i++) {
			el = tables[i];
			if (el.className && el.className.indexOf("ranked-table-pending") !== -1) {
				el.className = el.className.replace(/\branked-table-pending\b/g, " ").replace(/\s{2,}/g, " ").replace(/^ | $/g, "");
			}
		}
	}, 6000);
})();
</script>
