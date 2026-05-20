/**
 * Theme lab — toggles + sample chart (reads CSS variables for colors).
 */
(function () {
    'use strict';

    var root = document.documentElement;
    var chartInstance = null;

    function cssVar(name) {
        return getComputedStyle(root).getPropertyValue(name).trim();
    }

    function bindToggle(selector, attr, value) {
        document.querySelectorAll(selector).forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.disabled) {
                    return;
                }
                root.setAttribute(attr, value(btn));
                syncControlButtons();
                if (attr === 'data-neon' || attr === 'data-realm' || attr === 'data-amiga-accent' || attr === 'data-display-font') {
                    updateChartColors();
                    updateHeroCopy();
                }
            });
        });
    }

    function syncControlButtons() {
        var neon = root.getAttribute('data-neon') || 'a';
        var realm = root.getAttribute('data-realm') || 'online';
        var onlineAccent = root.getAttribute('data-online-accent') || 'green';
        var amigaAccent = root.getAttribute('data-amiga-accent') || 'amber';
        var displayFont = root.getAttribute('data-display-font') || 'exo';
        var tableHighlight = root.getAttribute('data-table-highlight') || 'cyan-magenta';
        var amigaLinks = root.getAttribute('data-amiga-links') || 'realm';
        var labView = root.getAttribute('data-lab-view') || 'hub';
        var navStyle = root.getAttribute('data-nav-style') || 'boxed';

        document.querySelectorAll('[data-set-neon]').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-set-neon') === neon);
        });
        document.querySelectorAll('[data-set-realm]').forEach(function (btn) {
            var on = btn.getAttribute('data-set-realm') === realm;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        document.querySelectorAll('[data-set-lab-view]').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-set-lab-view') === labView);
        });
        document.querySelectorAll('[data-set-nav-style]').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-set-nav-style') === navStyle);
        });
        document.querySelectorAll('[data-set-online-accent]').forEach(function (btn) {
            var show = realm === 'online';
            btn.hidden = !show;
            btn.classList.toggle('is-active', show && btn.getAttribute('data-set-online-accent') === onlineAccent);
        });
        document.querySelectorAll('[data-set-amiga-accent]').forEach(function (btn) {
            var show = realm === 'amiga';
            btn.hidden = !show;
            btn.classList.toggle('is-active', show && btn.getAttribute('data-set-amiga-accent') === amigaAccent);
        });
        document.querySelectorAll('[data-set-display-font]').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-set-display-font') === displayFont);
        });
        document.querySelectorAll('[data-set-table-highlight]').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-set-table-highlight') === tableHighlight);
        });
        document.querySelectorAll('[data-set-amiga-links]').forEach(function (btn) {
            var show = realm === 'amiga';
            btn.hidden = !show;
            btn.classList.toggle('is-active', show && btn.getAttribute('data-set-amiga-links') === amigaLinks);
        });

        var onlineGroup = document.getElementById('lab-online-accent-group');
        var amigaGroup = document.getElementById('lab-amiga-accent-group');
        var amigaLinksGroup = document.getElementById('lab-amiga-links-group');
        if (onlineGroup) {
            onlineGroup.hidden = realm !== 'online';
        }
        if (amigaGroup) {
            amigaGroup.hidden = realm !== 'amiga';
        }
        if (amigaLinksGroup) {
            amigaLinksGroup.hidden = realm !== 'amiga';
        }

        document.querySelectorAll('.k2-lab-control-group--tokens-only').forEach(function (group) {
            group.hidden = labView !== 'tokens';
        });
    }

    function setLabView(view) {
        root.setAttribute('data-lab-view', view);
        ['hub', 'player', 'tokens'].forEach(function (id) {
            var el = document.getElementById('lab-view-' + id);
            if (el) {
                el.hidden = view !== id;
            }
        });
        syncControlButtons();
        if (view === 'hub') {
            ensureHubTab(root.getAttribute('data-hub-tab') || 'status');
        }
    }

    function ensureHubTab(tab) {
        root.setAttribute('data-hub-tab', tab);
        activateTab(document.querySelectorAll('[data-hub-tab]'), 'data-hub-tab', tab);
        document.querySelectorAll('[data-hub-panel]').forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-hub-panel') !== tab;
        });
        if (tab === 'trends') {
            initChart();
        }
    }

    function activateTab(buttons, attr, value) {
        buttons.forEach(function (btn) {
            var on = btn.getAttribute(attr) === value;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    function initNavMock() {
        document.querySelectorAll('[data-set-lab-view]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setLabView(btn.getAttribute('data-set-lab-view'));
            });
        });

        document.querySelectorAll('[data-hub-tab]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                ensureHubTab(btn.getAttribute('data-hub-tab'));
            });
        });

        document.querySelectorAll('[data-lb-tab]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                activateTab(document.querySelectorAll('[data-lb-tab]'), 'data-lb-tab', btn.getAttribute('data-lb-tab'));
            });
        });

        document.querySelectorAll('[data-player-tab]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tab = btn.getAttribute('data-player-tab');
                activateTab(document.querySelectorAll('[data-player-tab]'), 'data-player-tab', tab);
                document.querySelectorAll('[data-player-panel]').forEach(function (panel) {
                    panel.hidden = panel.getAttribute('data-player-panel') !== tab;
                });
            });
        });

        document.querySelectorAll('[data-goto-player]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                setLabView('player');
            });
        });

        setLabView(root.getAttribute('data-lab-view') || 'hub');
        if ((root.getAttribute('data-lab-view') || 'hub') === 'hub') {
            ensureHubTab('status');
        }
    }

    function updateHeroCopy() {
        var realm = root.getAttribute('data-realm') || 'online';
        var badge = document.getElementById('hero-realm-badge');
        var bio = document.getElementById('hero-bio');
        var videoCaption = document.getElementById('hero-video-caption');
        var pulseRealm = document.getElementById('pulse-realm-label');

        if (badge) {
            badge.textContent = realm === 'amiga' ? 'amiga · real-world' : 'online · rated play';
        }
        if (bio) {
            bio.textContent = realm === 'amiga'
                ? 'Regular on the European offline circuit. Three-time World Cup participant — photo and match highlights from real Amiga 500 sessions.'
                : 'Online ladder regular since 2007. Peak 2354 — feast landing: charts, highlights, personality.';
        }
        if (videoCaption) {
            videoCaption.textContent = realm === 'amiga'
                ? 'WC highlight reel (placeholder — click-to-play later)'
                : 'Featured clip (placeholder — online realm)';
        }
        if (pulseRealm) {
            pulseRealm.textContent = realm === 'amiga' ? 'Amiga realm (preview)' : 'Online realm';
        }
    }

    function sampleChartData() {
        var months = [];
        var now = new Date();
        for (var i = 11; i >= 0; i--) {
            var d = new Date(now.getFullYear(), now.getMonth() - i, 1);
            months.push({
                x: d,
                y: 40 + Math.round(Math.random() * 80) + (11 - i) * 3
            });
        }
        return months;
    }

    function fetchOrSampleChartData(callback) {
        fetch('api/server_games_by_month.php?realm=online', { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('bad_status');
                }
                return r.json();
            })
            .then(function (data) {
                var months = data.months || [];
                if (!months.length) {
                    callback(sampleChartData(), 'Sample data (API empty)');
                    return;
                }
                var chartData = [];
                for (var i = 0; i < months.length; i++) {
                    var d = new Date(months[i].month + '-01T00:00:00');
                    if (!isNaN(d.getTime())) {
                        chartData.push({ x: d, y: months[i].games });
                    }
                }
                callback(chartData, 'Live data from staging API');
            })
            .catch(function () {
                callback(sampleChartData(), 'Sample data (local / no API)');
            });
    }

    function chartColors() {
        var accent = cssVar('--k2-realm-accent') || '#9ccc65';
        return {
            bg: accent.indexOf('#') === 0 ? hexToRgba(accent, 0.65) : 'rgba(156, 204, 101, 0.65)',
            border: accent
        };
    }

    function hexToRgba(hex, alpha) {
        var h = hex.replace('#', '');
        if (h.length === 3) {
            h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
        }
        var r = parseInt(h.slice(0, 2), 16);
        var g = parseInt(h.slice(2, 4), 16);
        var b = parseInt(h.slice(4, 6), 16);
        return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
    }

    function updateChartColors() {
        if (!chartInstance) {
            return;
        }
        var colors = chartColors();
        chartInstance.data.datasets[0].backgroundColor = colors.bg;
        chartInstance.data.datasets[0].borderColor = colors.border;
        chartInstance.update('none');
    }

    function initChart() {
        var canvas = document.getElementById('lab-chart');
        var status = document.getElementById('lab-chart-status');
        if (!canvas || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart.js not loaded.';
            }
            return;
        }
        if (chartInstance) {
            return;
        }
        fetchOrSampleChartData(function (chartData, statusText) {
            if (status) {
                status.textContent = statusText;
            }
            var colors = chartColors();
            var textMuted = cssVar('--k2-text-muted') || '#8b949e';
            var textPrimary = cssVar('--k2-text-primary') || '#e6edf3';
            var grid = 'rgba(255, 255, 255, 0.08)';

            if (chartInstance) {
                chartInstance.destroy();
            }

            chartInstance = new Chart(canvas, {
                type: 'bar',
                data: {
                    datasets: [{
                        label: 'Games per month',
                        data: chartData,
                        backgroundColor: colors.bg,
                        borderColor: colors.border,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            labels: { color: textPrimary }
                        }
                    },
                    scales: {
                        x: {
                            type: 'time',
                            time: { unit: 'month', displayFormats: { month: 'MMM yyyy' } },
                            ticks: { color: textMuted, maxRotation: 45 },
                            grid: { color: grid }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { color: textMuted },
                            grid: { color: grid }
                        }
                    }
                }
            });
        });
    }

    function initControls() {
        document.querySelectorAll('[data-set-neon]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                root.setAttribute('data-neon', btn.getAttribute('data-set-neon'));
                syncControlButtons();
                updateChartColors();
            });
        });
        document.querySelectorAll('[data-set-realm]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                root.setAttribute('data-realm', btn.getAttribute('data-set-realm'));
                syncControlButtons();
                updateChartColors();
                updateHeroCopy();
            });
        });
        document.querySelectorAll('[data-set-amiga-accent]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                root.setAttribute('data-amiga-accent', btn.getAttribute('data-set-amiga-accent'));
                syncControlButtons();
                updateChartColors();
            });
        });
        document.querySelectorAll('[data-set-online-accent]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                root.setAttribute('data-online-accent', btn.getAttribute('data-set-online-accent'));
                syncControlButtons();
                updateChartColors();
            });
        });
        document.querySelectorAll('[data-set-display-font]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                root.setAttribute('data-display-font', btn.getAttribute('data-set-display-font'));
                syncControlButtons();
            });
        });
        document.querySelectorAll('[data-set-table-highlight]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                root.setAttribute('data-table-highlight', btn.getAttribute('data-set-table-highlight'));
                syncControlButtons();
            });
        });
        document.querySelectorAll('[data-set-amiga-links]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                root.setAttribute('data-amiga-links', btn.getAttribute('data-set-amiga-links'));
                syncControlButtons();
            });
        });
        document.querySelectorAll('[data-set-nav-style]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                root.setAttribute('data-nav-style', btn.getAttribute('data-set-nav-style'));
                syncControlButtons();
            });
        });
        syncControlButtons();
        updateHeroCopy();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initControls();
            initNavMock();
        });
    } else {
        initControls();
        initNavMock();
    }
})();
