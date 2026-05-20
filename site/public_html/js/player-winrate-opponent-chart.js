/**
 * Win rate vs opponent pre-game rating (Chart.js bar).
 * Expects api/player_winrate_vs_opponent_rating.php
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;

    var API_PATH = 'api/player_winrate_vs_opponent_rating.php';

    function initRoot(root) {
        var playerId = root.getAttribute('data-player-id');
        if (!playerId) {
            return;
        }

        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.player-winrate-opponent-chart-status');
        if (!canvas || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        if (status) {
            status.textContent = 'Loading win rate vs opponent rating…';
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
                var buckets = data.buckets || [];
                var bucketSize = data.bucket_size || 50;
                if (!buckets.length) {
                    if (status) {
                        status.textContent = 'No rated games to chart.';
                    }
                    return;
                }

                var labels = [];
                var chartData = [];
                var meta = [];
                for (var i = 0; i < buckets.length; i++) {
                    var b = buckets[i];
                    labels.push(String(b.bucket_start));
                    chartData.push(Math.round(1000 * b.win_rate) / 10);
                    meta.push(b);
                }

                if (status) {
                    status.textContent = '';
                }

                new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Win %',
                            data: chartData,
                            backgroundColor: T.fill(T.green(), 0.7),
                            borderColor: T.green(),
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                labels: { color: T.textPrimary() }
                            },
                            tooltip: {
                                callbacks: {
                                    title: function (items) {
                                        if (!items.length) {
                                            return '';
                                        }
                                        var b = meta[items[0].dataIndex];
                                        return 'Opponent rating ' + b.bucket_start + '–' + b.bucket_end;
                                    },
                                    label: function (item) {
                                        return 'Win rate: ' + item.formattedValue + '%';
                                    },
                                    afterLabel: function (item) {
                                        var b = meta[item.dataIndex];
                                        return b.games + ' games: ' + b.wins + 'W ' + b.draws + 'D ' + b.losses + 'L';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                title: {
                                    display: true,
                                    text: 'Opponent rating (' + bucketSize + '-point buckets)',
                                    color: T.tickColor()
                                },
                                ticks: { color: T.tickColor(), maxRotation: 45 },
                                grid: { color: T.grid() }
                            },
                            y: {
                                min: 0,
                                max: 100,
                                title: {
                                    display: true,
                                    text: 'Win %',
                                    color: T.tickColor()
                                },
                                ticks: {
                                    color: T.tickColor(),
                                    callback: function (v) {
                                        return v + '%';
                                    }
                                },
                                grid: { color: T.grid() }
                            }
                        }
                    }
                });
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load win rate vs opponent rating.';
                }
            });
    }

    function boot() {
        var roots = document.querySelectorAll('.player-winrate-opponent-chart');
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
