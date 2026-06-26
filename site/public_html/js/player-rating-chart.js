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
    var GAME_PAGE_ANCHOR = '#k2-game'; /* keep in sync with k2_game_page_anchor_hash() */

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

    var START_RATING = 1600;
    var GAME_AXIS_STEP = 500;

    function gameAxisStep(maxGame, eventMode) {
        if (eventMode) {
            if (maxGame <= 20) {
                return 5;
            }
            if (maxGame <= 100) {
                return 10;
            }
            if (maxGame <= 500) {
                return 50;
            }
            return 100;
        }
        if (maxGame <= 50) {
            return 10;
        }
        if (maxGame <= 200) {
            return 50;
        }
        if (maxGame <= 1000) {
            return 100;
        }
        return GAME_AXIS_STEP;
    }

    /** Last x tick label — round step only; omit messy career end (e.g. 3651 → 3500). */
    function lastLabeledAxisTick(maxGame, step) {
        if (maxGame <= 0) {
            return 0;
        }
        if (maxGame % step === 0) {
            return maxGame;
        }
        return Math.floor(maxGame / step) * step;
    }

    function buildNiceAxisTickValues(maxGame, eventMode) {
        var step = gameAxisStep(maxGame, eventMode);
        var last = lastLabeledAxisTick(maxGame, step);
        var ticks = [];
        var v;
        for (v = 0; v <= last; v += step) {
            ticks.push(v);
        }
        return ticks;
    }

    function withGameChartOrigin(chartData) {
        if (!chartData.length) {
            return chartData.slice();
        }
        var out = [{ x: 0, y: START_RATING, isOrigin: true }];
        for (var i = 0; i < chartData.length; i++) {
            out.push(chartData[i]);
        }
        return out;
    }

    function buildGameChartData(points) {
        var chartData = [];
        for (var i = 0; i < points.length; i++) {
            var n = points[i].eventNumber != null ? points[i].eventNumber : points[i].gameNumber;
            if (n == null || n < 1) {
                n = i + 1;
            }
            chartData.push({
                x: n,
                y: points[i].rating,
                date: points[i].date,
                gameId: points[i].gameId,
                tournamentId: points[i].tournamentId,
                tournamentName: points[i].tournamentName,
                gamesInEvent: points[i].gamesInEvent,
                ratingDelta: points[i].ratingDelta
            });
        }
        return chartData;
    }

    function historyIsEventGranularity(data, realm) {
        return (data && data.meta && data.meta.granularity === 'event')
            || realm === 'amiga';
    }

    function chartOptions(extra, chartKind) {
        if (T && T.activityChartOptions) {
            return T.activityChartOptions(Object.assign({ maintainAspectRatio: false }, extra || {}), {
                chartKind: chartKind || 'line'
            });
        }
        return Object.assign({ responsive: true, maintainAspectRatio: false }, extra || {});
    }

    function withCareerPlotGutter(extra, chartKind) {
        var gutter = T && T.careerChartGutterOptions ? T.careerChartGutterOptions() : {};
        return chartOptions(Object.assign({}, gutter, extra || {}), chartKind || 'line');
    }

    function careerYScale(extra) {
        var scale = Object.assign({
            ticks: { color: T.tickColor() },
            grid: { color: T.softGrid ? T.softGrid() : T.grid() }
        }, extra || {});
        return T && T.careerChartYAxisOptions ? T.careerChartYAxisOptions(scale) : scale;
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

    function peakAfterClause(tournamentName) {
        var Core = window.K2PlayerRankChartCore;
        if (Core && Core.peakAfterClause) {
            return Core.peakAfterClause(tournamentName);
        }
        if (!tournamentName) {
            return '';
        }
        return ', after ' + String(tournamentName)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
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
        summary.innerHTML = 'Peak: <span class="pm3-chart-peak-value">' + stats.peak + '</span>'
            + ' <span class="pm3d-chart__summary-note">on ' + peakWhen
            + peakAfterClause(stats.tournamentName) + '.</span>';
        summary.hidden = false;
    }

    function renderGameSummary(summary, stats, totalGames, eventMode) {
        if (!summary) {
            return;
        }
        var unitLabel = eventMode ? 'tournaments' : 'rated games';
        var atLabel = eventMode ? 'tournament #' : 'game #';
        summary.innerHTML = 'Peak: <span class="pm3-chart-peak-value">' + stats.peak + '</span>'
            + ' <span class="pm3d-chart__summary-note">at ' + atLabel + stats.peakGame
            + ' &nbsp;&middot;&nbsp; ' + totalGames + ' ' + unitLabel + '</span>';
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
                ctx.strokeStyle = T ? T.holo() : '#b388ff';
                ctx.globalAlpha = 0.85;
                ctx.beginPath();
                ctx.moveTo(area.left, y);
                ctx.lineTo(area.right, y);
                ctx.stroke();
                ctx.setLineDash([]);
                ctx.restore();
            }
        };
    }

    function amigaDateChartTimeRange(chartData, timelineStart, cutoffActive) {
        if (DR && DR.ratingChartTimeRange) {
            return DR.ratingChartTimeRange(chartData, timelineStart, cutoffActive);
        }
        if (DR && DR.careerTimeRangeFromStart) {
            if (timelineStart) {
                return DR.careerTimeRangeFromStart(timelineStart);
            }
            if (chartData.length && chartData[0].x) {
                var first = chartData[0].x;
                return DR.careerTimeRangeFromStart(
                    first.getFullYear() + '-'
                        + String(first.getMonth() + 1).padStart(2, '0') + '-'
                        + String(first.getDate()).padStart(2, '0')
                );
            }
            return DR.careerTimeRangeFromStart();
        }
        return {
            xMin: undefined,
            xMax: DR && DR.endOfToday ? DR.endOfToday() : undefined
        };
    }

    function createDateChart(canvas, chartData, peakValue, realm, timelineStart, cutoffActive) {
        var timeRange;
        if (realm === 'online' && DR && DR.profileCareerTimeRange) {
            timeRange = DR.profileCareerTimeRange();
        } else if (realm === 'amiga') {
            timeRange = amigaDateChartTimeRange(chartData, timelineStart, cutoffActive);
        } else {
            timeRange = {
                xMin: DR && DR.serverStartDate ? DR.serverStartDate() : undefined,
                xMax: DR && DR.endOfToday ? DR.endOfToday() : undefined
            };
        }
        return createChart(canvas, {
            type: 'line',
            data: {
                datasets: [Object.assign({
                    label: 'ELO rating (at day end)',
                    data: chartData,
                    fill: true,
                    tension: 0.1,
                    pointRadius: 0,
                    pointHoverRadius: 4
                }, T.lineStroke(T.amber(), 0.15))]
            },
            plugins: [peakLinePlugin(peakValue)],
            options: withCareerPlotGutter({
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
                        min: timeRange.xMin,
                        max: timeRange.xMax,
                        offset: false,
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
                    y: careerYScale()
                }
            }, 'line')
        }, 'line');
    }

    function createGameChart(canvas, chartData, peakValue, eventMode) {
        var xTitle = eventMode ? 'Tournament number' : 'Rated game number';
        var datasetLabel = eventMode ? 'ELO rating (after tournament)' : 'ELO rating (after game)';
        var xMax = chartData.length ? chartData[chartData.length - 1].x : 0;
        var xMin = 0;
        var seriesData = withGameChartOrigin(chartData);
        var tickValues = buildNiceAxisTickValues(xMax, eventMode);
        return createChart(canvas, {
            type: 'line',
            data: {
                datasets: [Object.assign({
                    label: datasetLabel,
                    data: seriesData,
                    fill: true,
                    tension: 0.1,
                    pointRadius: 0,
                    pointHoverRadius: 4
                }, T.lineStroke(T.amber(), 0.15))]
            },
            plugins: [peakLinePlugin(peakValue)],
            options: withCareerPlotGutter({
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
                                if (pt && pt.isOrigin) {
                                    return (eventMode
                                        ? 'Tournament #0 — starting rating'
                                        : 'Game #0 — starting rating');
                                }
                                var prefix = eventMode ? 'Tournament #' : 'Game #';
                                var title = prefix + items[0].parsed.x;
                                if (eventMode && pt && pt.tournamentName) {
                                    title += ' — ' + pt.tournamentName;
                                }
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
                                if (pt && pt.isOrigin) {
                                    var beforeLabel = eventMode
                                        ? 'before the first tournament'
                                        : 'before the first rated game';
                                    return String(START_RATING) + ' Elo ' + beforeLabel;
                                }
                                if (eventMode && pt && pt.tournamentId) {
                                    var lines = ['/amiga/tournament/event-stats.php?id=' + pt.tournamentId];
                                    if (pt.gamesInEvent > 1) {
                                        lines.push(pt.gamesInEvent + ' games in event');
                                    }
                                    if (typeof pt.ratingDelta === 'number') {
                                        var sign = pt.ratingDelta > 0 ? '+' : '';
                                        lines.push('Event delta: ' + sign + pt.ratingDelta);
                                    }
                                    return lines;
                                }
                                if (pt && pt.gameId) {
                                    return '/game.php?id=' + pt.gameId + GAME_PAGE_ANCHOR;
                                }
                                return '';
                            }
                        }
                    })
                },
                scales: {
                    x: {
                        type: 'linear',
                        bounds: 'data',
                        min: xMin,
                        max: xMax,
                        grace: 0,
                        title: {
                            display: true,
                            text: xTitle,
                            color: T.tickColor()
                        },
                        afterBuildTicks: function (scale) {
                            scale.ticks = tickValues.map(function (v) {
                                return { value: v };
                            });
                        },
                        ticks: {
                            color: T.tickColor(),
                            maxRotation: 0,
                            autoSkip: false,
                            callback: function (value) {
                                var n = Number(value);
                                return tickValues.indexOf(n) >= 0 ? String(n) : '';
                            }
                        },
                        grid: { color: T.softGrid ? T.softGrid() : T.grid() }
                    },
                    y: careerYScale()
                }
            }, 'line')
        }, 'line');
    }

    function readInitialView(root) {
        var toggle = root.querySelector('.pm3d-rating-toggle');
        if (!toggle) {
            return 'game';
        }
        var activeBtn = toggle.querySelector('.pm3d-rating-toggle__btn.is-active[data-view]');
        if (activeBtn) {
            var view = activeBtn.getAttribute('data-view');
            if (view === 'date' || view === 'game') {
                return view;
            }
        }
        return 'game';
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
                state.gameChart = createGameChart(
                    gameCanvas,
                    state.gameChartData,
                    state.peakValue,
                    state.eventMode
                );
            }
        }

        var activeChart = view === 'date' ? state.dateChart : state.gameChart;
        if (activeChart) {
            activeChart.resize();
        }
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

        var initialView = readInitialView(root);

        var state = {
            dateChart: null,
            gameChart: null,
            gameChartData: [],
            peakValue: null,
            timelineStart: null,
            activeView: initialView,
            eventMode: false
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

        var asParam = root.getAttribute('data-as') || '';
        if (!asParam && typeof URLSearchParams !== 'undefined') {
            asParam = new URLSearchParams(window.location.search).get('as') || '';
        }
        var loadOpts = asParam ? { as: asParam } : {};

        History.load(playerId, realm, loadOpts)
            .then(function (data) {
                state.timelineStart = data.timelineStart || null;
                state.eventMode = historyIsEventGranularity(data, realm);
                var cutoffActive = !!(data.meta && data.meta.cutoffActive);

                var points = data.points || [];
                if (!points.length) {
                    if (status) {
                        status.textContent = cutoffActive
                            ? 'Not on the ladder at this date.'
                            : (state.eventMode
                                ? 'No rating events to chart.'
                                : 'No rated games to chart.');
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
                if (!cutoffActive && DR && DR.appendRatingThroughToday) {
                    dateChartData = DR.appendRatingThroughToday(dateChartData, currentRating);
                }

                state.gameChartData = buildGameChartData(points);

                if (state.eventMode && data.peak && data.peak.rating > 0 && data.peak.eventDate) {
                    var storedPeakDate = parseGameDate(data.peak.eventDate);
                    if (storedPeakDate) {
                        state.peakValue = data.peak.rating;
                        renderDateSummary(dateSummary, {
                            peak: data.peak.rating,
                            peakDate: storedPeakDate,
                            tournamentName: data.peak.tournamentName || '',
                            latest: dateChartData[dateChartData.length - 1].y
                        });
                    }
                } else if (careerPeakStats) {
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
                        state.gameChartData[state.gameChartData.length - 1].x,
                        state.eventMode
                    );
                }

                if (status) {
                    status.textContent = '';
                }

                state.dateChart = createDateChart(
                    dateCanvas,
                    dateChartData,
                    state.peakValue,
                    realm,
                    state.timelineStart,
                    cutoffActive
                );
                setActiveView(root, state.activeView, state);
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

    (window.k2OnPageReady || function (fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    })(boot);
})();
