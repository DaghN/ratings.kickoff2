/**
 * Tint picker (hub accent pills, data-k2-accent).
 * Tint follows a six-hour schedule; a pill pick overrides only until the next boundary.
 */
(function () {
    'use strict';

    var root = document.documentElement;
    var S = window.K2TintSchedule;
    var ACCENT_KEY = S ? S.ACCENT_KEY : 'k2-accent-tune';
    var PERIOD_KEY = S ? S.PERIOD_KEY : 'k2-accent-manual-period';
    var DEFAULT_ACCENT = S ? S.DEFAULT_ACCENT : 'amber';
    var periodTimer = null;

    function isValidAccent(accent) {
        return S ? S.isValidAccent(accent) : false;
    }

    function writeLocal(key, value) {
        try {
            localStorage.setItem(key, value);
        } catch (e) {
            /* ignore */
        }
    }

    function currentAccent() {
        var accent = root.getAttribute('data-k2-accent');
        if (isValidAccent(accent)) {
            return accent;
        }
        return DEFAULT_ACCENT;
    }

    function syncAccentButtons() {
        var accent = currentAccent();
        document.querySelectorAll('.k2-tint-menu__choice').forEach(function (btn) {
            var on = btn.getAttribute('data-k2-accent') === accent;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    function applyResolvedAccent() {
        var accent = S ? S.resolveAccent() : DEFAULT_ACCENT;
        if (!isValidAccent(accent)) {
            accent = DEFAULT_ACCENT;
        }
        root.setAttribute('data-k2-accent', accent);
        syncAccentButtons();
    }

    function clearPeriodTimer() {
        if (periodTimer) {
            clearTimeout(periodTimer);
            periodTimer = null;
        }
    }

    function scheduleNextPeriodTick() {
        clearPeriodTimer();
        if (!S) {
            return;
        }
        var delay = S.msUntilNextPeriod();
        if (delay < 1) {
            delay = 1;
        }
        periodTimer = setTimeout(function () {
            applyResolvedAccent();
            scheduleNextPeriodTick();
        }, delay);
    }

    function setAccentTune(accent) {
        if (!isValidAccent(accent)) {
            return;
        }
        if (S) {
            S.setManualAccent(accent);
        } else {
            writeLocal(ACCENT_KEY, accent);
        }
        root.setAttribute('data-k2-accent', accent);
        syncAccentButtons();
        scheduleNextPeriodTick();
    }

    function init() {
        applyResolvedAccent();
        scheduleNextPeriodTick();
        syncAccentButtons();
    }

    function boot() {
        syncAccentButtons();
    }

    // Turbo Drive re-evaluates this body script on every in-page navigation. Bind the
    // global listeners + schedule timer + first init only ONCE per document; otherwise
    // each visit stacks another click/storage handler and another setTimeout chain.
    // See docs/k2-turbo-page-init-checklist.md.
    if (window.__k2RealmSwitchBound) {
        boot();
        return;
    }
    window.__k2RealmSwitchBound = true;

    document.addEventListener('click', function (ev) {
        var btn = ev.target && ev.target.closest ? ev.target.closest('.k2-tint-menu__choice') : null;
        if (!btn) {
            return;
        }
        setAccentTune(btn.getAttribute('data-k2-accent') || '');
    });

    window.addEventListener('storage', function (ev) {
        if (ev.key === ACCENT_KEY || ev.key === PERIOD_KEY) {
            applyResolvedAccent();
            scheduleNextPeriodTick();
        }
        if (S && ev.key === S.CLOCK_KEY) {
            scheduleNextPeriodTick();
            applyResolvedAccent();
        }
    });

    if (window.k2PageReady) {
        window.k2PageReady(boot);
    }

    (window.k2OnPageReady || function (fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    })(function () {
        init();
        boot();
    });
})();
