/**
 * Staging: accent preview hide toggle (hub bar).
 * Hub nav style is set in theme_boot_head.php (?k2_hub_nav= + session).
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
		btn.title = hidden ? 'Show accent preview pills' : 'Hide accent preview pills';
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
		var btn = document.querySelector('.k2-accent-pills-toggle');
		if (!btn) {
			return;
		}
		syncHideButton(btn);
		btn.addEventListener('click', function () {
			var hidden = root.getAttribute('data-k2-accent-pills-hidden') !== '1';
			setHidden(hidden);
			syncHideButton(btn);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
