/**
 * Top activity eras: monthly line chart showing players while they are
 * in the top N for rated games in a calendar month.
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

    function initRoot(root) {
        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.server-top-activity-eras-chart-status');
        var hint = root.querySelector('.k2-chart-block__hint');
        if (!canvas || typeof Chart === 'undefined') {
            if (status) { status.textContent = 'Chart library failed to load.'; }
            return;
        }
        if (status) { status.textContent = 'Loading top activity eras\u2026'; }

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
                            : 'No top activity data to chart.';
                    }
                    return;
                }

                if (hint) {
                    hint.textContent = players.length + ' players have appeared in the monthly top '
                        + (data.limit || 10) + ' across ' + months.length + ' months. Hover for names and ranks.';
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
                    for (var i = 0; i < months.length; i++) { dataArr[i] = null; }

                    var rankMap = {};
                    for (var j = 0; j < points.length; j++) {
                        var mi = monthIndex[points[j].month];
                        if (mi !== undefined) {
                            dataArr[mi] = points[j].games;
                            rankMap[points[j].month] = points[j].rank;
                        }
                    }

                    var color = playerColor(p);
                    datasets.push({
                        label: player.name,
                        data: dataArr,
                        borderColor: color,
                        backgroundColor: T.fill(color, 0.08),
                        borderWidth: 2,
                        pointRadius: 0,
                        pointHitRadius: 8,
                        pointHoverRadius: 4,
                        pointHoverBorderWidth: 2,
                        fill: false,
                        spanGaps: false,
                        tension: 0.25,
                        _k2RankMap: rankMap,
                        _k2PlayerId: player.id
                    });
                }

                if (!datasets.length) {
                    if (status) { status.textContent = 'No chartable data.'; }
                    return;
                }

                if (status) { status.textContent = ''; }

                var chartInstance = new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: monthDates,
                        datasets: datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        interaction: {
                            mode: 'nearest',
                            axis: 'x',
                            intersect: false
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    title: function (items) {
                                        if (!items.length) { return ''; }
                                        var raw = items[0].parsed && items[0].parsed.x;
                                        if (raw != null) {
                                            var dt = new Date(raw);
                                            if (!isNaN(dt.getTime())) {
                                                return dt.toLocaleDateString(undefined, {
                                                    year: 'numeric', month: 'long'
                                                });
                                            }
                                        }
                                        return items[0].label || '';
                                    },
                                    label: function (ctx) {
                                        var ds = ctx.dataset;
                                        var name = ds.label || '';
                                        var games = ctx.parsed.y;
                                        if (games == null) { return ''; }
                                        var monthStr = months[ctx.dataIndex];
                                        var rank = ds._k2RankMap && ds._k2RankMap[monthStr];
                                        var parts = name + ': ' + games + ' games';
                                        if (rank) { parts += ' (#' + rank + ')'; }
                                        return parts;
                                    }
                                }
                            }
                        },
                        onHover: function (event, elements) {
                            var activeIdx = -1;
                            if (elements.length) {
                                activeIdx = elements[0].datasetIndex;
                            }
                            var changed = false;
                            for (var d = 0; d < chartInstance.data.datasets.length; d++) {
                                var ds = chartInstance.data.datasets[d];
                                var target = (activeIdx === -1) ? 2 : (d === activeIdx ? 3 : 1);
                                if (ds.borderWidth !== target) {
                                    ds.borderWidth = target;
                                    changed = true;
                                }
                            }
                            if (changed) { chartInstance.update('none'); }
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
                                    text: 'Games',
                                    color: T.textMuted(),
                                    font: { size: 11 }
                                },
                                ticks: {
                                    color: T.tickColor(),
                                    precision: 0
                                },
                                grid: { color: T.softGrid() }
                            }
                        }
                    }
                });
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load top activity eras.';
                }
            });
    }

    function boot() {
        var roots = document.querySelectorAll('.server-top-activity-eras-chart');
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
