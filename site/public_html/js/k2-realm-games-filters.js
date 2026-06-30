/**
 * All games vault filters — player/opponent pickers + opponent search.
 */
(function () {
    'use strict';

    var OPPONENT_API = '/api/player_h2h_opponent_search.php';

    function filterRealm(form) {
        var realm = form.getAttribute('data-k2-realm-games-realm') || 'online';
        return realm === 'amiga' ? 'amiga' : 'online';
    }
    var DEBOUNCE_MS = 200;
    var MIN_CHARS = 2;

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

    function storeCarryScroll() {
        if (window.K2CarryScroll && typeof window.K2CarryScroll.store === 'function') {
            window.K2CarryScroll.store();
        }
    }

    function filterForm(root) {
        return root.closest('.k2-realm-games-filters__form');
    }

    function hiddenInput(form, key) {
        if (!form) {
            return null;
        }
        return form.querySelector('#k2-realm-games-' + key);
    }

    function submitFilterForm(form) {
        if (!form) {
            return;
        }
        storeCarryScroll();
        form.submit();
    }

    function navigateFilterUrl(form, updates) {
        var base = form.getAttribute('data-k2-realm-games-filter-base') || form.getAttribute('action') || window.location.pathname;
        var path = base;
        var params = new URLSearchParams(window.location.search);

        if (base.indexOf('?') >= 0) {
            var qIdx = base.indexOf('?');
            path = base.slice(0, qIdx);
            params = new URLSearchParams(base.slice(qIdx + 1));
        }

        Object.keys(updates).forEach(function (key) {
            var value = updates[key];
            if (value === null || value === undefined || value === '' || value === '0' || value === 0) {
                params.delete(key);
            } else {
                params.set(key, String(value));
            }
        });

        params.delete('offset');

        storeCarryScroll();
        var query = params.toString();
        window.location.href = query ? path + '?' + query : path;
    }

    function viaInput(form, entity) {
        return form.querySelector('#k2-realm-games-' + entity + '-via');
    }

    function setVia(form, entity, via) {
        var input = viaInput(form, entity);
        if (input) {
            input.value = via || '';
        }
    }

    function clearOpponentFilter(form) {
        hiddenInput(form, 'opponent').value = '0';
        setVia(form, 'opponent', '');
    }

    /**
     * Clearing the player/opponent search field (native X or delete-to-empty) drops that
     * filter only — other active filters are preserved.
     */
    function initActiveSearchClear(form, config) {
        var entityKey = config.entityKey;
        var entityInput = hiddenInput(form, entityKey);
        var viaInputEl = viaInput(form, entityKey);
        if (!entityInput) {
            return;
        }

        var searchRoot = form.querySelector(config.rootSelector);
        if (!searchRoot) {
            return;
        }

        var input = searchRoot.querySelector('.player-search-input');
        if (!input) {
            return;
        }

        var pendingClear = false;

        function isActiveViaSearch() {
            if (String(entityInput.value || '0') === '0') {
                return false;
            }

            return viaInputEl && viaInputEl.value === 'search';
        }

        function clearFilterIfEmpty() {
            if (pendingClear || !isActiveViaSearch() || input.value.trim() !== '') {
                return;
            }

            pendingClear = true;
            var updates = {};
            updates[entityKey] = null;
            updates[entityKey + '_via'] = null;
            if (entityKey === 'player') {
                updates.opponent = null;
                updates.opponent_via = null;
            }
            navigateFilterUrl(form, updates);
        }

        input.addEventListener('search', clearFilterIfEmpty);
        input.addEventListener('input', clearFilterIfEmpty);
    }

    function initPlayerSearchClear(form) {
        initActiveSearchClear(form, {
            entityKey: 'player',
            rootSelector: '.player-search[data-player-search-mode="filter"]'
        });
    }

    function initOpponentSearchClear(form) {
        initActiveSearchClear(form, {
            entityKey: 'opponent',
            rootSelector: '[data-k2-realm-games-opponent-search] .player-search'
        });
    }

    function initListboxPicker(form, valueInputId, entityKey, via, onChange) {
        var valueInput = form.querySelector('#' + valueInputId);
        if (!valueInput) {
            return;
        }

        valueInput.addEventListener('change', function () {
            var val = this.value || '0';
            hiddenInput(form, entityKey).value = val;
            setVia(form, entityKey, val === '0' ? '' : via);
            if (typeof onChange === 'function') {
                onChange(val);
            }
            submitFilterForm(form);
        });
    }

    function initPlayerPickers(form) {
        var onPlayerChange = function (val) {
            clearOpponentFilter(form);
            if (String(val) === '0') {
                setVia(form, 'player', '');
            }
        };

        initListboxPicker(form, 'k2-realm-games-player-rating', 'player', 'rating', onPlayerChange);
        initListboxPicker(form, 'k2-realm-games-player-alpha', 'player', 'alpha', onPlayerChange);
    }

    function initOpponentPickers(form) {
        initListboxPicker(form, 'k2-realm-games-opponent-games', 'opponent', 'games', function (val) {
            if (String(val) === '0') {
                setVia(form, 'opponent', '');
            }
        });
        initListboxPicker(form, 'k2-realm-games-opponent-alpha', 'opponent', 'alpha', function (val) {
            if (String(val) === '0') {
                setVia(form, 'opponent', '');
            }
        });
    }

    function initListboxes(form) {
        if (typeof window.K2ArchiveListbox !== 'undefined') {
            window.K2ArchiveListbox.init(form);
        }

        form.addEventListener('change', function (e) {
            var target = e.target;
            if (target && target.classList && target.classList.contains('k2-archive-listbox__value') && target.name) {
                submitFilterForm(form);
            }
        });
    }

    function initYearModeCoupling(form) {
        var yearInput = form.querySelector('#k2-realm-games-year');
        var modeInput = form.querySelector('#k2-realm-games-year-mode');
        if (!yearInput || !modeInput) {
            return;
        }

        yearInput.addEventListener('change', function () {
            if (String(this.value) !== '0') {
                modeInput.setAttribute('name', 'year_mode');
                return;
            }

            modeInput.value = 'in';
            modeInput.removeAttribute('name');
            var modeBox = modeInput.closest('[data-k2-archive-listbox]');
            if (modeBox && window.K2ArchiveListbox && typeof window.K2ArchiveListbox.setValue === 'function') {
                window.K2ArchiveListbox.setValue(modeBox, 'in', 'Just this year', true);
            }
        });
    }

    function metaLabel(gamesVs) {
        if (gamesVs > 0) {
            return gamesVs + ' game' + (gamesVs === 1 ? '' : 's');
        }
        return 'No games';
    }

    function initOpponentSearch(form) {
        var root = form.querySelector('[data-k2-realm-games-opponent-search]');
        if (!root) {
            return;
        }

        var playerId = root.getAttribute('data-player-id');
        var input = root.querySelector('.player-search-input');
        var list = root.querySelector('.player-search-results');
        if (!playerId || playerId === '0' || !input || !list) {
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
            navigateFilterUrl(form, { opponent: opponentId, opponent_via: 'search' });
        }

        function renderGroup(label, players) {
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
                btn.addEventListener('click', (function (id) {
                    return function () {
                        selectOpponent(id);
                    };
                })(p.id));

                li.appendChild(btn);
                list.appendChild(li);
            }
        }

        var timer = null;
        input.addEventListener('input', function () {
            clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < MIN_CHARS) {
                closeList();
                return;
            }
            timer = setTimeout(function () {
                var url = OPPONENT_API + '?realm=' + encodeURIComponent(filterRealm(form))
                    + '&player_id=' + encodeURIComponent(playerId) + '&q=' + encodeURIComponent(q);
                fetch(url, { credentials: 'same-origin' })
                    .then(function (res) {
                        return res.json();
                    })
                    .then(function (data) {
                        list.innerHTML = '';
                        renderGroup('Opponents', data.played || []);
                        renderGroup('Other players', data.others || []);
                        if (!list.children.length) {
                            closeList();
                            return;
                        }
                        list.hidden = false;
                        input.setAttribute('aria-expanded', 'true');
                    })
                    .catch(function () {
                        closeList();
                    });
            }, DEBOUNCE_MS);
        });

        document.addEventListener('click', function (e) {
            if (!root.contains(e.target)) {
                closeList();
            }
        });
    }

    function initForm(form) {
        if (form._k2RealmGamesFiltersBound) {
            if (typeof window.K2ArchiveListbox !== 'undefined') {
                window.K2ArchiveListbox.init(form);
            }
            return;
        }
        form._k2RealmGamesFiltersBound = true;
        initListboxes(form);
        initYearModeCoupling(form);
        initPlayerPickers(form);
        initOpponentPickers(form);
        initPlayerSearchClear(form);
        initOpponentSearchClear(form);
        initOpponentSearch(form);
    }

    function boot() {
        var forms = document.querySelectorAll('.k2-realm-games-filters__form');
        for (var i = 0; i < forms.length; i++) {
            initForm(forms[i]);
        }
    }

    onReady(boot);
}());
