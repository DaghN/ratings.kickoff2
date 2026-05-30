(function () {
    'use strict';

    var root = document.querySelector('[data-k2-activity-mode]');
    if (!root) {
        return;
    }

    var buttons = root.querySelectorAll('[data-k2-activity-target]');
    var panels = root.querySelectorAll('[data-k2-activity-panel]');
    var PEAK_HASH_PREFIX = 'k2-peak-period-';
    var CALENDAR_PERIODS = { day: true, week: true, month: true, year: true };

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

    function scrollToPeakPeriodHash() {
        var hash = window.location.hash;
        var id;
        var period;
        var el;

        if (!hash || hash.charAt(0) !== '#') {
            return;
        }

        id = hash.slice(1);
        if (id.indexOf(PEAK_HASH_PREFIX) !== 0) {
            return;
        }

        period = id.slice(PEAK_HASH_PREFIX.length);
        if (period === 'longevity' || period === 'all-time') {
            show('all-time');
        } else if (CALENDAR_PERIODS[period]) {
            show('period');
        }

        el = document.getElementById(id);
        if (!el) {
            return;
        }

        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                el.scrollIntoView({ block: 'start', behavior: 'auto' });
            });
        });
    }

    for (var b = 0; b < buttons.length; b++) {
        buttons[b].addEventListener('click', function () {
            show(this.getAttribute('data-k2-activity-target') || 'period');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scrollToPeakPeriodHash);
    } else {
        scrollToPeakPeriodHash();
    }
})();
