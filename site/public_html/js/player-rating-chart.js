/**
 * Career rating chart — calendar time (default) or by game number (toggle).
 * Expects K2PlayerRatingHistory, chartjs-adapter-date-fns.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;
    var DR = window.K2ChartDateRange;
    var History = window.K2PlayerRatingHistory;

    function parseGameDate(dateStr) {
        if (!dateStr) {
            return null;
        }
        var normalized = String(dateStr).trim().replace(' ', 'T');
        var d = new Date(normalized);
        return isNaN(d.getTime()) ? null : d;
    }

    function buildDateChartData(points) {
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

    function buildGameChartData(points) {
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

    function peakStatsFromValues(values, peakIndexField, latestField) {
        var peak = values[0].y;
        var peakIndex = 0;
        for (var i = 1; i < values.length; i++) {
            if (values[i].y > peak) {
                peak = values[i].y;
                peakIndex = i;
            }
        }
        var out = {
            peak: peak,
            latest: values[values.length - 1].y
        };
        out[peakIndexField] = values[peakIndex].x;
        return out;
    }

    function renderDateSummary(summary, stats) {
        if (!summary) {
            return;
        }
        var peakWhen = stats.peakDate.toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
        var gap = stats.peak - stats.latest;
        var gapText = gap === 0
            ? ' (latest rating is peak)'
            : ' (' + gap + ' below peak now)';
        summary.innerHTML = 'Peak: <strong>' + stats.peak + '</strong>'
            + ' <span class="pm3d-chart__summary-note">on ' + peakWhen + gapText + '</span>';
        summary.style.display = 'block';
    }

    function renderGameSummary(summary, stats, totalGames) {
        if (!summary) {
            return;
        }
        var gap = stats.peak - stats.latest;
        var gapText = gap === 0
            ? ' (latest rating is peak)'
            : ' (' + gap + ' below peak now)';
        summary.innerHTML = 'Peak: <strong>' + stats.peak + '</strong>'
            + ' <span class="pm3d-chart__summary-note">at game #' + stats.peakGame + gapText
            + ' &nbsp;&middot;&nbsp; ' + totalGames + ' rated games</span>';
        summary.style.display = 'block';
    }

    function createDateChart(canvas, chartData) {
        return new Chart(canvas, {
            type: 'line',
            data: {
                datasets: [{
                    label: 'ELO rating (after game)',
                    data: chartData,
                    borderColor: T.green(),
                    backgroundColor: T.fill(T.green(), 0.15),
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    fill: true,
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: { mode: 'nearest', axis: 'x', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: function (items) {
                                if (!items.length) {
                                    return '';
                                }
                                var d = new Date(items[0].parsed.x);
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
                    }
                },
                scales: {
                    x: {
                        type: 'time',
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
                            minRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 12
                        },
                        grid: { color: T.grid() }
                    },
                    y: {
                        ticks: { color: T.tickColor() },
                        grid: { color: T.grid() }
                    }
                }
            }
        });
    }

    function createGameChart(canvas, chartData) {
        return new Chart(canvas, {
            type: 'line',
            data: {
                datasets: [{
                    label: 'ELO rating (after game)',
                    data: chartData,
                    borderColor: T.purple(),
                    backgroundColor: T.fill(T.purple(), 0.15),
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    fill: true,
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                parsing: false,
                interaction: { mode: 'nearest', axis: 'x', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: function (items) {
                                if (!items.length) {
                                    return '';
                                }
                                var pt = items[0].raw;
                                var title = 'Game #' + items[0].parsed.x;
                                if (pt && pt.date) {
                                    var d = parseGameDate(pt.date);
                                    if (d) {
                                        title += ' — ' + d.toLocaleDateString(undefined, {
                                            year: 'numeric',
                                            month: 'short',
                                            day: 'numeric'
                                        });
                                    }
                                }
                                return title;
                            },
                            afterBody: function (items) {
                                if (!items.length) {
                                    return '';
                                }
                                var pt = items[0].raw;
                                if (pt && pt.gameId) {
                                    return 'game.php?id=' + pt.gameId;
                                }
                                return '';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'linear',
                        title: {
                            display: true,
                            text: 'Rated game number',
                            color: T.tickColor()
                        },
                        ticks: {
                            color: T.tickColor(),
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 14
                        },
                        grid: { color: T.grid() }
                    },
                    y: {
                        ticks: { color: T.tickColor() },
                        grid: { color: T.grid() }
                    }
                }
            }
        });
    }

    function setActiveView(root, view, state) {
        var dateView = root.querySelector('.player-rating-view--date');
        var gameView = root.querySelector('.player-rating-view--game');
        var buttons = root.querySelectorAll('.pm3d-rating-toggle__btn');

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

        if (view === 'game' && !state.gameChart && state.gameChartData.length) {
            var gameCanvas = root.querySelector('.player-rating-canvas--game');
            if (gameCanvas) {
                state.gameChart = createGameChart(gameCanvas, state.gameChartData);
            }
        }

        var activeChart = view === 'date' ? state.dateChart : state.gameChart;
        if (activeChart) {
            activeChart.resize();
        }
    }

    function initRoot(root) {
        var playerId = root.getAttribute('data-player-id');
        if (!playerId) {
            return;
        }

        var status = root.querySelector('.player-rating-chart-status');
        var dateSummary = root.querySelector('.player-rating-peak-current-summary');
        var gameSummary = root.querySelector('.player-rating-game-peak-current-summary');
        var dateCanvas = root.querySelector('.player-rating-canvas--date');
        var toggle = root.querySelector('.pm3d-rating-toggle');

        if (!dateCanvas || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        if (!History || !History.load) {
            if (status) {
                status.textContent = 'Rating history loader failed to load.';
            }
            return;
        }

        if (status) {
            status.textContent = 'Loading rating history…';
        }

        var state = {
            dateChart: null,
            gameChart: null,
            gameChartData: [],
            activeView: 'date'
        };

        if (toggle) {
            toggle.addEventListener('click', function (evt) {
                var btn = evt.target.closest('.pm3d-rating-toggle__btn');
                if (!btn || !root.contains(btn)) {
                    return;
                }
                var view = btn.getAttribute('data-view');
                if (!view || view === state.activeView) {
                    return;
                }
                state.activeView = view;
                setActiveView(root, view, state);
            });
        }

        History.load(playerId, 'online')
            .then(function (data) {
                var points = data.points || [];
                if (!points.length) {
                    if (status) {
                        status.textContent = 'No rated games to chart.';
                    }
                    if (toggle) {
                        toggle.hidden = true;
                    }
                    return;
                }

                var dateChartData = buildDateChartData(points);
                if (!dateChartData.length) {
                    if (status) {
                        status.textContent = 'No chartable dates in game history.';
                    }
                    return;
                }

                var currentRating = typeof data.currentRating === 'number'
                    ? data.currentRating
                    : dateChartData[dateChartData.length - 1].y;
                if (DR && DR.appendRatingThroughToday) {
                    dateChartData = DR.appendRatingThroughToday(dateChartData, currentRating);
                }

                state.gameChartData = buildGameChartData(points);

                var dateStats = peakStatsFromValues(dateChartData, 'peakDate', 'latest');
                renderDateSummary(dateSummary, dateStats);

                if (state.gameChartData.length) {
                    var gameStats = peakStatsFromValues(state.gameChartData, 'peakGame', 'latest');
                    renderGameSummary(
                        gameSummary,
                        gameStats,
                        state.gameChartData[state.gameChartData.length - 1].x
                    );
                }

                if (status) {
                    status.textContent = '';
                }

                state.dateChart = createDateChart(dateCanvas, dateChartData);
                setActiveView(root, 'date', state);
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load rating history.';
                }
            });
    }

    function boot() {
        var roots = document.querySelectorAll('.player-rating-chart');
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
