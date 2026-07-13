(function () {
  "use strict";

  function embedUrl(youtubeId, startSec, autoplay) {
    var id = encodeURIComponent(youtubeId || "");
    var url = "https://www.youtube-nocookie.com/embed/" + id;
    var params = ["origin=" + encodeURIComponent(window.location.origin || "")];
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

  function parseStartSec(raw) {
    var startSec = parseInt(raw || "0", 10);
    if (Number.isNaN(startSec) || startSec < 0) {
      return 0;
    }
    return startSec;
  }

  function mountEmbed(root, youtubeId, startSec, autoplay) {
    var box = root.querySelector(".k2-game-page__video");
    if (!box || !youtubeId) {
      return;
    }
    box.innerHTML = "";
    var frame = document.createElement("iframe");
    frame.className = "k2-game-page__video-iframe k2-tournament-videos__spotlight-iframe";
    frame.src = embedUrl(youtubeId, startSec, autoplay);
    frame.title = root.getAttribute("data-k2-game-video-title") || "Game video";
    frame.setAttribute(
      "allow",
      "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
    );
    frame.setAttribute("referrerpolicy", "strict-origin-when-cross-origin");
    frame.setAttribute("allowfullscreen", "");
    box.appendChild(frame);
  }

  function setActiveLink(menu, link) {
    menu.querySelectorAll("[data-k2-game-video]").forEach(function (el) {
      var on = el === link;
      el.classList.toggle("k2-link-star", on);
      el.classList.toggle("k2-amiga-game-videos__menu-link--muted", !on);
      if (on) {
        el.setAttribute("aria-current", "true");
      } else {
        el.removeAttribute("aria-current");
      }
    });
  }

  function init(root) {
    var menu = root.querySelector(".k2-amiga-game-videos__menu");
    if (!menu) {
      return;
    }
    menu.addEventListener("click", function (event) {
      var link = event.target.closest("[data-k2-game-video]");
      if (!link || link.tagName !== "A") {
        return;
      }
      event.preventDefault();
      var youtubeId = link.getAttribute("data-k2-game-video") || "";
      if (!youtubeId) {
        return;
      }
      var startSec = parseStartSec(link.getAttribute("data-start-sec"));
      setActiveLink(menu, link);
      mountEmbed(root, youtubeId, startSec, true);
      var href = link.getAttribute("href");
      if (href) {
        history.replaceState(null, "", href);
      }
    });
  }

  window.k2OnPageReady(function () {
    document.querySelectorAll(".k2-amiga-game-videos").forEach(init);
  });
})();