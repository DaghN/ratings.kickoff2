/**
 * H2H scoreline heatmap — full GF×GA grid (subject POV), outcome tint + count intensity.
 * Click cell → games tab ?gf= + ?ga= + opponent=.
 */
(function () {
    'use strict';

    var API_PATH = '/api/player_h2h_scoreline_heatmap.php';
    var EVENT_NAME = 'kool-opponent-selected';
    var CTX = window.K2PlayerOpponentsH2hContext;
    var TIP_ID = 'k2-h2h-scoreline-heatmap-tooltip';
    var META_HINT = 'Each square is how many times this scoreline happened.';
    var LABEL_COL = 28;
    var GAP = 2;
    var CELL_SIZE = 36;

    function axisLimits(cells, data) {
        var maxGf = typeof data.maxGoalsFor === 'number' ? data.maxGoalsFor : -1;
        var maxGa = typeof data.maxGoalsAgainst === 'number' ? data.maxGoalsAgainst : -1;
        if (maxGf < 0 || maxGa < 0) {
            maxGf = 0;
            maxGa = 0;
            var i;
            for (i = 0; i < cells.length; i++) {
                if (cells[i].goals_for > maxGf) {
                    maxGf = cells[i].goals_for;
                }
                if (cells[i].goals_against > maxGa) {
                    maxGa = cells[i].goals_against;
                }
            }
        }
        return { maxGf: maxGf, maxGa: maxGa };
    }

    function effectiveLevelCount(peak) {
        if (peak <= 0) {
            return 0;
        }
        if (peak <= 1) {
            return 1;
        }
        return Math.min(LEVEL_COUNT, peak);
    }

    function cellKey(gf, ga) {
        return gf + ':' + ga;
    }

    function gamesListUrl(playerId, goalsFor, goalsAgainst, opponentId, ctxEl) {
        if (CTX) {
            return CTX.gamesListUrl(ctxEl, playerId, {
                gf: goalsFor,
                ga: goalsAgainst,
                opponent: opponentId
            });
        }
        return '/player/games.php?id=' + encodeURIComponent(String(playerId))
            + '&gf=' + encodeURIComponent(String(goalsFor))
            + '&ga=' + encodeURIComponent(String(goalsAgainst))
            + '&opponent=' + encodeURIComponent(String(opponentId))
            + '#k2-player-games-filters';
    }

    var LEVEL_COUNT = 8;
    var MIX_MIN = 30;

    function countToLevel(count, maxCount) {
        if (count <= 0) {
            return 0;
        }
        var levels = effectiveLevelCount(maxCount);
        if (levels <= 1) {
            return 1;
        }
        var level = Math.ceil((count / maxCount) * levels);
        if (level < 1) {
            level = 1;
        }
        if (level > levels) {
            level = levels;
        }
        return level;
    }

    function levelToMixPercent(level, levelCount) {
        if (level <= 0) {
            return 0;
        }
        levelCount = levelCount || LEVEL_COUNT;
        if (levelCount <= 1) {
            return 100;
        }
        if (level >= levelCount) {
            return 100;
        }
        var span = 100 - MIX_MIN;

        return Math.round(MIX_MIN + ((level - 1) / (levelCount - 1)) * span);
    }

    function countRangeLabel(level, peak, levelCount) {
        if (peak <= 0 || levelCount <= 0) {
            return '';
        }
        if (levelCount <= 1 || peak <= 1) {
            return level === 1 ? '1' : '';
        }
        var lo = level === 1 ? 1 : Math.floor(((level - 1) / levelCount) * peak) + 1;
        var hi = level === levelCount ? peak : Math.floor((level / levelCount) * peak);
        if (lo > hi) {
            return '';
        }
        if (lo === hi) {
            return String(lo);
        }
        return lo + '–' + hi;
    }

    function appendIntensityScale(container, peak, playerName, opponentName) {
        var levelCount = effectiveLevelCount(peak);
        if (levelCount <= 0) {
            return;
        }

        var scale = document.createElement('div');
        scale.className = 'h2h-scoreline-heatmap__scale';

        var matrix = document.createElement('div');
        matrix.className = 'h2h-scoreline-heatmap__scale-matrix';

        var gutter = document.createElement('div');
        gutter.className = 'h2h-scoreline-heatmap__scale-gutter';

        var heroWinLabel = playerName + ' win';
        var rivalWinLabel = opponentName + ' win';

        var heroLabel = document.createElement('span');
        heroLabel.className = 'h2h-scoreline-heatmap__scale-row-label h2h-scoreline-heatmap__scale-row-label--hero';
        heroLabel.textContent = heroWinLabel;
        heroLabel.setAttribute('title', heroWinLabel);

        var rivalLabel = document.createElement('span');
        rivalLabel.className = 'h2h-scoreline-heatmap__scale-row-label h2h-scoreline-heatmap__scale-row-label--rival';
        rivalLabel.textContent = rivalWinLabel;
        rivalLabel.setAttribute('title', rivalWinLabel);

        gutter.appendChild(heroLabel);
        gutter.appendChild(rivalLabel);
        gutter.appendChild(document.createElement('span'));

        var cols = document.createElement('div');
        cols.className = 'h2h-scoreline-heatmap__scale-cols';

        var level;
        for (level = 1; level <= levelCount; level++) {
            var col = document.createElement('div');
            col.className = 'h2h-scoreline-heatmap__scale-col';

            var winSwatch = document.createElement('span');
            winSwatch.className = 'h2h-scoreline-heatmap__cell h2h-scoreline-heatmap__scale-swatch';
            winSwatch.setAttribute('data-outcome', 'win');
            winSwatch.style.setProperty('--h2h-sh-mix', levelToMixPercent(level, levelCount) + '%');
            winSwatch.setAttribute('aria-hidden', 'true');

            var lossSwatch = document.createElement('span');
            lossSwatch.className = 'h2h-scoreline-heatmap__cell h2h-scoreline-heatmap__scale-swatch';
            lossSwatch.setAttribute('data-outcome', 'loss');
            lossSwatch.style.setProperty('--h2h-sh-mix', levelToMixPercent(level, levelCount) + '%');
            lossSwatch.setAttribute('aria-hidden', 'true');

            var countLabel = document.createElement('span');
            countLabel.className = 'h2h-scoreline-heatmap__scale-count';
            countLabel.textContent = countRangeLabel(level, peak, levelCount);

            col.appendChild(winSwatch);
            col.appendChild(lossSwatch);
            col.appendChild(countLabel);
            cols.appendChild(col);
        }

        matrix.appendChild(gutter);
        matrix.appendChild(cols);
        scale.appendChild(matrix);
        container.appendChild(scale);
    }

    function cellOutcome(gf, ga) {
        if (gf > ga) {
            return 'win';
        }
        if (gf < ga) {
            return 'loss';
        }
        return 'draw';
    }

    function buildLookup(cells) {
        var map = Object.create(null);
        var i;
        for (i = 0; i < cells.length; i++) {
            var cell = cells[i];
            map[cellKey(cell.goals_for, cell.goals_against)] = cell;
        }
        return map;
    }

    function maxGames(cells) {
        var max = 0;
        var i;
        for (i = 0; i < cells.length; i++) {
            if (cells[i].games > max) {
                max = cells[i].games;
            }
        }
        return max;
    }

    function tooltip() {
        var tip = document.getElementById(TIP_ID);
        if (tip) {
            return tip;
        }
        tip = document.createElement('div');
        tip.id = TIP_ID;
        tip.className = 'k2-table-tooltip';
        tip.setAttribute('role', 'tooltip');
        tip.setAttribute('aria-hidden', 'true');
        tip.innerHTML = '<div class="k2-table-tooltip__title"></div><div class="k2-table-tooltip__body"></div>';
        tip.hidden = true;
        document.body.appendChild(tip);
        return tip;
    }

    function positionTooltip(anchor, tip) {
        var rect = anchor.getBoundingClientRect();
        var tipRect;
        var margin = 8;
        var left;
        var top;
        tip.style.left = '0px';
        tip.style.top = '0px';
        tip.hidden = false;
        tipRect = tip.getBoundingClientRect();
        left = rect.left + rect.width / 2 - tipRect.width / 2;
        left = Math.max(margin, Math.min(left, window.innerWidth - tipRect.width - margin));
        top = rect.top - tipRect.height - margin;
        if (top < margin) {
            top = rect.bottom + margin;
        }
        tip.style.left = Math.round(left) + 'px';
        tip.style.top = Math.round(top) + 'px';
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function showScorelineTooltip(cell, playerName, opponentName, goalsFor, goalsAgainst, games, pinned) {
        var tip = tooltip();
        var titleEl = tip.querySelector('.k2-table-tooltip__title');
        var bodyEl = tip.querySelector('.k2-table-tooltip__body');
        if (titleEl) {
            titleEl.innerHTML = '<span class="h2h-scoreline-heatmap__tip-hero">' + escapeHtml(playerName) + '</span> '
                + goalsFor + '-' + goalsAgainst + ' '
                + '<span class="h2h-scoreline-heatmap__tip-rival">' + escapeHtml(opponentName) + '</span>';
        }
        if (bodyEl) {
            bodyEl.textContent = games + ' game' + (games === 1 ? '' : 's')
                + (pinned ? ' · Tap again to view games' : ' · Click to filter games list');
            bodyEl.style.display = '';
        }
        tip.setAttribute('aria-hidden', 'false');
        positionTooltip(cell, tip);
    }

    function hideTooltip() {
        var tip = document.getElementById(TIP_ID);
        if (tip) {
            tip.hidden = true;
            tip.setAttribute('aria-hidden', 'true');
        }
        clearPinnedCell();
    }

    function bindActiveCell(cell, playerName, opponentName, playerId, opponentId, gf, ga, games, ctxEl) {
        var href = gamesListUrl(playerId, gf, ga, opponentId, ctxEl);
        var coarse = isCoarsePointer();

        if (coarse) {
            cell.type = 'button';
            cell.setAttribute('data-games-href', href);
            cell.addEventListener('click', function (evt) {
                evt.preventDefault();
                var el = evt.currentTarget;
                if (pinnedCell === el) {
                    window.location.href = href;
                    return;
                }
                pinCell(el);
                showScorelineTooltip(
                    el,
                    playerName,
                    opponentName,
                    parseInt(el.getAttribute('data-gf') || '0', 10),
                    parseInt(el.getAttribute('data-ga') || '0', 10),
                    parseInt(el.getAttribute('data-games') || '0', 10),
                    true
                );
            });
            return;
        }

        cell.href = href;
        cell.addEventListener('mouseenter', function (evt) {
            var el = evt.currentTarget;
            showScorelineTooltip(
                el,
                playerName,
                opponentName,
                parseInt(el.getAttribute('data-gf') || '0', 10),
                parseInt(el.getAttribute('data-ga') || '0', 10),
                parseInt(el.getAttribute('data-games') || '0', 10),
                false
            );
        });
        cell.addEventListener('mouseleave', hideTooltip);
        cell.addEventListener('focus', function (evt) {
            evt.currentTarget.dispatchEvent(new MouseEvent('mouseenter', { bubbles: true }));
        });
        cell.addEventListener('blur', hideTooltip);
    }

    var touchDismissInstalled = false;
    var pinnedCell = null;

    function isCoarsePointer() {
        return window.matchMedia('(pointer: coarse)').matches
            || window.matchMedia('(hover: none)').matches;
    }

    function clearPinnedCell() {
        if (pinnedCell) {
            pinnedCell.classList.remove('h2h-scoreline-heatmap__cell--pinned');
            pinnedCell = null;
        }
    }

    function pinCell(cell) {
        clearPinnedCell();
        pinnedCell = cell;
        cell.classList.add('h2h-scoreline-heatmap__cell--pinned');
    }

    function installTouchDismiss() {
        if (touchDismissInstalled) {
            return;
        }
        touchDismissInstalled = true;
        window.addEventListener('scroll', hideTooltip, { passive: true, capture: true });
        document.addEventListener('pointerdown', function (evt) {
            if (!pinnedCell) {
                return;
            }
            var tip = document.getElementById(TIP_ID);
            var target = evt.target;
            if (target === pinnedCell || pinnedCell.contains(target)) {
                return;
            }
            if (tip && !tip.hidden && tip.contains(target)) {
                return;
            }
            hideTooltip();
        }, true);
    }

    function setHeading(root, opponentLabel) {
        var matchups = root.closest('.pm3d-matchups');
        var heading = matchups ? matchups.querySelector('.player-h2h-scoreline-heatmap-heading') : null;
        if (!heading) {
            return;
        }
        heading.textContent = opponentLabel
            ? 'Scoreline heatmap vs ' + opponentLabel
            : 'Scoreline heatmap';
    }

    function resizeHeatmap(container, wrap) {
        if (!container || !wrap) {
            return;
        }
        var maxCols = parseInt(container.getAttribute('data-cols') || '0', 10);
        var maxRows = parseInt(container.getAttribute('data-rows') || '0', 10);
        if (!maxCols || !maxRows) {
            return;
        }

        var w = wrap.clientWidth;
        var reserved = (2 * LABEL_COL) + (2 * GAP);
        var cell = CELL_SIZE;
        var totalWidth = reserved + maxCols * cell + (maxCols - 1) * GAP;
        container.style.setProperty('--h2h-sh-cell', cell + 'px');
        container.style.setProperty('--h2h-sh-cols', String(maxCols));
        container.style.setProperty('--h2h-sh-rows', String(maxRows));
        container.classList.toggle('h2h-scoreline-heatmap--scrolls', totalWidth > w);
    }

    function renderHeatmap(root, data) {
        var wrap = root.querySelector('.h2h-scoreline-heatmap-wrap');
        var status = root.querySelector('.player-h2h-scoreline-heatmap-status');
        var meta = root.querySelector('.player-h2h-scoreline-heatmap-meta');
        var playerId = root.getAttribute('data-player-id');
        var opponentId = root.getAttribute('data-opponent-id') || '';
        var cells = data.cells || [];
        var limits = axisLimits(cells, data);
        var maxGf = limits.maxGf;
        var maxGa = limits.maxGa;

        if (!wrap) {
            return;
        }

        wrap.textContent = '';
        hideTooltip();

        if (data.opponentName) {
            setHeading(root, data.opponentName);
        }

        if (meta) {
            meta.textContent = META_HINT;
        }

        if (!cells.length || maxGf < 0) {
            if (status) {
                status.textContent = 'No rated games against this opponent.';
            }
            return;
        }

        if (status) {
            status.textContent = '';
        }

        var lookup = buildLookup(cells);
        var peak = maxGames(cells);
        var levelCount = effectiveLevelCount(peak);
        var playerName = data.playerName || 'You';
        var opponentName = data.opponentName || 'Opponent';
        var gf;
        var ga;

        var container = document.createElement('div');
        container.className = 'h2h-scoreline-heatmap';
        container.setAttribute('data-cols', String(maxGa + 1));
        container.setAttribute('data-rows', String(maxGf + 1));

        var body = document.createElement('div');
        body.className = 'h2h-scoreline-heatmap__body';

        var yTitle = document.createElement('div');
        yTitle.className = 'h2h-scoreline-heatmap__y-title';
        yTitle.textContent = playerName;
        body.appendChild(yTitle);

        var plot = document.createElement('div');
        plot.className = 'h2h-scoreline-heatmap__plot';

        var grid = document.createElement('div');
        grid.className = 'h2h-scoreline-heatmap__grid';
        grid.setAttribute('role', 'grid');
        grid.setAttribute('aria-label', 'Scoreline frequency heatmap');

        for (gf = maxGf; gf >= 0; gf--) {
            var yTick = document.createElement('span');
            yTick.className = 'h2h-scoreline-heatmap__y-tick';
            yTick.textContent = String(gf);
            grid.appendChild(yTick);

            for (ga = 0; ga <= maxGa; ga++) {
                var hit = lookup[cellKey(gf, ga)];
                var games = hit ? hit.games : 0;
                var outcome = hit ? hit.outcome : cellOutcome(gf, ga);
                var level = countToLevel(games, peak);
                var cell = document.createElement(hit && games > 0 ? (isCoarsePointer() ? 'button' : 'a') : 'span');
                cell.className = 'h2h-scoreline-heatmap__cell';
                cell.setAttribute('role', 'gridcell');
                cell.setAttribute('data-outcome', games > 0 ? outcome : 'empty');
                cell.setAttribute('data-level', String(level));
                cell.setAttribute('aria-label', playerName + ' ' + gf + '-' + ga + ' ' + opponentName + ', ' + games + ' games');

                if (games > 0) {
                    cell.style.setProperty('--h2h-sh-mix', levelToMixPercent(level, levelCount) + '%');
                }

                if (hit && games > 0) {
                    bindActiveCell(cell, playerName, opponentName, playerId, opponentId, gf, ga, games, root);
                }

                cell.setAttribute('data-gf', String(gf));
                cell.setAttribute('data-ga', String(ga));
                cell.setAttribute('data-games', String(games));
                if (games > 0) {
                    cell.setAttribute('data-outcome', outcome);
                }

                grid.appendChild(cell);
            }
        }

        var xFooter = document.createElement('div');
        xFooter.className = 'h2h-scoreline-heatmap__x-footer';

        var xTicks = document.createElement('div');
        xTicks.className = 'h2h-scoreline-heatmap__x-ticks';
        var xCorner = document.createElement('span');
        xCorner.className = 'h2h-scoreline-heatmap__grid-corner';
        xCorner.setAttribute('aria-hidden', 'true');
        xTicks.appendChild(xCorner);

        for (ga = 0; ga <= maxGa; ga++) {
            var xTick = document.createElement('span');
            xTick.className = 'h2h-scoreline-heatmap__tick';
            xTick.textContent = String(ga);
            xTicks.appendChild(xTick);
        }
        xFooter.appendChild(xTicks);

        var xTitle = document.createElement('div');
        xTitle.className = 'h2h-scoreline-heatmap__axis-title h2h-scoreline-heatmap__axis-title--x';
        xTitle.textContent = opponentName;
        xFooter.appendChild(xTitle);

        plot.appendChild(grid);
        plot.appendChild(xFooter);
        body.appendChild(plot);
        container.appendChild(body);
        appendIntensityScale(container, peak, playerName, opponentName);

        wrap.appendChild(container);

        function doResize() {
            resizeHeatmap(container, wrap);
        }

        doResize();
        if (typeof ResizeObserver !== 'undefined') {
            if (root._k2ScorelineResizeObserver) {
                root._k2ScorelineResizeObserver.disconnect();
            }
            var ro = new ResizeObserver(doResize);
            ro.observe(wrap);
            root._k2ScorelineResizeObserver = ro;
        } else {
            window.addEventListener('resize', doResize);
        }

        installTouchDismiss();
    }

    function initRoot(root) {
        if (root.getAttribute('data-k2-chart-bound') === '1') {
            return;
        }
        root.setAttribute('data-k2-chart-bound', '1');

        var playerId = root.getAttribute('data-player-id');
        if (!playerId) {
            return;
        }

        function loadOpponent(opponentId) {
            if (!opponentId) {
                return;
            }

            root.setAttribute('data-opponent-id', String(opponentId));

            var status = root.querySelector('.player-h2h-scoreline-heatmap-status');
            var wrap = root.querySelector('.h2h-scoreline-heatmap-wrap');
            if (status) {
                status.textContent = 'Loading scoreline heatmap…';
            }
            if (wrap) {
                wrap.textContent = '';
            }

            var url = API_PATH + '?id=' + encodeURIComponent(playerId)
                + '&opponent=' + encodeURIComponent(opponentId)
                + (CTX ? CTX.apiSuffix(root) : '&realm=online');

            fetch(url, { credentials: 'same-origin' })
                .then(function (r) {
                    if (!r.ok) {
                        throw new Error('bad_status');
                    }
                    return r.json();
                })
                .then(function (data) {
                    renderHeatmap(root, data);
                })
                .catch(function () {
                    if (status) {
                        status.textContent = 'Could not load scoreline heatmap.';
                    }
                });
        }

        document.addEventListener(EVENT_NAME, function (e) {
            if (!e.detail || String(e.detail.playerId) !== String(playerId)) {
                return;
            }
            loadOpponent(e.detail.opponentId);
        });

        var h2hRoot = root.closest('.k2-player-opponents-h2h');
        if (h2hRoot) {
            var initialId = h2hRoot.getAttribute('data-chart-opponent-id');
            if (initialId) {
                loadOpponent(initialId);
            }
        } else {
            var staticOpponentId = root.getAttribute('data-opponent-id');
            if (staticOpponentId) {
                loadOpponent(staticOpponentId);
            }
        }
    }

    function boot() {
        var roots = document.querySelectorAll('.player-h2h-scoreline-heatmap');
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
