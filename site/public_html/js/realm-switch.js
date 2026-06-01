/**
 * Realm switcher (data-realm) + tint picker (hub accent pills, data-k2-accent).
 * Tint follows a six-hour schedule; a pill pick overrides only until the next boundary.
 */
(function () {
    'use strict';

    var root = document.documentElement;
    var REALM_KEY = 'k2-realm';
    var S = window.K2TintSchedule;
    var ACCENT_KEY = S ? S.ACCENT_KEY : 'k2-accent-tune';
    var PERIOD_KEY = S ? S.PERIOD_KEY : 'k2-accent-manual-period';
    var DEFAULT_ACCENT = S ? S.DEFAULT_ACCENT : 'amber';
    var periodTimer = null;

    function isValidAccent(accent) {
        return S ? S.isValidAccent(accent) : false;
    }

    function readLocal(key) {
        try {
            return localStorage.getItem(key);
        } catch (e) {
            return null;
        }
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

    function dispatchChange() {
        document.dispatchEvent(new CustomEvent('k2-realm-change', {
            detail: {
                realm: root.getAttribute('data-realm') || 'online',
                accent: currentAccent()
            }
        }));
    }

    function syncRealmButtons() {
        var realm = root.getAttribute('data-realm') || 'online';
        document.querySelectorAll('.k2-realm-switch__btn').forEach(function (btn) {
            var on = btn.getAttribute('data-realm') === realm;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
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
        dispatchChange();
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
        dispatchChange();
        scheduleNextPeriodTick();
    }

    function setRealm(realm) {
        if (realm !== 'online' && realm !== 'amiga') {
            realm = 'online';
        }
        root.setAttribute('data-realm', realm);
        writeLocal(REALM_KEY, realm);
        syncRealmButtons();
        dispatchChange();
    }

    function initAccent() {
        applyResolvedAccent();
        scheduleNextPeriodTick();
    }

    function init() {
        var savedRealm = readLocal(REALM_KEY);

        if (savedRealm === 'online' || savedRealm === 'amiga') {
            root.setAttribute('data-realm', savedRealm);
        }
        syncRealmButtons();

        initAccent();

        document.querySelectorAll('.k2-realm-switch__btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var realm = btn.getAttribute('data-realm') || 'online';
                var current = root.getAttribute('data-realm') || 'online';
                if (realm !== current) {
                    setRealm(realm);
                } else {
                    dispatchChange();
                }
            });
        });

        document.querySelectorAll('.k2-tint-menu__choice').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setAccentTune(btn.getAttribute('data-k2-accent') || '');
            });
        });
    }

    window.addEventListener('storage', function (ev) {
        if (ev.key === ACCENT_KEY || ev.key === PERIOD_KEY) {
            applyResolvedAccent();
            scheduleNextPeriodTick();
        }
        if (ev.key === REALM_KEY && (ev.newValue === 'online' || ev.newValue === 'amiga')) {
            root.setAttribute('data-realm', ev.newValue);
            syncRealmButtons();
            dispatchChange();
        }
        if (S && ev.key === S.CLOCK_KEY) {
            scheduleNextPeriodTick();
            applyResolvedAccent();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
