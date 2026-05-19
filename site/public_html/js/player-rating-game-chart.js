/**
 * ELO rating after each game vs game number (Chart.js linear X).
 * Expects api/player_rating_history.php (gameNumber + rating from NewRating*).
 */
(function () {
    'use strict';

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
            var n = points[i].gameNumber;
            if (n == null || n < 1) {
                n = i + 1;
            }
            chartData.push({
                x: n,
                y: points[i].rating,
                date: points[i].date,
                gameId: points[i].gameId
            });
        }
        return chartData;
    }

    function peakAndCurrent(chartData) {
        var current = chartData[chartData.length - 1].y;
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
            peakGame: chartData[peakIndex].x
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
        var status = root.querySelector('.player-rating-game-chart-status');
        var summary = root.querySelector('.player-rating-game-peak-current-summary');

        if (!canvas || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        if (status) {
            status.textContent = 'Loading rating by game number…';
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
                        status.textContent = 'No chartable games in history.';
                    }
                    return;
                }

                var stats = peakAndCurrent(chartData);
                var peakLine = horizontalLine(chartData, stats.peak);
                var currentLine = horizontalLine(chartData, stats.current);
                var totalGames = chartData[chartData.length - 1].x;

                if (summary) {
                    var gap = stats.peak - stats.current;
                    var gapText = gap === 0
                        ? ' (at peak now)'
                        : ' (' + gap + ' below peak)';
                    summary.innerHTML = 'Current: <strong>' + stats.current + '</strong>'
                        + ' &nbsp;&middot;&nbsp; Peak: <strong>' + stats.peak + '</strong>'
                        + ' <span style="color: var(--color-text-muted, #b0b0b0); font-size: 0.92em;">'
                        + 'at game #' + stats.peakGame + gapText
                        + ' &nbsp;&middot;&nbsp; ' + totalGames + ' rated games</span>';
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
                                borderColor: '#ffb74d',
                                borderWidth: 1.5,
                                borderDash: [6, 4],
                                pointRadius: 0,
                                fill: false,
                                order: 0
                            },
                            {
                                label: 'Current (' + stats.current + ')',
                                data: currentLine,
                                borderColor: '#64b5f6',
                                borderWidth: 1.5,
                                borderDash: [6, 4],
                                pointRadius: 0,
                                fill: false,
                                order: 1
                            },
                            {
                                label: 'ELO rating (after game)',
                                data: chartData,
                                borderColor: '#ce93d8',
                                backgroundColor: 'rgba(206, 147, 216, 0.15)',
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
                        parsing: false,
                        interaction: { mode: 'nearest', axis: 'x', intersect: false },
                        plugins: {
                            legend: {
                                labels: { color: '#e3e3e3' }
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
                                        var pt = items[0].raw;
                                        var title = 'Game #' + items[0].parsed.x;
                                        if (pt && pt.date) {
                                            var d = parseGameDate(pt.date);
                                            if (d) {
                                                title += ' — ' + d.toLocaleDateString(undefined, {
                                                    year: 'numeric',
                                                    month: 'short',
                                                    day: 'numeric'
                                                });
                                            }
                                        }
                                        return title;
                                    },
                                    afterBody: function (items) {
                                        if (!items.length) {
                                            return '';
                                        }
                                        var pt = items[0].raw;
                                        if (pt && pt.gameId) {
                                            return 'game.php?id=' + pt.gameId;
                                        }
                                        return '';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                type: 'linear',
                                title: {
                                    display: true,
                                    text: 'Rated game number',
                                    color: '#b0b0b0'
                                },
                                ticks: {
                                    color: '#b0b0b0',
                                    maxRotation: 0,
                                    autoSkip: true,
                                    maxTicksLimit: 14
                                },
                                grid: { color: 'rgba(255, 255, 255, 0.08)' }
                            },
                            y: {
                                ticks: { color: '#b0b0b0' },
                                grid: { color: 'rgba(255, 255, 255, 0.08)' }
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
        var roots = document.querySelectorAll('.player-rating-game-chart');
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
