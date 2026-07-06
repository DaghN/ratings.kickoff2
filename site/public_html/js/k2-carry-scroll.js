/**

 * Carry window scrollY across full-page peer navigation: nav pills, listbox filter

 * forms (data-k2-carry-scroll), and server-sort links on player games / realm All games tables.

 * Pill clicks also store nav viewport offset so filter/table height changes do not nudge scroll.

 * Restore: k2_carry_scroll_restore.php in <head> (pre-paint cloak on full-page load).

 */

(function () {

	'use strict';



	var KEY = 'k2:carryScrollY';

	var PILL_LINK_SEL =

		'nav[data-k2-carry-scroll] a.k2-hub-tabs__btn, ' +

		'nav[data-k2-carry-scroll] a.k2-player-nav__btn, ' +

		'nav[data-k2-carry-scroll] a.k2-chrome-tabs__tab, ' +

		'nav[data-k2-carry-scroll] a.k2-realm-switch__btn';

	var SORT_LINK_SEL =
		'.k2-table--player-games th.k2-table-sortable a[href], ' +
		'.k2-table--realm-games-all th.k2-table-sortable a[href]';

	var GAMES_ACTION_SEL =

		'[data-k2-carry-scroll] a.k2-player-games-action[href], ' +

		'[data-k2-carry-scroll] a.k2-player-games-reset[href], ' +

		'[data-k2-carry-scroll] a.k2-player-games-day-step[href], ' +

		'[data-k2-carry-scroll] a.k2-league-period__sibling-link[href], ' +

		'[data-k2-carry-scroll] a.k2-carry-scroll-link[href]';



	function scrollYNow() {

		var y = window.pageYOffset;

		if (y == null) {

			y = window.scrollY;

		}

		return y || 0;

	}

	/* Sticky TT ribbon (CSS position:sticky): when stuck, nav rect.top is pinned near
	   viewport top so anchor viewportOffset no longer encodes scroll depth. */
	var TT_RIBBON_STUCK_TOP_PX = 15;

	function ttRibbonSectionFromNav(nav) {

		if (!nav || !nav.closest) {

			return null;

		}

		return nav.closest('.k2-amiga-time-travel--active');

	}

	function ttRibbonIsStuck(section) {

		if (!section) {

			return false;

		}

		return section.getBoundingClientRect().top <= TT_RIBBON_STUCK_TOP_PX;

	}

	function carryAnchorFromNav(nav) {

		if (!nav) {

			return null;

		}

		var label = nav.getAttribute('aria-label');

		if (!label) {

			return null;

		}

		if (ttRibbonIsStuck(ttRibbonSectionFromNav(nav))) {

			return null;

		}

		return {

			label: label,

			viewportOffset: nav.getBoundingClientRect().top,

		};

	}



	function writePayload(payload) {

		try {

			sessionStorage.setItem(KEY, JSON.stringify(payload));

		} catch (e) {

			/* ignore */

		}

	}



	function storeScrollY() {

		writePayload({ y: scrollYNow() });

	}

	function carryAnchorFromTimeTravelRibbon() {

		var section = document.querySelector('.k2-amiga-time-travel--active, .k2-amiga-time-travel');

		if (!section) {

			return null;

		}

		var nav = section.querySelector('nav[data-k2-carry-scroll][aria-label="Time travel snapshot"]');

		if (!nav) {

			nav = section.querySelector('nav[data-k2-carry-scroll]');

		}

		return carryAnchorFromNav(nav);

	}

	function storeScrollYFromForm(form) {

		var payload = { y: scrollYNow() };

		var anchor = null;

		if (form && form.closest) {

			var ttSection = form.closest('.k2-amiga-time-travel--active, .k2-amiga-time-travel');

			if (ttSection) {

				var stepperNav = ttSection.querySelector('nav[data-k2-carry-scroll][aria-label="Time travel snapshot"]');

				anchor = carryAnchorFromNav(stepperNav);

				if (!anchor) {

					anchor = carryAnchorFromNav(ttSection.querySelector('nav[data-k2-carry-scroll]'));

				}

			}

		}

		if (!anchor) {

			anchor = carryAnchorFromTimeTravelRibbon();

		}

		if (anchor) {

			payload.anchor = anchor;

		}

		writePayload(payload);

	}



	function storeScrollYFromPill(pill) {

		var payload = { y: scrollYNow() };

		var nav = pill && pill.closest ? pill.closest('nav[data-k2-carry-scroll]') : null;

		var anchor = carryAnchorFromNav(nav);

		if (anchor) {

			payload.anchor = anchor;

		}

		writePayload(payload);

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

				storeScrollYFromPill(pill);

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
				var carryNav = gamesAction.closest
					? gamesAction.closest('nav[data-k2-carry-scroll]')
					: null;
				if (carryNav) {
					var carryPayload = { y: scrollYNow() };
					var carryAnchor = carryAnchorFromNav(carryNav);
					if (carryAnchor) {
						carryPayload.anchor = carryAnchor;
					}
					writePayload(carryPayload);
				} else {
					storeScrollY();
				}
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

			if (!target.closest('form[data-k2-carry-scroll]') && !target.closest('.k2-player-opponents-h2h[data-k2-carry-scroll]')) {

				return;

			}

			storeScrollYFromForm(target.closest('form[data-k2-carry-scroll]'));

		},

		true

	);

})();

