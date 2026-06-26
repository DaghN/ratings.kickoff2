/**
 * Shared date-range helpers for player time charts (local timezone).
 */
(function (global) {
    'use strict';

    var SERVER_START = new Date(2017, 5, 9, 0, 0, 0, 0);

    function monthToDate(monthStr) {
        if (!monthStr || monthStr.length < 7) {
            return null;
        }
        var d = new Date(monthStr + '-01T00:00:00');
        return isNaN(d.getTime()) ? null : d;
    }

    function ymString(d) {
        var m = d.getMonth() + 1;
        return d.getFullYear() + '-' + (m < 10 ? '0' : '') + m;
    }

    function endOfCurrentMonth() {
        var now = new Date();
        return new Date(now.getFullYear(), now.getMonth() + 1, 0, 23, 59, 59, 999);
    }

    function endOfToday() {
        var now = new Date();
        return new Date(now.getFullYear(), now.getMonth(), now.getDate(), 23, 59, 59, 999);
    }

    function startOfToday() {
        var now = new Date();
        return new Date(now.getFullYear(), now.getMonth(), now.getDate(), 0, 0, 0, 0);
    }

    function parseStartDate(value) {
        if (!value) {
            return null;
        }
        var s = String(value).trim();
        if (!s) {
            return null;
        }
        var d = new Date(s.replace(' ', 'T'));
        if (isNaN(d.getTime()) && s.length >= 10) {
            d = new Date(s.slice(0, 10) + 'T00:00:00');
        }
        return isNaN(d.getTime()) ? null : d;
    }

    function serverStartDate(override) {
        var custom = parseStartDate(override);
        if (custom) {
            return custom;
        }
        return new Date(SERVER_START.getTime());
    }

    function serverStartMonth() {
        return new Date(SERVER_START.getFullYear(), SERVER_START.getMonth(), 1);
    }

    /**
     * Profile career stack (rating by date + games/month): shared x-axis window.
     * Data may end earlier (e.g. rating line at today); axis runs through month-end.
     */
    function profileCareerTimeRange() {
        return {
            xMin: serverStartMonth(),
            xMax: endOfCurrentMonth()
        };
    }

    /** Amiga (or other realms): x-axis from ladder / career origin through today. */
    function careerTimeRangeFromStart(startValue) {
        var start = parseStartDate(startValue);
        if (!start) {
            return { xMin: undefined, xMax: endOfToday() };
        }
        return {
            xMin: new Date(start.getFullYear(), start.getMonth(), 1),
            xMax: endOfToday()
        };
    }

    function endOfDay(d) {
        if (!(d instanceof Date) || isNaN(d.getTime())) {
            return null;
        }
        return new Date(d.getFullYear(), d.getMonth(), d.getDate(), 23, 59, 59, 999);
    }

    function maxEventDateFromPoints(points, dateField) {
        var field = dateField || 'eventDate';
        if (!points || !points.length) {
            return null;
        }
        var maxD = null;
        for (var i = 0; i < points.length; i++) {
            var raw = points[i][field];
            if (raw == null && field === 'eventDate') {
                raw = points[i].date;
            }
            var d = parseStartDate(raw);
            if (!d) {
                continue;
            }
            if (!maxD || d.getTime() > maxD.getTime()) {
                maxD = d;
            }
        }
        return maxD;
    }

    function maxChartPointDate(chartData) {
        if (!chartData || !chartData.length) {
            return null;
        }
        var maxX = null;
        for (var i = 0; i < chartData.length; i++) {
            var xVal = chartData[i].x;
            if (xVal instanceof Date && !isNaN(xVal.getTime())) {
                if (!maxX || xVal.getTime() > maxX.getTime()) {
                    maxX = xVal;
                }
            }
        }
        return maxX;
    }

    /**
     * Community timeline origin through today, or through cutoff day when time travel is active.
     */
    function careerTimeRangeWithCutoff(timelineStart, cutoffActive, cutoffDate) {
        var range = careerTimeRangeFromStart(timelineStart);
        if (!cutoffActive || !cutoffDate) {
            return range;
        }
        var d = cutoffDate instanceof Date ? cutoffDate : parseStartDate(cutoffDate);
        var end = endOfDay(d);
        if (end) {
            range.xMax = end;
        }
        return range;
    }

    function normalizeRankPointsList(pointsList) {
        if (!Array.isArray(pointsList) || !pointsList.length) {
            return [];
        }
        if (Array.isArray(pointsList[0])) {
            var points = pointsList[0] || [];
            for (var p = 1; p < pointsList.length; p++) {
                if ((pointsList[p] || []).length > points.length) {
                    points = pointsList[p];
                }
            }
            return points;
        }
        return pointsList;
    }

    function rankPointsTimeRange(pointsList, timelineStart, cutoffActive) {
        var points = normalizeRankPointsList(pointsList);
        var cutoffDate = cutoffActive ? maxEventDateFromPoints(points, 'eventDate') : null;
        return careerTimeRangeWithCutoff(timelineStart, cutoffActive, cutoffDate);
    }

    function ratingChartTimeRange(chartData, timelineStart, cutoffActive) {
        var cutoffDate = cutoffActive ? maxChartPointDate(chartData) : null;
        if (timelineStart) {
            return careerTimeRangeWithCutoff(timelineStart, cutoffActive, cutoffDate);
        }
        if (chartData && chartData.length && chartData[0].x instanceof Date) {
            var first = chartData[0].x;
            var startStr = first.getFullYear() + '-'
                + String(first.getMonth() + 1).padStart(2, '0') + '-'
                + String(first.getDate()).padStart(2, '0');
            return careerTimeRangeWithCutoff(startStr, cutoffActive, cutoffDate);
        }
        return careerTimeRangeWithCutoff(null, cutoffActive, cutoffDate);
    }

    function sameLocalDay(a, b) {
        return a.getFullYear() === b.getFullYear()
            && a.getMonth() === b.getMonth()
            && a.getDate() === b.getDate();
    }

    /**
     * Append (or replace same-day tail with) a terminal point at end of today.
     */
    function appendRatingThroughToday(chartData, currentRating) {
        if (!chartData.length) {
            return chartData;
        }
        var y = typeof currentRating === 'number' && !isNaN(currentRating)
            ? currentRating
            : chartData[chartData.length - 1].y;
        var terminal = { x: endOfToday(), y: y };
        var out = chartData.slice();
        var last = out[out.length - 1];
        if (sameLocalDay(last.x, terminal.x)) {
            out[out.length - 1] = terminal;
        } else if (last.x.getTime() < terminal.x.getTime()) {
            out.push(terminal);
        }
        return out;
    }

    /**
     * Full month series from server start through end of current month (y=0 where no games).
     */
    function padGamesPerMonth(months) {
        var byMonth = {};
        for (var i = 0; i < months.length; i++) {
            byMonth[months[i].month] = months[i].games;
        }

        var range = profileCareerTimeRange();
        var xMax = range.xMax;
        var chartData = [];
        var first = range.xMin;
        var cursor = new Date(first.getFullYear(), first.getMonth(), 1);

        while (cursor.getTime() <= xMax.getTime()) {
            var x = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
            var games = byMonth[ymString(cursor)] || 0;
            chartData.push({ x: x, y: games });
            cursor.setMonth(cursor.getMonth() + 1);
        }

        return {
            chartData: chartData,
            xMin: range.xMin,
            xMax: range.xMax
        };
    }

    function pad2(n) {
        return String(n).padStart(2, '0');
    }

    function pointXMs(x) {
        if (x instanceof Date) {
            return x.getTime();
        }
        var d = new Date(x);
        return isNaN(d.getTime()) ? NaN : d.getTime();
    }

    function calendarDateKeyFromMs(ms) {
        var d = new Date(ms);
        if (isNaN(d.getTime())) {
            return '';
        }
        return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
    }

    function formatCompareDateTooltipTitle(hoverMs) {
        if (hoverMs == null || isNaN(hoverMs)) {
            return '';
        }
        var d = new Date(hoverMs);
        if (isNaN(d.getTime())) {
            return '';
        }
        return d.toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    /**
     * Stepped or connected rating on a linear x-axis at hoverX.
     * @param {Array<Object>} data sorted by x
     * @param {number} hoverX
     * @param {boolean} stepped
     * @return {number|null}
     */
    function ratingYAtLinearIndex(data, hoverX, stepped) {
        if (!data || !data.length || !isFinite(hoverX)) {
            return null;
        }
        var last = null;
        var next = null;
        var i;
        for (i = 0; i < data.length; i++) {
            var pt = data[i];
            if (!pt || pt.y == null || pt.x == null) {
                continue;
            }
            var px = Number(pt.x);
            if (!isFinite(px)) {
                continue;
            }
            if (px <= hoverX) {
                last = pt;
            }
            if (px >= hoverX && !next) {
                next = pt;
            }
        }
        if (!last) {
            return next ? Number(next.y) : null;
        }
        if (stepped || !next || last.x === next.x || Number(last.x) === hoverX) {
            return Number(last.y);
        }
        var t = (hoverX - Number(last.x)) / (Number(next.x) - Number(last.x));
        return Number(last.y) + t * (Number(next.y) - Number(last.y));
    }

    /**
     * H2H compare on linear x (game # / tournament #): one value per dataset at hover x.
     *
     * @return {{hoverX: number, items: Array<Object>}}
     */
    function resolveCompareIndexTooltipItems(chart, caretX, opts) {
        var empty = { hoverX: NaN, items: [] };
        if (!chart || !opts || caretX == null) {
            return empty;
        }
        var area = chart.chartArea;
        if (!area || caretX < area.left || caretX > area.right) {
            return empty;
        }
        var xScale = chart.scales && chart.scales.x;
        if (!xScale || typeof xScale.getValueForPixel !== 'function') {
            return empty;
        }
        var hoverX = Number(xScale.getValueForPixel(caretX));
        if (!isFinite(hoverX)) {
            return empty;
        }
        var items = [];
        var ds;
        for (ds = 0; ds < chart.data.datasets.length; ds++) {
            var dataset = chart.data.datasets[ds];
            var meta = chart.getDatasetMeta(ds);
            if (!meta || meta.hidden) {
                continue;
            }
            var best = null;
            var i;
            for (i = 0; i < meta.data.length; i++) {
                var pt = dataset.data[i];
                if (!pt || !opts.acceptDatasetPoint(pt)) {
                    continue;
                }
                var px = Number(pt.x);
                if (!isFinite(px) || px > hoverX) {
                    continue;
                }
                var order = opts.orderFromPoint(pt);
                if (!best || px > best.px || (px === best.px && order > best.order)) {
                    best = {
                        pt: pt,
                        px: px,
                        order: order,
                        index: i,
                        element: meta.data[i]
                    };
                }
            }
            if (best) {
                items.push(opts.buildTooltipItem(
                    chart,
                    best.pt,
                    best.element,
                    dataset,
                    ds,
                    best.index,
                    hoverX
                ));
            }
        }
        return { hoverX: hoverX, items: items };
    }

    /**
     * @return {{hoverX: number, items: Array<Object>}}
     */
    function resolveCompareRatingGameTooltipItems(chart, caretX, stepped) {
        return resolveCompareIndexTooltipItems(chart, caretX, {
            acceptDatasetPoint: function (pt) {
                return !!(pt && pt.y != null && pt.x != null);
            },
            orderFromPoint: function (pt) {
                return (Number(pt.eventNumber) || Number(pt.gameNumber) || 0) * 1000000
                    + (Number(pt.tournamentId) || Number(pt.gameId) || 0);
            },
            buildTooltipItem: function (chart, pt, element, dataset, datasetIndex, index, hoverX) {
                var lineY = ratingYAtLinearIndex(dataset.data, hoverX, stepped);
                var displayY = lineY != null ? lineY : pt.y;
                return {
                    chart: chart,
                    dataset: dataset,
                    datasetIndex: datasetIndex,
                    index: index,
                    parsed: { x: Number(pt.x), y: displayY },
                    raw: pt,
                    formattedValue: String(Math.round(displayY)),
                    element: element,
                    markerY: displayY
                };
            }
        });
    }

    /**
     * H2H compare-by-date external tooltip: one stepped value per dataset at hover x.
     * Same calendar day with multiple finalizes → latest event that day; else last point at/before hover.
     *
     * @param {Object} chart Chart.js instance
     * @param {number} caretX canvas x of hover
     * @param {Object} opts
     * @param {function(*):boolean} opts.acceptDatasetPoint
     * @param {function(*):string} opts.dayKeyFromPoint
     * @param {function(*):number} opts.orderFromPoint
     * @param {function(Object, *, *, Object, number, number):Object} opts.buildTooltipItem
     * @return {{hoverMs: number, items: Array<Object>}}
     */
    function resolveCompareDateTooltipItems(chart, caretX, opts) {
        var empty = { hoverMs: NaN, items: [] };
        if (!chart || !opts || caretX == null) {
            return empty;
        }
        var area = chart.chartArea;
        if (!area || caretX < area.left || caretX > area.right) {
            return empty;
        }
        var xScale = chart.scales && chart.scales.x;
        if (!xScale || typeof xScale.getValueForPixel !== 'function') {
            return empty;
        }
        var hoverValue = xScale.getValueForPixel(caretX);
        var hoverMs = pointXMs(hoverValue);
        if (isNaN(hoverMs)) {
            return empty;
        }
        var hoverDayKey = calendarDateKeyFromMs(hoverMs);
        var items = [];
        var ds;
        for (ds = 0; ds < chart.data.datasets.length; ds++) {
            var dataset = chart.data.datasets[ds];
            var meta = chart.getDatasetMeta(ds);
            if (!meta || meta.hidden) {
                continue;
            }
            var bestSameDay = null;
            var bestAtOrBefore = null;
            var i;
            for (i = 0; i < meta.data.length; i++) {
                var element = meta.data[i];
                var pt = dataset.data[i];
                if (!element || !opts.acceptDatasetPoint(pt)) {
                    continue;
                }
                var xMs = pointXMs(pt.x);
                if (isNaN(xMs)) {
                    continue;
                }
                var dayKey = opts.dayKeyFromPoint(pt);
                var order = opts.orderFromPoint(pt);
                var candidate = { pt: pt, xMs: xMs, order: order, index: i, element: element };

                if (dayKey === hoverDayKey) {
                    if (!bestSameDay || xMs > bestSameDay.xMs
                        || (xMs === bestSameDay.xMs && order > bestSameDay.order)) {
                        bestSameDay = candidate;
                    }
                }
                if (xMs <= hoverMs) {
                    if (!bestAtOrBefore || xMs > bestAtOrBefore.xMs
                        || (xMs === bestAtOrBefore.xMs && order > bestAtOrBefore.order)) {
                        bestAtOrBefore = candidate;
                    }
                }
            }
            var chosen = bestSameDay || bestAtOrBefore;
            if (chosen) {
                items.push(opts.buildTooltipItem(
                    chart,
                    chosen.pt,
                    chosen.element,
                    dataset,
                    ds,
                    chosen.index
                ));
            }
        }
        return { hoverMs: hoverMs, items: items };
    }

    /**
     * @return {{hoverMs: number, items: Array<Object>}}
     */
    function resolveCompareRankDateTooltipItems(chart, caretX) {
        return resolveCompareDateTooltipItems(chart, caretX, {
            acceptDatasetPoint: function (pt) {
                return !!(pt && !pt.clipped && pt.y != null && pt.raw);
            },
            dayKeyFromPoint: function (pt) {
                if (pt.raw && pt.raw.eventDate) {
                    return String(pt.raw.eventDate).substring(0, 10);
                }
                return calendarDateKeyFromMs(pointXMs(pt.x));
            },
            orderFromPoint: function (pt) {
                var r = pt.raw || {};
                return (Number(r.eventChrono) || 0) * 1000000 + (Number(r.tournamentId) || 0);
            },
            buildTooltipItem: function (chart, pt, element, dataset, datasetIndex, index) {
                return {
                    chart: chart,
                    dataset: dataset,
                    datasetIndex: datasetIndex,
                    index: index,
                    parsed: { x: pointXMs(pt.x), y: pt.y },
                    raw: pt,
                    formattedValue: String(pt.y),
                    element: element
                };
            }
        });
    }

    /**
     * @return {{hoverMs: number, items: Array<Object>}}
     */
    function resolveCompareRatingDateTooltipItems(chart, caretX) {
        return resolveCompareDateTooltipItems(chart, caretX, {
            acceptDatasetPoint: function (pt) {
                return !!(pt && pt.y != null && pt.x != null);
            },
            dayKeyFromPoint: function (pt) {
                if (pt.date) {
                    return String(pt.date).substring(0, 10);
                }
                return calendarDateKeyFromMs(pointXMs(pt.x));
            },
            orderFromPoint: function (pt) {
                return (Number(pt.eventNumber) || Number(pt.gameNumber) || 0) * 1000000
                    + (Number(pt.tournamentId) || Number(pt.gameId) || 0);
            },
            buildTooltipItem: function (chart, pt, element, dataset, datasetIndex, index) {
                return {
                    chart: chart,
                    dataset: dataset,
                    datasetIndex: datasetIndex,
                    index: index,
                    parsed: { x: pointXMs(pt.x), y: pt.y },
                    raw: pt,
                    formattedValue: String(pt.y),
                    element: element
                };
            }
        });
    }

    function compareDateTooltipActiveElements(points) {
        var out = [];
        var i;
        for (i = 0; i < points.length; i++) {
            out.push({
                datasetIndex: points[i].datasetIndex,
                index: points[i].index
            });
        }
        return out;
    }

    function compareDateTooltipHigherAnchorY(points) {
        if (!points || !points.length) {
            return null;
        }
        var anchor = points[0];
        var i;
        for (i = 1; i < points.length; i++) {
            if (Number(points[i].parsed.y) > Number(anchor.parsed.y)) {
                anchor = points[i];
            }
        }
        return anchor.element ? anchor.element.y : null;
    }

    /** Canvas Y for tooltip anchor — from line value, not stored point pixel (rating by-date). */
    function compareDateTooltipHigherAnchorPixelY(chart, points) {
        if (!points || !points.length || !chart || !chart.scales || !chart.scales.y) {
            return null;
        }
        var yScale = chart.scales.y;
        var best = points[0];
        var i;
        for (i = 1; i < points.length; i++) {
            if (Number(points[i].parsed.y) > Number(best.parsed.y)) {
                best = points[i];
            }
        }
        return yScale.getPixelForValue(best.parsed.y);
    }

    function compareDateTooltipBridgePlugin() {
        return {
            id: 'k2CompareDateTooltipBridge',
            afterEvent: function (chart, args) {
                var event = args.event;
                if (!chart.tooltip || typeof chart.tooltip.update !== 'function') {
                    return;
                }
                if (event.type === 'mouseout' || event.type === 'mouseleave') {
                    if (typeof chart.setActiveElements === 'function') {
                        chart.setActiveElements([]);
                    }
                    if (chart.$k2CompareRatingDateHover) {
                        chart.$k2CompareRatingDateHover = null;
                        chart.draw();
                    }
                    return;
                }
                if (event.type !== 'mousemove' && event.type !== 'touchmove') {
                    return;
                }
                var area = chart.chartArea;
                if (!area
                    || event.x < area.left || event.x > area.right
                    || event.y < area.top || event.y > area.bottom) {
                    return;
                }
                chart.tooltip.update(true);
            }
        };
    }

    global.K2ChartDateRange = {
        monthToDate: monthToDate,
        parseStartDate: parseStartDate,
        serverStartDate: serverStartDate,
        serverStartMonth: serverStartMonth,
        profileCareerTimeRange: profileCareerTimeRange,
        careerTimeRangeFromStart: careerTimeRangeFromStart,
        careerTimeRangeWithCutoff: careerTimeRangeWithCutoff,
        endOfDay: endOfDay,
        maxEventDateFromPoints: maxEventDateFromPoints,
        maxChartPointDate: maxChartPointDate,
        normalizeRankPointsList: normalizeRankPointsList,
        rankPointsTimeRange: rankPointsTimeRange,
        ratingChartTimeRange: ratingChartTimeRange,
        endOfCurrentMonth: endOfCurrentMonth,
        endOfToday: endOfToday,
        appendRatingThroughToday: appendRatingThroughToday,
        padGamesPerMonth: padGamesPerMonth,
        formatCompareDateTooltipTitle: formatCompareDateTooltipTitle,
        resolveCompareDateTooltipItems: resolveCompareDateTooltipItems,
        resolveCompareRankDateTooltipItems: resolveCompareRankDateTooltipItems,
        resolveCompareRatingDateTooltipItems: resolveCompareRatingDateTooltipItems,
        resolveCompareRatingGameTooltipItems: resolveCompareRatingGameTooltipItems,
        resolveCompareIndexTooltipItems: resolveCompareIndexTooltipItems,
        ratingYAtLinearIndex: ratingYAtLinearIndex,
        compareDateTooltipActiveElements: compareDateTooltipActiveElements,
        compareDateTooltipHigherAnchorY: compareDateTooltipHigherAnchorY,
        compareDateTooltipHigherAnchorPixelY: compareDateTooltipHigherAnchorPixelY,
        compareDateTooltipBridgePlugin: compareDateTooltipBridgePlugin
    };
}(typeof window !== 'undefined' ? window : this));
