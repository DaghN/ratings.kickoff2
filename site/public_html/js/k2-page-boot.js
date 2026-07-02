/**
 * Page boot helpers (no SPA / Turbo) — every navigation is a normal full page load.
 *
 * Provides the same small API older scripts already use:
 *   k2OnPageReady(fn) — run fn once when the DOM is ready (or now if already ready).
 *   k2PageReady(fn)   — run fn on the k2:page-ready signal (dispatched once per load).
 *
 * Both effectively run once per page load now that there is no in-page navigation.
 * Kept as a tiny shim so the many existing consumers of these globals keep working
 * unchanged after Turbo Drive was removed (Jun 2026).
 */
(function () {
	"use strict";

	var pageReadyFired = false;

	window.k2PageReady = function (fn) {
		if (typeof fn !== "function") {
			return;
		}
		document.addEventListener("k2:page-ready", fn);
		if (pageReadyFired) {
			fn();
		}
	};

	window.k2OnPageReady = function (fn) {
		if (typeof fn !== "function") {
			return;
		}
		// Exactly once per load. The old implementation piggybacked on
		// k2PageReady + its own DOMContentLoaded/immediate call, which invoked
		// fn twice on every load (masked by idempotent consumers).
		if (pageReadyFired) {
			fn();
			return;
		}
		document.addEventListener("k2:page-ready", fn, { once: true });
	};

	function dispatchPageReady() {
		if (pageReadyFired) {
			return;
		}
		pageReadyFired = true;
		document.dispatchEvent(new CustomEvent("k2:page-ready", { bubbles: true }));
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", dispatchPageReady);
	} else {
		dispatchPageReady();
	}
})();