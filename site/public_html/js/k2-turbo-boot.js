/**
 * Turbo Drive boot — in-page navigation; k2:page-ready for script re-init.
 */
(function () {
	"use strict";

	var turboVisitIndex = 0;

	function configureTurbo() {
		if (!window.Turbo) {
			return;
		}
		if (window.Turbo.config && window.Turbo.config.drive) {
			window.Turbo.config.drive.progressBarDelay = Number.POSITIVE_INFINITY;
		}
		if (window.Turbo.session) {
			window.Turbo.session.progressBarDelay = Number.POSITIVE_INFINITY;
		}
	}

	window.k2PageReady = function (fn) {
		if (typeof fn !== "function") {
			return;
		}
		document.addEventListener("k2:page-ready", fn);
	};

	function dispatchPageReady() {
		document.dispatchEvent(new CustomEvent("k2:page-ready", { bubbles: true }));
	}

	function reinitDomContentScripts() {
		document.dispatchEvent(new Event("DOMContentLoaded", { bubbles: true }));
	}

	function markTurboNavStart() {
		document.documentElement.classList.add("k2-turbo-navigating");
	}

	function markTurboNavEnd() {
		window.requestAnimationFrame(function () {
			document.documentElement.classList.remove("k2-turbo-navigating");
		});
	}

	configureTurbo();

	document.addEventListener("turbo:before-render", markTurboNavStart);

	document.addEventListener("turbo:render", markTurboNavEnd);

	document.addEventListener("turbo:load", function () {
		if (turboVisitIndex > 0) {
			reinitDomContentScripts();
		}
		turboVisitIndex += 1;
		dispatchPageReady();
	});

	document.addEventListener("turbo:before-cache", function () {
		if (typeof window.Chart === "undefined" || !window.Chart.getChart) {
			return;
		}
		document.querySelectorAll("canvas").forEach(function (canvas) {
			var chart = window.Chart.getChart(canvas);
			if (chart) {
				chart.destroy();
			}
		});
	});

	document.addEventListener("DOMContentLoaded", function () {
		if (window.Turbo && turboVisitIndex > 0) {
			return;
		}
		if (!window.Turbo) {
			dispatchPageReady();
		}
	});
})();