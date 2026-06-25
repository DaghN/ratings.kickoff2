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

    function compareDateTooltipBridgePlugin() {
        return {
            id: 'k2CompareDateTooltipBridge',
            afterEvent: function (chart, args) {
                var event = args.event;
                if (!chart.tooltip || typeof chart.tooltip.update !== 'function') {
                    return;
                }
                if (event.type === 'mouseout' || event.type === 'mouseleave') {
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
        endOfCurrentMonth: endOfCurrentMonth,
        endOfToday: endOfToday,
        appendRatingThroughToday: appendRatingThroughToday,
        padGamesPerMonth: padGamesPerMonth,
        formatCompareDateTooltipTitle: formatCompareDateTooltipTitle,
        resolveCompareDateTooltipItems: resolveCompareDateTooltipItems,
        resolveCompareRankDateTooltipItems: resolveCompareRankDateTooltipItems,
        resolveCompareRatingDateTooltipItems: resolveCompareRatingDateTooltipItems,
        compareDateTooltipBridgePlugin: compareDateTooltipBridgePlugin
    };
}(typeof window !== 'undefined' ? window : this));
