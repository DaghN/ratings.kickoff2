/**
 * Compare two players' Amiga Elo rank over time (shared date + Y axis).
 * Listens for kool-opponent-selected.
 */
(function () {
    'use strict';

    var Core = window.K2PlayerRankChartCore;
    var T = window.K2ChartTheme;
    var DR = window.K2ChartDateRange;
    var CTX = window.K2PlayerOpponentsH2hContext;
    var API_PATH = '/api/player_compare_rank_history.php';
    var EVENT_NAME = 'kool-opponent-selected';

    if (!Core) {
        return;
    }

    function fetchJson(url) {
        return fetch(url, { credentials: 'same-origin' }).then(function (r) {
            if (!r.ok) {
                throw new Error('bad_status');
            }
            return r.json();
        });
    }

    function setToggleGroup(root, selector, attr, value) {
        var buttons = root.querySelectorAll(selector + ' .pm3d-rating-toggle__btn');
        for (var i = 0; i < buttons.length; i++) {
            var btn = buttons[i];
            var active = btn.getAttribute(attr) === value;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        }
    }

    function syncToolbarVisibility(root, state) {
        var toolbar = root.querySelector('.player-compare-rank-chart__toolbar');
        if (toolbar) {
            toolbar.setAttribute('data-range-mode', state.scale);
        }
    }

    function setHeading(root, opponentLabel) {
        var matchups = root.closest('.pm3d-matchups');
        var heading = matchups ? matchups.querySelector('.player-compare-rank-chart-heading') : null;
        if (!heading) {
            return;
        }
        heading.textContent = opponentLabel
            ? 'Rank comparison vs ' + opponentLabel
            : 'Rank comparison';
    }

    function destroyChart(state) {
        if (state.chart && typeof state.chart.destroy === 'function') {
            state.chart.destroy();
        }
        state.chart = null;
    }

    function buildCompareChart(canvas, playerPoints, opponentPoints, mergedMeta, timelineStart, state, playerName, opponentName) {
        var domain = Core.computeDomain(state.scale, state.linearWindow, state.percentileWindow, mergedMeta);
        var playerSeries = Core.buildSeries(playerPoints, state.scale, domain);
        var opponentSeries = Core.buildSeries(opponentPoints, state.scale, domain);
        var bandOk = Core.compareBandHasAnyPlayer(
            playerPoints,
            opponentPoints,
            state.scale,
            state.linearWindow,
            state.percentileWindow
        );
        var showData = bandOk && Core.anyPlottedSeries([playerSeries, opponentSeries]);
        var playerData = showData && Core.hasPlottedPoints(playerSeries) ? playerSeries : [];
        var opponentData = showData && Core.hasPlottedPoints(opponentSeries) ? opponentSeries : [];
        var timeRange = Core.rankChartTimeRange(
            [playerPoints, opponentPoints],
            timelineStart,
            mergedMeta.cutoffActive
        );

        return Core.createChart(canvas, {
            type: 'line',
            data: {
                datasets: [
                    Object.assign({
                        label: (playerName || 'Player') + ' rank',
                        data: playerData,
                        spanGaps: false,
                        stepped: true,
                        tension: 0,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointHitRadius: 12,
                        borderColor: T.h2hSubjectBorder(),
                        backgroundColor: T.h2hSubjectFill(0.1),
                        borderWidth: 2,
                        fill: false
                    }, T.h2hSubjectPointHover ? T.h2hSubjectPointHover() : {}),
                    Object.assign({
                        label: (opponentName || 'Opponent') + ' rank',
                        data: opponentData,
                        spanGaps: false,
                        stepped: true,
                        tension: 0,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointHitRadius: 12,
                        borderColor: T.h2hOpponentBorder(),
                        backgroundColor: T.h2hOpponentFill(0.1),
                        borderWidth: 2,
                        fill: false
                    }, T.h2hOpponentPointHover ? T.h2hOpponentPointHover() : {})
                ]
            },
            options: Core.withCareerPlotGutter({
                interaction: { mode: 'nearest', axis: 'x', intersect: false },
                plugins: {
                    legend: {
                        labels: { color: T.textPrimary() }
                    },
                    tooltip: T.mergeTooltip({
                        enabled: false,
                        external: Core.bindCompareRankExternalTooltip(T),
                        filter: function (item) {
                            return item.raw && !item.raw.clipped && item.raw.y != null;
                        }
                    }),
                    k2CompareDateTooltipBridge: (DR && DR.compareDateTooltipBridgePlugin)
                        ? DR.compareDateTooltipBridgePlugin()
                        : undefined
                },
                scales: {
                    x: Core.buildTimeXScale(timeRange),
                    y: Object.assign({ title: { display: false } }, Core.yAxisConfig(state.scale, domain))
                }
            }, 'line')
        });
    }

    function renderChart(root, state) {
        var canvas = root.querySelector('.player-compare-rank-canvas');
        var status = root.querySelector('.player-compare-rank-chart-status');
        var subjectSummary = root.querySelector('.player-compare-rank-peak-subject');
        var opponentSummary = root.querySelector('.player-compare-rank-peak-opponent');
        var frame = root.querySelector('.k2-chart-frame');
        if (!canvas || !state.data) {
            return;
        }

        destroyChart(state);

        var player = state.data.player || {};
        var opponent = state.data.opponent || {};
        var playerPoints = player.points || [];
        var opponentPoints = opponent.points || [];

        if (!playerPoints.length && !opponentPoints.length) {
            if (subjectSummary) {
                subjectSummary.hidden = true;
            }
            if (opponentSummary) {
                opponentSummary.hidden = true;
            }
            if (status) {
                status.textContent = 'No rank history to chart.';
            }
            if (frame) {
                frame.hidden = true;
            }
            return;
        }

        var mergedMeta = Core.mergeCompareMeta(player.meta, opponent.meta);
        Core.renderPeakSummary(subjectSummary, playerPoints, state.scale, {
            namePrefix: player.playerName || 'Player',
            peakValueClass: 'pm3-chart-peak-value pm3-chart-peak-value--subject',
            peakLinkClass: 'pm3-chart-peak-link pm3-chart-peak-link--subject',
            peak: player.peak
        });
        Core.renderPeakSummary(opponentSummary, opponentPoints, state.scale, {
            namePrefix: opponent.playerName || state.opponentName || 'Opponent',
            peakValueClass: 'pm3-chart-peak-value pm3-chart-peak-value--opponent',
            peakLinkClass: 'pm3-chart-peak-link pm3-chart-peak-link--opponent',
            peak: opponent.peak
        });

        state.chart = buildCompareChart(
            canvas,
            playerPoints,
            opponentPoints,
            mergedMeta,
            state.data.timelineStart,
            state,
            player.playerName,
            opponent.playerName || state.opponentName
        );

        if (status) {
            status.textContent = '';
        }
        if (frame) {
            frame.hidden = false;
        }
    }

    function initRoot(root) {
        if (root.getAttribute('data-k2-chart-bound') === '1') {
            return;
        }
        root.setAttribute('data-k2-chart-bound', '1');

        var playerId = root.getAttribute('data-player-id');
        if (!playerId || typeof Chart === 'undefined') {
            return;
        }

        var state = {
            scale: 'linear',
            linearWindow: 'career',
            percentileWindow: 'career',
            data: null,
            chart: null,
            opponentName: ''
        };

        syncToolbarVisibility(root, state);

        if (T && T.registerChartHtmlTooltipScrollDismiss) {
            T.registerChartHtmlTooltipScrollDismiss(function () {
                if (state.chart && !state.chart.destroyed) {
                    if (T.clearChartTooltipHover) {
                        T.clearChartTooltipHover(state.chart);
                    }
                    state.chart.draw();
                }
            });
        }

        function bindToggle(selector, attr, key, onChange) {
            var group = root.querySelector(selector);
            if (!group) {
                return;
            }
            group.addEventListener('click', function (evt) {
                var btn = evt.target.closest('.pm3d-rating-toggle__btn');
                if (!btn || !group.contains(btn)) {
                    return;
                }
                var val = btn.getAttribute(attr);
                if (!val || val === state[key]) {
                    return;
                }
                state[key] = val;
                setToggleGroup(root, selector, attr, val);
                if (onChange) {
                    onChange();
                }
                renderChart(root, state);
            });
        }

        bindToggle('.player-compare-rank-chart__scale', 'data-scale', 'scale', function () {
            syncToolbarVisibility(root, state);
        });
        bindToggle('.player-compare-rank-chart__window', 'data-window', 'linearWindow');
        bindToggle('.player-compare-rank-chart__percentile-window', 'data-pwindow', 'percentileWindow');

        function loadOpponent(opponentId, opponentName) {
            if (!opponentId) {
                return;
            }

            var status = root.querySelector('.player-compare-rank-chart-status');
            if (status) {
                status.textContent = 'Loading rank comparison…';
            }

            var compareUrl = API_PATH + '?id=' + encodeURIComponent(playerId)
                + '&opponent=' + encodeURIComponent(opponentId)
                + (CTX ? CTX.apiSuffix(root) : '&realm=amiga');

            fetchJson(compareUrl)
                .then(function (data) {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    state.data = data;
                    state.opponentName = opponentName || (data.opponent && data.opponent.playerName) || '';
                    setHeading(root, state.opponentName);
                    renderChart(root, state);
                })
                .catch(function () {
                    if (status) {
                        status.textContent = 'Could not load rank comparison.';
                    }
                });
        }

        document.addEventListener(EVENT_NAME, function (e) {
            if (!e.detail || String(e.detail.playerId) !== String(playerId)) {
                return;
            }
            loadOpponent(e.detail.opponentId, e.detail.opponentName);
        });

        var h2hRoot = root.closest('.k2-player-opponents-h2h');
        if (h2hRoot) {
            var initialId = h2hRoot.getAttribute('data-chart-opponent-id');
            if (initialId) {
                loadOpponent(initialId, h2hRoot.getAttribute('data-chart-opponent-name') || '');
            }
        }
    }

    function boot() {
        var roots = document.querySelectorAll('.player-compare-rank-chart');
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
}());