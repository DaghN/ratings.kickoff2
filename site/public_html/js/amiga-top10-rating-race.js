/**
 * Amiga News — top-10 Elo line chart race (smooth linear segments between events).
 * Expects Chart.js, chartjs-adapter-date-fns, K2ChartTheme.
 */
(function () {
    'use strict';

    var API = '/api/amiga_top10_rating_race.php';
    var T = window.K2ChartTheme;
    var MS_PER_EVENT = 420;
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

    function top10IdSet(frame) {
        var set = Object.create(null);
        if (!frame || !frame.top10) {
            return set;
        }
        var i;
        for (i = 0; i < frame.top10.length; i++) {
            set[frame.top10[i].playerId] = true;
        }
        return set;
    }

    function ratingAtFrame(series, frameIndex) {
        var last = null;
        var i;
        for (i = 0; i < series.length; i++) {
            if (series[i].i > frameIndex) {
                break;
            }
            last = series[i].rating;
        }
        return last;
    }

    /**
     * Line history through playhead, with head interpolated between events.
     *
     * @param {number} playhead 0 .. frames.length - 1 (fractional allowed)
     */
    function seriesUpToPlayhead(series, playhead, frames) {
        if (!frames.length || !series.length) {
            return [];
        }

        var maxIndex = frames.length - 1;
        var clamped = Math.max(0, Math.min(playhead, maxIndex));
        var i0 = Math.floor(clamped);
        var i1 = Math.min(i0 + 1, maxIndex);
        var t = clamped - i0;

        var out = [];
        var i;
        for (i = 0; i < series.length; i++) {
            if (series[i].i > i0) {
                break;
            }
            var x = parseEventDate(series[i].date);
            if (x !== null) {
                out.push({ x: x, y: series[i].rating });
            }
        }

        var r0 = ratingAtFrame(series, i0);
        if (r0 === null) {
            return out;
        }

        var d0 = parseEventDate(frames[i0].date);
        if (d0 === null) {
            return out;
        }

        if (t <= 0 || i0 === i1) {
            pushTailPoint(out, d0, r0);
            return out;
        }

        var r1 = ratingAtFrame(series, i1);
        if (r1 === null) {
            r1 = r0;
        }
        var d1 = parseEventDate(frames[i1].date);
        if (d1 === null) {
            pushTailPoint(out, d0, r0);
            return out;
        }

        var xMs = d0.getTime() + (d1.getTime() - d0.getTime()) * t;
        var y = r0 + (r1 - r0) * t;
        pushTailPoint(out, new Date(xMs), y);

        return out;
    }

    function pushTailPoint(out, x, y) {
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

    function displayFrameIndex(playhead, frameCount) {
        if (frameCount < 1) {
            return 0;
        }
        return Math.max(0, Math.min(Math.floor(playhead), frameCount - 1));
    }

    function endLabelsPlugin(getState) {
        return {
            id: 'k2AmigaRaceEndLabels',
            afterDatasetsDraw: function (chart) {
                var state = getState();
                if (!state || !state.frame) {
                    return;
                }
                var topSet = top10IdSet(state.frame);
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

    function applyPlayhead(chart, payload, state, playhead) {
        if (!chart || !payload.frames.length) {
            return;
        }
        var frameIdx = displayFrameIndex(playhead, payload.frames.length);
        var frame = payload.frames[frameIdx];
        state.frame = frame;
        state.frameIndex = frameIdx;
        state.playhead = playhead;

        var topSet = top10IdSet(frame);
        var i;
        for (i = 0; i < payload.players.length; i++) {
            var player = payload.players[i];
            var ds = chart.data.datasets[i];
            var inTop = !!topSet[player.id];
            ds.data = seriesUpToPlayhead(player.series, playhead, payload.frames);
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

    function formatFrameMeta(frame) {
        if (!frame) {
            return '';
        }
        return frame.label + ' — top player ' + (frame.top10[0] ? frame.top10[0].name : '—')
            + ' (' + (frame.top10[0] ? Math.round(frame.top10[0].rating) : '—') + ')';
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
            playhead: 0,
            frameIndex: 0,
            frame: null,
            palette: []
        };

        function msPerEvent() {
            return MS_PER_EVENT / state.speedMult;
        }

        function syncSlider() {
            if (!state.payload) {
                return;
            }
            slider.value = String(displayFrameIndex(state.playhead, state.payload.frames.length));
        }

        function setMeta() {
            if (!metaEl || !state.payload) {
                return;
            }
            var idx = displayFrameIndex(state.playhead, state.payload.frames.length);
            metaEl.textContent = formatFrameMeta(state.payload.frames[idx]);
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

            var maxPlayhead = state.payload.frames.length - 1;
            state.playhead += dt / msPerEvent();
            if (state.playhead >= maxPlayhead) {
                state.playhead = maxPlayhead;
                applyPlayhead(state.chart, state.payload, state, state.playhead);
                syncSlider();
                setMeta();
                stopPlay();
                return;
            }

            applyPlayhead(state.chart, state.payload, state, state.playhead);
            syncSlider();
            setMeta();
            state.rafId = requestAnimationFrame(tick);
        }

        function startPlay() {
            if (!state.payload || state.payload.frames.length < 2) {
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
                if (state.playhead >= state.payload.frames.length - 1) {
                    state.playhead = 0;
                    applyPlayhead(state.chart, state.payload, state, 0);
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
                if (!payload || !payload.frames || !payload.frames.length) {
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
                slider.max = String(payload.frames.length - 1);
                state.playhead = payload.frames.length - 1;
                syncSlider();
                applyPlayhead(state.chart, payload, state, state.playhead);
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
            var idx = parseInt(slider.value, 10);
            if (!state.payload || isNaN(idx)) {
                return;
            }
            state.playhead = idx;
            applyPlayhead(state.chart, state.payload, state, state.playhead);
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
        var root = document.querySelector('[data-amiga-rating-race]');
        if (root) {
            init(root);
        }
    });
}());
