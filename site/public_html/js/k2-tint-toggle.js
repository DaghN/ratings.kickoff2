/**
 * Tint picker hide toggle (hub + player nav).
 */
(function () {
	'use strict';

	var root = document.documentElement;
	var HIDE_KEY = 'k2-accent-pills-hidden';

	function syncHideButton(btn) {
		var hidden = root.getAttribute('data-k2-accent-pills-hidden') === '1';
		btn.classList.toggle('is-active', hidden);
		btn.setAttribute('aria-pressed', hidden ? 'true' : 'false');
		btn.textContent = hidden ? 'Show tint' : 'Hide tint';
		btn.removeAttribute('title');
	}

	function setHidden(hidden) {
		if (hidden) {
			root.setAttribute('data-k2-accent-pills-hidden', '1');
		} else {
			root.removeAttribute('data-k2-accent-pills-hidden');
		}
		try {
			sessionStorage.setItem(HIDE_KEY, hidden ? '1' : '0');
		} catch (e) {
			/* ignore */
		}
	}

	function init() {
		var buttons = document.querySelectorAll('.k2-accent-pills-toggle');
		if (!buttons.length) {
			return;
		}
		for (var i = 0; i < buttons.length; i++) {
			syncHideButton(buttons[i]);
		}
		document.addEventListener('click', function (ev) {
			var btn = ev.target && ev.target.closest ? ev.target.closest('.k2-accent-pills-toggle') : null;
			if (!btn) {
				return;
			}
			var hidden = root.getAttribute('data-k2-accent-pills-hidden') !== '1';
			setHidden(hidden);
			for (var j = 0; j < buttons.length; j++) {
				syncHideButton(buttons[j]);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
