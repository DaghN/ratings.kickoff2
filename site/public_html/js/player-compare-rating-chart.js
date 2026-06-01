/**
 * Compare two players' full career rating over time (shared date axis).
 * Listens for kool-opponent-selected.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;
    var DR = window.K2ChartDateRange;

    var API_PATH = 'api/player_compare_rating_history.php';
    var H2H_API_PATH = 'api/player_head_to_head.php';
    var EVENT_NAME = 'kool-opponent-selected';

    function fetchJson(url) {
        return fetch(url, { credentials: 'same-origin' }).then(function (r) {
            if (!r.ok) {
                throw new Error('bad_status');
            }
            return r.json();
        });
    }

    function compareMetaLine(player, opponent, h2h) {
        var playerGames = player.points ? player.points.length : 0;
        var opponentGames = opponent.points ? opponent.points.length : 0;
        var line = 'Full career rating paths';
        if (h2h && typeof h2h.total_games === 'number' && h2h.total_games > 0) {
            line += ' · ' + h2h.total_games + ' rated game'
                + (h2h.total_games === 1 ? '' : 's') + ' between them';
        }
        line += ' · ' + playerGames + ' career games (this player) · '
            + opponentGames + ' career games (opponent)';
        return line;
    }

    function parseGameDate(dateStr) {
        if (!dateStr) {
            return null;
        }
        var normalized = String(dateStr).trim().replace(' ', 'T');
        var d = new Date(normalized);
        return isNaN(d.getTime()) ? null : d;
    }

    function pointsToChartData(points) {
        var chartData = [];
        for (var i = 0; i < points.length; i++) {
            var x = parseGameDate(points[i].date);
            if (x === null) {
                continue;
            }
            chartData.push({ x: x, y: points[i].rating });
        }
        return chartData;
    }

    function pointsToGameData(points) {
        var chartData = [];
        for (var i = 0; i < points.length; i++) {
            var n = points[i].gameNumber;
            if (n == null || n < 1) {
                n = i + 1;
            }
            chartData.push({
                x: n,
                y: points[i].rating,
                date: points[i].date,
                gameId: points[i].gameId
            });
        }
        return chartData;
    }

    function chartOptions(extra) {
        if (T && T.activityChartOptions) {
            return T.activityChartOptions(Object.assign({ maintainAspectRatio: false }, extra || {}), {
                chartKind: 'line'
            });
        }
        return Object.assign({ responsive: true, maintainAspectRatio: false }, extra || {});
    }

    function createChart(canvas, config) {
        if (T && T.createActivityChart) {
            return T.createActivityChart(canvas, config, 'line');
        }
        return new Chart(canvas, config);
    }

    function setActiveView(root, view, state) {
        var dateView = root.querySelector('.player-compare-rating-view--date');
        var gameView = root.querySelector('.player-compare-rating-view--game');
        var buttons = root.querySelectorAll('.pm3d-rating-toggle__btn');
        var activeChart;
        for (var i = 0; i < buttons.length; i++) {
            var btn = buttons[i];
            var active = btn.getAttribute('data-view') === view;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        }
        if (dateView) {
            dateView.hidden = view !== 'date';
        }
        if (gameView) {
            gameView.hidden = view !== 'game';
        }
        if (view === 'game' && !state.gameChart && state.latestConfig) {
            state.gameChart = renderChart(state.gameCanvas, state.latestConfig, 'game');
        }
        activeChart = view === 'game' ? state.gameChart : state.dateChart;
        if (activeChart && typeof activeChart.resize === 'function') {
            activeChart.resize();
        }
    }

    function renderChart(canvas, cfg, view) {
        var isGame = view === 'game';
        var playerData = isGame ? cfg.playerGameData : cfg.playerDateData;
        var opponentData = isGame ? cfg.opponentGameData : cfg.opponentDateData;
        var xScale = isGame
            ? {
                type: 'linear',
                title: {
                    display: true,
                    text: 'Rated games played',
                    color: T.tickColor()
                },
                ticks: {
                    color: T.tickColor(),
                    maxRotation: 0,
                    autoSkip: true,
                    maxTicksLimit: 14
                },
                grid: { color: T.softGrid ? T.softGrid() : T.grid() }
            }
            : {
                type: 'time',
                min: DR && DR.serverStartDate ? DR.serverStartDate() : undefined,
                max: DR ? DR.endOfToday() : undefined,
                time: {
                    displayFormats: {
                        year: 'yyyy',
                        month: 'MMM yyyy',
                        day: 'MMM d, yyyy'
                    }
                },
                ticks: {
                    color: T.tickColor(),
                    maxRotation: 45,
                    autoSkip: true,
                    maxTicksLimit: 14
                },
                grid: { color: T.softGrid ? T.softGrid() : T.grid() }
            };

        return createChart(canvas, {
            type: 'line',
            data: {
                datasets: [
                    {
                        label: (cfg.player.playerName || 'Player') + ' rating',
                        data: playerData,
                        borderColor: T.profileCompareBorder(),
                        backgroundColor: T.profileCompareFill(0.1),
                        borderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        fill: false,
                        tension: 0.1
                    },
                    {
                        label: (cfg.opponent.playerName || cfg.opponentName || 'Opponent') + ' rating',
                        data: opponentData,
                        borderColor: T.opponentFocusBorder(),
                        backgroundColor: T.opponentFocusFill(0.1),
                        borderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        fill: false,
                        tension: 0.1
                    }
                ]
            },
            options: chartOptions({
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        labels: { color: T.textPrimary() }
                    },
                    tooltip: T.mergeTooltip({
                        callbacks: {
                            title: function (items) {
                                var d;
                                var pt;
                                if (!items.length) {
                                    return '';
                                }
                                if (isGame) {
                                    pt = items[0].raw || {};
                                    return 'Game #' + items[0].parsed.x + (pt.date ? ' · ' + pt.date.substring(0, 10) : '');
                                }
                                d = new Date(items[0].parsed.x);
                                if (isNaN(d.getTime())) {
                                    return '';
                                }
                                return d.toLocaleDateString(undefined, {
                                    year: 'numeric',
                                    month: 'short',
                                    day: 'numeric'
                                });
                            }
                        }
                    })
                },
                scales: {
                    x: xScale,
                    y: {
                        ticks: { color: T.tickColor() },
                        grid: { color: T.softGrid ? T.softGrid() : T.grid() }
                    }
                }
            }, 'line')
        }, 'line');
    }

    function initRoot(root) {
        var playerId = root.getAttribute('data-player-id');
        if (!playerId) {
            return;
        }

        var dateCanvas = root.querySelector('.player-compare-rating-canvas--date');
        var gameCanvas = root.querySelector('.player-compare-rating-canvas--game');
        var status = root.querySelector('.player-compare-rating-chart-status');
        var titleOpponent = root.querySelector('.player-compare-rating-opponent-name');
        var meta = root.querySelector('.player-compare-rating-meta');
        var toggle = root.querySelector('.pm3d-rating-toggle');
        var state = {
            dateChart: null,
            gameChart: null,
            dateCanvas: dateCanvas,
            gameCanvas: gameCanvas,
            latestConfig: null,
            activeView: 'date'
        };

        if (!dateCanvas || !gameCanvas || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        function setMeta(text) {
            if (meta) {
                meta.textContent = text || '';
            }
        }

        function loadOpponent(opponentId, opponentName) {
            if (!opponentId) {
                return;
            }

            if (titleOpponent) {
                titleOpponent.textContent = opponentName || '…';
            }
            if (status) {
                status.textContent = 'Loading rating comparison…';
            }
            setMeta('');

            var compareUrl = API_PATH + '?id=' + encodeURIComponent(playerId)
                + '&opponent=' + encodeURIComponent(opponentId) + '&realm=online';
            var h2hUrl = H2H_API_PATH + '?id=' + encodeURIComponent(playerId)
                + '&opponent=' + encodeURIComponent(opponentId) + '&realm=online';

            Promise.all([
                fetchJson(compareUrl),
                fetchJson(h2hUrl).catch(function () {
                    return null;
                })
            ])
                .then(function (results) {
                    var data = results[0];
                    var h2h = results[1];
                    var player = data.player || {};
                    var opponent = data.opponent || {};
                    var playerDateData = pointsToChartData(player.points || []);
                    var opponentDateData = pointsToChartData(opponent.points || []);
                    var playerGameData = pointsToGameData(player.points || []);
                    var opponentGameData = pointsToGameData(opponent.points || []);

                    if (DR && DR.appendRatingThroughToday) {
                        var playerRating = typeof player.currentRating === 'number'
                            ? player.currentRating
                            : (playerDateData.length ? playerDateData[playerDateData.length - 1].y : null);
                        var opponentRating = typeof opponent.currentRating === 'number'
                            ? opponent.currentRating
                            : (opponentDateData.length ? opponentDateData[opponentDateData.length - 1].y : null);
                        if (playerDateData.length) {
                            playerDateData = DR.appendRatingThroughToday(playerDateData, playerRating);
                        }
                        if (opponentDateData.length) {
                            opponentDateData = DR.appendRatingThroughToday(opponentDateData, opponentRating);
                        }
                    }

                    if (!playerDateData.length && !opponentDateData.length) {
                        if (state.dateChart) {
                            state.dateChart.destroy();
                            state.dateChart = null;
                        }
                        if (state.gameChart) {
                            state.gameChart.destroy();
                            state.gameChart = null;
                        }
                        if (status) {
                            status.textContent = 'No rated games to chart.';
                        }
                        return;
                    }

                    if (titleOpponent && opponent.playerName) {
                        titleOpponent.textContent = opponent.playerName;
                    }

                    setMeta(compareMetaLine(player, opponent, h2h));

                    if (status) {
                        status.textContent = '';
                    }

                    if (state.dateChart) {
                        state.dateChart.destroy();
                        state.dateChart = null;
                    }
                    if (state.gameChart) {
                        state.gameChart.destroy();
                        state.gameChart = null;
                    }

                    state.latestConfig = {
                        player: player,
                        opponent: opponent,
                        opponentName: opponentName,
                        playerDateData: playerDateData,
                        opponentDateData: opponentDateData,
                        playerGameData: playerGameData,
                        opponentGameData: opponentGameData
                    };
                    state.dateChart = renderChart(dateCanvas, state.latestConfig, 'date');
                    setActiveView(root, state.activeView, state);
                })
                .catch(function () {
                    if (status) {
                        status.textContent = 'Could not load rating comparison.';
                    }
                });
        }

        document.addEventListener(EVENT_NAME, function (e) {
            if (!e.detail || String(e.detail.playerId) !== String(playerId)) {
                return;
            }
            loadOpponent(e.detail.opponentId, e.detail.opponentName);
        });

        if (toggle) {
            toggle.addEventListener('click', function (evt) {
                var btn = evt.target.closest('.pm3d-rating-toggle__btn');
                var view;
                if (!btn || !root.contains(btn)) {
                    return;
                }
                view = btn.getAttribute('data-view');
                if (!view || view === state.activeView) {
                    return;
                }
                state.activeView = view;
                setActiveView(root, view, state);
            });
        }
    }

    function boot() {
        var roots = document.querySelectorAll('.player-compare-rating-chart');
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
