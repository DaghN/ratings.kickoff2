(function () {
	'use strict';

	function clearGlow(el) {
		if (!el) {
			return;
		}
		el.classList.remove('k2-live-glow');
		if (el.__k2LiveGlowTimer) {
			clearTimeout(el.__k2LiveGlowTimer);
			el.__k2LiveGlowTimer = null;
		}
		if (el.__k2LiveGlowEnd) {
			el.removeEventListener('animationend', el.__k2LiveGlowEnd);
			el.__k2LiveGlowEnd = null;
		}
	}

	function triggerLiveGlow(el) {
		if (!el) {
			return;
		}
		clearGlow(el);
		void el.offsetWidth;
		el.classList.add('k2-live-glow');
		el.__k2LiveGlowEnd = function (ev) {
			if (ev.animationName === 'k2-live-glow-bloom') {
				clearGlow(el);
			}
		};
		el.addEventListener('animationend', el.__k2LiveGlowEnd);
		el.__k2LiveGlowTimer = setTimeout(function () {
			clearGlow(el);
		}, 3000);
	}

	function triggerScorePulse(el) {
		if (!el) {
			return;
		}
		el.classList.remove('k2-live-score-pulse');
		void el.offsetWidth;
		el.classList.add('k2-live-score-pulse');
		el.addEventListener('animationend', function onEnd(ev) {
			if (ev.animationName === 'k2-live-score-pulse') {
				el.classList.remove('k2-live-score-pulse');
				el.removeEventListener('animationend', onEnd);
			}
		});
	}

	function staggerGlow(elements, delayMs) {
		delayMs = delayMs || 150;
		for (var i = 0; i < elements.length; i++) {
			(function (el, step) {
				if (!el) {
					return;
				}
				setTimeout(function () {
					triggerLiveGlow(el);
				}, step * delayMs);
			})(elements[i], i);
		}
	}

	window.k2LiveGlow = {
		trigger: triggerLiveGlow,
		scorePulse: triggerScorePulse,
		stagger: staggerGlow,
	};
})();
