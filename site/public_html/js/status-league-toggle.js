/**
 * Status league period switch — in-place, no navigation (status.php).
 */
(function () {
    'use strict';

    function pluralRatedGames(n) {
        return n === 1 ? 'rated game' : 'rated games';
    }

    function formatRemaining(seconds) {
        seconds = Math.max(0, Math.floor(seconds));
        if (seconds <= 0) {
            return 'ended';
        }

        var days = Math.floor(seconds / 86400);
        var hours = Math.floor((seconds % 86400) / 3600);
        var minutes = Math.floor((seconds % 3600) / 60);
        if (days > 0) {
            return days + 'd ' + hours + 'h left';
        }
        if (hours > 0) {
            return hours + 'h ' + minutes + 'm left';
        }

        return Math.max(1, minutes) + 'm left';
    }

    function serverNowEpoch(root) {
        var initialServer = parseInt(root.getAttribute('data-server-now-epoch'), 10);
        var loadedAt = parseInt(root.getAttribute('data-browser-loaded-epoch'), 10);
        if (!initialServer || !loadedAt) {
            return Math.floor(Date.now() / 1000);
        }

        return initialServer + Math.floor(Date.now() / 1000) - loadedAt;
    }

    function metaText(root, panel) {
        var fallback = panel.getAttribute('data-league-meta-text') || '';
        var label = panel.getAttribute('data-league-period-label') || '';
        var total = parseInt(panel.getAttribute('data-league-total-games'), 10);
        var endLabel = panel.getAttribute('data-league-end-label') || '';
        var endEpoch = parseInt(panel.getAttribute('data-league-end-epoch'), 10);
        if (!label || isNaN(total) || !endLabel || !endEpoch) {
            return fallback;
        }

        var now = serverNowEpoch(root);
        var isLive = endEpoch > now;
        var text = label + ' · ' + total + ' ' + pluralRatedGames(total)
            + ' · ' + (isLive ? 'ends ' : 'ended ') + endLabel + ' server time';
        if (isLive) {
            text += ' · ' + formatRemaining(endEpoch - now);
        }

        return text;
    }

    function show(root, target) {
        var panels = root.querySelectorAll('[data-league-panel]');
        var buttons = root.querySelectorAll('[data-league-target]');
        var meta = root.querySelector('[data-league-meta]');
        var i;
        var activePanel = null;
        for (i = 0; i < panels.length; i++) {
            var panel = panels[i];
            var on = panel.getAttribute('data-league-panel') === target;
            panel.hidden = !on;
            if (on) {
                activePanel = panel;
            }
        }
        for (i = 0; i < buttons.length; i++) {
            var btn = buttons[i];
            var active = btn.getAttribute('data-league-target') === target;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        }
        if (meta && activePanel) {
            meta.textContent = metaText(root, activePanel);
        }
    }

    function activeTarget(root) {
        var active = root.querySelector('[data-league-target].is-active');
        return active ? active.getAttribute('data-league-target') : 'current';
    }

    function initRoot(root) {
        if (!root.getAttribute('data-browser-loaded-epoch')) {
            root.setAttribute('data-browser-loaded-epoch', String(Math.floor(Date.now() / 1000)));
        }
        var buttons = root.querySelectorAll('[data-league-target]');
        for (var b = 0; b < buttons.length; b++) {
            buttons[b].addEventListener('click', function (ev) {
                ev.preventDefault();
                show(root, this.getAttribute('data-league-target'));
            });
        }
        show(root, activeTarget(root));
    }

    function refreshCountdowns() {
        var roots = document.querySelectorAll('[data-k2-status-league]');
        for (var i = 0; i < roots.length; i++) {
            show(roots[i], activeTarget(roots[i]));
        }
    }

    function init() {
        var roots = document.querySelectorAll('[data-k2-status-league]');
        for (var i = 0; i < roots.length; i++) {
            initRoot(roots[i]);
        }
        if (roots.length) {
            window.setInterval(refreshCountdowns, 30000);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
