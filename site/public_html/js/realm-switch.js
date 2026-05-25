/**
 * Realm switcher (data-realm) + tint picker (hub accent pills, data-k2-accent).
 * Tint and realm are independent — realm click does not change or clear tint.
 */
(function () {
    'use strict';

    var root = document.documentElement;
    var REALM_KEY = 'k2-realm';
    var ACCENT_KEY = 'k2-accent-tune';
    var VALID_ACCENTS = ['amber', 'pitch', 'chrome', 'holo'];
    var DEFAULT_ACCENT = 'amber';

    function isValidAccent(accent) {
        return accent && VALID_ACCENTS.indexOf(accent) !== -1;
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

    function readSession(key) {
        try {
            return sessionStorage.getItem(key);
        } catch (e) {
            return null;
        }
    }

    function removeSession(key) {
        try {
            sessionStorage.removeItem(key);
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

    function savedAccent() {
        var accent = readLocal(ACCENT_KEY);
        if (isValidAccent(accent)) {
            return accent;
        }

        /* Upgrade pre-persistence sessions without losing the current tab. */
        accent = readSession(ACCENT_KEY);
        if (isValidAccent(accent)) {
            writeLocal(ACCENT_KEY, accent);
            removeSession(ACCENT_KEY);
            return accent;
        }

        return null;
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
        document.querySelectorAll('.k2-accent-pills__btn').forEach(function (btn) {
            var on = btn.getAttribute('data-k2-accent') === accent;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    function setAccentTune(accent) {
        if (!isValidAccent(accent)) {
            return;
        }
        root.setAttribute('data-k2-accent', accent);
        writeLocal(ACCENT_KEY, accent);
        removeSession(ACCENT_KEY);
        syncAccentButtons();
        dispatchChange();
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

    function init() {
        var savedRealm = readLocal(REALM_KEY);

        if (savedRealm === 'online' || savedRealm === 'amiga') {
            root.setAttribute('data-realm', savedRealm);
        }
        syncRealmButtons();

        var accent = savedAccent();
        if (accent) {
            root.setAttribute('data-k2-accent', accent);
        } else {
            root.setAttribute('data-k2-accent', DEFAULT_ACCENT);
        }
        syncAccentButtons();

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

        document.querySelectorAll('.k2-accent-pills__btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setAccentTune(btn.getAttribute('data-k2-accent') || '');
            });
        });
    }

    window.addEventListener('storage', function (ev) {
        if (ev.key === ACCENT_KEY) {
            var accent = isValidAccent(ev.newValue) ? ev.newValue : DEFAULT_ACCENT;
            root.setAttribute('data-k2-accent', accent);
            syncAccentButtons();
            dispatchChange();
        }
        if (ev.key === REALM_KEY && (ev.newValue === 'online' || ev.newValue === 'amiga')) {
            root.setAttribute('data-realm', ev.newValue);
            syncRealmButtons();
            dispatchChange();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

