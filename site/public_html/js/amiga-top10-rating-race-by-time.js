/**
 * Amiga News — top-10 Elo line race by calendar time (smooth playhead on time axis).
 * Expects Chart.js, chartjs-adapter-date-fns, K2ChartTheme.
 */
(function () {
    'use strict';

    var API = '/api/amiga_top10_rating_race.php';
    var T = window.K2ChartTheme;
    var PLAY_DURATION_MS = 120000;
    var RATING_Y_MIN = 1600;
    var RATING_Y_MAX = 2650;

    var RACE_COLOR_KEYS = [
        'pitch', 'chrome', 'amber', 'holo', 'teal', 'magenta',
        'purePitch', 'pureChrome', 'pureAmber', 'pureHolo', 'linkStar', 'accent'
    ];

    function parseEventDate(dateStr) {
        if (!dateStr) {
            return null;
        }
        var d = new Date(String(dateStr).trim() + 'T12:00:00Z');
        return isNaN(d.getTime()) ? null : d;
    }

    function pointSortMs(point) {
        if (point.sortMs != null) {
            return Number(point.sortMs);
        }
        var d = parseEventDate(point.date);
        return d ? d.getTime() : 0;
    }

    function normalizeSeries(series) {
        var pts = [];
        var i;
        for (i = 0; i < series.length; i++) {
            pts.push({
                ms: pointSortMs(series[i]),
                rating: series[i].rating
            });
        }
        pts.sort(function (a, b) {
            return a.ms - b.ms || 0;
        });
        return pts;
    }

    function pushTail(out, x, y) {
        if (out.length === 0) {
            out.push({ x: x, y: y });
            return;
        }
        var tail = out[out.length - 1];
        if (tail.x.getTime() === x.getTime() && tail.y === y) {
            return;
        }
        out.push({ x: x, y: y });
    }

    function ratingAtTime(series, playheadMs) {
        var pts = normalizeSeries(series);
        if (!pts.length || playheadMs < pts[0].ms) {
            return null;
        }
        var i;
        for (i = 0; i < pts.length; i++) {
            if (pts[i].ms > playheadMs) {
                if (i === 0) {
                    return null;
                }
                var prev = pts[i - 1];
                var next = pts[i];
                if (next.ms === prev.ms) {
                    return prev.rating;
                }
                var t = (playheadMs - prev.ms) / (next.ms - prev.ms);
                return prev.rating + (next.rating - prev.rating) * t;
            }
        }
        return pts[pts.length - 1].rating;
    }

    function seriesUpToTime(series, playheadMs) {
        var pts = normalizeSeries(series);
        if (!pts.length || playheadMs < pts[0].ms) {
            return [];
        }

        var out = [];
        var i;
        for (i = 0; i < pts.length; i++) {
            if (pts[i].ms > playheadMs) {
                break;
            }
            out.push({ x: new Date(pts[i].ms), y: pts[i].rating });
        }

        if (i < pts.length && i > 0) {
            var prev = pts[i - 1];
            var next = pts[i];
            if (next.ms > prev.ms) {
                var t = (playheadMs - prev.ms) / (next.ms - prev.ms);
                var y = prev.rating + (next.rating - prev.rating) * t;
                pushTail(out, new Date(playheadMs), y);
            }
        } else if (pts.length && playheadMs > pts[pts.length - 1].ms) {
            var last = pts[pts.length - 1];
            pushTail(out, new Date(playheadMs), last.rating);
        }

        return out;
    }

    function top10AtTime(players, playheadMs, topN) {
        var rows = [];
        var i;
        for (i = 0; i < players.length; i++) {
            var player = players[i];
            var rating = ratingAtTime(player.series, playheadMs);
            if (rating === null) {
                continue;
            }
            rows.push({
                playerId: player.id,
                name: player.name,
                rating: rating
            });
        }
        rows.sort(function (a, b) {
            var cmp = b.rating - a.rating;
            if (cmp !== 0) {
                return cmp;
            }
            return a.playerId - b.playerId;
        });
        var out = [];
        var rank = 0;
        for (i = 0; i < rows.length && i < topN; i++) {
            rank++;
            out.push({
                playerId: rows[i].playerId,
                name: rows[i].name,
                rating: rows[i].rating,
                rank: rank
            });
        }
        return out;
    }

    function raceColors() {
        if (!T) {
            return ['#9ccc65', '#64b5f6', '#ffb74d', '#b388ff', '#4db6ac', '#ff4081'];
        }
        var out = [];
        var i;
        for (i = 0; i < RACE_COLOR_KEYS.length; i++) {
            var fn = T[RACE_COLOR_KEYS[i]];
            if (typeof fn === 'function') {
                out.push(fn.call(T));
            }
        }
        return out.length ? out : ['#9ccc65', '#64b5f6', '#ffb74d'];
    }

    function colorForPlayer(playerId, palette) {
        return palette[Math.abs(playerId) % palette.length];
    }

    function top10IdSet(top10) {
        var set = Object.create(null);
        var i;
        for (i = 0; i < top10.length; i++) {
            set[top10[i].playerId] = true;
        }
        return set;
    }

    function formatPlayheadMeta(playheadMs) {
        var d = new Date(playheadMs);
        if (isNaN(d.getTime())) {
            return '';
        }
        return d.toLocaleDateString('en-GB', { month: 'long', year: 'numeric', timeZone: 'UTC' });
    }

    function endLabelsPlugin(getState) {
        return {
            id: 'k2AmigaRaceTimeEndLabels',
            afterDatasetsDraw: function (chart) {
                var state = getState();
                if (!state || !state.top10) {
                    return;
                }
                var topSet = top10IdSet(state.top10);
                var ctx = chart.ctx;
                var i;
                ctx.save();
                ctx.font = '600 12px "IBM Plex Sans", sans-serif';
                ctx.textBaseline = 'middle';
                for (i = 0; i < chart.data.datasets.length; i++) {
                    var ds = chart.data.datasets[i];
                    if (!topSet[ds.playerId]) {
                        continue;
                    }
                    var meta = chart.getDatasetMeta(i);
                    if (!meta || meta.hidden || !meta.data || !meta.data.length) {
                        continue;
                    }
                    var pt = meta.data[meta.data.length - 1];
                    if (!pt || typeof pt.x !== 'number' || typeof pt.y !== 'number') {
                        continue;
                    }
                    ctx.fillStyle = ds.borderColor || '#ccc';
                    ctx.fillText(ds.label || '', pt.x + 6, pt.y);
                }
                ctx.restore();
            }
        };
    }

    function buildChart(canvas, payload, state) {
        var palette = raceColors();
        state.palette = palette;
        var datasets = [];
        var i;
        for (i = 0; i < payload.players.length; i++) {
            var player = payload.players[i];
            var color = colorForPlayer(player.id, palette);
            var stroke = T && T.lineStroke ? T.lineStroke(color, 0.08) : {
                borderColor: color,
                backgroundColor: 'transparent',
                borderWidth: 2
            };
            datasets.push(Object.assign({
                label: player.name,
                playerId: player.id,
                data: [],
                pointRadius: 0,
                pointHitRadius: 0,
                tension: 0,
                fill: false
            }, stroke));
        }

        return new Chart(canvas, {
            type: 'line',
            data: { datasets: datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                interaction: { mode: 'nearest', intersect: false },
                layout: {
                    padding: { right: 96, top: 8, bottom: 4, left: 4 }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: false }
                },
                scales: {
                    x: {
                        type: 'time',
                        min: payload.meta.timelineStartMs,
                        max: payload.meta.timelineEndMs,
                        time: { unit: 'year', tooltipFormat: 'MMM yyyy' },
                        grid: { color: 'rgba(139, 148, 158, 0.14)' },
                        ticks: {
                            color: T && T.tickColor ? T.tickColor() : '#9aa5b1',
                            maxRotation: 0
                        }
                    },
                    y: {
                        min: RATING_Y_MIN,
                        max: RATING_Y_MAX,
                        grid: { color: 'rgba(139, 148, 158, 0.14)' },
                        ticks: {
                            color: T && T.tickColor ? T.tickColor() : '#9aa5b1',
                            callback: function (v) {
                                return Math.round(v);
                            }
                        },
                        title: {
                            display: true,
                            text: 'Elo',
                            color: T && T.tickColor ? T.tickColor() : '#9aa5b1'
                        }
                    }
                }
            },
            plugins: [endLabelsPlugin(function () { return state; })]
        });
    }

    function applyPlayhead(chart, payload, state, playheadMs) {
        if (!chart || !payload.players.length) {
            return;
        }

        var startMs = payload.meta.timelineStartMs;
        var endMs = payload.meta.timelineEndMs;
        var clamped = Math.max(startMs, Math.min(playheadMs, endMs));
        state.playheadMs = clamped;

        var top10 = top10AtTime(payload.players, clamped, payload.meta.topN);
        state.top10 = top10;
        var topSet = top10IdSet(top10);

        var i;
        for (i = 0; i < payload.players.length; i++) {
            var player = payload.players[i];
            var ds = chart.data.datasets[i];
            var inTop = !!topSet[player.id];
            ds.data = seriesUpToTime(player.series, clamped);
            ds.hidden = !inTop;
            var baseColor = colorForPlayer(player.id, state.palette);
            if (T && T.fill) {
                ds.borderColor = baseColor;
                ds.backgroundColor = T.fill(baseColor, inTop ? 0.1 : 0.02);
            } else {
                ds.borderColor = baseColor;
            }
            ds.borderWidth = inTop ? (T && T.lineBorderWidth ? T.lineBorderWidth() : 2.5) : 1;
            if (chart.getDatasetMeta(i)) {
                chart.getDatasetMeta(i).hidden = !inTop;
            }
        }
        chart.update('none');
    }

    function init(root) {
        if (typeof Chart === 'undefined' || !root) {
            return;
        }

        var canvas = root.querySelector('canvas[data-amiga-race-chart]');
        var metaEl = root.querySelector('[data-amiga-race-meta]');
        var slider = root.querySelector('[data-amiga-race-slider]');
        var playBtn = root.querySelector('[data-amiga-race-play]');
        var speedSelect = root.querySelector('[data-amiga-race-speed]');
        if (!canvas || !slider) {
            return;
        }

        var state = {
            playing: false,
            rafId: null,
            lastTs: 0,
            speedMult: 1,
            chart: null,
            payload: null,
            playheadMs: 0,
            top10: [],
            palette: []
        };

        function timelineSpanMs() {
            if (!state.payload) {
                return 1;
            }
            return Math.max(1, state.payload.meta.timelineEndMs - state.payload.meta.timelineStartMs);
        }

        function msPerRealMs() {
            return (timelineSpanMs() / PLAY_DURATION_MS) * state.speedMult;
        }

        function playheadToSlider(ms) {
            var span = timelineSpanMs();
            var start = state.payload.meta.timelineStartMs;
            return Math.round(((ms - start) / span) * 1000);
        }

        function sliderToPlayhead(val) {
            var span = timelineSpanMs();
            var start = state.payload.meta.timelineStartMs;
            return start + (val / 1000) * span;
        }

        function syncSlider() {
            if (!state.payload) {
                return;
            }
            slider.value = String(playheadToSlider(state.playheadMs));
        }

        function setMeta() {
            if (!metaEl) {
                return;
            }
            var leader = state.top10[0];
            var when = formatPlayheadMeta(state.playheadMs);
            if (!leader) {
                metaEl.textContent = when;
                return;
            }
            metaEl.textContent = when + ' — top player ' + leader.name
                + ' (' + Math.round(leader.rating) + ')';
        }

        function stopPlay() {
            state.playing = false;
            state.lastTs = 0;
            if (state.rafId !== null) {
                cancelAnimationFrame(state.rafId);
                state.rafId = null;
            }
            if (playBtn) {
                playBtn.textContent = 'Play';
                playBtn.setAttribute('aria-pressed', 'false');
            }
        }

        function tick(ts) {
            if (!state.playing || !state.payload) {
                return;
            }
            if (!state.lastTs) {
                state.lastTs = ts;
            }
            var dt = ts - state.lastTs;
            state.lastTs = ts;

            var endMs = state.payload.meta.timelineEndMs;
            state.playheadMs += dt * msPerRealMs();
            if (state.playheadMs >= endMs) {
                state.playheadMs = endMs;
                applyPlayhead(state.chart, state.payload, state, state.playheadMs);
                syncSlider();
                setMeta();
                stopPlay();
                return;
            }

            applyPlayhead(state.chart, state.payload, state, state.playheadMs);
            syncSlider();
            setMeta();
            state.rafId = requestAnimationFrame(tick);
        }

        function startPlay() {
            if (!state.payload) {
                return;
            }
            state.playing = true;
            state.lastTs = 0;
            if (playBtn) {
                playBtn.textContent = 'Pause';
                playBtn.setAttribute('aria-pressed', 'true');
            }
            state.rafId = requestAnimationFrame(tick);
        }

        function togglePlay() {
            if (state.playing) {
                stopPlay();
            } else {
                if (state.playheadMs >= state.payload.meta.timelineEndMs) {
                    state.playheadMs = state.payload.meta.timelineStartMs;
                    applyPlayhead(state.chart, state.payload, state, state.playheadMs);
                    syncSlider();
                    setMeta();
                }
                startPlay();
            }
        }

        fetch(API, { credentials: 'same-origin' })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('fetch_failed');
                }
                return res.json();
            })
            .then(function (payload) {
                if (!payload || !payload.meta || payload.meta.timelineStartMs == null) {
                    root.setAttribute('data-amiga-race-empty', '1');
                    var emptyNote = root.querySelector('[data-amiga-race-empty]');
                    if (emptyNote) {
                        emptyNote.hidden = false;
                    }
                    return;
                }
                state.payload = payload;
                state.chart = buildChart(canvas, payload, state);
                slider.min = '0';
                slider.max = '1000';
                state.playheadMs = payload.meta.timelineEndMs;
                syncSlider();
                applyPlayhead(state.chart, payload, state, state.playheadMs);
                setMeta();
            })
            .catch(function () {
                root.setAttribute('data-amiga-race-error', '1');
                var errNote = root.querySelector('[data-amiga-race-error]');
                if (errNote) {
                    errNote.hidden = false;
                }
            });

        slider.addEventListener('input', function () {
            stopPlay();
            var val = parseInt(slider.value, 10);
            if (!state.payload || isNaN(val)) {
                return;
            }
            state.playheadMs = sliderToPlayhead(val);
            applyPlayhead(state.chart, state.payload, state, state.playheadMs);
            setMeta();
        });

        if (playBtn) {
            playBtn.addEventListener('click', togglePlay);
        }

        if (speedSelect) {
            speedSelect.addEventListener('change', function () {
                var mult = parseFloat(speedSelect.value);
                state.speedMult = (isNaN(mult) || mult <= 0) ? 1 : mult;
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.querySelector('[data-amiga-rating-race-time]');
        if (root) {
            init(root);
        }
    });
}());
