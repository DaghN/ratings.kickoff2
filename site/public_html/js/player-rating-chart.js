/**
 * ELO rating over time (Chart.js). Peak/current as reference lines on the same chart.
 * Expects api/player_rating_history.php and chartjs-adapter-date-fns.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;
    var DR = window.K2ChartDateRange;

    var API_PATH = 'api/player_rating_history.php';

    function parseGameDate(dateStr) {
        if (!dateStr) {
            return null;
        }
        var normalized = String(dateStr).trim().replace(' ', 'T');
        var d = new Date(normalized);
        return isNaN(d.getTime()) ? null : d;
    }

    function buildChartData(points) {
        var chartData = [];
        for (var i = 0; i < points.length; i++) {
            var x = parseGameDate(points[i].date);
            if (x === null) {
                continue;
            }
            chartData.push({ x: x, y: points[i].rating });
        }
        return chartData;
    }

    function peakAndCurrent(chartData, currentRating) {
        var current = typeof currentRating === 'number' && !isNaN(currentRating)
            ? currentRating
            : chartData[chartData.length - 1].y;
        var peak = chartData[0].y;
        var peakIndex = 0;
        for (var i = 1; i < chartData.length; i++) {
            if (chartData[i].y > peak) {
                peak = chartData[i].y;
                peakIndex = i;
            }
        }
        return {
            current: current,
            peak: peak,
            peakDate: chartData[peakIndex].x
        };
    }

    function horizontalLine(chartData, yVal) {
        return [
            { x: chartData[0].x, y: yVal },
            { x: chartData[chartData.length - 1].x, y: yVal }
        ];
    }

    function initRoot(root) {
        var playerId = root.getAttribute('data-player-id');
        if (!playerId) {
            return;
        }

        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.player-rating-chart-status');
        var summary = root.querySelector('.player-rating-peak-current-summary');

        if (!canvas || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        if (status) {
            status.textContent = 'Loading rating history…';
        }

        var url = API_PATH + '?id=' + encodeURIComponent(playerId) + '&realm=online';

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('bad_status');
                }
                return r.json();
            })
            .then(function (data) {
                var points = data.points || [];
                if (!points.length) {
                    if (status) {
                        status.textContent = 'No rated games to chart.';
                    }
                    return;
                }

                var chartData = buildChartData(points);
                if (!chartData.length) {
                    if (status) {
                        status.textContent = 'No chartable dates in game history.';
                    }
                    return;
                }

                var currentRating = typeof data.currentRating === 'number'
                    ? data.currentRating
                    : chartData[chartData.length - 1].y;
                if (DR && DR.appendRatingThroughToday) {
                    chartData = DR.appendRatingThroughToday(chartData, currentRating);
                }

                var stats = peakAndCurrent(chartData, currentRating);
                var peakLine = horizontalLine(chartData, stats.peak);
                var currentLine = horizontalLine(chartData, stats.current);

                if (summary) {
                    var peakWhen = stats.peakDate.toLocaleDateString(undefined, {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric'
                    });
                    var gap = stats.peak - stats.current;
                    var gapText = gap === 0
                        ? ' (at peak now)'
                        : ' (' + gap + ' below peak)';
                    summary.innerHTML = 'Current: <strong>' + stats.current + '</strong>'
                        + ' &nbsp;&middot;&nbsp; Peak: <strong>' + stats.peak + '</strong>'
                        + ' <span style="color: var(--color-text-muted, #b0b0b0); font-size: 0.92em;">'
                        + 'on ' + peakWhen + gapText + '</span>';
                    summary.style.display = 'block';
                }

                if (status) {
                    status.textContent = '';
                }

                new Chart(canvas, {
                    type: 'line',
                    data: {
                        datasets: [
                            {
                                label: 'Peak (' + stats.peak + ')',
                                data: peakLine,
                                borderColor: T.amber(),
                                borderWidth: 1.5,
                                borderDash: [6, 4],
                                pointRadius: 0,
                                fill: false,
                                order: 0
                            },
                            {
                                label: 'Current (' + stats.current + ')',
                                data: currentLine,
                                borderColor: T.blue(),
                                borderWidth: 1.5,
                                borderDash: [6, 4],
                                pointRadius: 0,
                                fill: false,
                                order: 1
                            },
                            {
                                label: 'ELO rating (after game)',
                                data: chartData,
                                borderColor: T.green(),
                                backgroundColor: T.fill(T.green(), 0.15),
                                borderWidth: 2,
                            pointRadius: 0,
                            pointHoverRadius: 4,
                                fill: true,
                                tension: 0.1,
                                order: 2
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        interaction: { mode: 'nearest', axis: 'x', intersect: false },
                        plugins: {
                            legend: {
                                labels: { color: T.textPrimary() }
                            },
                            tooltip: {
                                filter: function (item) {
                                    return item.datasetIndex === 2;
                                },
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
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                type: 'time',
                                max: DR ? DR.endOfToday() : undefined,
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
                                grid: { color: T.grid() }
                            },
                            y: {
                                ticks: { color: T.tickColor() },
                                grid: { color: T.grid() }
                            }
                        }
                    }
                });
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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
