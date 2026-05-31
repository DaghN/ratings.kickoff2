/**
 * Monthly unlock counts for one milestone (milestone.php).
 * Root: .milestone-unlock-timeline-chart[data-milestone-key][data-chart-token]
 */
(function () {
    'use strict';

    var API_PATH = 'api/milestone_unlock_timeline.php';

    function tokenColor(token) {
        var T = window.K2ChartTheme;
        if (!T) {
            return '#9ccc65';
        }
        if (token === 'chrome') {
            return T.chrome ? T.chrome() : '#64b5f6';
        }
        if (token === 'amber') {
            return T.amber ? T.amber() : '#ffb74d';
        }
        if (token === 'holo') {
            return T.holo ? T.holo() : '#b388ff';
        }
        return T.pitch ? T.pitch() : '#9ccc65';
    }

    function initRoot(root) {
        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.milestone-unlock-timeline-chart-status');
        var key = root.getAttribute('data-milestone-key');
        var token = root.getAttribute('data-chart-token') || 'pitch';
        if (!canvas || !key || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        if (status) {
            status.textContent = 'Loading unlock timeline…';
        }

        fetch(API_PATH + '?realm=online&key=' + encodeURIComponent(key), { credentials: 'same-origin' })
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
                        status.textContent = 'No unlocks yet for this milestone.';
                    }
                    return;
                }

                var labels = [];
                var values = [];
                for (var i = 0; i < points.length; i++) {
                    labels.push(points[i].month);
                    values.push(points[i].unlocks);
                }

                var color = tokenColor(data.chart_token || token);
                if (status) {
                    status.textContent = '';
                }

                new Chart(canvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Unlocks',
                            data: values,
                            backgroundColor: color,
                            borderColor: color,
                            borderWidth: 1,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { display: false },
                        },
                        scales: {
                            x: {
                                ticks: { maxRotation: 45, minRotation: 0, color: '#a8b0c0' },
                                grid: { color: 'rgba(255,255,255,0.06)' },
                            },
                            y: {
                                beginAtZero: true,
                                ticks: { precision: 0, color: '#a8b0c0' },
                                grid: { color: 'rgba(255,255,255,0.06)' },
                            },
                        },
                    },
                });
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load unlock timeline.';
                }
            });
    }

    function boot() {
        var roots = document.querySelectorAll('.milestone-unlock-timeline-chart');
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
