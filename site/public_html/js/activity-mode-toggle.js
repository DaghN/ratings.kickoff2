(function () {
    'use strict';

    var root = document.querySelector('[data-k2-activity-mode]');
    if (!root) {
        return;
    }

    var buttons = root.querySelectorAll('[data-k2-activity-target]');
    var panels = root.querySelectorAll('[data-k2-activity-panel]');

    function show(target) {
        var i;
        root.setAttribute('data-k2-activity-mode', target);

        for (i = 0; i < panels.length; i++) {
            var panel = panels[i];
            panel.hidden = panel.getAttribute('data-k2-activity-panel') !== target;
        }

        for (i = 0; i < buttons.length; i++) {
            var btn = buttons[i];
            var active = btn.getAttribute('data-k2-activity-target') === target;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        }
    }

    for (var b = 0; b < buttons.length; b++) {
        buttons[b].addEventListener('click', function () {
            show(this.getAttribute('data-k2-activity-target') || 'period');
        });
    }
})();
