(function () {
    'use strict';

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
            return;
        }
        fn();
    }

    onReady(function () {
        var forms = document.querySelectorAll('.k2-player-games-controls, .k2-realm-games-filters');
        if (!forms.length || typeof window.K2ArchiveListbox === 'undefined') {
            return;
        }

        forms.forEach(function (form) {
            window.K2ArchiveListbox.init(form);

            form.addEventListener('change', function (e) {
                var target = e.target;
                if (target && target.classList && target.classList.contains('k2-archive-listbox__value')) {
                    form.submit();
                }
            });
        });
    });
}());
