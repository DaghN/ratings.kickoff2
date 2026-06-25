/**
 * Compare two players' full career rating over time (shared date axis).
 * Listens for kool-opponent-selected.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;
    var DR = window.K2ChartDateRange;

    var API_PATH = '/api/player_compare_rating_history.php';
    var EVENT_NAME = 'kool-opponent-selected';
    var CTX = window.K2PlayerOpponentsH2hContext;
    var START_RATING = 1600;
    var HTML_TOOLTIP_ID = 'k2-compare-rating-html-tooltip';
    var RATING_DATE_HOVER_RADIUS = 5;

    function clearCompareRatingDateHoverMarkers(chart) {
        if (!chart || !chart.$k2CompareRatingDateHover) {
            return;
        }
        chart.$k2CompareRatingDateHover = null;
    }

    function ratingDateHoverMarkersFromPoints(points) {
        return points.map(function (item) {
            return {
                y: item.markerY != null ? Number(item.markerY) : Number(item.parsed.y),
                color: item.datasetIndex === 0 ? T.h2hSubjectBorder() : T.h2hOpponentBorder()
            };
        });
    }

    function compareRatingHoverPlugin() {
        return {
            id: 'k2CompareRatingHover',
            afterDraw: function (chart) {
                drawCompareRatingDateHoverMarkers(chart);
            },
            afterEvent: function (chart, args) {
                var event = args.event;
                if (!chart.tooltip || typeof chart.tooltip.update !== 'function') {
                    return;
                }
                if (event.type === 'mouseout' || event.type === 'mouseleave') {
                    clearCompareRatingDateHoverMarkers(chart);
                    if (typeof chart.setActiveElements === 'function') {
                        chart.setActiveElements([]);
                    }
                    chart.draw();
                    return;
                }
                if (event.type !== 'mousemove' && event.type !== 'touchmove') {
                    return;
                }
                var area = chart.chartArea;
                if (!area
                    || event.x < area.left || event.x > area.right
                    || event.y < area.top || event.y > area.bottom) {
                    clearCompareRatingDateHoverMarkers(chart);
                    chart.draw();
                    return;
                }
                chart.tooltip.update(true);
            }
        };
    }

    function drawCompareRatingDateHoverMarkers(chart) {
        var state = chart.$k2CompareRatingDateHover;
        var ctx = chart.ctx;
        var yScale = chart.scales.y;
        var i;
        var marker;
        var py;
        if (!state || !state.markers || !state.markers.length || !ctx || !yScale) {
            return;
        }
        for (i = 0; i < state.markers.length; i++) {
            marker = state.markers[i];
            py = yScale.getPixelForValue(marker.y);
            ctx.save();
            ctx.beginPath();
            ctx.arc(state.caretX, py, RATING_DATE_HOVER_RADIUS, 0, Math.PI * 2);
            ctx.fillStyle = marker.color;
            ctx.fill();
            ctx.lineWidth = 2;
            ctx.strokeStyle = marker.color;
            ctx.stroke();
            ctx.restore();
        }
    }

    function syncCompareRatingDateHoverMarkers(chart, caretX, points) {
        if (!chart || !points || !points.length) {
            clearCompareRatingDateHoverMarkers(chart);
            return;
        }
        chart.$k2CompareRatingDateHover = {
            caretX: caretX,
            markers: ratingDateHoverMarkersFromPoints(points)
        };
        if (typeof chart.setActiveElements === 'function') {
            chart.setActiveElements([]);
        }
        if (!chart.$k2RatingHoverRedraw) {
            chart.$k2RatingHoverRedraw = true;
            requestAnimationFrame(function () {
                chart.$k2RatingHoverRedraw = false;
                if (chart && !chart.destroyed) {
                    chart.draw();
                }
            });
        }
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function ratingClassForDatasetIndex(datasetIndex) {
        return datasetIndex === 0
            ? 'pm3-chart-tooltip-rating--subject'
            : 'pm3-chart-tooltip-rating--opponent';
    }

    function formatRatingHtml(value, datasetIndex) {
        return '<span class="' + ratingClassForDatasetIndex(datasetIndex) + '">'
            + escapeHtml(value) + '</span>';
    }

    function getOrCreateCompareRatingTooltip() {
        var el = document.getElementById(HTML_TOOLTIP_ID);
        if (el) {
            return el;
        }
        el = document.createElement('div');
        el.id = HTML_TOOLTIP_ID;
        el.className = 'k2-chart-html-tooltip';
        el.setAttribute('role', 'tooltip');
        el.hidden = true;
        document.body.appendChild(el);
        return el;
    }

    function buildCompareTooltipTitle(items, isGame, eventMode, hoverMs, hoverX) {
        var pt;
        if (!items.length) {
            return '';
        }
        if (isGame) {
            pt = items[0].raw || {};
            var idx = hoverX != null && isFinite(hoverX)
                ? Math.max(0, Math.floor(hoverX + 1e-6))
                : items[0].parsed.x;
            if (pt.isOrigin && idx <= 0) {
                return eventMode
                    ? 'Tournament #0 — starting rating'
                    : 'Game #0 — starting rating';
            }
            return (eventMode ? 'Tournament #' : 'Game #') + idx;
        }
        if (DR && DR.formatCompareDateTooltipTitle) {
            return DR.formatCompareDateTooltipTitle(hoverMs);
        }
        return '';
    }

    function formatCompareGameTooltipHtml(item, eventMode) {
        var pt = item.raw || {};
        var name = escapeHtml(playerSeriesShortName(item.dataset && item.dataset.label));
        var ratingHtml = formatRatingHtml(item.formattedValue, item.datasetIndex);
        if (pt.isOrigin) {
            return name + ': ' + ratingHtml;
        }
        var line = name;
        if (eventMode && pt.tournamentName) {
            line += ' \u2014 ' + escapeHtml(pt.tournamentName);
        }
        if (pt.date) {
            line += ' \u00b7 ' + escapeHtml(String(pt.date).substring(0, 10));
        }
        return line + ': ' + ratingHtml;
    }

    function formatCompareDateTooltipHtml(item) {
        var name = escapeHtml(playerSeriesShortName(item.dataset && item.dataset.label));
        return name + ': ' + formatRatingHtml(item.formattedValue, item.datasetIndex);
    }

    function swatchColorForItem(item) {
        var ds = item.dataset || {};
        var color = ds.borderColor;
        if (typeof color === 'function') {
            color = color(item);
        }
        if (!color || color === 'transparent') {
            color = item.datasetIndex === 0 ? T.h2hSubjectBorder() : T.h2hOpponentBorder();
        }
        return color;
    }

    function bindCompareRatingExternalTooltip(isGame, eventMode, lineStyle) {
        return function (context) {
            var tooltipEl = getOrCreateCompareRatingTooltip();
            var tooltip = context.tooltip;
            var chart = context.chart;
            var points;
            var title;
            var resolved;
            var stepped = lineStyle === 'stepped';
            if (!tooltip) {
                tooltipEl.hidden = true;
                return;
            }
            if (!isGame && DR && DR.resolveCompareRatingDateTooltipItems) {
                resolved = DR.resolveCompareRatingDateTooltipItems(chart, tooltip.caretX);
                points = resolved.items;
                if (!points.length) {
                    if (typeof chart.setActiveElements === 'function') {
                        chart.setActiveElements([]);
                    }
                    clearCompareRatingDateHoverMarkers(chart);
                    if (!chart.$k2RatingHoverRedraw) {
                        chart.$k2RatingHoverRedraw = true;
                        requestAnimationFrame(function () {
                            chart.$k2RatingHoverRedraw = false;
                            if (chart && !chart.destroyed) {
                                chart.draw();
                            }
                        });
                    }
                    tooltipEl.hidden = true;
                    return;
                }
                title = buildCompareTooltipTitle(points, false, eventMode, resolved.hoverMs, null);
            } else if (isGame && DR && DR.resolveCompareRatingGameTooltipItems) {
                resolved = DR.resolveCompareRatingGameTooltipItems(chart, tooltip.caretX, stepped);
                points = resolved.items;
                if (!points.length) {
                    if (typeof chart.setActiveElements === 'function') {
                        chart.setActiveElements([]);
                    }
                    clearCompareRatingDateHoverMarkers(chart);
                    if (!chart.$k2RatingHoverRedraw) {
                        chart.$k2RatingHoverRedraw = true;
                        requestAnimationFrame(function () {
                            chart.$k2RatingHoverRedraw = false;
                            if (chart && !chart.destroyed) {
                                chart.draw();
                            }
                        });
                    }
                    tooltipEl.hidden = true;
                    return;
                }
                title = buildCompareTooltipTitle(points, true, eventMode, null, resolved.hoverX);
            } else {
                if (tooltip.opacity === 0) {
                    clearCompareRatingDateHoverMarkers(chart);
                    tooltipEl.hidden = true;
                    return;
                }
                points = tooltip.dataPoints || [];
                if (!points.length) {
                    clearCompareRatingDateHoverMarkers(chart);
                    tooltipEl.hidden = true;
                    return;
                }
                title = buildCompareTooltipTitle(points, isGame, eventMode, null, null);
            }
            var bodyHtml = points.map(function (item) {
                var line = isGame
                    ? formatCompareGameTooltipHtml(item, eventMode)
                    : formatCompareDateTooltipHtml(item);
                var swatch = swatchColorForItem(item);
                return '<div class="k2-chart-html-tooltip__row">'
                    + '<span class="k2-chart-html-tooltip__swatch" style="background:'
                    + escapeHtml(swatch) + '"></span>'
                    + '<span class="k2-chart-html-tooltip__text">' + line + '</span>'
                    + '</div>';
            }).join('');
            tooltipEl.innerHTML = '<div class="k2-chart-html-tooltip__title">'
                + escapeHtml(title) + '</div>'
                + '<div class="k2-chart-html-tooltip__body">' + bodyHtml + '</div>';
            tooltipEl.hidden = false;

            var caretX = tooltip.caretX;
            var caretY = tooltip.caretY;
            if (points.length) {
                syncCompareRatingDateHoverMarkers(chart, caretX, points);
                if (DR.compareDateTooltipHigherAnchorPixelY) {
                    var anchorPixelY = DR.compareDateTooltipHigherAnchorPixelY(chart, points);
                    if (anchorPixelY != null) {
                        caretY = anchorPixelY;
                    }
                }
            } else {
                clearCompareRatingDateHoverMarkers(chart);
            }

            var canvas = context.chart.canvas;
            var rect = canvas.getBoundingClientRect();
            var left = rect.left + caretX;
            var top = rect.top + caretY;
            tooltipEl.style.left = left + 'px';
            tooltipEl.style.top = top + 'px';
            tooltipEl.style.opacity = '1';
        };
    }

    function fetchJson(url) {
        return fetch(url, { credentials: 'same-origin' }).then(function (r) {
            if (!r.ok) {
                throw new Error('bad_status');
            }
            return r.json();
        });
    }

    function historyIsEventGranularity(data, realm) {
        return (data && data.meta && data.meta.granularity === 'event')
            || realm === 'amiga';
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
        return 500;
    }

    function buildNiceAxisTickValues(maxGame, eventMode) {
        var step = gameAxisStep(maxGame, eventMode);
        var last = maxGame <= 0 ? 0 : (maxGame % step === 0 ? maxGame : Math.floor(maxGame / step) * step);
        var ticks = [];
        for (var v = 0; v <= last; v += step) {
            ticks.push(v);
        }
        return ticks;
    }

    function amigaDateChartTimeRange(chartData, timelineStart) {
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

    function parseGameDate(dateStr) {
        if (!dateStr) {
            return null;
        }
        var normalized = String(dateStr).trim().replace(' ', 'T');
        var d = new Date(normalized);
        return isNaN(d.getTime()) ? null : d;
    }

    function peakAfterClause(tournamentName) {
        var Core = window.K2PlayerRankChartCore;
        if (Core && Core.peakAfterClause) {
            return Core.peakAfterClause(tournamentName);
        }
        if (!tournamentName) {
            return '';
        }
        return ', after ' + escapeHtml(tournamentName);
    }

    function renderRatingPeakSummary(summary, peak, namePrefix, valueClass) {
        if (!summary) {
            return;
        }
        if (!peak || peak.rating <= 0 || !peak.eventDate) {
            summary.hidden = true;
            return;
        }
        var whenDate = parseGameDate(peak.eventDate);
        if (!whenDate) {
            summary.hidden = true;
            return;
        }
        var when = whenDate.toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
        summary.innerHTML = namePrefix + ' peak: <span class="' + valueClass + '">' + peak.rating + '</span>'
            + ' <span class="pm3d-chart__summary-note">on ' + when
            + peakAfterClause(peak.tournamentName) + '.</span>';
        summary.hidden = false;
    }

    function pointsToChartData(points) {
        var chartData = [];
        for (var i = 0; i < points.length; i++) {
            var x = parseGameDate(points[i].date);
            if (x === null) {
                continue;
            }
            chartData.push({
                x: x,
                y: points[i].rating,
                date: points[i].date,
                gameNumber: points[i].gameNumber,
                eventNumber: points[i].eventNumber,
                tournamentId: points[i].tournamentId,
                tournamentName: points[i].tournamentName,
                gameId: points[i].gameId
            });
        }
        return chartData;
    }

    function playerSeriesShortName(datasetLabel) {
        if (!datasetLabel) {
            return 'Player';
        }
        return datasetLabel.replace(/\s+rating$/i, '');
    }

    function pointsToGameData(points, eventMode) {
        var chartData = [];
        for (var i = 0; i < points.length; i++) {
            var n = eventMode && points[i].eventNumber != null
                ? points[i].eventNumber
                : points[i].gameNumber;
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

    function chartOptions(extra) {
        if (T && T.activityChartOptions) {
            return T.activityChartOptions(Object.assign({ maintainAspectRatio: false }, extra || {}), {
                chartKind: 'line'
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

    function setActiveView(root, view, state) {
        var dateView = root.querySelector('.player-compare-rating-view--date');
        var gameView = root.querySelector('.player-compare-rating-view--game');
        var viewToggle = root.querySelector('.player-compare-rating-chart__view');
        var lineStyleToggle = root.querySelector('.player-compare-rating-chart__line-style');
        var viewButtons = viewToggle
            ? viewToggle.querySelectorAll('.pm3d-rating-toggle__btn[data-view]')
            : [];
        var activeChart;
        var i;
        for (i = 0; i < viewButtons.length; i++) {
            var btn = viewButtons[i];
            var active = btn.getAttribute('data-view') === view;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        }
        if (lineStyleToggle) {
            lineStyleToggle.hidden = false;
        }
        if (dateView) {
            dateView.hidden = view !== 'date';
        }
        if (gameView) {
            gameView.hidden = view !== 'game';
        }
        if (view === 'date' && state.latestConfig) {
            refreshDateChart(state);
        } else if (view === 'game' && state.latestConfig) {
            refreshGameChart(state);
        }
        activeChart = view === 'game' ? state.gameChart : state.dateChart;
        if (activeChart && typeof activeChart.resize === 'function') {
            activeChart.resize();
        }
    }

    function setLineStyleToggle(root, style) {
        var lineStyleToggle = root.querySelector('.player-compare-rating-chart__line-style');
        if (!lineStyleToggle) {
            return;
        }
        var buttons = lineStyleToggle.querySelectorAll('.pm3d-rating-toggle__btn[data-line-style]');
        for (var i = 0; i < buttons.length; i++) {
            var btn = buttons[i];
            var active = btn.getAttribute('data-line-style') === style;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        }
    }

    function refreshDateChart(state) {
        if (!state.latestConfig || !state.dateCanvas) {
            return;
        }
        if (state.dateChart) {
            state.dateChart.destroy();
            state.dateChart = null;
        }
        state.dateChart = renderChart(state.dateCanvas, state.latestConfig, 'date');
    }

    function refreshGameChart(state) {
        if (!state.latestConfig || !state.gameCanvas) {
            return;
        }
        if (state.gameChart) {
            state.gameChart.destroy();
            state.gameChart = null;
        }
        state.gameChart = renderChart(state.gameCanvas, state.latestConfig, 'game');
    }

    function refreshInactiveChart(state) {
        if (!state.latestConfig) {
            return;
        }
        if (state.activeView === 'game') {
            if (state.dateChart) {
                state.dateChart.destroy();
                state.dateChart = null;
            }
        } else if (state.gameChart) {
            state.gameChart.destroy();
            state.gameChart = null;
        }
    }

    function renderChart(canvas, cfg, view) {
        var isGame = view === 'game';
        var eventMode = !!cfg.eventMode;
        var playerData = isGame ? cfg.playerGameData : cfg.playerDateData;
        var opponentData = isGame ? cfg.opponentGameData : cfg.opponentDateData;
        var xScale;
        if (isGame) {
            var combinedMax = 0;
            if (playerData.length) {
                combinedMax = Math.max(combinedMax, playerData[playerData.length - 1].x);
            }
            if (opponentData.length) {
                combinedMax = Math.max(combinedMax, opponentData[opponentData.length - 1].x);
            }
            if (eventMode) {
                playerData = withGameChartOrigin(playerData);
                opponentData = withGameChartOrigin(opponentData);
                if (combinedMax < playerData.length - 1) {
                    combinedMax = playerData.length - 1;
                }
            }
            xScale = {
                type: 'linear',
                min: eventMode ? 0 : undefined,
                title: {
                    display: true,
                    text: eventMode ? 'Tournament number' : 'Rated games played',
                    color: T.tickColor()
                },
                ticks: eventMode ? {
                    color: T.tickColor(),
                    maxRotation: 0,
                    autoSkip: false,
                    callback: function (value) {
                        var ticks = buildNiceAxisTickValues(combinedMax, eventMode);
                        return ticks.indexOf(value) >= 0 ? value : '';
                    }
                } : {
                    color: T.tickColor(),
                    maxRotation: 0,
                    autoSkip: true,
                    maxTicksLimit: 14
                },
                grid: { color: T.softGrid ? T.softGrid() : T.grid() }
            };
        } else {
            var timeRange;
            if (cfg.realm === 'online' && DR && DR.profileCareerTimeRange) {
                timeRange = DR.profileCareerTimeRange();
            } else if (cfg.realm === 'amiga') {
                timeRange = amigaDateChartTimeRange(playerData, cfg.timelineStart);
            } else {
                timeRange = {
                    xMin: DR && DR.serverStartDate ? DR.serverStartDate() : undefined,
                    xMax: DR ? DR.endOfToday() : undefined
                };
            }
            xScale = {
                type: 'time',
                min: timeRange.xMin,
                max: timeRange.xMax,
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
        }

        var lineStyle = cfg.lineStyle || cfg.dateLineStyle || 'smooth';
        var lineStepped = lineStyle === 'stepped';
        var chartConfig = {
            type: 'line',
            data: {
                datasets: [
                    {
                        label: (cfg.player.playerName || 'Player') + ' rating',
                        data: playerData,
                        borderColor: T.h2hSubjectBorder(),
                        backgroundColor: T.h2hSubjectFill(0.1),
                        borderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 0,
                        pointHitRadius: 12,
                        fill: false,
                        stepped: lineStepped,
                        tension: lineStepped ? 0 : 0.1
                    },
                    {
                        label: (cfg.opponent.playerName || cfg.opponentName || 'Opponent') + ' rating',
                        data: opponentData,
                        borderColor: T.h2hOpponentBorder(),
                        backgroundColor: T.h2hOpponentFill(0.1),
                        borderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 0,
                        pointHitRadius: 12,
                        fill: false,
                        stepped: lineStepped,
                        tension: lineStepped ? 0 : 0.1
                    }
                ]
            },
            options: chartOptions({
                interaction: { mode: 'nearest', axis: 'x', intersect: false },
                plugins: {
                    legend: {
                        labels: { color: T.textPrimary() }
                    },
                    tooltip: T.mergeTooltip({
                        enabled: false,
                        external: bindCompareRatingExternalTooltip(isGame, eventMode, lineStyle)
                    })
                },
                scales: {
                    x: xScale,
                    y: {
                        ticks: { color: T.tickColor() },
                        grid: { color: T.softGrid ? T.softGrid() : T.grid() }
                    }
                }
            }, 'line'),
            plugins: [compareRatingHoverPlugin()]
        };

        return createChart(canvas, chartConfig, 'line');
    }

    function initRoot(root) {
        var playerId = root.getAttribute('data-player-id');
        if (!playerId) {
            return;
        }

        var dateCanvas = root.querySelector('.player-compare-rating-canvas--date');
        var gameCanvas = root.querySelector('.player-compare-rating-canvas--game');
        var status = root.querySelector('.player-compare-rating-chart-status');
        var subjectPeakSummary = root.querySelector('.player-compare-rating-peak-subject');
        var opponentPeakSummary = root.querySelector('.player-compare-rating-peak-opponent');
        var viewToggle = root.querySelector('.player-compare-rating-chart__view');
        var lineStyleToggle = root.querySelector('.player-compare-rating-chart__line-style');
        var state = {
            dateChart: null,
            gameChart: null,
            dateCanvas: dateCanvas,
            gameCanvas: gameCanvas,
            latestConfig: null,
            activeView: 'date',
            lineStyle: 'smooth',
            realm: CTX ? CTX.realmFrom(root) : 'online'
        };

        if (!dateCanvas || !gameCanvas || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        function setHeading(opponentLabel) {
            var matchups = root.closest('.pm3d-matchups');
            var heading = matchups ? matchups.querySelector('.player-compare-rating-chart-heading') : null;
            if (!heading) {
                return;
            }
            heading.textContent = opponentLabel
                ? 'Rating comparison vs ' + opponentLabel
                : 'Rating comparison';
        }

        function loadOpponent(opponentId, opponentName) {
            if (!opponentId) {
                return;
            }

            if (status) {
                status.textContent = 'Loading rating comparison…';
            }

            var compareUrl = API_PATH + '?id=' + encodeURIComponent(playerId)
                + '&opponent=' + encodeURIComponent(opponentId)
                + (CTX ? CTX.apiSuffix(root) : '&realm=online');

            fetchJson(compareUrl)
                .then(function (data) {
                    var player = data.player || {};
                    var opponent = data.opponent || {};
                    var eventMode = historyIsEventGranularity(data, state.realm);
                    var timelineStart = data.timelineStart || null;
                    var playerDateData = pointsToChartData(player.points || []);
                    var opponentDateData = pointsToChartData(opponent.points || []);
                    var playerGameData = pointsToGameData(player.points || [], eventMode);
                    var opponentGameData = pointsToGameData(opponent.points || [], eventMode);

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
                        if (subjectPeakSummary) {
                            subjectPeakSummary.hidden = true;
                        }
                        if (opponentPeakSummary) {
                            opponentPeakSummary.hidden = true;
                        }
                        if (status) {
                            status.textContent = 'No rated games to chart.';
                        }
                        return;
                    }

                    setHeading(opponent.playerName || opponentName);

                    if (state.realm === 'amiga') {
                        renderRatingPeakSummary(
                            subjectPeakSummary,
                            player.peak,
                            player.playerName || 'Player',
                            'pm3-chart-peak-value pm3-chart-peak-value--subject'
                        );
                        renderRatingPeakSummary(
                            opponentPeakSummary,
                            opponent.peak,
                            opponent.playerName || opponentName || 'Opponent',
                            'pm3-chart-peak-value pm3-chart-peak-value--opponent'
                        );
                    } else {
                        if (subjectPeakSummary) {
                            subjectPeakSummary.hidden = true;
                        }
                        if (opponentPeakSummary) {
                            opponentPeakSummary.hidden = true;
                        }
                    }

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
                        opponentGameData: opponentGameData,
                        eventMode: eventMode,
                        lineStyle: state.lineStyle,
                        realm: state.realm,
                        timelineStart: timelineStart
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

        var h2hRoot = root.closest('.k2-player-opponents-h2h');
        if (h2hRoot) {
            var initialId = h2hRoot.getAttribute('data-chart-opponent-id');
            if (initialId) {
                loadOpponent(initialId, h2hRoot.getAttribute('data-chart-opponent-name') || '');
            }
        }

        if (viewToggle) {
            viewToggle.addEventListener('click', function (evt) {
                var btn = evt.target.closest('.pm3d-rating-toggle__btn[data-view]');
                var view;
                if (!btn || !viewToggle.contains(btn)) {
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

        if (lineStyleToggle) {
            lineStyleToggle.addEventListener('click', function (evt) {
                var btn = evt.target.closest('.pm3d-rating-toggle__btn[data-line-style]');
                var style;
                if (!btn || !lineStyleToggle.contains(btn)) {
                    return;
                }
                style = btn.getAttribute('data-line-style');
                if (!style || style === state.lineStyle) {
                    return;
                }
                state.lineStyle = style;
                setLineStyleToggle(root, style);
                if (state.latestConfig) {
                    state.latestConfig.lineStyle = style;
                    if (state.activeView === 'game') {
                        refreshGameChart(state);
                    } else {
                        refreshDateChart(state);
                    }
                    refreshInactiveChart(state);
                }
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
