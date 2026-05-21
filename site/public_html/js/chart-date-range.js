/**
 * Shared date-range helpers for player time charts (local timezone).
 */
(function (global) {
    'use strict';

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
     * Full month series from first API month through end of current month (y=0 where no games).
     */
    function padGamesPerMonth(months) {
        if (!months.length) {
            return { chartData: [], xMin: null, xMax: endOfCurrentMonth() };
        }

        var byMonth = {};
        for (var i = 0; i < months.length; i++) {
            byMonth[months[i].month] = months[i].games;
        }

        var first = monthToDate(months[0].month);
        if (first === null) {
            return { chartData: [], xMin: null, xMax: endOfCurrentMonth() };
        }

        var xMax = endOfCurrentMonth();
        var chartData = [];
        var cursor = new Date(first.getFullYear(), first.getMonth(), 1);

        while (cursor.getTime() <= xMax.getTime()) {
            var x = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
            var games = byMonth[ymString(cursor)] || 0;
            chartData.push({ x: x, y: games });
            cursor.setMonth(cursor.getMonth() + 1);
        }

        return {
            chartData: chartData,
            xMin: new Date(first.getFullYear(), first.getMonth(), 1),
            xMax: xMax
        };
    }

    global.K2ChartDateRange = {
        monthToDate: monthToDate,
        endOfCurrentMonth: endOfCurrentMonth,
        endOfToday: endOfToday,
        appendRatingThroughToday: appendRatingThroughToday,
        padGamesPerMonth: padGamesPerMonth
    };
}(typeof window !== 'undefined' ? window : this));
