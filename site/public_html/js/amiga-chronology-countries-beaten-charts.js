/**
 * Countries beaten chronology graphs — inline payload from graphs.php. — inline payload from graphs.php.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;
    var DR = window.K2ChartDateRange;

    function chartColor() {
        return T ? T.tintChartInk() : '#ffb74d';
    }

    function yearToDate(year) {
        var y = parseInt(year, 10);
        if (!y || y < 1000) {
            return null;
        }
        var d = new Date(y + '-01-01T00:00:00');
        return isNaN(d.getTime()) ? null : d;
    }

    function parseGameDate(dateStr) {
        if (!dateStr) {
            return null;
        }
        var s = String(dateStr).trim();
        var d = new Date(s.indexOf('T') === -1 ? s.replace(' ', 'T') : s);
        if (isNaN(d.getTime()) && s.length >= 10) {
            d = new Date(s.substring(0, 10) + 'T00:00:00');
        }
        return isNaN(d.getTime()) ? null : d;
    }

    function readPayload() {
        var el = document.getElementById('k2-amiga-chronology-countries-beaten-chart-data');
        if (!el || !el.textContent) {
            return null;
        }
        try {
            return JSON.parse(el.textContent);
        } catch (e) {
            return null;
        }
    }

    function maybeShowEmptyNote(totalUnlocks, label) {
        var note = document.getElementById('k2-amiga-chronology-charts-empty-note');
        if (!note || totalUnlocks > 0) {
            return;
        }
        note.hidden = false;
        note.textContent = 'No countries beaten yet. The charts below span the Amiga rated era (zero unlocks so far).';
    }

    function initYearChart(root, data) {
        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.k2-amiga-chronology-year-chart-status');
        if (!canvas || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        var years = data.years || [];
        var color = chartColor();
        var xMin = data.first_year ? yearToDate(data.first_year) : null;
        var chartData = [];
        var i;

        for (i = 0; i < years.length; i++) {
            var x = yearToDate(years[i].year);
            if (x === null) {
                continue;
            }
            chartData.push({ x: x, y: years[i].unlocks });
        }

        if (!chartData.length) {
            if (status) {
                status.textContent = 'No rated history to chart.';
            }
            return;
        }

        if (status) {
            status.textContent = '';
        }

        new Chart(canvas, {
            type: 'bar',
            data: {
                datasets: [Object.assign({
                    label: 'New countries beaten',
                    data: chartData
                }, T.barStroke(color))]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { labels: { color: T.textSecondary() } },
                    tooltip: T.mergeTooltip({
                        callbacks: {
                            title: function (items) {
                                if (!items.length) {
                                    return '';
                                }
                                var d = new Date(items[0].parsed.x);
                                return isNaN(d.getTime()) ? '' : String(d.getFullYear());
                            },
                            label: function (item) {
                                var n = item.parsed.y || 0;
                                return n + (n === 1 ? ' country beaten' : ' countries beaten');
                            }
                        }
                    })
                },
                scales: {
                    x: {
                        type: 'time',
                        min: xMin || undefined,
                        max: DR ? DR.endOfToday() : undefined,
                        time: {
                            unit: 'year',
                            round: 'year',
                            displayFormats: { year: 'yyyy' }
                        },
                        ticks: { color: T.tickColor(), maxRotation: 45, autoSkip: true },
                        grid: { color: T.grid() }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { color: T.tickColor(), precision: 0 },
                        grid: { color: T.grid() }
                    }
                }
            }
        });
    }

    function initCumulativeChart(root, data) {
        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.k2-amiga-chronology-cumulative-chart-status');
        if (!canvas || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        var points = data.cumulative_points || [];
        var color = chartColor();
        var firstX = data.first_year ? yearToDate(data.first_year) : null;
        var chartData = [];
        var lastY = 0;
        var i;
        var x;

        if (firstX) {
            chartData.push({ x: firstX, y: 0 });
        }

        for (i = 0; i < points.length; i++) {
            x = parseGameDate(points[i].date);
            if (x === null) {
                continue;
            }
            lastY = points[i].cumulative;
            chartData.push({ x: x, y: lastY });
        }

        if (!chartData.length && firstX) {
            chartData.push({ x: firstX, y: 0 });
        }

        if (DR && DR.appendRatingThroughToday) {
            chartData = DR.appendRatingThroughToday(chartData, lastY);
        }

        if (!chartData.length) {
            if (status) {
                status.textContent = 'No rated history to chart.';
            }
            return;
        }

        if (status) {
            status.textContent = '';
        }

        new Chart(canvas, {
            type: 'line',
            data: {
                datasets: [Object.assign({
                    label: 'Cumulative countries beaten',
                    data: chartData,
                    fill: true,
                    stepped: true,
                    pointRadius: 0,
                    pointHitRadius: 6
                }, T.lineStroke(color))]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { labels: { color: T.textSecondary() } },
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
                            label: function (item) {
                                return 'Total countries beaten: ' + item.parsed.y;
                            }
                        }
                    })
                },
                scales: {
                    x: {
                        type: 'time',
                        min: firstX || undefined,
                        max: DR ? DR.endOfToday() : undefined,
                        time: {
                            displayFormats: {
                                year: 'yyyy',
                                month: 'MMM yyyy',
                                day: 'd MMM yyyy'
                            }
                        },
                        ticks: {
                            color: T.tickColor(),
                            maxRotation: 45,
                            autoSkip: true,
                            maxTicksLimit: 18
                        },
                        grid: { color: T.grid() }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { color: T.tickColor(), precision: 0 },
                        grid: { color: T.grid() }
                    }
                }
            }
        });
    }

    function boot() {
        var data = readPayload();
        if (!data) {
            return;
        }
        maybeShowEmptyNote(data.total_unlocks || 0, data.kind_label);
        var yearRoot = document.querySelector('.k2-amiga-chronology-year-chart');
        var cumRoot = document.querySelector('.k2-amiga-chronology-cumulative-chart');
        if (yearRoot) {
            initYearChart(yearRoot, data);
        }
        if (cumRoot) {
            initCumulativeChart(cumRoot, data);
        }
    }

    if (typeof window.k2OnPageReady === 'function') {
        window.k2OnPageReady(boot);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();