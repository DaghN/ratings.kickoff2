/**
 * Carry window scrollY across full-page peer navigation: nav pills, listbox filter
 * forms (data-k2-carry-scroll), and server-sort links on player games tables.
 * Store on interaction; restore via k2_carry_scroll_restore.php in <head> (conditional retries until success or user scroll).
 */
(function () {
	'use strict';

	var KEY = 'k2:carryScrollY';
	var PILL_LINK_SEL =
		'nav[data-k2-carry-scroll] a.k2-hub-tabs__btn, ' +
		'nav[data-k2-carry-scroll] a.k2-player-nav__btn, ' +
		'nav[data-k2-carry-scroll] a.k2-chrome-tabs__tab';
	var SORT_LINK_SEL = '.k2-table--player-games th.k2-table-sortable a[href]';
	var GAMES_ACTION_SEL =
		'[data-k2-carry-scroll] a.k2-player-games-action[href]';

	function storeScrollY() {
		try {
			var y = window.pageYOffset;
			if (y == null) {
				y = window.scrollY;
			}
			sessionStorage.setItem(KEY, String(y || 0));
		} catch (e) {
			/* ignore */
		}
	}

	function isModifiedClick(ev) {
		return (
			ev.button !== 0 ||
			ev.metaKey ||
			ev.ctrlKey ||
			ev.shiftKey ||
			ev.altKey
		);
	}

	window.K2CarryScroll = {
		store: storeScrollY,
	};

	document.addEventListener(
		'click',
		function (ev) {
			if (isModifiedClick(ev)) {
				return;
			}

			var pill =
				ev.target && ev.target.closest
					? ev.target.closest(PILL_LINK_SEL)
					: null;
			if (pill && pill.href) {
				if (
					pill.pathname === window.location.pathname &&
					pill.search === window.location.search
				) {
					/* Already on this pill — avoid reload that would jump to top. */
					ev.preventDefault();
					return;
				}
				storeScrollY();
				return;
			}

			var sort =
				ev.target && ev.target.closest
					? ev.target.closest(SORT_LINK_SEL)
					: null;
			if (sort && sort.href) {
				storeScrollY();
				return;
			}

			var gamesAction =
				ev.target && ev.target.closest
					? ev.target.closest(GAMES_ACTION_SEL)
					: null;
			if (gamesAction && gamesAction.href) {
				storeScrollY();
			}
		},
		true
	);

	document.addEventListener(
		'change',
		function (ev) {
			var target = ev.target;
			if (
				!target ||
				!target.classList ||
				!target.classList.contains('k2-archive-listbox__value')
			) {
				return;
			}
			if (!target.closest('form[data-k2-carry-scroll]')) {
				return;
			}
			storeScrollY();
		},
		true
	);
})();
