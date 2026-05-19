/**
 * Established players by current ELO bucket (histogram bar chart).
 * Expects api/server_established_rating_distribution.php
 */
(function () {
    'use strict';

    var API_PATH = 'api/server_established_rating_distribution.php';

    function initRoot(root) {
        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.server-established-rating-distribution-chart-status');
        if (!canvas || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        if (status) {
            status.textContent = 'Loading rating distribution…';
        }

        fetch(API_PATH + '?realm=online&bucket=100&min_games=20', { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('bad_status');
                }
                return r.json();
            })
            .then(function (data) {
                var buckets = data.buckets || [];
                var bucketSize = data.bucket_size || 100;
                var minGames = data.min_games || 20;

                if (!buckets.length) {
                    if (status) {
                        status.textContent = 'No established players to chart.';
                    }
                    return;
                }

                var labels = [];
                var counts = [];
                var meta = [];
                for (var i = 0; i < buckets.length; i++) {
                    var b = buckets[i];
                    labels.push(String(b.bucket_start));
                    counts.push(b.players);
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
                            label: 'Established players (' + minGames + '+ games)',
                            data: counts,
                            backgroundColor: 'rgba(77, 182, 172, 0.7)',
                            borderColor: '#4db6ac',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                labels: { color: '#e3e3e3' }
                            },
                            tooltip: {
                                callbacks: {
                                    title: function (items) {
                                        if (!items.length) {
                                            return '';
                                        }
                                        var b = meta[items[0].dataIndex];
                                        return 'Rating ' + b.bucket_start + '\u2013' + b.bucket_end;
                                    },
                                    label: function (item) {
                                        return item.parsed.y + ' players';
                                    },
                                    afterLabel: function (item) {
                                        var total = data.total_players || 0;
                                        if (total < 1) {
                                            return '';
                                        }
                                        var pct = Math.round(1000 * meta[item.dataIndex].players / total) / 10;
                                        return pct + '% of established players';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                title: {
                                    display: true,
                                    text: 'ELO rating (' + bucketSize + '-point buckets)',
                                    color: '#b0b0b0'
                                },
                                ticks: { color: '#b0b0b0', maxRotation: 45 },
                                grid: { color: 'rgba(255, 255, 255, 0.08)' }
                            },
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Players',
                                    color: '#b0b0b0'
                                },
                                ticks: {
                                    color: '#b0b0b0',
                                    precision: 0
                                },
                                grid: { color: 'rgba(255, 255, 255, 0.08)' }
                            }
                        }
                    }
                });
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load rating distribution.';
                }
            });
    }

    function boot() {
        var roots = document.querySelectorAll('.server-established-rating-distribution-chart');
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
