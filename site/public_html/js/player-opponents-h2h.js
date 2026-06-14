/**
 * Opponents → Head-to-head: search, dropdowns, pair headline (charts later).
 */
(function () {
    'use strict';

    var DEBOUNCE_MS = 200;
    var MIN_CHARS = 2;
    var API_PATH = '/api/player_h2h_opponent_search.php';

    function debounce(fn, ms) {
        var timer = null;
        return function () {
            var ctx = this;
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(ctx, args);
            }, ms);
        };
    }

    function storeCarryScroll() {
        if (window.K2CarryScroll && typeof window.K2CarryScroll.store === 'function') {
            window.K2CarryScroll.store();
        }
    }

    function navigateToOpponent(root, opponentId) {
        var base = root.getAttribute('data-h2h-base') || '';
        if (!base || !opponentId) {
            return;
        }
        var sep = base.indexOf('?') >= 0 ? '&' : '?';
        storeCarryScroll();
        window.location.href = base + sep + 'opponent=' + encodeURIComponent(String(opponentId));
    }

    function metaLabel(gamesVs) {
        if (gamesVs > 0) {
            return gamesVs + ' game' + (gamesVs === 1 ? '' : 's');
        }

        return 'No games';
    }

    function renderGroup(list, label, players, playerId, selectFn) {
        if (!players.length) {
            return;
        }

        var heading = document.createElement('li');
        heading.className = 'player-search-group';
        heading.setAttribute('role', 'presentation');
        heading.textContent = label;
        list.appendChild(heading);

        for (var i = 0; i < players.length; i++) {
            var p = players[i];
            if (String(p.id) === String(playerId)) {
                continue;
            }

            var li = document.createElement('li');
            li.setAttribute('role', 'presentation');

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'player-h2h-search-option k2-player-opponents-h2h__search-option';
            btn.setAttribute('role', 'option');

            var nameSpan = document.createElement('span');
            nameSpan.className = 'player-search-name';
            nameSpan.textContent = p.name;

            var metaSpan = document.createElement('span');
            metaSpan.className = 'player-search-meta';
            metaSpan.textContent = metaLabel(p.games_vs);

            btn.appendChild(nameSpan);
            btn.appendChild(metaSpan);
            btn.addEventListener('click', function (id) {
                return function () {
                    selectFn(id);
                };
            }(p.id));

            li.appendChild(btn);
            list.appendChild(li);
        }
    }

    function initSearch(root) {
        var playerId = root.getAttribute('data-player-id');
        var input = root.querySelector('.k2-player-opponents-h2h__search-input');
        var list = root.querySelector('.k2-player-opponents-h2h__search-results');
        if (!playerId || !input || !list) {
            return;
        }

        function closeList() {
            list.hidden = true;
            list.innerHTML = '';
            input.setAttribute('aria-expanded', 'false');
        }

        function selectOpponent(opponentId) {
            input.value = '';
            closeList();
            navigateToOpponent(root, opponentId);
        }

        function renderResults(data) {
            list.innerHTML = '';
            var played = data.played || [];
            var others = data.others || [];
            if (!played.length && !others.length) {
                closeList();
                return;
            }

            renderGroup(list, 'Opponents', played, playerId, selectOpponent);
            renderGroup(list, 'Other players', others, playerId, selectOpponent);

            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }

        var runSearch = debounce(function () {
            var q = input.value.trim();
            if (q.length < MIN_CHARS) {
                closeList();
                return;
            }

            var url = API_PATH + '?player_id=' + encodeURIComponent(playerId)
                + '&q=' + encodeURIComponent(q);

            fetch(url, { credentials: 'same-origin' })
                .then(function (res) {
                    return res.json();
                })
                .then(function (data) {
                    renderResults(data);
                })
                .catch(function () {
                    closeList();
                });
        }, DEBOUNCE_MS);

        input.addEventListener('input', runSearch);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeList();
            }
        });

        document.addEventListener('click', function (e) {
            if (!root.contains(e.target)) {
                closeList();
            }
        });
    }

    function initListboxes(root) {
        if (typeof window.K2ArchiveListbox !== 'undefined') {
            window.K2ArchiveListbox.init(root);
        }

        var inputs = root.querySelectorAll('.k2-player-opponents-h2h__listbox .k2-archive-listbox__value');
        for (var i = 0; i < inputs.length; i++) {
            inputs[i].addEventListener('change', function () {
                var val = this.value;
                if (!val) {
                    return;
                }
                navigateToOpponent(root, val);
            });
        }
    }

    function initRoot(root) {
        initSearch(root);
        initListboxes(root);
    }

    function boot() {
        var roots = document.querySelectorAll('.k2-player-opponents-h2h');
        for (var i = 0; i < roots.length; i++) {
            initRoot(roots[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());
