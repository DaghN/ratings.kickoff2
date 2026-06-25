/**
 * Realm-aware player autocomplete (KOOL). Expects api/player_search.php.
 */
(function () {
    'use strict';

    var DEBOUNCE_MS = 200;
    var MIN_CHARS = 2;
    var API_PATH = '/api/player_search.php';
    var PLAYER_PROFILE_FRAGMENT = 'player';
    var PROFILE_BY_REALM = {
        online: '/player/profile.php',
        amiga: '/amiga/player/profile.php'
    };
    var REALM_LABELS = {
        online: 'Online',
        amiga: 'Amiga'
    };

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

    function setExpanded(input, expanded) {
        input.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }

    function closeList(root, input, list) {
        list.hidden = true;
        list.innerHTML = '';
        setExpanded(input, false);
        root._psActiveIndex = -1;
        root._psItems = [];
    }

    function announce(root, text) {
        var live = root.querySelector('.player-search-live');
        if (live) {
            live.textContent = text || '';
        }
    }

    function moveHighlight(list, delta, root) {
        var items = root._psItems || [];
        if (!items.length) {
            return;
        }
        var idx = root._psActiveIndex;
        if (idx < 0) {
            idx = delta > 0 ? 0 : items.length - 1;
        } else {
            idx = (idx + delta + items.length) % items.length;
        }
        root._psActiveIndex = idx;
        for (var i = 0; i < items.length; i++) {
            if (i === idx) {
                items[i].classList.add('player-search-active');
                items[i].setAttribute('aria-selected', 'true');
            } else {
                items[i].classList.remove('player-search-active');
                items[i].removeAttribute('aria-selected');
            }
        }
    }

    function isFilterMode(root) {
        return root.getAttribute('data-player-search-mode') === 'filter';
    }

    function navigatePick(root, p, profilePage) {
        storeCarryScroll();
        window.location.href = playerPickHref(root, p, profilePage);
    }

    function playerRealmFromAnchor(a, fallbackRealm) {
        if (!a) {
            return fallbackRealm || 'online';
        }
        return a.getAttribute('data-player-realm') || fallbackRealm || 'online';
    }

    function pickActive(root, input, list, profilePage, realm) {
        var items = root._psItems || [];
        var idx = root._psActiveIndex;
        if (idx >= 0 && idx < items.length) {
            var a = items[idx].querySelector('a');
            var playerId = a ? a.getAttribute('data-player-id') : '';
            if (playerId) {
                navigatePick(root, { id: playerId, realm: playerRealmFromAnchor(a, realm) }, profilePage);
                return;
            }
        }
        closeList(root, input, list);
    }

    function storeCarryScroll() {
        if (window.K2CarryScroll && typeof window.K2CarryScroll.store === 'function') {
            window.K2CarryScroll.store();
        }
    }

    function playerProfileHref(p, profilePage) {
        var realm = p.realm || 'online';
        var base = PROFILE_BY_REALM[realm] || profilePage;
        return base + '?id=' + encodeURIComponent(p.id) + '#' + PLAYER_PROFILE_FRAGMENT;
    }

    function playerPickHref(root, p, profilePage) {
        if (!isFilterMode(root)) {
            return playerProfileHref(p, profilePage);
        }

        var filterBase = root.getAttribute('data-player-search-filter-href') || '';
        var filterParam = root.getAttribute('data-player-search-filter-param') || 'player';
        var path = window.location.pathname;
        var params = new URLSearchParams(window.location.search);

        if (filterBase !== '') {
            var qIdx = filterBase.indexOf('?');
            path = qIdx >= 0 ? filterBase.slice(0, qIdx) : filterBase;
            if (qIdx >= 0) {
                params = new URLSearchParams(filterBase.slice(qIdx + 1));
            }
        }

        params.set(filterParam, String(p.id));
        params.delete('offset');
        if (filterParam === 'player') {
            params.delete('opponent');
            params.delete('opponent_via');
            params.set('player_via', 'search');
        }

        var query = params.toString();
        return query ? path + '?' + query : path;
    }

    function renderResults(root, input, list, players, profilePage, showRealmLabel) {
        list.innerHTML = '';
        root._psItems = [];
        root._psActiveIndex = -1;

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

            var a = document.createElement('a');
            a.setAttribute('role', 'option');
            a.setAttribute('id', 'player-search-opt-' + i);
            a.setAttribute('data-player-id', String(p.id));
            a.setAttribute('data-player-realm', p.realm || 'online');
            a.href = playerPickHref(root, p, profilePage);
            if (isFilterMode(root)) {
                a.setAttribute('data-player-search-pick', 'filter');
            }
            a.tabIndex = -1;

            var nameSpan = document.createElement('span');
            nameSpan.className = 'player-search-name';
            nameSpan.appendChild(document.createTextNode(p.name));

            var metaSpan = document.createElement('span');
            metaSpan.className = 'player-search-meta';

            if (showRealmLabel && p.realm && REALM_LABELS[p.realm]) {
                var realmSpan = document.createElement('span');
                realmSpan.className = 'player-search-realm';
                realmSpan.appendChild(document.createTextNode(REALM_LABELS[p.realm]));
                metaSpan.appendChild(realmSpan);
            }

            var ratingSpan = document.createElement('span');
            ratingSpan.className = 'player-search-rating';
            ratingSpan.appendChild(document.createTextNode(String(p.rating)));
            metaSpan.appendChild(ratingSpan);

            a.appendChild(nameSpan);
            a.appendChild(metaSpan);

            li.appendChild(a);
            list.appendChild(li);
            root._psItems.push(li);
        }

        list.hidden = false;
        setExpanded(input, true);
        announce(root, players.length + ' matches.');
    }

    function initRoot(root) {
        if (root.getAttribute('data-ps-initialized') === '1') {
            return;
        }
        root.setAttribute('data-ps-initialized', '1');

        var realm = root.getAttribute('data-player-search-realm') || 'online';
        var profilePage = root.getAttribute('data-player-profile-page') || '/player/profile.php';
        var showRealmLabel = root.getAttribute('data-player-search-cross-realm') === '1';
        var input = root.querySelector('.player-search-input');
        var list = root.querySelector('.player-search-results');
        if (!input || !list) {
            return;
        }

        root._psAbort = null;
        root._psItems = [];
        root._psActiveIndex = -1;

        var runFetch = debounce(function () {
            var q = input.value.replace(/^\s+|\s+$/g, '');
            if (q.length < MIN_CHARS) {
                if (root._psAbort) {
                    root._psAbort.abort();
                }
                closeList(root, input, list);
                announce(root, '');
                return;
            }

            if (root._psAbort) {
                root._psAbort.abort();
            }
            if (typeof AbortController !== 'undefined') {
                root._psAbort = new AbortController();
            }

            var url = API_PATH + '?realm=' + encodeURIComponent(realm)
                + '&q=' + encodeURIComponent(q)
                + '&limit=15';

            var fetchOpts = { credentials: 'same-origin' };
            if (root._psAbort) {
                fetchOpts.signal = root._psAbort.signal;
            }

            fetch(url, fetchOpts)
                .then(function (r) {
                    if (!r.ok) {
                        throw new Error('bad_status');
                    }
                    return r.json();
                })
                .then(function (data) {
                    var players = data.players || [];
                    renderResults(root, input, list, players, profilePage, showRealmLabel);
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
            if (!list.hidden && root._psItems && root._psItems.length) {
                if (ev.key === 'ArrowDown') {
                    ev.preventDefault();
                    moveHighlight(list, 1, root);
                    return;
                }
                if (ev.key === 'ArrowUp') {
                    ev.preventDefault();
                    moveHighlight(list, -1, root);
                    return;
                }
                if (ev.key === 'Enter') {
                    ev.preventDefault();
                    pickActive(root, input, list, profilePage, realm);
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

        list.addEventListener('mousedown', function (ev) {
            var t = ev.target;
            if (t && t.closest && t.closest('a')) {
                ev.preventDefault();
            }
        });

        list.addEventListener('click', function (ev) {
            var a = ev.target && ev.target.closest ? ev.target.closest('a') : null;
            if (!a) {
                return;
            }
            ev.preventDefault();
            var playerId = a.getAttribute('data-player-id');
            if (!playerId) {
                return;
            }
            navigatePick(root, { id: playerId, realm: playerRealmFromAnchor(a, realm) }, profilePage);
        });
    }

    function boot() {
        var roots = document.querySelectorAll('.player-search[data-player-search-realm]');
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
