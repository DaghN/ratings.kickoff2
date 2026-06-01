/**
 * Player search for H2H comparison — selects opponent (does not navigate away).
 */
(function () {
    'use strict';

    var DEBOUNCE_MS = 200;
    var MIN_CHARS = 2;
    var API_PATH = 'api/player_search.php';
    var EVENT_NAME = 'kool-opponent-selected';

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

    function dispatchSelection(playerId, opponentId, opponentName) {
        document.dispatchEvent(new CustomEvent(EVENT_NAME, {
            detail: {
                playerId: playerId,
                opponentId: opponentId,
                opponentName: opponentName
            }
        }));
    }

    function bindOption(btn, selectFn, opponentId, opponentName) {
        btn.addEventListener('click', function () {
            selectFn(opponentId, opponentName);
        });
    }

    function initRoot(root) {
        var playerId = root.getAttribute('data-player-id');
        var input = root.querySelector('.player-h2h-search-input');
        var list = root.querySelector('.player-h2h-search-results');
        if (!playerId || !input || !list) {
            return;
        }

        var realm = root.getAttribute('data-realm') || 'online';

        function closeList() {
            list.hidden = true;
            list.innerHTML = '';
            input.setAttribute('aria-expanded', 'false');
        }

        function selectOpponent(id, name) {
            input.value = name;
            closeList();
            dispatchSelection(playerId, id, name);
        }

        function renderResults(players) {
            list.innerHTML = '';
            if (!players.length) {
                closeList();
                return;
            }

            for (var i = 0; i < players.length; i++) {
                var p = players[i];
                if (String(p.id) === String(playerId)) {
                    continue;
                }

                var li = document.createElement('li');
                li.setAttribute('role', 'presentation');

                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'player-h2h-search-option';
                btn.setAttribute('role', 'option');

                var nameSpan = document.createElement('span');
                nameSpan.className = 'player-search-name';
                nameSpan.textContent = p.name;

                var ratingSpan = document.createElement('span');
                ratingSpan.className = 'player-search-rating';
                ratingSpan.textContent = String(p.rating);

                btn.appendChild(nameSpan);
                btn.appendChild(ratingSpan);
                bindOption(btn, selectOpponent, p.id, p.name);

                li.appendChild(btn);
                list.appendChild(li);
            }

            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }

        var runSearch = debounce(function () {
            var q = input.value.trim();
            if (q.length < MIN_CHARS) {
                closeList();
                return;
            }

            var url = API_PATH + '?q=' + encodeURIComponent(q)
                + '&realm=' + encodeURIComponent(realm) + '&limit=15';

            fetch(url, { credentials: 'same-origin' })
                .then(function (r) {
                    if (!r.ok) {
                        throw new Error('bad_status');
                    }
                    return r.json();
                })
                .then(function (data) {
                    renderResults(data.players || []);
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

        document.addEventListener(EVENT_NAME, function (e) {
            if (!e.detail || String(e.detail.playerId) !== String(playerId)) {
                return;
            }
            if (e.detail.opponentName) {
                input.value = e.detail.opponentName;
            }
            closeList();
        });
    }

    function boot() {
        var roots = document.querySelectorAll('.player-h2h-opponent-search');
        for (var i = 0; i < roots.length; i++) {
            initRoot(roots[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
