/**
 * Tint palette disclosure (hub + player nav).
 * Closed by default; does not persist open state across page loads.
 */
(function () {
	'use strict';

	var root = document.documentElement;

	function isOpen() {
		return root.getAttribute('data-k2-accent-palette-open') === '1';
	}

	function setOpen(open) {
		if (open) {
			root.setAttribute('data-k2-accent-palette-open', '1');
			root.removeAttribute('data-k2-accent-palette-hidden');
		} else {
			root.removeAttribute('data-k2-accent-palette-open');
			root.setAttribute('data-k2-accent-palette-hidden', '1');
		}
	}

	function closeIfOpen() {
		if (!isOpen()) {
			return;
		}
		setOpen(false);
		syncToggleButtons();
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
		setOpen(false);
		syncToggleButtons();
	}

	function isNavLink(el) {
		if (!el || el.tagName !== 'A') {
			return false;
		}
		if (el.closest('.k2-tint-menu')) {
			return false;
		}
		var href = el.getAttribute('href');
		if (!href || href === '#' || href.indexOf('javascript:') === 0) {
			return false;
		}
		return true;
	}

	function boot() {
		syncToggleButtons();
	}

	// Turbo Drive re-evaluates this body script on every in-page navigation.
	// Bind the document click listener + first init only ONCE per document, or each
	// visit would stack another toggle handler and clicks would fire an even number of
	// times (the picker appears dead). See docs/k2-turbo-page-init-checklist.md.
	if (window.__k2TintToggleBound) {
		boot();
		return;
	}
	window.__k2TintToggleBound = true;

	document.addEventListener('click', function (ev) {
		var target = ev.target;
		if (!target || !target.closest) {
			return;
		}
		var toggle = target.closest('.k2-tint-menu__toggle');
		if (toggle) {
			setOpen(!isOpen());
			syncToggleButtons();
			return;
		}
		if (isNavLink(target.closest('a[href]'))) {
			closeIfOpen();
		}
	});

	window.addEventListener('pageshow', function (ev) {
		if (ev.persisted) {
			closeIfOpen();
		}
	});

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
		init();
		boot();
	});
})();
