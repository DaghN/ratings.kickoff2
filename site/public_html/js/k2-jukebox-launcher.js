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

	function openJukebox(frontHint) {
		/* Already have a live handle (consecutive clicks on the same page): toggle
		   stacking without re-opening, so a second click sends it behind. */
		if (jukeboxWin && !jukeboxWin.closed) {
			/* Use the state captured at pointerdown: pressing the FAB focuses the main
			   window, which blurs the popup; that blur message would otherwise flip
			   jukeboxFocused to false before this click runs, making us always raise. */
			var isFront = typeof frontHint === "boolean" ? frontHint : jukeboxFocused;
			if (isFront) {
				sendBehind(jukeboxWin);
			} else {
				raise(jukeboxWin);
			}
			return;
		}

		var w;
		try {
			w = window.open("", WIN_NAME, buildFeatures());
		} catch (e) {
			w = null;
		}
		if (!w) {
			/* Popup blocked — open as a normal navigable window as a fallback. */
			try {
				window.open(JUKEBOX_URL, WIN_NAME);
			} catch (e2) {
				/* ignore */
			}
			return;
		}
		jukeboxWin = w;
		var ready = false;
		try {
			ready = !!w.__k2JukeboxReady;
		} catch (e3) {
			ready = false;
		}
		if (!ready) {
			/* Freshly created (about:blank) window — load the player into it. */
			try {
				w.location.replace(JUKEBOX_URL);
			} catch (e5) {
				w.location.href = JUKEBOX_URL;
			}
		}
		/* First click (or first click after a main-tab navigation): bring it forward. */
		raise(w);
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

	function bindFab() {
		var btn = fabButton();
		if (!btn || btn.__k2JukeboxBound) {
			return;
		}
		btn.__k2JukeboxBound = true;
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
			} else if (data.type === "closed") {
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