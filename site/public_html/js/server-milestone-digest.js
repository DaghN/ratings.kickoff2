/**
 * Recent milestone digest (DOM card, not a chart).
 * Renders a compact grid of recent milestones fetched from
 * api/server_recent_milestones.php.
 */
(function () {
    'use strict';

    var API_PATH = 'api/server_recent_milestones.php';

    function friendlyDate(iso) {
        if (!iso) return '';
        if (iso.length === 7) {
            var d = new Date(iso + '-01T00:00:00');
            return isNaN(d.getTime()) ? iso
                : d.toLocaleDateString(undefined, { year: 'numeric', month: 'long' });
        }
        var dt = new Date(iso + 'T00:00:00');
        return isNaN(dt.getTime()) ? iso
            : dt.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function initRoot(root) {
        var wrap   = root.querySelector('.milestone-digest-wrap');
        var status = root.querySelector('.server-milestone-digest-status');
        if (!wrap) return;

        fetch(API_PATH + '?realm=online', { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) throw new Error('bad_status');
                return r.json();
            })
            .then(function (data) {
                var items = data.milestones || [];
                if (!items.length) {
                    if (status) status.textContent = 'No milestones available.';
                    return;
                }

                var grid = document.createElement('div');
                grid.className = 'milestone-digest-grid';

                for (var i = 0; i < items.length; i++) {
                    var m = items[i];
                    var card = document.createElement('div');
                    card.className = 'milestone-digest-card';

                    var lbl = document.createElement('span');
                    lbl.className = 'milestone-digest-card__label';
                    lbl.textContent = m.label;
                    card.appendChild(lbl);

                    var val = document.createElement('span');
                    val.className = 'milestone-digest-card__value';
                    val.textContent = m.player || m.value || '';
                    card.appendChild(val);

                    var dt = document.createElement('span');
                    dt.className = 'milestone-digest-card__date';
                    dt.textContent = friendlyDate(m.date);
                    card.appendChild(dt);

                    grid.appendChild(card);
                }

                wrap.appendChild(grid);
                if (status) status.textContent = '';
            })
            .catch(function () {
                if (status) status.textContent = 'Could not load milestones.';
            });
    }

    function boot() {
        var roots = document.querySelectorAll('.server-milestone-digest');
        for (var i = 0; i < roots.length; i++) initRoot(roots[i]);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
