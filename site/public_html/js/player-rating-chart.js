/**
 * Career rating chart — calendar time (default) or by game number (toggle).
 * Expects K2PlayerRatingHistory, chartjs-adapter-date-fns.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;
    var DR = window.K2ChartDateRange;
    var History = window.K2PlayerRatingHistory;
    var DayTip = window.K2PlayerCalendarDayTooltip;
    var PEAK_LINE_ID = 'k2PlayerPeakLine';
    var RATING_DAY_TIP_ID = 'k2-player-rating-day-tooltip';
    var RATING_GAME_TIP_ID = 'k2-player-rating-game-tooltip';
    var GAME_PAGE_ANCHOR = '#k2-game'; /* keep in sync with k2_game_page_anchor_hash() */
    var ratingDayTipKey = null;

    function ratingSeriesInk(realm) {
        if (realm === 'amiga') {
            return T.pitch();
        }
        return T.amber();
    }

    function ratingSeriesStroke(realm) {
        return T.lineStroke(ratingSeriesInk(realm), 0.15);
    }

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

    function utcDayKeyForDate(d) {
        if (DayTip && DayTip.utcCalendarDayKey) {
            return DayTip.utcCalendarDayKey(d);
        }
        if (!d || isNaN(d.getTime())) {
            return '';
        }
        var m = d.getUTCMonth() + 1;
        var day = d.getUTCDate();
        return d.getUTCFullYear() + '-' + (m < 10 ? '0' : '') + m + '-' + (day < 10 ? '0' : '') + day;
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
                    gamesOnDay: 1,
                    utcDayKey: utcDayKeyForDate(x)
                };
            } else {
                lastOfDay.x = x;
                lastOfDay.y = points[i].rating;
                lastOfDay.gamesOnDay += 1;
                lastOfDay.utcDayKey = utcDayKeyForDate(x);
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
                gameNumber: n,
                name_a: points[i].name_a,
                name_b: points[i].name_b,
                rating_a: points[i].rating_a,
                rating_b: points[i].rating_b,
                goals_a: points[i].goals_a,
                goals_b: points[i].goals_b,
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

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /** Per-game career peak may exceed end-of-day chart points — keep dashed line inside the plot. */
    function ratingYScaleWithPeak(chartData, peakValue, extra) {
        var scaleExtra = Object.assign({}, extra || {});
        var dataMax = 0;
        for (var i = 0; i < chartData.length; i++) {
            if (chartData[i].y > dataMax) {
                dataMax = chartData[i].y;
            }
        }
        if (typeof peakValue === 'number' && isFinite(peakValue) && peakValue > dataMax) {
            scaleExtra.suggestedMax = peakValue + Math.max(10, Math.round((peakValue - dataMax) * 0.1));
        }
        return careerYScale(scaleExtra);
    }

    function peakAfterClause(tournament, options) {
        var Core = window.K2PlayerRankChartCore;
        if (Core && Core.peakAfterClause) {
            return Core.peakAfterClause(tournament, options);
        }
        if (!tournament) {
            return '';
        }
        if (typeof tournament === 'string') {
            if (!tournament) {
                return '';
            }
            return ', after ' + escapeHtml(tournament);
        }
        var name = tournament.tournamentName ? String(tournament.tournamentName) : '';
        if (!name) {
            return '';
        }
        return ', after ' + escapeHtml(name);
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
            + peakAfterClause({
                tournamentName: stats.tournamentName || '',
                tournamentId: stats.tournamentId || null,
                hostCountry: stats.hostCountry || '',
                flagCode: stats.flagCode || ''
            }) + '.</span>';
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

    function peakLinePlugin(peakValue, realm) {
        return {
            id: PEAK_LINE_ID,
            afterDatasetsDraw: function (chart) {
                if (typeof peakValue !== 'number' || !isFinite(peakValue)) {
                    return;
                }
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
                ctx.strokeStyle = T ? ratingSeriesInk(realm) : '#9ccc65';
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

    function getRatingDayTooltipEl() {
        var el = document.getElementById(RATING_DAY_TIP_ID);
        if (el) {
            return el;
        }
        el = document.createElement('div');
        el.id = RATING_DAY_TIP_ID;
        el.className = 'k2-table-tooltip k2-table-tooltip--player-cal k2-table-tooltip--rating-day';
        el.setAttribute('role', 'tooltip');
        el.setAttribute('aria-hidden', 'true');
        el.innerHTML = '<div class="k2-table-tooltip__title"></div>'
            + '<div class="k2-table-tooltip__body"></div>';
        el.hidden = true;
        document.body.appendChild(el);
        return el;
    }

    function hideRatingDayTooltip() {
        ratingDayTipKey = null;
        var tip = document.getElementById(RATING_DAY_TIP_ID);
        if (tip) {
            tip.hidden = true;
            tip.setAttribute('aria-hidden', 'true');
        }
    }

    function hideRatingGameTooltip() {
        var tip = document.getElementById(RATING_GAME_TIP_ID);
        if (tip) {
            tip.hidden = true;
            tip.setAttribute('aria-hidden', 'true');
        }
    }

    function hideRatingChartTooltips() {
        hideRatingDayTooltip();
        hideRatingGameTooltip();
    }

    function showRatingDayTooltipShell(title, bodyHtml, anchorX, anchorY) {
        var tip = getRatingDayTooltipEl();
        var titleEl = tip.querySelector('.k2-table-tooltip__title');
        var bodyEl = tip.querySelector('.k2-table-tooltip__body');
        if (titleEl) {
            titleEl.textContent = title;
        }
        if (bodyEl) {
            bodyEl.innerHTML = bodyHtml;
        }
        tip.setAttribute('aria-hidden', 'false');
        if (DayTip && DayTip.positionTooltipNearPoint) {
            DayTip.positionTooltipNearPoint(tip, anchorX, anchorY);
        } else {
            tip.style.left = Math.round(anchorX) + 'px';
            tip.style.top = Math.round(anchorY) + 'px';
            tip.hidden = false;
        }
    }

    function bindRatingDateExternalTooltip(dayTooltipCtx) {
        return function (context) {
            if (!DayTip || !dayTooltipCtx || !dayTooltipCtx.playerId) {
                hideRatingDayTooltip();
                return;
            }
            hideRatingGameTooltip();
            var tooltip = context.tooltip;
            var chart = context.chart;
            if (!tooltip || tooltip.opacity === 0) {
                hideRatingDayTooltip();
                return;
            }
            var points = tooltip.dataPoints || [];
            if (!points.length) {
                hideRatingDayTooltip();
                return;
            }
            var raw = points[0].raw || {};
            var dayEndRating = points[0].parsed.y;
            var dayKey = raw.utcDayKey;
            if (!dayKey && raw.x) {
                dayKey = utcDayKeyForDate(new Date(raw.x));
            }
            if (!dayKey) {
                hideRatingDayTooltip();
                return;
            }
            var cacheKey = dayKey;
            ratingDayTipKey = cacheKey;
            var canvas = chart.canvas;
            var rect = canvas.getBoundingClientRect();
            var anchorX = rect.left + tooltip.caretX;
            var anchorY = rect.top + tooltip.caretY;
            var title = DayTip.formatDayTitle(dayKey);
            var gamesHint = raw.gamesOnDay > 0 ? raw.gamesOnDay : null;

            showRatingDayTooltipShell(
                title,
                '<p class="pm3-cal__tip-summary">' + DayTip.dayTooltipSummary(gamesHint || 1, dayEndRating)
                    + '</p><p class="pm3-cal__tip-loading">Loading games\u2026</p>',
                anchorX,
                anchorY
            );

            DayTip.fetchDayGames(dayKey, dayTooltipCtx.playerId, dayTooltipCtx.cache)
                .then(function (payload) {
                    if (ratingDayTipKey !== cacheKey) {
                        return;
                    }
                    var list = payload.games.slice(0, DayTip.MAX_TOOLTIP_GAMES);
                    showRatingDayTooltipShell(
                        title,
                        DayTip.renderDayGamesBody(payload.total, list, dayEndRating),
                        anchorX,
                        anchorY
                    );
                })
                .catch(function () {
                    if (ratingDayTipKey !== cacheKey) {
                        return;
                    }
                    showRatingDayTooltipShell(
                        title,
                        'Could not load games for this day.',
                        anchorX,
                        anchorY
                    );
                });
        };
    }

    function getRatingGameTooltipEl() {
        var el = document.getElementById(RATING_GAME_TIP_ID);
        if (el) {
            return el;
        }
        el = document.createElement('div');
        el.id = RATING_GAME_TIP_ID;
        el.className = 'k2-table-tooltip k2-table-tooltip--player-cal';
        el.setAttribute('role', 'tooltip');
        el.setAttribute('aria-hidden', 'true');
        el.innerHTML = '<div class="k2-table-tooltip__title"></div>'
            + '<div class="k2-table-tooltip__body"></div>';
        el.hidden = true;
        document.body.appendChild(el);
        return el;
    }

    function showRatingGameTooltipShell(title, bodyHtml, anchorX, anchorY) {
        var tip = getRatingGameTooltipEl();
        var titleEl = tip.querySelector('.k2-table-tooltip__title');
        var bodyEl = tip.querySelector('.k2-table-tooltip__body');
        if (titleEl) {
            titleEl.textContent = title;
        }
        if (bodyEl) {
            bodyEl.innerHTML = bodyHtml;
        }
        tip.setAttribute('aria-hidden', 'false');
        if (DayTip && DayTip.positionTooltipNearPoint) {
            DayTip.positionTooltipNearPoint(tip, anchorX, anchorY);
        } else {
            tip.style.left = Math.round(anchorX) + 'px';
            tip.style.top = Math.round(anchorY) + 'px';
            tip.hidden = false;
        }
    }

    function bindRatingGameExternalTooltip(gameTooltipCtx) {
        return function (context) {
            if (!DayTip || !gameTooltipCtx) {
                hideRatingGameTooltip();
                return;
            }
            hideRatingDayTooltip();
            var tooltip = context.tooltip;
            var chart = context.chart;
            if (!tooltip || tooltip.opacity === 0) {
                hideRatingGameTooltip();
                return;
            }
            var points = tooltip.dataPoints || [];
            if (!points.length) {
                hideRatingGameTooltip();
                return;
            }
            var item = points[0];
            var raw = item.raw || {};
            var pt = Object.assign({}, raw, {
                x: item.parsed.x,
                y: item.parsed.y,
                rating: item.parsed.y,
                gameNumber: raw.gameNumber != null ? raw.gameNumber : item.parsed.x
            });
            var canvas = chart.canvas;
            var rect = canvas.getBoundingClientRect();
            var anchorX = rect.left + tooltip.caretX;
            var anchorY = rect.top + tooltip.caretY;
            var title = DayTip.renderRatingGameTooltipTitle(pt, gameTooltipCtx.eventMode);
            var body = DayTip.renderRatingGameTooltipBody(pt, {
                realm: gameTooltipCtx.realm,
                eventMode: gameTooltipCtx.eventMode,
                startRating: START_RATING
            });
            showRatingGameTooltipShell(title, body, anchorX, anchorY);
        };
    }

    function createDateChart(canvas, chartData, peakValue, realm, timelineStart, cutoffActive, lineStyle, dayTooltipCtx) {
        var stepped = lineStyle === 'stepped';
        var useDayGamesTooltip = realm === 'online' && dayTooltipCtx && dayTooltipCtx.playerId && DayTip;
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
                    stepped: stepped,
                    tension: stepped ? 0 : 0.1,
                    pointRadius: 0,
                    pointHoverRadius: 4
                }, ratingSeriesStroke(realm))]
            },
            plugins: [peakLinePlugin(peakValue, realm)],
            options: withCareerPlotGutter({
                interaction: { mode: 'nearest', axis: 'x', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: T.mergeTooltip({
                        enabled: !useDayGamesTooltip,
                        external: useDayGamesTooltip ? bindRatingDateExternalTooltip(dayTooltipCtx) : undefined,
                        callbacks: useDayGamesTooltip ? {} : {
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
                            label: function (item) {
                                return 'Rating at day end: ' + item.formattedValue;
                            },
                            afterBody: function (items) {
                                if (!items.length) {
                                    return '';
                                }
                                var raw = items[0].raw;
                                if (raw && raw.gamesOnDay > 1) {
                                    return raw.gamesOnDay + ' rated games that day';
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
                    y: ratingYScaleWithPeak(chartData, peakValue)
                }
            }, 'line')
        }, 'line');
    }

    function createGameChart(canvas, chartData, peakValue, eventMode, lineStyle, realm, gameTooltipCtx) {
        var stepped = lineStyle === 'stepped';
        var xTitle = eventMode ? 'Tournament number' : 'Rated game number';
        var datasetLabel = eventMode ? 'ELO rating (after tournament)' : 'ELO rating (after game)';
        var xMax = chartData.length ? chartData[chartData.length - 1].x : 0;
        var xMin = 0;
        var seriesData = withGameChartOrigin(chartData);
        var tickValues = buildNiceAxisTickValues(xMax, eventMode);
        var useGameHtmlTooltip = gameTooltipCtx && DayTip;
        return createChart(canvas, {
            type: 'line',
            data: {
                datasets: [Object.assign({
                    label: datasetLabel,
                    data: seriesData,
                    fill: true,
                    stepped: stepped,
                    tension: stepped ? 0 : 0.1,
                    pointRadius: 0,
                    pointHoverRadius: 4
                }, ratingSeriesStroke(realm))]
            },
            plugins: [peakLinePlugin(peakValue, realm)],
            options: withCareerPlotGutter({
                parsing: false,
                interaction: { mode: 'nearest', axis: 'x', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: T.mergeTooltip({
                        enabled: !useGameHtmlTooltip,
                        external: useGameHtmlTooltip ? bindRatingGameExternalTooltip(gameTooltipCtx) : undefined,
                        callbacks: useGameHtmlTooltip ? {} : {
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
                                return prefix + items[0].parsed.x;
                            },
                            label: function (item) {
                                return 'Rating after: ' + item.formattedValue;
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

    /** Default 'smooth' when no line-style toggle is present (preserves online profile). */
    function readInitialLineStyle(root) {
        var toggle = root.querySelector('.player-rating-chart__line-style');
        if (!toggle) {
            return 'smooth';
        }
        var activeBtn = toggle.querySelector('.pm3d-rating-toggle__btn.is-active[data-line-style]');
        if (activeBtn) {
            var style = activeBtn.getAttribute('data-line-style');
            if (style === 'stepped' || style === 'smooth') {
                return style;
            }
        }
        return 'smooth';
    }

    function setLineStyleToggle(root, style) {
        var toggle = root.querySelector('.player-rating-chart__line-style');
        if (!toggle) {
            return;
        }
        var buttons = toggle.querySelectorAll('.pm3d-rating-toggle__btn[data-line-style]');
        for (var i = 0; i < buttons.length; i++) {
            var btn = buttons[i];
            var active = btn.getAttribute('data-line-style') === style;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        }
    }

    function rebuildChartsForLineStyle(root, state) {
        if (state.dateChart) {
            state.dateChart.destroy();
            state.dateChart = null;
        }
        if (state.gameChart) {
            state.gameChart.destroy();
            state.gameChart = null;
        }
        var dateCanvas = root.querySelector('.player-rating-canvas--date');
        if (dateCanvas && state.dateChartData && state.dateChartData.length) {
            state.dateChart = createDateChart(
                dateCanvas,
                state.dateChartData,
                state.peakValue,
                state.realm,
                state.timelineStart,
                state.cutoffActive,
                state.lineStyle,
                state.dayTooltipCtx
            );
        }
        setActiveView(root, state.activeView, state);
    }

    function setActiveView(root, view, state) {
        var dateView = root.querySelector('.player-rating-view--date');
        var gameView = root.querySelector('.player-rating-view--game');
        var buttons = root.querySelectorAll('.pm3d-rating-toggle__btn[data-view]');

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
        if (view !== 'date') {
            hideRatingDayTooltip();
        }
        if (view !== 'game') {
            hideRatingGameTooltip();
        }

        if (view === 'game' && !state.gameChart && state.gameChartData.length) {
            var gameCanvas = root.querySelector('.player-rating-canvas--game');
            if (gameCanvas) {
                state.gameChart = createGameChart(
                    gameCanvas,
                    state.gameChartData,
                    state.peakValue,
                    state.eventMode,
                    state.lineStyle,
                    state.realm,
                    state.gameTooltipCtx
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
        var lineStyleToggle = root.querySelector('.player-rating-chart__line-style');

        var state = {
            dateChart: null,
            gameChart: null,
            gameChartData: [],
            dateChartData: [],
            peakValue: null,
            timelineStart: null,
            activeView: initialView,
            lineStyle: readInitialLineStyle(root),
            realm: 'online',
            cutoffActive: false,
            eventMode: false,
            dayTooltipCtx: null,
            gameTooltipCtx: null
        };

        if (DayTip && DayTip.createFetchCache) {
            var dayTipCache = DayTip.createFetchCache();
            state.dayTooltipCtx = {
                playerId: parseInt(playerId, 10),
                cache: dayTipCache
            };
            if (!state.dayTooltipCtx.playerId) {
                state.dayTooltipCtx = null;
            }
        }
        if (DayTip) {
            state.gameTooltipCtx = {
                realm: 'online',
                eventMode: false
            };
        }

        if (toggle) {
            toggle.addEventListener('click', function (evt) {
                var btn = evt.target.closest('.pm3d-rating-toggle__btn[data-view]');
                if (!btn || !toggle.contains(btn)) {
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

        if (lineStyleToggle) {
            lineStyleToggle.addEventListener('click', function (evt) {
                var btn = evt.target.closest('.pm3d-rating-toggle__btn[data-line-style]');
                if (!btn || !lineStyleToggle.contains(btn)) {
                    return;
                }
                var style = btn.getAttribute('data-line-style');
                if (!style || style === state.lineStyle) {
                    return;
                }
                state.lineStyle = style;
                setLineStyleToggle(root, style);
                rebuildChartsForLineStyle(root, state);
            });
        }

        var realm = root.getAttribute('data-realm')
            || (document.documentElement && document.documentElement.getAttribute('data-realm'))
            || 'online';
        state.realm = realm;
        if (state.gameTooltipCtx) {
            state.gameTooltipCtx.realm = realm;
        }

        var asParam = root.getAttribute('data-as') || '';
        if (!asParam && typeof URLSearchParams !== 'undefined') {
            asParam = new URLSearchParams(window.location.search).get('as') || '';
        }
        var loadOpts = asParam ? { as: asParam } : {};

        History.load(playerId, realm, loadOpts)
            .then(function (data) {
                state.timelineStart = data.timelineStart || null;
                state.eventMode = historyIsEventGranularity(data, realm);
                if (state.gameTooltipCtx) {
                    state.gameTooltipCtx.eventMode = state.eventMode;
                    state.gameTooltipCtx.realm = realm;
                }
                var cutoffActive = !!(data.meta && data.meta.cutoffActive);
                state.cutoffActive = cutoffActive;

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
                state.dateChartData = dateChartData;

                state.gameChartData = buildGameChartData(points);

                if (state.eventMode && data.peak && data.peak.rating > 0 && data.peak.eventDate) {
                    var storedPeakDate = parseGameDate(data.peak.eventDate);
                    if (storedPeakDate) {
                        state.peakValue = data.peak.rating;
                        renderDateSummary(dateSummary, {
                            peak: data.peak.rating,
                            peakDate: storedPeakDate,
                            tournamentName: data.peak.tournamentName || '',
                            tournamentId: data.peak.tournamentId || null,
                            hostCountry: data.peak.hostCountry || '',
                            flagCode: data.peak.flagCode || '',
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
                    cutoffActive,
                    state.lineStyle,
                    state.dayTooltipCtx
                );
                setActiveView(root, state.activeView, state);
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load rating history.';
                }
            });
    }

    var ratingDayTipDismissInstalled = false;

    function installRatingChartTipDismiss() {
        if (ratingDayTipDismissInstalled) {
            return;
        }
        ratingDayTipDismissInstalled = true;
        window.addEventListener('scroll', hideRatingChartTooltips, { passive: true, capture: true });
    }

    function boot() {
        installRatingChartTipDismiss();
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
