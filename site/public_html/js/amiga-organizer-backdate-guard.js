/**
 * AD8 — show admin password panel when create date is older than server threshold.
 * Prefer inline script in fixtures.php; this file is a backup if the page enqueues it.
 */
(function () {
    'use strict';

    function syncBackdateGuard(root) {
        var threshold = root.getAttribute('data-threshold') || '';
        var inputId = root.getAttribute('data-date-input-id') || 'amiga-fixture-event-date';
        var dateInput = document.getElementById(inputId);
        var pwd = document.getElementById('amiga-organizer-backdate-admin-password');
        if (!dateInput || !pwd || threshold === '') {
            return;
        }
        var ymd = (dateInput.value || '').trim();
        var needsAdmin = ymd !== '' && ymd < threshold;
        if (needsAdmin) {
            root.removeAttribute('hidden');
            pwd.removeAttribute('disabled');
            pwd.setAttribute('required', 'required');
        } else {
            root.setAttribute('hidden', 'hidden');
            pwd.removeAttribute('required');
            pwd.setAttribute('disabled', 'disabled');
            pwd.value = '';
        }
    }

    function bind(root) {
        if (!root || root.getAttribute('data-amiga-backdate-bound') === '1') {
            return;
        }
        root.setAttribute('data-amiga-backdate-bound', '1');
        var inputId = root.getAttribute('data-date-input-id') || 'amiga-fixture-event-date';
        var dateInput = document.getElementById(inputId);
        var last = dateInput ? dateInput.value : '';
        function tick() {
            if (!dateInput) {
                return;
            }
            if (dateInput.value !== last) {
                last = dateInput.value;
                syncBackdateGuard(root);
            }
        }
        if (dateInput) {
            dateInput.addEventListener('change', tick);
            dateInput.addEventListener('input', tick);
        }
        window.setInterval(tick, 250);
        syncBackdateGuard(root);
    }

    function boot() {
        var roots = document.querySelectorAll('[data-amiga-backdate-guard]');
        for (var i = 0; i < roots.length; i++) {
            bind(roots[i]);
        }
    }

    if (typeof window.k2OnPageReady === 'function') {
        window.k2OnPageReady(boot);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());