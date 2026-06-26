/**
 * Amiga tournament organizer — player autocomplete for league create (add to selected list).
 * Uses /api/player_search.php?realm=amiga; pick navigates to create_add_player_id URL.
 */
(function () {
    'use strict';

    var DEBOUNCE_MS = 200;
    var MIN_CHARS = 2;
    var API_PATH = '/api/player_search.php';

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

    function parseSelectedIds(root) {
        var raw = root.getAttribute('data-organizer-selected-ids') || '';
        var ids = {};
        raw.split(',').forEach(function (part) {
            var id = parseInt(part, 10);
            if (id > 0) {
                ids[id] = true;
            }
        });
        return ids;
    }

    function setExpanded(input, expanded) {
        input.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }

    function closeList(root, input, list) {
        list.hidden = true;
        list.innerHTML = '';
        setExpanded(input, false);
        root._aoppActiveIndex = -1;
        root._aoppItems = [];
    }

    function announce(root, text) {
        var live = root.querySelector('.k2-amiga-organizer-player-search__live');
        if (live) {
            live.textContent = text || '';
        }
    }

    function addPlayerUrl(root, playerId) {
        var base = root.getAttribute('data-organizer-add-base') || '';
        var join = base.indexOf('?') >= 0 ? '&' : '?';
        return base + join + 'create_add_player_id=' + encodeURIComponent(String(playerId));
    }

    function moveHighlight(root, delta) {
        var items = root._aoppItems || [];
        if (!items.length) {
            return;
        }
        var idx = root._aoppActiveIndex;
        if (idx < 0) {
            idx = delta > 0 ? 0 : items.length - 1;
        } else {
            idx = (idx + delta + items.length) % items.length;
        }
        root._aoppActiveIndex = idx;
        for (var i = 0; i < items.length; i++) {
            if (i === idx) {
                items[i].classList.add('player-search-active');
                items[i].setAttribute('aria-selected', 'true');
            } else {
                items[i].classList.remove('player-search-active');
                items[i].setAttribute('aria-selected', 'false');
            }
        }
    }

    function pickActive(root, input, list) {
        var items = root._aoppItems || [];
        var idx = root._aoppActiveIndex;
        if (idx >= 0 && idx < items.length) {
            var btn = items[idx].querySelector('[data-organizer-pick-id]');
            if (btn) {
                var pickId = btn.getAttribute('data-organizer-pick-id');
                if (pickId) {
                    window.location.href = addPlayerUrl(root, pickId);
                    return;
                }
            }
        }
        closeList(root, input, list);
    }

    function renderResults(root, input, list, players) {
        list.innerHTML = '';
        root._aoppItems = [];
        root._aoppActiveIndex = -1;
        var selected = parseSelectedIds(root);

        if (!players.length) {
            list.hidden = true;
            setExpanded(input, false);
            announce(root, 'No players matched.');
            return;
        }

        for (var i = 0; i < players.length; i++) {
            var p = players[i];
            var li = document.createElement('li');
            li.setAttribute('role', 'presentation');

            var row = document.createElement('div');
            row.className = 'k2-amiga-organizer-player-search__option';
            row.setAttribute('role', 'option');
            row.tabIndex = -1;

            var nameSpan = document.createElement('span');
            nameSpan.className = 'player-search-name';
            nameSpan.appendChild(document.createTextNode(p.name));

            var metaSpan = document.createElement('span');
            metaSpan.className = 'player-search-meta';

            var ratingSpan = document.createElement('span');
            ratingSpan.className = 'player-search-rating';
            ratingSpan.appendChild(document.createTextNode(String(p.rating)));
            metaSpan.appendChild(ratingSpan);

            row.appendChild(nameSpan);
            row.appendChild(metaSpan);

            if (selected[p.id]) {
                var picked = document.createElement('span');
                picked.className = 'k2-amiga-organizer-player-search__picked';
                picked.appendChild(document.createTextNode('selected'));
                row.appendChild(picked);
            } else {
                var pick = document.createElement('button');
                pick.type = 'button';
                pick.className = 'k2-amiga-organizer-player-search__pick';
                pick.setAttribute('data-organizer-pick-id', String(p.id));
                pick.appendChild(document.createTextNode('Add to league'));
                pick.addEventListener('click', function (ev) {
                    ev.preventDefault();
                    var id = ev.currentTarget.getAttribute('data-organizer-pick-id');
                    if (id) {
                        window.location.href = addPlayerUrl(root, id);
                    }
                });
                row.appendChild(pick);
            }

            li.appendChild(row);
            list.appendChild(li);
            root._aoppItems.push(li);
        }

        list.hidden = false;
        setExpanded(input, true);
        announce(root, players.length + ' matches.');
    }

    function initRoot(root) {
        if (root.getAttribute('data-aopp-initialized') === '1') {
            return;
        }
        root.setAttribute('data-aopp-initialized', '1');

        var realm = root.getAttribute('data-organizer-search-realm') || 'amiga';
        var input = root.querySelector('.k2-amiga-organizer-player-search__input');
        var list = root.querySelector('.k2-amiga-organizer-player-search__results');
        if (!input || !list) {
            return;
        }

        root._aoppAbort = null;
        root._aoppItems = [];
        root._aoppActiveIndex = -1;

        var runFetch = debounce(function () {
            var q = input.value.replace(/^\s+|\s+$/g, '');
            if (q.length < MIN_CHARS) {
                if (root._aoppAbort) {
                    root._aoppAbort.abort();
                }
                closeList(root, input, list);
                announce(root, '');
                return;
            }

            if (root._aoppAbort) {
                root._aoppAbort.abort();
            }
            if (typeof AbortController !== 'undefined') {
                root._aoppAbort = new AbortController();
            }

            var url = API_PATH + '?realm=' + encodeURIComponent(realm)
                + '&q=' + encodeURIComponent(q)
                + '&limit=15';

            var fetchOpts = { credentials: 'same-origin' };
            if (root._aoppAbort) {
                fetchOpts.signal = root._aoppAbort.signal;
            }

            fetch(url, fetchOpts)
                .then(function (r) {
                    if (!r.ok) {
                        throw new Error('bad_status');
                    }
                    return r.json();
                })
                .then(function (data) {
                    renderResults(root, input, list, data.players || []);
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') {
                        return;
                    }
                    closeList(root, input, list);
                    announce(root, 'Search failed.');
                });
        }, DEBOUNCE_MS);

        input.addEventListener('input', runFetch);

        input.addEventListener('keydown', function (ev) {
            if (!list.hidden && root._aoppItems && root._aoppItems.length) {
                if (ev.key === 'ArrowDown') {
                    ev.preventDefault();
                    moveHighlight(root, 1);
                    return;
                }
                if (ev.key === 'ArrowUp') {
                    ev.preventDefault();
                    moveHighlight(root, -1);
                    return;
                }
                if (ev.key === 'Enter') {
                    ev.preventDefault();
                    pickActive(root, input, list);
                    return;
                }
                if (ev.key === 'Escape') {
                    ev.preventDefault();
                    closeList(root, input, list);
                    announce(root, '');
                    return;
                }
            }
        });

        document.addEventListener('click', function (ev) {
            if (!root.contains(ev.target)) {
                closeList(root, input, list);
            }
        });
    }

    function boot() {
        var roots = document.querySelectorAll('.k2-amiga-organizer-player-search');
        for (var i = 0; i < roots.length; i++) {
            initRoot(roots[i]);
        }
    }

    (window.k2OnPageReady || function (fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    })(boot);
})();
