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

    global.K2ChartDateRange = {
        monthToDate: monthToDate,
        parseStartDate: parseStartDate,
        serverStartDate: serverStartDate,
        serverStartMonth: serverStartMonth,
        profileCareerTimeRange: profileCareerTimeRange,
        endOfCurrentMonth: endOfCurrentMonth,
        endOfToday: endOfToday,
        appendRatingThroughToday: appendRatingThroughToday,
        padGamesPerMonth: padGamesPerMonth
    };
}(typeof window !== 'undefined' ? window : this));
