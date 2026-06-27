(function () {
  var PLAYER_HASH = "k2-tournament-video-player";
  // Remembers the clip the visitor most recently opened this session, so that
  // after Back → index we can re-highlight that row (locate the next leg fast).
  var lastWatchedState = null;

  function currentPathSearch() {
    return window.location.pathname + window.location.search;
  }

  function embedUrl(youtubeId, startSec, autoplay) {
    var id = encodeURIComponent(youtubeId || "");
    var url = "https://www.youtube-nocookie.com/embed/" + id;
    var params = [];
    if (startSec > 0) {
      params.push("start=" + String(startSec));
    }
    if (autoplay) {
      params.push("autoplay=1");
    }
    if (params.length) {
      url += "?" + params.join("&");
    }
    return url;
  }

  function parseUrl(href) {
    var u = new URL(href, window.location.origin);
    return {
      v: u.searchParams.get("v") || "",
      game: u.searchParams.get("game") || "",
      t: u.searchParams.get("t") || "",
      hasVideo: u.searchParams.has("v") && u.searchParams.get("v") !== "",
    };
  }

  function buildIndexUrl(root) {
    var indexUrl = root.getAttribute("data-k2-tv-index-url") || window.location.pathname;
    var u = new URL(indexUrl, window.location.origin);
    u.searchParams.delete("v");
    u.searchParams.delete("game");
    u.searchParams.delete("t");
    u.hash = "";
    return u.pathname + u.search;
  }

  function buildVideoUrl(root, youtubeId, gameId, startSec, withHash) {
    var indexUrl = root.getAttribute("data-k2-tv-index-url") || window.location.pathname;
    var u = new URL(indexUrl, window.location.origin);
    u.searchParams.set("v", youtubeId);
    if (gameId) {
      u.searchParams.set("game", String(gameId));
    } else {
      u.searchParams.delete("game");
    }
    if (startSec > 0) {
      u.searchParams.set("t", String(startSec));
    } else {
      u.searchParams.delete("t");
    }
    u.hash = withHash ? PLAYER_HASH : "";
    return u.pathname + u.search + u.hash;
  }

  function scrollToPlayer(root, behavior) {
    var player = document.getElementById(PLAYER_HASH);
    if (player && !player.hidden) {
      player.scrollIntoView({ behavior: behavior || "smooth", block: "start" });
      return;
    }
    scrollToTable(root);
  }

  function scrollToTable(root) {
    var selector = root.getAttribute("data-k2-tv-table") || ".k2-table--tournament-videos-games";
    var table = root.querySelector(selector);
    if (table) {
      table.scrollIntoView({ behavior: "smooth", block: "start" });
    }
  }

  function clearActive(root) {
    root.querySelectorAll(".k2-tournament-videos__play-btn.is-active").forEach(function (el) {
      el.classList.remove("is-active");
    });
    root.querySelectorAll("tr.is-active").forEach(function (tr) {
      tr.classList.remove("is-active");
    });
  }

  function setActive(link) {
    var root = link.closest(".k2-tournament-videos--wc");
    if (!root) {
      return;
    }
    clearActive(root);
    link.classList.add("is-active");
    var row = link.closest("tr");
    if (row) {
      row.classList.add("is-active");
    }
  }

  function spotlightEl(root) {
    return root.querySelector(".k2-tournament-videos__spotlight");
  }

  function videoMount(root) {
    return root.querySelector(".k2-game-page__video");
  }

  // Mount the embed by REPLACING the iframe node — never by reassigning .src.
  // A YouTube iframe pushes an entry onto the shared session history on every
  // src navigation, which hijacks the browser Back button (Back steps inside the
  // iframe with no URL change / no popstate). A freshly created iframe's first
  // load replaces its own initial blank entry, so node replacement adds nothing.
  function mountEmbed(root, src, title) {
    var mount = videoMount(root);
    if (!mount) {
      return;
    }
    var old = mount.querySelector("iframe");
    var frame = document.createElement("iframe");
    frame.className = "k2-game-page__video-iframe k2-tournament-videos__spotlight-iframe";
    frame.setAttribute(
      "allow",
      "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
    );
    frame.setAttribute("referrerpolicy", "strict-origin-when-cross-origin");
    frame.setAttribute("allowfullscreen", "");
    frame.title = title || "Tournament video";
    frame.src = src;
    if (old && old.parentNode) {
      old.parentNode.removeChild(old);
    }
    mount.appendChild(frame);
  }

  function unmountEmbed(root) {
    var mount = videoMount(root);
    if (!mount) {
      return;
    }
    var old = mount.querySelector("iframe");
    if (old && old.parentNode) {
      old.parentNode.removeChild(old);
    }
  }

  function showSpotlightBox(root) {
    var box = spotlightEl(root);
    if (box) {
      box.hidden = false;
      box.removeAttribute("hidden");
      box.classList.remove("k2-tournament-videos__spotlight--empty");
    }
  }

  // Hide the player without touching row highlight (the index keeps the
  // last-watched row marked — see highlightLastWatched).
  function hidePlayer(root) {
    var box = spotlightEl(root);
    var labelEl = root.querySelector(".k2-tournament-videos__spotlight-label");
    if (box) {
      box.hidden = true;
      box.setAttribute("hidden", "hidden");
      box.classList.add("k2-tournament-videos__spotlight--empty");
    }
    unmountEmbed(root);
    if (labelEl) {
      labelEl.textContent = "";
    }
  }

  // Mark the row of the most recently watched clip (if any) without showing the
  // player. Returns the row element so callers can scroll it into view.
  function highlightLastWatched(root) {
    clearActive(root);
    if (!lastWatchedState || !lastWatchedState.v) {
      return null;
    }
    var link = findLinkForState(root, lastWatchedState);
    if (!link) {
      return null;
    }
    link.classList.add("is-active");
    var row = link.closest("tr");
    if (row) {
      row.classList.add("is-active");
    }
    return row || link;
  }

  function applyFromLink(root, link, startSecOverride, autoplay) {
    if (!link) {
      return;
    }
    var labelEl = root.querySelector(".k2-tournament-videos__spotlight-label");
    var yt = link.getAttribute("data-youtube-id") || "";
    if (!yt) {
      return;
    }
    var startSec =
      startSecOverride !== undefined && startSecOverride !== null
        ? startSecOverride
        : parseInt(link.getAttribute("data-start-sec") || "0", 10);
    if (Number.isNaN(startSec) || startSec < 0) {
      startSec = 0;
    }
    var aria = link.getAttribute("aria-label") || "YouTube video";
    var title = aria.replace(/^Play video:\s*/i, "");
    showSpotlightBox(root);
    mountEmbed(root, embedUrl(yt, startSec, autoplay), title);
    if (labelEl) {
      labelEl.textContent = link.getAttribute("data-spotlight-label") || title;
    }
    setActive(link);
    lastWatchedState = { v: yt, game: link.getAttribute("data-game-id") || "" };
  }

  function findLinkForState(root, state) {
    var links = root.querySelectorAll(".k2-tournament-videos__play-btn");
    var i;
    var link;
    var gameId;

    if (state.game) {
      for (i = 0; i < links.length; i++) {
        link = links[i];
        if ((link.getAttribute("data-youtube-id") || "") !== state.v) {
          continue;
        }
        gameId = link.getAttribute("data-game-id") || "";
        if (gameId === state.game) {
          return link;
        }
      }
    }

    for (i = 0; i < links.length; i++) {
      if ((links[i].getAttribute("data-youtube-id") || "") === state.v) {
        return links[i];
      }
    }
    return null;
  }

  function parseStartSec(raw) {
    var startSec = parseInt(raw || "0", 10);
    if (Number.isNaN(startSec) || startSec < 0) {
      return 0;
    }
    return startSec;
  }

  function clearPendingHashScroll() {
    try {
      sessionStorage.removeItem("k2:pendingHashScroll");
    } catch (e) {
      /* ignore */
    }
  }

  function renderIndex(root, doScroll) {
    hidePlayer(root);
    clearPendingHashScroll();
    var row = highlightLastWatched(root);
    if (doScroll) {
      if (row) {
        row.scrollIntoView({ behavior: "smooth", block: "center" });
      } else {
        scrollToTable(root);
      }
    }
  }

  function renderWatch(root, parsed, doScroll) {
    var link = findLinkForState(root, parsed);
    if (link) {
      applyFromLink(root, link, parsed.t ? parseStartSec(parsed.t) : null);
      if (doScroll) {
        scrollToPlayer(root);
      }
      return;
    }

    // Ambiguous or off-page v= : show the embed without highlighting a row (§1.4).
    var labelEl = root.querySelector(".k2-tournament-videos__spotlight-label");
    showSpotlightBox(root);
    mountEmbed(root, embedUrl(parsed.v, parseStartSec(parsed.t)), "Video");
    if (labelEl) {
      labelEl.textContent = "Video";
    }
    lastWatchedState = { v: parsed.v, game: parsed.game };
    clearActive(root);
    if (doScroll) {
      scrollToPlayer(root);
    }
  }

  // Single source of truth: render whatever the current URL says.
  function renderFromUrl(root, doScroll) {
    var parsed = parseUrl(window.location.href);
    if (!parsed.hasVideo) {
      renderIndex(root, doScroll);
    } else {
      renderWatch(root, parsed, doScroll);
    }
  }

  function onPopstate(root) {
    if (!root.isConnected) {
      return;
    }
    renderFromUrl(root, true);
  }

  function onAllVideos(root) {
    // Distinct from the browser Back button (popstate → renderIndex, which
    // re-centres the just-watched row). "All videos" returns the visitor to the
    // normal cold-landing context: the index with no centred highlight, pinning
    // the tournament hero to the top of the viewport.
    if (parseUrl(window.location.href).hasVideo) {
      history.pushState(null, "", buildIndexUrl(root));
    }
    hidePlayer(root);
    clearActive(root);
    var hero = document.querySelector(".k2-amiga-tournament-hero");
    if (hero) {
      hero.scrollIntoView({ behavior: "smooth", block: "start" });
    } else {
      window.scrollTo({ top: 0, behavior: "smooth" });
    }
  }

  function onPlayClick(root, link) {
    var youtubeId = link.getAttribute("data-youtube-id") || "";
    if (!youtubeId) {
      return;
    }
    clearPendingHashScroll();
    var gameId = link.getAttribute("data-game-id") || "";
    // Hashless URL: the server detects ?v= on full loads and scrolls to the
    // player via carry-scroll, so the address bar stays clean and shareable.
    var nextUrl = buildVideoUrl(root, youtubeId, gameId, 0, false);
    // Cap the stack at [index, clip]: the first pick from the index pushes one
    // entry; switching clips while already watching replaces it. Back therefore
    // always returns to the index, never to an earlier clip.
    if (currentPathSearch() !== nextUrl) {
      if (parseUrl(window.location.href).hasVideo) {
        history.replaceState(null, "", nextUrl);
      } else {
        history.pushState(null, "", nextUrl);
      }
    }
    applyFromLink(root, link, null, true);
    scrollToPlayer(root);
  }

  function setBackHref(root) {
    var back = root.querySelector("[data-k2-tv-back]");
    if (back) {
      back.setAttribute("href", buildIndexUrl(root));
    }
  }

  // Cold load: the server already painted the correct first view. Sync the active
  // row, point the "All videos" link at the index, seed an index history entry
  // beneath the clip (so Back returns to the list), and scroll to the player when
  // the shared URL had no #hash.
  function syncColdLoad(root) {
    setBackHref(root);
    var parsed = parseUrl(window.location.href);
    if (!parsed.hasVideo) {
      hidePlayer(root);
      clearActive(root);
      return;
    }
    var clipPathSearch = currentPathSearch();
    // Seed an index entry beneath this clip so Back / "All videos" returns to the
    // list even on a cold shared link (otherwise Back would leave the site).
    // (The pre-paint scroll to the player is owned by carry-scroll via the
    // server-declared target — see k2_carry_scroll_restore.php.)
    history.replaceState(null, "", buildIndexUrl(root));
    history.pushState(null, "", clipPathSearch);
    showSpotlightBox(root);
    lastWatchedState = { v: parsed.v, game: parsed.game };
    var link = findLinkForState(root, parsed);
    if (link) {
      setActive(link);
    } else {
      clearActive(root);
    }
  }

  function init(root) {
    if (root.dataset.k2TvInit === "1") {
      return;
    }
    root.dataset.k2TvInit = "1";

    syncColdLoad(root);

    root.addEventListener("click", function (event) {
      var back = event.target.closest("[data-k2-tv-back]");
      if (back && root.contains(back)) {
        event.preventDefault();
        onAllVideos(root);
        return;
      }
      var link = event.target.closest(".k2-tournament-videos__play-btn");
      if (!link || !root.contains(link)) {
        return;
      }
      event.preventDefault();
      onPlayClick(root, link);
    });
  }

  function boot() {
    document.querySelectorAll(".k2-tournament-videos--wc").forEach(init);
  }

  if (!window.__k2TvPopstateBound) {
    window.__k2TvPopstateBound = true;
    window.addEventListener("popstate", function () {
      document.querySelectorAll(".k2-tournament-videos--wc").forEach(onPopstate);
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
  document.addEventListener("k2:page-ready", boot);
})();
