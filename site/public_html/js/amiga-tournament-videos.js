(function () {
  function init(root) {
    if (root.dataset.k2TvInit === "1") {
      return;
    }
    root.dataset.k2TvInit = "1";

    var iframe = root.querySelector(".k2-tournament-videos__spotlight-iframe");
    var label = root.querySelector(".k2-tournament-videos__spotlight-label");
    if (!iframe) {
      return;
    }

    root.querySelectorAll(".k2-tournament-videos__play-btn").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var yt = btn.getAttribute("data-youtube-id") || "";
        if (!yt) {
          return;
        }
        iframe.src =
          "https://www.youtube-nocookie.com/embed/" +
          encodeURIComponent(yt);
        var aria = btn.getAttribute("aria-label") || "YouTube video";
        iframe.title = aria.replace(/^Play video:\s*/i, "");
        if (label) {
          label.textContent = btn.getAttribute("data-spotlight-label") || iframe.title;
        }
        root.querySelectorAll(".k2-tournament-videos__play-btn.is-active").forEach(function (el) {
          el.classList.remove("is-active");
        });
        root.querySelectorAll("tr.is-active").forEach(function (tr) {
          tr.classList.remove("is-active");
        });
        btn.classList.add("is-active");
        var row = btn.closest("tr");
        if (row) {
          row.classList.add("is-active");
        }
        var player = document.getElementById("k2-tournament-video-player");
        if (player) {
          player.scrollIntoView({ behavior: "smooth", block: "start" });
        }
      });
    });
  }

  function boot() {
    document.querySelectorAll(".k2-tournament-videos--wc").forEach(init);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
  document.addEventListener("k2:page-ready", boot);
})();