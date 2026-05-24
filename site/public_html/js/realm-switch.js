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

    function currentAccent() {
        var accent = root.getAttribute('data-k2-accent');
        if (accent && VALID_ACCENTS.indexOf(accent) !== -1) {
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
        document.querySelectorAll('.k2-accent-pills__btn').forEach(function (btn) {
            var on = btn.getAttribute('data-k2-accent') === accent;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    function setAccentTune(accent) {
        if (VALID_ACCENTS.indexOf(accent) === -1) {
            return;
        }
        root.setAttribute('data-k2-accent', accent);
        try {
            sessionStorage.setItem(ACCENT_KEY, accent);
        } catch (e) {
            /* ignore */
        }
        syncAccentButtons();
        dispatchChange();
    }

    function setRealm(realm) {
        if (realm !== 'online' && realm !== 'amiga') {
            realm = 'online';
        }
        root.setAttribute('data-realm', realm);
        try {
            localStorage.setItem(REALM_KEY, realm);
        } catch (e) {
            /* ignore */
        }
        syncRealmButtons();
        dispatchChange();
    }

    function init() {
        var savedRealm = null;
        var savedAccent = null;
        try {
            savedRealm = localStorage.getItem(REALM_KEY);
            savedAccent = sessionStorage.getItem(ACCENT_KEY);
        } catch (e) {
            /* ignore */
        }

        if (savedRealm === 'online' || savedRealm === 'amiga') {
            root.setAttribute('data-realm', savedRealm);
        }
        syncRealmButtons();

        if (savedAccent && VALID_ACCENTS.indexOf(savedAccent) !== -1) {
            root.setAttribute('data-k2-accent', savedAccent);
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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

