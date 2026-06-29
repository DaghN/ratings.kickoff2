/**
 * Jukebox launcher — opens the standalone player in a separate popup window so audio
 * keeps playing (gapless) while the visitor navigates the main site with normal full
 * page loads. The popup owns the <audio>; this script only opens/focuses it and mirrors
 * the now-playing state onto the floating FAB via BroadcastChannel.
 */
(function () {
	"use strict";

	var JUKEBOX_URL = "/jukebox.php";
	var WIN_NAME = "k2jukebox";
	var WIN_W = 360;
	var WIN_H = 500;
	var CHANNEL_NAME = "k2-jukebox";
	var POPUP_LIVE_KEY = "k2-jukebox-popup-live";
	var POPUP_BOOT_BG = "#0b0f14";

	/* Centre the popup on the screen the browser sits on (availLeft/availTop keep it
	   on the correct monitor in multi-display setups; position applies only when the
	   window is freshly created — reusing an existing window ignores it). */
	function buildFeatures() {
		var scr = window.screen || {};
		var sw = scr.availWidth || 1024;
		var sh = scr.availHeight || 768;
		var sx = typeof scr.availLeft === "number" ? scr.availLeft : 0;
		var sy = typeof scr.availTop === "number" ? scr.availTop : 0;
		var left = Math.round(sx + Math.max(0, (sw - WIN_W) / 2));
		var top = Math.round(sy + Math.max(0, (sh - WIN_H) / 2));
		return "popup=yes,width=" + WIN_W + ",height=" + WIN_H + ",left=" + left + ",top=" + top;
	}

	function fabButton() {
		return document.querySelector("[data-k2-jukebox-launch]");
	}

	function fabRoot() {
		return document.querySelector("#k2-jukebox-root");
	}

	var jukeboxWin = null;
	var jukeboxFocused = false;
	var deferRaiseTarget = null;
	var deferRaiseRaf = 0;

	function readPopupLive() {
		try {
			return sessionStorage.getItem(POPUP_LIVE_KEY) === "1";
		} catch (e) {
			return false;
		}
	}

	function setPopupLive(live) {
		try {
			if (live) {
				sessionStorage.setItem(POPUP_LIVE_KEY, "1");
			} else {
				sessionStorage.removeItem(POPUP_LIVE_KEY);
			}
		} catch (e2) {
			/* ignore */
		}
	}

	function cancelDeferRaise() {
		if (deferRaiseRaf) {
			cancelAnimationFrame(deferRaiseRaf);
			deferRaiseRaf = 0;
		}
		deferRaiseTarget = null;
	}

	function completeDeferRaise() {
		var w = deferRaiseTarget;
		if (!w || w.closed || !isPlayerReady(w)) {
			return false;
		}
		cancelDeferRaise();
		raise(w);
		return true;
	}

	function scheduleDeferRaise(w) {
		cancelDeferRaise();
		deferRaiseTarget = w;
		/* Keep the main tab focused while the popup loads — avoids a full main-window
		   repaint on first open. Raise once the player has booted. */
		try {
			window.focus();
		} catch (eKeep) {
			/* ignore */
		}
		var attempts = 0;
		function tick() {
			deferRaiseRaf = 0;
			if (completeDeferRaise()) {
				return;
			}
			if (!deferRaiseTarget || deferRaiseTarget.closed) {
				cancelDeferRaise();
				return;
			}
			if (++attempts > 360) {
				var fallback = deferRaiseTarget;
				cancelDeferRaise();
				raise(fallback);
				return;
			}
			deferRaiseRaf = requestAnimationFrame(tick);
		}
		deferRaiseRaf = requestAnimationFrame(tick);
	}

	function raise(w) {
		try {
			w.focus();
		} catch (e) {
			/* ignore */
		}
		jukeboxFocused = true;
	}

	/* Send the popup behind the main window: blur it, then pull our own window
	   forward. (There is no standard "lower window" API; this is the portable way.) */
	function sendBehind(w) {
		try {
			w.blur();
		} catch (e) {
			/* ignore */
		}
		try {
			window.focus();
		} catch (e2) {
			/* ignore */
		}
		jukeboxFocused = false;
	}

	function isPlayerReady(w) {
		if (!w || w.closed) {
			return false;
		}
		try {
			return !!w.__k2JukeboxReady;
		} catch (e) {
			return false;
		}
	}

	/* Reuse the named popup without window features — passing features on an existing
	   named window makes some browsers spawn a second popup instead of reusing it. */
	function reacquireNamedWindow() {
		var w = jukeboxWin;
		if (w && !w.closed) {
			return w;
		}
		try {
			w = window.open("", WIN_NAME);
		} catch (e) {
			w = null;
		}
		if (w) {
			jukeboxWin = w;
		}
		return w;
	}

	function toggleOrRaise(w, frontHint) {
		/* Use the state captured at pointerdown: pressing the FAB focuses the main
		   window, which blurs the popup; that blur message would otherwise flip
		   jukeboxFocused to false before this click runs, making us always raise. */
		var isFront = typeof frontHint === "boolean" ? frontHint : jukeboxFocused;
		if (isFront) {
			sendBehind(w);
		} else {
			raise(w);
		}
	}

	function openFreshPlayerWindow() {
		var w;
		try {
			/* about:blank + synchronous dark boot doc — avoids a white frame while jukebox.php
			   is still on the wire (window.open(url) leaves the popup white until first byte). */
			w = window.open("about:blank", WIN_NAME, buildFeatures());
		} catch (e) {
			w = null;
		}
		if (!w) {
			try {
				window.open(JUKEBOX_URL, WIN_NAME);
			} catch (e2) {
				/* ignore */
			}
			return;
		}
		try {
			w.document.open();
			w.document.write(
				"<!DOCTYPE html><html lang=\"en\" style=\"background:" +
					POPUP_BOOT_BG +
					";color-scheme:dark\">" +
					"<head><meta charset=\"utf-8\"><meta name=\"color-scheme\" content=\"dark\">" +
					"<style>html,body{background:" +
					POPUP_BOOT_BG +
					";margin:0;height:100%}</style></head><body></body></html>"
			);
			w.document.close();
		} catch (eBoot) {
			/* ignore — navigation below still runs */
		}
		jukeboxWin = w;
		setPopupLive(true);
		try {
			w.location.replace(JUKEBOX_URL);
		} catch (eNav) {
			try {
				w.location.href = JUKEBOX_URL;
			} catch (eNav2) {
				/* ignore */
			}
		}
		scheduleDeferRaise(w);
	}

	function openJukebox(frontHint) {
		var w = jukeboxWin && !jukeboxWin.closed ? jukeboxWin : null;

		/* After a main-tab navigation we lose the in-memory handle; re-acquire only when
		   a live player is expected — never on first open (that spawned a throwaway blank
		   window, closed it, then created a second centred popup). */
		if (!w && readPopupLive()) {
			w = reacquireNamedWindow();
		}

		if (w && !w.closed && isPlayerReady(w)) {
			jukeboxWin = w;
			toggleOrRaise(w, frontHint);
			return;
		}

		if (w && !w.closed && !isPlayerReady(w)) {
			try {
				w.close();
			} catch (e4) {
				/* ignore */
			}
			jukeboxWin = null;
			setPopupLive(false);
		}

		openFreshPlayerWindow();
	}

	function applyState(playing, title) {
		var root = fabRoot();
		var btn = fabButton();
		if (root) {
			root.classList.toggle("is-playing", !!playing);
		}
		if (btn) {
			btn.setAttribute("aria-pressed", playing ? "true" : "false");
			btn.setAttribute(
				"data-k2-help",
				playing && title ? "Playing: " + title : "Open Amiga jukebox"
			);
			btn.removeAttribute("title");
		}
	}

	function clearAutoAdvanceGlow(root, btn) {
		if (!root) {
			return;
		}
		root.classList.remove("is-track-change");
		if (root.__k2TrackGlowTimer) {
			clearTimeout(root.__k2TrackGlowTimer);
			root.__k2TrackGlowTimer = null;
		}
		if (btn && root.__k2TrackGlowAnimEnd) {
			btn.removeEventListener("animationend", root.__k2TrackGlowAnimEnd);
			root.__k2TrackGlowAnimEnd = null;
		}
	}

	function triggerAutoAdvanceGlow() {
		var root = fabRoot();
		var btn = fabButton();
		if (!root) {
			return;
		}
		clearAutoAdvanceGlow(root, btn);
		void root.offsetWidth;
		root.classList.add("is-track-change");
		if (btn) {
			root.__k2TrackGlowAnimEnd = function (ev) {
				if (ev.animationName === "k2-jukebox-track-glow") {
					clearAutoAdvanceGlow(root, btn);
				}
			};
			btn.addEventListener("animationend", root.__k2TrackGlowAnimEnd);
		}
		root.__k2TrackGlowTimer = setTimeout(function () {
			clearAutoAdvanceGlow(root, btn);
		}, 3000);
	}

	function bindFab() {
		var btn = fabButton();
		if (!btn || btn.__k2JukeboxBound) {
			return;
		}
		btn.__k2JukeboxBound = true;
		/* Warm jukebox.php in the HTTP cache so the boot-screen → player hop is faster. */
		btn.addEventListener("pointerenter", function () {
			if (btn.__k2JukeboxPrefetched) {
				return;
			}
			btn.__k2JukeboxPrefetched = true;
			try {
				var link = document.createElement("link");
				link.rel = "prefetch";
				link.href = JUKEBOX_URL;
				link.as = "document";
				document.head.appendChild(link);
			} catch (ePre) {
				/* ignore */
			}
		}, { once: true });
		/* Snapshot the popup's front/behind state the instant the press starts, before
		   the resulting focus shift can blur it and corrupt jukeboxFocused. */
		var frontAtPress = null;
		btn.addEventListener("pointerdown", function () {
			frontAtPress = jukeboxFocused;
		});
		btn.addEventListener("click", function (ev) {
			ev.preventDefault();
			var hint = frontAtPress;
			frontAtPress = null;
			openJukebox(hint);
		});
		/* Ask an already-open player for its state so the FAB reflects playback
		   right after a full-page navigation (BroadcastChannel has no last value). */
		if (channel) {
			try {
				channel.postMessage({ type: "ping" });
			} catch (e) {
				/* ignore */
			}
		}
	}

	var channel = null;
	try {
		channel = new BroadcastChannel(CHANNEL_NAME);
	} catch (e) {
		channel = null;
	}
	if (channel) {
		channel.addEventListener("message", function (ev) {
			var data = ev.data || {};
			if (data.type === "state") {
				applyState(data.playing, data.title);
			} else if (data.type === "track-change" && data.reason === "auto-advance") {
				triggerAutoAdvanceGlow();
			} else if (data.type === "ready") {
				setPopupLive(true);
				completeDeferRaise();
			} else if (data.type === "closed") {
				cancelDeferRaise();
				setPopupLive(false);
				applyState(false, "");
				jukeboxWin = null;
				jukeboxFocused = false;
			} else if (data.type === "focus") {
				jukeboxFocused = true;
			} else if (data.type === "blur") {
				jukeboxFocused = false;
			}
		});
	}

	(window.k2OnPageReady || function (fn) {
		if (document.readyState === "loading") {
			document.addEventListener("DOMContentLoaded", fn);
		} else {
			fn();
		}
	})(bindFab);
})();