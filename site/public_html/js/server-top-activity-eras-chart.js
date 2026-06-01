/**
 * All-time busiest players: monthly games for the current top N by total
 * rated games (playertable.NumberGames), full server month range.
 * Expects api/server_top_activity_eras.php and chartjs-adapter-date-fns.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;
    var API_PATH = 'api/server_top_activity_eras.php';

    var PALETTE = [
        function () { return T.pitch(); },
        function () { return T.chrome(); },
        function () { return T.amber(); },
        function () { return T.teal(); },
        function () { return T.magenta(); },
        function () { return T.holo(); },
        function () { return '#e57373'; },
        function () { return '#81c784'; },
        function () { return '#fff176'; },
        function () { return '#4fc3f7'; },
        function () { return '#ba68c8'; },
        function () { return '#ff8a65'; },
        function () { return '#aed581'; },
        function () { return '#4dd0e1'; },
        function () { return '#f06292'; },
        function () { return '#dce775'; },
        function () { return '#7986cb'; },
        function () { return '#a1887f'; },
        function () { return '#90a4ae'; },
        function () { return '#ffab91'; }
    ];

    function playerColor(index) {
        var fn = PALETTE[index % PALETTE.length];
        return fn();
    }

    function monthToDate(monthStr) {
        if (!monthStr || monthStr.length < 7) { return null; }
        var d = new Date(monthStr + '-15T00:00:00');
        return isNaN(d.getTime()) ? null : d;
    }

    function buildMonthIndex(months) {
        var idx = {};
        for (var i = 0; i < months.length; i++) {
            idx[months[i]] = i;
        }
        return idx;
    }

    /** Trailing mean; uses fewer months at the start of the series (expanding window). */
    function rollingMeanTrailing(values, windowSize) {
        var n = values.length;
        var out = new Array(n);
        for (var i = 0; i < n; i++) {
            var start = Math.max(0, i - windowSize + 1);
            var sum = 0;
            for (var k = start; k <= i; k++) {
                sum += values[k];
            }
            out[i] = sum / (i - start + 1);
        }
        return out;
    }

    var ROLLING_MONTHS = 6;

    function formatMonthLabel(rawX) {
        if (rawX == null) { return ''; }
        var dt = new Date(rawX);
        if (isNaN(dt.getTime())) { return ''; }
        return dt.toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'long'
        });
    }

    function applyDatasetHighlight(chart, activeIdx) {
        if (chart._k2HighlightIdx === activeIdx) {
            return;
        }
        chart._k2HighlightIdx = activeIdx;
        var dsList = chart.data.datasets;
        for (var d = 0; d < dsList.length; d++) {
            var ds = dsList[d];
            var base = ds._k2BaseColor;
            if (activeIdx === -1 || d === activeIdx) {
                ds.borderColor = base;
                ds.borderWidth = activeIdx === -1 ? 2 : 3;
                ds.backgroundColor = T.fill(base, activeIdx === -1 ? 0.08 : 0.12);
            } else {
                ds.borderColor = T.fill(base, 0.2);
                ds.borderWidth = 2;
                ds.backgroundColor = 'transparent';
            }
        }
        chart.update('none');
    }

    function initRoot(root) {
        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.server-top-activity-eras-chart-status');
        if (!canvas || typeof Chart === 'undefined') {
            if (status) { status.textContent = 'Chart library failed to load.'; }
            return;
        }
        if (status) { status.textContent = 'Loading\u2026'; }

        fetch(API_PATH + '?realm=online', { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) { throw new Error('bad_status'); }
                return r.json();
            })
            .then(function (data) {
                var months = data.months || [];
                var players = data.players || [];
                if (!months.length || !players.length) {
                    if (status) {
                        var note = (data.meta && data.meta.note) || '';
                        status.textContent = note
                            ? 'No data available (' + note + ').'
                            : 'No busiest-player data to chart.';
                    }
                    return;
                }

                var monthIndex = buildMonthIndex(months);
                var monthDates = [];
                for (var m = 0; m < months.length; m++) {
                    monthDates.push(monthToDate(months[m]));
                }

                var datasets = [];
                for (var p = 0; p < players.length; p++) {
                    var player = players[p];
                    var points = player.points || [];
                    if (!points.length) { continue; }

                    var dataArr = new Array(months.length);
                    for (var j = 0; j < points.length; j++) {
                        var mi = monthIndex[points[j].month];
                        if (mi !== undefined) {
                            dataArr[mi] = points[j].games;
                        }
                    }
                    for (var i = 0; i < months.length; i++) {
                        if (dataArr[i] === undefined) {
                            dataArr[i] = 0;
                        }
                    }
                    dataArr = rollingMeanTrailing(dataArr, ROLLING_MONTHS);

                    var color = playerColor(p);
                    datasets.push({
                        label: player.name,
                        data: dataArr,
                        _k2BaseColor: color,
                        borderColor: color,
                        backgroundColor: T.fill(color, 0.08),
                        borderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 0,
                        pointHitRadius: 12,
                        fill: false,
                        spanGaps: false,
                        tension: 0.25,
                        _k2PlayerId: player.id,
                        _k2TotalGames: player.total_games
                    });
                }

                if (!datasets.length) {
                    if (status) { status.textContent = 'No chartable data.'; }
                    return;
                }

                if (status) { status.textContent = ''; }

                var chartInstance = T.createChart(canvas, {
                    type: 'line',
                    data: {
                        labels: monthDates,
                        datasets: datasets
                    },
                    options: T.mergeChartOptions({
                        responsive: true,
                        maintainAspectRatio: true,
                        interaction: {
                            mode: 'dataset',
                            intersect: false
                        },
                        elements: {
                            point: {
                                radius: 0,
                                hoverRadius: 0,
                                hitRadius: 12
                            }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: T.mergeTooltip({
                                /* dataset mode lists every month on the line; nearest = one point */
                                mode: 'nearest',
                                intersect: false,
                                displayColors: false,
                                callbacks: {
                                    title: function (items) {
                                        if (!items.length) { return ''; }
                                        var item = items[0];
                                        var name = (item.dataset && item.dataset.label)
                                            ? item.dataset.label
                                            : '';
                                        var month = formatMonthLabel(
                                            item.parsed && item.parsed.x
                                        );
                                        if (name && month) {
                                            return [name, month];
                                        }
                                        return name || month || '';
                                    },
                                    /* undefined = Chart.js default "Name: 12.3" body line */
                                    label: function () {
                                        return '';
                                    },
                                    footer: function () {
                                        return '';
                                    }
                                }
                            }),
                        },
                        onHover: function (event, elements) {
                            var activeIdx = elements.length ? elements[0].datasetIndex : -1;
                            applyDatasetHighlight(chartInstance, activeIdx);
                            var target = event && event.native ? event.native.target : canvas;
                            if (target) {
                                target.style.cursor = activeIdx === -1 ? 'default' : 'pointer';
                            }
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    unit: 'month',
                                    round: 'month',
                                    displayFormats: {
                                        month: 'MMM yyyy',
                                        year: 'yyyy'
                                    }
                                },
                                ticks: {
                                    color: T.tickColor(),
                                    maxRotation: 45,
                                    autoSkip: true,
                                    maxTicksLimit: 24
                                },
                                grid: { color: T.softGrid() }
                            },
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Games / month (' + ROLLING_MONTHS + '-mo avg)',
                                    color: T.textMuted(),
                                    font: { size: 11 }
                                },
                                ticks: {
                                    color: T.tickColor(),
                                    precision: 1
                                },
                                grid: { color: T.softGrid() }
                            }
                        }
                    }, 'line'),
                });

                chartInstance._k2HighlightIdx = -1;
                canvas.addEventListener('mouseleave', function () {
                    applyDatasetHighlight(chartInstance, -1);
                    canvas.style.cursor = 'default';
                });
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load busiest players chart.';
                }
            });
    }

    function boot() {
        var roots = document.querySelectorAll('.server-top-activity-eras-chart');
        for (var i = 0; i < roots.length; i++) {
            (function (root) {
                T.whenBlockVisible(root, function () {
                    initRoot(root);
                }, 10);
            })(roots[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
