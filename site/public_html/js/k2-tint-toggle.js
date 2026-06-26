/**
 * Tint palette disclosure (hub + player nav).
 * Closed by default; open state persisted in sessionStorage.
 */
(function () {
	'use strict';

	var root = document.documentElement;
	var OPEN_KEY = 'k2-accent-palette-open';
	var LEGACY_HIDE_KEY = 'k2-accent-pills-hidden';

	function isOpen() {
		return root.getAttribute('data-k2-accent-palette-open') === '1';
	}

	function readSession(key) {
		try {
			return sessionStorage.getItem(key);
		} catch (e) {
			return null;
		}
	}

	function writeSession(key, value) {
		try {
			sessionStorage.setItem(key, value);
		} catch (e) {
			/* ignore */
		}
	}

	function migrateLegacyHidden() {
		if (readSession(OPEN_KEY) !== null) {
			return;
		}
		if (readSession(LEGACY_HIDE_KEY) === '0') {
			writeSession(OPEN_KEY, '1');
		} else if (readSession(LEGACY_HIDE_KEY) === '1') {
			writeSession(OPEN_KEY, '0');
		}
	}

	function setOpen(open) {
		if (open) {
			root.setAttribute('data-k2-accent-palette-open', '1');
			root.removeAttribute('data-k2-accent-palette-hidden');
		} else {
			root.removeAttribute('data-k2-accent-palette-open');
			root.setAttribute('data-k2-accent-palette-hidden', '1');
		}
		try {
			writeSession(OPEN_KEY, open ? '1' : '0');
		} catch (e) {
			/* ignore */
		}
	}

	function syncToggleButtons() {
		var open = isOpen();
		document.querySelectorAll('.k2-tint-menu__toggle').forEach(function (btn) {
			btn.setAttribute('aria-expanded', open ? 'true' : 'false');
			var choices = btn.getAttribute('aria-controls');
			if (choices) {
				var panel = document.getElementById(choices);
				if (panel) {
					if (open) {
						panel.removeAttribute('hidden');
					} else {
						panel.setAttribute('hidden', 'hidden');
					}
				}
			}
		});
	}

	function init() {
		migrateLegacyHidden();
		if (readSession(OPEN_KEY) === '1') {
			setOpen(true);
		} else {
			setOpen(false);
		}
		syncToggleButtons();
	}

	document.addEventListener('click', function (ev) {
		var btn = ev.target && ev.target.closest ? ev.target.closest('.k2-tint-menu__toggle') : null;
		if (!btn) {
			return;
		}
		setOpen(!isOpen());
		syncToggleButtons();
	});

	function boot() {
		syncToggleButtons();
	}

	var tintInitialized = false;

	function firstInit() {
		if (tintInitialized) {
			return;
		}
		tintInitialized = true;
		init();
	}

	if (window.k2PageReady) {
		window.k2PageReady(boot);
	}

	(window.k2OnPageReady || function (fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	})(function () {
		firstInit();
		boot();
	});
})();
