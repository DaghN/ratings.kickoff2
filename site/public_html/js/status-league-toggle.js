/**
 * Monthly league month switch — in-place, no navigation (status.php).
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-k2-status-league]');
    if (!root) {
        return;
    }

    var panels = root.querySelectorAll('[data-league-panel]');
    var buttons = root.querySelectorAll('[data-league-target]');
    var meta = root.querySelector('[data-league-meta]');

    function show(target) {
        var i;
        for (i = 0; i < panels.length; i++) {
            var panel = panels[i];
            var on = panel.getAttribute('data-league-panel') === target;
            panel.hidden = !on;
        }
        for (i = 0; i < buttons.length; i++) {
            var btn = buttons[i];
            var active = btn.getAttribute('data-league-target') === target;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        }
        if (meta) {
            var activePanel = root.querySelector('[data-league-panel="' + target + '"]');
            if (activePanel && activePanel.getAttribute('data-league-meta-text')) {
                meta.textContent = activePanel.getAttribute('data-league-meta-text');
            }
        }
    }

    for (var b = 0; b < buttons.length; b++) {
        buttons[b].addEventListener('click', function (ev) {
            ev.preventDefault();
            show(this.getAttribute('data-league-target'));
        });
    }
})();
