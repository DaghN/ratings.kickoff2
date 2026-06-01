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
        var form = document.querySelector('.k2-player-games-controls');
        if (!form || typeof window.K2ArchiveListbox === 'undefined') {
            return;
        }

        window.K2ArchiveListbox.init(form);

        form.addEventListener('change', function (e) {
            var target = e.target;
            if (target && target.classList && target.classList.contains('k2-archive-listbox__value')) {
                form.submit();
            }
        });
    });
}());
