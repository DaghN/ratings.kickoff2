/**
 * Site jukebox — opt-in HTML5 player; persists playback across page loads.
 */
(function () {
	"use strict";

	var ROOT_SEL = "[data-k2-jukebox]";
	var STORAGE_PREFIX = "k2-jukebox-";
	var PLAYLIST_URL = "/audio/amiga/playlist.json";

	var root = document.querySelector(ROOT_SEL);
	if (!root) {
		return;
	}

	var audio = root.querySelector(".k2-jukebox__audio");
	var panel = root.querySelector(".k2-jukebox__panel");
	var toggleBtn = root.querySelector(".k2-jukebox__toggle");
	var hideBtn = root.querySelector(".k2-jukebox__hide");
	var trackList = root.querySelector(".k2-jukebox__tracks");
	var nowTitle = root.querySelector(".k2-jukebox__now-title");
	var nowGame = root.querySelector(".k2-jukebox__now-game");
	var playBtn = root.querySelector(".k2-jukebox__play");
	var prevBtn = root.querySelector(".k2-jukebox__prev");
	var nextBtn = root.querySelector(".k2-jukebox__next");
	var shuffleBtn = root.querySelector(".k2-jukebox__shuffle");
	var volumeInput = root.querySelector(".k2-jukebox__volume");
	var progressBar = root.querySelector(".k2-jukebox__progress");
	var progressFill = root.querySelector(".k2-jukebox__progress-fill");
	var timeCurrent = root.querySelector(".k2-jukebox__time-current");
	var timeTotal = root.querySelector(".k2-jukebox__time-total");
	var vuBars = root.querySelectorAll(".k2-jukebox__vu-bar");

	var tracks = [];
	var currentIndex = -1;
	var shuffleOn = false;
	var shuffleOrder = [];
	var shufflePos = 0;
	var panelOpen = false;
	var lastTimeSaveMs = 0;

	function readStorage(key, fallback) {
		try {
			var v = localStorage.getItem(STORAGE_PREFIX + key);
			return v === null ? fallback : v;
		} catch (e) {
			return fallback;
		}
	}

	function writeStorage(key, value) {
		try {
			localStorage.setItem(STORAGE_PREFIX + key, value);
		} catch (e) {
			/* ignore */
		}
	}

	function wasPlayingStored() {
		return readStorage("playing", "0") === "1";
	}

	function savePlaybackState() {
		if (!audio) {
			return;
		}
		writeStorage("playing", audio.paused ? "0" : "1");
		if (isFinite(audio.currentTime)) {
			writeStorage("current-time", String(audio.currentTime));
		}
	}

	function attemptPlay() {
		if (!audio) {
			return;
		}
		var playPromise = audio.play();
		if (playPromise && playPromise.catch) {
			playPromise.catch(function () {
				setPlayingUi(false);
				writeStorage("playing", "0");
			});
		}
	}

	function formatTime(sec) {
		if (!isFinite(sec) || sec < 0) {
			return "0:00";
		}
		var m = Math.floor(sec / 60);
		var s = Math.floor(sec % 60);
		return m + ":" + (s < 10 ? "0" : "") + s;
	}

	function setPlayingUi(playing) {
		root.classList.toggle("is-playing", playing);
		toggleBtn.setAttribute("aria-pressed", playing ? "true" : "false");
		if (playBtn) {
			playBtn.setAttribute("aria-label", playing ? "Pause" : "Play");
			playBtn.setAttribute("aria-pressed", playing ? "true" : "false");
		}
	}

	function setPanelOpen(open) {
		panelOpen = open;
		root.classList.toggle("is-open", open);
		toggleBtn.setAttribute("aria-expanded", open ? "true" : "false");
		if (panel) {
			if (open) {
				panel.removeAttribute("hidden");
			} else {
				panel.setAttribute("hidden", "hidden");
			}
		}
		writeStorage("panel-open", open ? "1" : "0");
	}

	function buildShuffleOrder() {
		shuffleOrder = [];
		for (var i = 0; i < tracks.length; i++) {
			shuffleOrder.push(i);
		}
		for (var j = shuffleOrder.length - 1; j > 0; j--) {
			var k = Math.floor(Math.random() * (j + 1));
			var tmp = shuffleOrder[j];
			shuffleOrder[j] = shuffleOrder[k];
			shuffleOrder[k] = tmp;
		}
		shufflePos = 0;
		if (currentIndex >= 0) {
			for (var p = 0; p < shuffleOrder.length; p++) {
				if (shuffleOrder[p] === currentIndex) {
					shufflePos = p;
					break;
				}
			}
		}
	}

	function syncShuffleUi() {
		if (!shuffleBtn) {
			return;
		}
		shuffleBtn.classList.toggle("is-active", shuffleOn);
		shuffleBtn.setAttribute("aria-pressed", shuffleOn ? "true" : "false");
		shuffleBtn.setAttribute("title", shuffleOn ? "Shuffle on" : "Shuffle off");
		shuffleBtn.setAttribute("aria-label", shuffleOn ? "Shuffle on — click to play in order" : "Shuffle off — click to shuffle playback");
	}

	function highlightTrack(index) {
		if (!trackList) {
			return;
		}
		var rows = trackList.querySelectorAll(".k2-jukebox__track");
		for (var i = 0; i < rows.length; i++) {
			var on = i === index;
			rows[i].classList.toggle("is-active", on);
			if (on) {
				rows[i].setAttribute("aria-current", "true");
			} else {
				rows[i].removeAttribute("aria-current");
			}
		}
		var active = rows[index];
		if (active && panelOpen) {
			active.scrollIntoView({ block: "nearest", behavior: "smooth" });
		}
	}

	function updateNowPlaying(track) {
		if (!track) {
			if (nowTitle) {
				nowTitle.textContent = "Choose a track";
			}
			if (nowGame) {
				nowGame.textContent = "Amiga classics";
			}
			return;
		}
		if (nowTitle) {
			nowTitle.textContent = track.title;
		}
		if (nowGame) {
			nowGame.textContent = track.game;
		}
	}

	function resolveIndex(index) {
		if (index < 0 || index >= tracks.length) {
			return -1;
		}
		return index;
	}

	function nextIndex(delta) {
		if (!tracks.length) {
			return -1;
		}
		if (shuffleOn) {
			if (!shuffleOrder.length) {
				buildShuffleOrder();
			}
			shufflePos += delta;
			if (shufflePos >= shuffleOrder.length) {
				shufflePos = 0;
			}
			if (shufflePos < 0) {
				shufflePos = shuffleOrder.length - 1;
			}
			return shuffleOrder[shufflePos];
		}
		var idx = currentIndex < 0 ? 0 : currentIndex + delta;
		if (idx >= tracks.length) {
			idx = 0;
		}
		if (idx < 0) {
			idx = tracks.length - 1;
		}
		return idx;
	}

	function loadTrack(index, autoplay, seekTime) {
		index = resolveIndex(index);
		if (index < 0 || !audio) {
			return;
		}
		var track = tracks[index];
		var url = "/audio/amiga/" + track.file;
		currentIndex = index;
		writeStorage("track-index", String(index));
		writeStorage("track-id", track.id);
		if (seekTime == null) {
			writeStorage("current-time", "0");
		}
		highlightTrack(index);
		updateNowPlaying(track);

		function applySeekAndPlay() {
			if (seekTime != null && isFinite(seekTime) && seekTime > 0) {
				try {
					var maxTime = isFinite(audio.duration) ? audio.duration : seekTime;
					audio.currentTime = Math.min(seekTime, maxTime);
				} catch (e) {
					/* ignore seek errors */
				}
			}
			updateProgress();
			if (autoplay) {
				attemptPlay();
			} else {
				savePlaybackState();
			}
		}

		audio.src = url;
		audio.load();
		audio.addEventListener("loadedmetadata", applySeekAndPlay, { once: true });
	}

	function togglePlay() {
		if (!audio) {
			return;
		}
		if (currentIndex < 0 && tracks.length) {
			loadTrack(0, true);
			return;
		}
		if (audio.paused) {
			attemptPlay();
		} else {
			audio.pause();
		}
	}

	function renderTrackList() {
		if (!trackList) {
			return;
		}
		trackList.innerHTML = "";
		for (var i = 0; i < tracks.length; i++) {
			(function (idx) {
				var t = tracks[idx];
				var li = document.createElement("li");
				li.className = "k2-jukebox__track";
				li.setAttribute("role", "option");
				li.setAttribute("tabindex", "-1");
				li.setAttribute("aria-current", "false");
				li.innerHTML =
					'<span class="k2-jukebox__track-index">' + String(idx + 1).padStart(2, "0") + "</span>" +
					'<span class="k2-jukebox__track-meta">' +
					'<span class="k2-jukebox__track-title">' + escapeHtml(t.title) + "</span>" +
					'<span class="k2-jukebox__track-game">' + escapeHtml(t.game) + "</span>" +
					"</span>" +
					'<span class="k2-jukebox__track-play" aria-hidden="true"></span>';
				li.addEventListener("click", function () {
					loadTrack(idx, true);
				});
				li.addEventListener("keydown", function (ev) {
					if (ev.key === "Enter" || ev.key === " ") {
						ev.preventDefault();
						loadTrack(idx, true);
					}
				});
				trackList.appendChild(li);
			})(i);
		}
	}

	function escapeHtml(str) {
		return String(str)
			.replace(/&/g, "&amp;")
			.replace(/</g, "&lt;")
			.replace(/>/g, "&gt;")
			.replace(/"/g, "&quot;");
	}

	function updateProgress() {
		if (!audio || !progressFill) {
			return;
		}
		var dur = audio.duration;
		var cur = audio.currentTime;
		if (timeCurrent) {
			timeCurrent.textContent = formatTime(cur);
		}
		if (timeTotal) {
			timeTotal.textContent = formatTime(dur);
		}
		var pct = dur > 0 ? (cur / dur) * 100 : 0;
		progressFill.style.width = pct + "%";
		if (progressBar) {
			progressBar.setAttribute("aria-valuenow", String(Math.round(pct)));
		}
	}

	function seekFromClientX(clientX) {
		if (!progressBar || !audio || !isFinite(audio.duration) || audio.duration <= 0) {
			return;
		}
		var rect = progressBar.getBoundingClientRect();
		var ratio = (clientX - rect.left) / rect.width;
		ratio = Math.max(0, Math.min(1, ratio));
		audio.currentTime = ratio * audio.duration;
		updateProgress();
		savePlaybackState();
	}

	function restorePreferences() {
		shuffleOn = readStorage("shuffle", "0") === "1";
		syncShuffleUi();
		if (shuffleOn) {
			buildShuffleOrder();
		}
		var vol = parseFloat(readStorage("volume", "0.72"));
		if (!isFinite(vol)) {
			vol = 0.72;
		}
		vol = Math.max(0, Math.min(1, vol));
		if (audio) {
			audio.volume = vol;
		}
		if (volumeInput) {
			volumeInput.value = String(Math.round(vol * 100));
		}
		if (readStorage("panel-open", "0") === "1") {
			setPanelOpen(true);
		}
	}

	function restoreTrackIndex() {
		var savedId = readStorage("track-id", "");
		var idx = -1;
		if (savedId) {
			for (var i = 0; i < tracks.length; i++) {
				if (tracks[i].id === savedId) {
					idx = i;
					break;
				}
			}
		}
		if (idx < 0) {
			idx = parseInt(readStorage("track-index", "0"), 10);
			if (!isFinite(idx)) {
				idx = 0;
			}
		}
		idx = resolveIndex(idx);
		if (idx >= 0) {
			var resume = wasPlayingStored();
			var seekTime = null;
			if (resume) {
				seekTime = parseFloat(readStorage("current-time", "0"));
				if (!isFinite(seekTime) || seekTime < 0) {
					seekTime = 0;
				}
			}
			loadTrack(idx, resume, resume ? seekTime : null);
		}
	}

	function bindEvents() {
		if (toggleBtn) {
			toggleBtn.addEventListener("click", function () {
				setPanelOpen(!panelOpen);
			});
		}
		if (hideBtn) {
			hideBtn.addEventListener("click", function () {
				setPanelOpen(false);
			});
		}
		if (playBtn) {
			playBtn.addEventListener("click", togglePlay);
		}
		if (prevBtn) {
			prevBtn.addEventListener("click", function () {
				loadTrack(nextIndex(-1), true);
			});
		}
		if (nextBtn) {
			nextBtn.addEventListener("click", function () {
				loadTrack(nextIndex(1), true);
			});
		}
		if (shuffleBtn) {
			shuffleBtn.addEventListener("click", function () {
				shuffleOn = !shuffleOn;
				writeStorage("shuffle", shuffleOn ? "1" : "0");
				syncShuffleUi();
				if (shuffleOn) {
					buildShuffleOrder();
				}
			});
		}
		if (volumeInput && audio) {
			volumeInput.addEventListener("input", function () {
				var v = parseInt(volumeInput.value, 10) / 100;
				audio.volume = v;
				writeStorage("volume", String(v));
			});
		}
		if (progressBar) {
			progressBar.addEventListener("click", function (ev) {
				seekFromClientX(ev.clientX);
			});
			progressBar.addEventListener("keydown", function (ev) {
				if (!audio || !isFinite(audio.duration)) {
					return;
				}
				var step = 5;
				if (ev.key === "ArrowRight") {
					ev.preventDefault();
					audio.currentTime = Math.min(audio.duration, audio.currentTime + step);
					updateProgress();
				} else if (ev.key === "ArrowLeft") {
					ev.preventDefault();
					audio.currentTime = Math.max(0, audio.currentTime - step);
					updateProgress();
				}
			});
		}
		if (audio) {
			audio.addEventListener("play", function () {
				setPlayingUi(true);
				writeStorage("playing", "1");
			});
			audio.addEventListener("pause", function () {
				setPlayingUi(false);
				writeStorage("playing", "0");
				savePlaybackState();
			});
			audio.addEventListener("timeupdate", function () {
				updateProgress();
				var now = Date.now();
				if (now - lastTimeSaveMs >= 1500) {
					lastTimeSaveMs = now;
					savePlaybackState();
				}
			});
			audio.addEventListener("loadedmetadata", updateProgress);
			audio.addEventListener("ended", function () {
				loadTrack(nextIndex(1), true);
			});
		}
		window.addEventListener("pagehide", savePlaybackState);
		window.addEventListener("pageshow", function (ev) {
			if (ev.persisted && audio && wasPlayingStored()) {
				attemptPlay();
			}
		});
		document.addEventListener("keydown", function (ev) {
			if (ev.target && (ev.target.tagName === "INPUT" || ev.target.tagName === "TEXTAREA" || ev.target.isContentEditable)) {
				return;
			}
			if (ev.key === "m" && ev.altKey) {
				ev.preventDefault();
				setPanelOpen(!panelOpen);
			}
		});
	}

	function init() {
		restorePreferences();
		bindEvents();
		fetch(PLAYLIST_URL, { credentials: "same-origin" })
			.then(function (res) {
				if (!res.ok) {
					throw new Error("playlist");
				}
				return res.json();
			})
			.then(function (data) {
				tracks = data && data.tracks ? data.tracks : [];
				renderTrackList();
				restoreTrackIndex();
			})
			.catch(function () {
				if (trackList) {
					trackList.innerHTML = '<li class="k2-jukebox__track k2-jukebox__track--empty">Playlist unavailable</li>';
				}
			});
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", init);
	} else {
		init();
	}
})();