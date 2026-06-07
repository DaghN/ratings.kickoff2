/**
 * Career rating chart — calendar time (default) or by game number (toggle).
 * Expects K2PlayerRatingHistory, chartjs-adapter-date-fns.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;
    var DR = window.K2ChartDateRange;
    var History = window.K2PlayerRatingHistory;
    var PEAK_LINE_ID = 'k2PlayerPeakLine';

    function parseGameDate(dateStr) {
        if (!dateStr) {
            return null;
        }
        var normalized = String(dateStr).trim().replace(' ', 'T');
        var d = new Date(normalized);
        return isNaN(d.getTime()) ? null : d;
    }

    function localCalendarDayKey(d) {
        var y = d.getFullYear();
        var m = d.getMonth() + 1;
        var day = d.getDate();
        return y + '-' + (m < 10 ? '0' : '') + m + '-' + (day < 10 ? '0' : '') + day;
    }

    function buildPerGameDateValues(points) {
        var values = [];
        for (var i = 0; i < points.length; i++) {
            var x = parseGameDate(points[i].date);
            if (x === null) {
                continue;
            }
            values.push({ x: x, y: points[i].rating });
        }
        return values;
    }

    /** Calendar view: one point per local day — last rating after the final game that day. */
    function buildDateChartData(points) {
        var chartData = [];
        var currentDay = null;
        var lastOfDay = null;

        for (var i = 0; i < points.length; i++) {
            var x = parseGameDate(points[i].date);
            if (x === null) {
                continue;
            }
            var dayKey = localCalendarDayKey(x);
            if (dayKey !== currentDay) {
                if (lastOfDay) {
                    chartData.push(lastOfDay);
                }
                currentDay = dayKey;
                lastOfDay = {
                    x: x,
                    y: points[i].rating,
                    gamesOnDay: 1
                };
            } else {
                lastOfDay.x = x;
                lastOfDay.y = points[i].rating;
                lastOfDay.gamesOnDay += 1;
            }
        }
        if (lastOfDay) {
            chartData.push(lastOfDay);
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

    function chartOptions(extra, chartKind) {
        if (T && T.activityChartOptions) {
            return T.activityChartOptions(Object.assign({ maintainAspectRatio: false }, extra || {}), {
                chartKind: chartKind || 'line'
            });
        }
        return Object.assign({ responsive: true, maintainAspectRatio: false }, extra || {});
    }

    function createChart(canvas, config, chartKind) {
        if (T && T.createActivityChart) {
            return T.createActivityChart(canvas, config, chartKind || 'line');
        }
        return new Chart(canvas, config);
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
        summary.hidden = false;
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
        summary.hidden = false;
    }

    function peakLinePlugin(peakValue) {
        return {
            id: PEAK_LINE_ID,
            afterDatasetsDraw: function (chart) {
                var yScale = chart.scales && chart.scales.y;
                var area = chart.chartArea;
                var ctx = chart.ctx;
                if (!yScale || !area || typeof yScale.getPixelForValue !== 'function') {
                    return;
                }
                var y = yScale.getPixelForValue(peakValue);
                if (!isFinite(y) || y < area.top || y > area.bottom) {
                    return;
                }
                ctx.save();
                ctx.setLineDash([6, 5]);
                ctx.lineWidth = 1;
                ctx.strokeStyle = T ? T.amber() : '#ffb74d';
                ctx.globalAlpha = 0.85;
                ctx.beginPath();
                ctx.moveTo(area.left, y);
                ctx.lineTo(area.right, y);
                ctx.stroke();
                ctx.setLineDash([]);
                ctx.fillStyle = T ? T.amber() : '#ffb74d';
                ctx.font = '600 11px IBM Plex Sans, Verdana, Arial, sans-serif';
                ctx.textAlign = 'right';
                var label = 'Peak ' + peakValue;
                var pad = 4;
                if (y - 14 < area.top) {
                    ctx.textBaseline = 'top';
                    ctx.fillText(label, area.right - 4, y + pad);
                } else {
                    ctx.textBaseline = 'bottom';
                    ctx.fillText(label, area.right - 4, y - pad);
                }
                ctx.restore();
            }
        };
    }

    function createDateChart(canvas, chartData, peakValue, timelineStart) {
        var xMin = DR && DR.serverStartDate
            ? DR.serverStartDate(timelineStart)
            : undefined;
        return createChart(canvas, {
            type: 'line',
            data: {
                datasets: [Object.assign({
                    label: 'ELO rating (after game)',
                    data: chartData,
                    fill: true,
                    tension: 0.1,
                    pointRadius: 0,
                    pointHoverRadius: 4
                }, T.lineStroke(T.pitch(), 0.15))]
            },
            plugins: [peakLinePlugin(peakValue)],
            options: chartOptions({
                interaction: { mode: 'nearest', axis: 'x', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: T.mergeTooltip({
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
                            },
                            afterBody: function (items) {
                                if (!items.length) {
                                    return '';
                                }
                                var raw = items[0].raw;
                                if (raw && raw.gamesOnDay > 1) {
                                    return 'End-of-day rating (' + raw.gamesOnDay + ' games that day)';
                                }
                                return '';
                            }
                        }
                    })
                },
                scales: {
                    x: {
                        type: 'time',
                        min: xMin,
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
                        grid: { color: T.softGrid ? T.softGrid() : T.grid() }
                    },
                    y: {
                        ticks: { color: T.tickColor() },
                        grid: { color: T.softGrid ? T.softGrid() : T.grid() }
                    }
                }
            }, 'line')
        }, 'line');
    }

    function createGameChart(canvas, chartData, peakValue) {
        return createChart(canvas, {
            type: 'line',
            data: {
                datasets: [Object.assign({
                    label: 'ELO rating (after game)',
                    data: chartData,
                    fill: true,
                    tension: 0.1,
                    pointRadius: 0,
                    pointHoverRadius: 4
                }, T.lineStroke(T.pitch(), 0.15))]
            },
            plugins: [peakLinePlugin(peakValue)],
            options: chartOptions({
                parsing: false,
                interaction: { mode: 'nearest', axis: 'x', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: T.mergeTooltip({
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
                                    return '/game.php?id=' + pt.gameId;
                                }
                                return '';
                            }
                        }
                    })
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
                        grid: { color: T.softGrid ? T.softGrid() : T.grid() }
                    },
                    y: {
                        ticks: { color: T.tickColor() },
                        grid: { color: T.softGrid ? T.softGrid() : T.grid() }
                    }
                }
            }, 'line')
        }, 'line');
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
                state.gameChart = createGameChart(gameCanvas, state.gameChartData, state.peakValue);
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
            peakValue: null,
            timelineStart: null,
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

        var realm = root.getAttribute('data-realm')
            || (document.documentElement && document.documentElement.getAttribute('data-realm'))
            || 'online';

        History.load(playerId, realm)
            .then(function (data) {
                state.timelineStart = data.timelineStart || null;

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

                var perGameDateValues = buildPerGameDateValues(points);
                var careerPeakStats = perGameDateValues.length
                    ? peakStatsFromValues(perGameDateValues, 'peakDate', 'latest')
                    : null;

                var currentRating = typeof data.currentRating === 'number'
                    ? data.currentRating
                    : dateChartData[dateChartData.length - 1].y;
                if (DR && DR.appendRatingThroughToday) {
                    dateChartData = DR.appendRatingThroughToday(dateChartData, currentRating);
                }

                state.gameChartData = buildGameChartData(points);

                if (careerPeakStats) {
                    state.peakValue = careerPeakStats.peak;
                    renderDateSummary(dateSummary, {
                        peak: careerPeakStats.peak,
                        peakDate: careerPeakStats.peakDate,
                        latest: dateChartData[dateChartData.length - 1].y
                    });
                }

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

                state.dateChart = createDateChart(
                    dateCanvas,
                    dateChartData,
                    state.peakValue,
                    state.timelineStart
                );
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
