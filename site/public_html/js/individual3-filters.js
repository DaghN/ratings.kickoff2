(function () {
    'use strict';

    function onReady(fn) {
        if (typeof window.k2OnPageReady === 'function') {
            window.k2OnPageReady(fn);
            return;
        }
        if (typeof window.k2PageReady === 'function') {
            window.k2PageReady(fn);
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
            return;
        }
        fn();
    }

    function boot() {
        var forms = document.querySelectorAll('.k2-player-games-controls, .k2-realm-games-filters');
        if (!forms.length || typeof window.K2ArchiveListbox === 'undefined') {
            return;
        }

        forms.forEach(function (form) {
            window.K2ArchiveListbox.init(form);

            if (form._k2Individual3FiltersBound) {
                return;
            }
            form._k2Individual3FiltersBound = true;

            form.addEventListener('change', function (e) {
                var target = e.target;
                if (target && target.classList && target.classList.contains('k2-archive-listbox__value')) {
                    form.submit();
                }
            });
        });
    }

    onReady(boot);
}());
