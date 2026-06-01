/**
 * Peer nav pills (hub, leaderboard wing, player profile, milestones segment bars): carry
 * window scrollY to the next full page load. Set on pill click; restored once by
 * k2_carry_scroll_restore.php in <head>.
 */
(function () {
	'use strict';

	var KEY = 'k2:carryScrollY';
	var LINK_SEL =
		'nav[data-k2-carry-scroll] a.k2-hub-tabs__btn, ' +
		'nav[data-k2-carry-scroll] a.k2-player-nav__btn, ' +
		'nav[data-k2-carry-scroll] a.k2-chrome-tabs__tab';

	document.addEventListener(
		'click',
		function (ev) {
			var a = ev.target && ev.target.closest ? ev.target.closest(LINK_SEL) : null;
			if (!a || !a.href) {
				return;
			}
			/* Modified / non-primary clicks: leave default (new tab, etc.). */
			if (ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) {
				return;
			}
			if (
				a.pathname === window.location.pathname &&
				a.search === window.location.search
			) {
				/* Already on this pill — avoid reload that would jump to top. */
				ev.preventDefault();
				return;
			}
			try {
				var y = window.pageYOffset;
				if (y == null) {
					y = window.scrollY;
				}
				sessionStorage.setItem(KEY, String(y || 0));
			} catch (e) {
				/* ignore */
			}
		},
		true
	);
})();
