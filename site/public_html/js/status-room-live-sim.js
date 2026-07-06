(function () {
  'use strict';

  function apiUrl(action, params) {
    var qs = 'action=' + encodeURIComponent(action);
    if (params) {
      Object.keys(params).forEach(function (key) {
        if (params[key] !== undefined && params[key] !== null && params[key] !== '') {
          qs += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(String(params[key]));
        }
      });
    }
    return '/api/status_room_live_sim.php?' + qs;
  }

  function setMessage(text) {
    var el = document.getElementById('k2-sim-message');
    if (el) {
      el.textContent = text || '';
    }
  }

  function formatWallRemaining(seconds) {
    if (seconds == null || seconds <= 0) {
      return '—';
    }
    var mins = Math.floor(seconds / 60);
    var secs = seconds % 60;
    return mins + ':' + String(secs).padStart(2, '0') + ' left';
  }

  function haltEventMessage(event) {
    if (event === 'time_limit') {
      return 'Sim auto-stopped — 10 minute wall limit reached.';
    }
    if (event === 'stalled') {
      return 'Sim auto-stopped — no games left in queue (crashes/cancels).';
    }
    if (event === 'complete') {
      return 'Sim complete — all queued games finished.';
    }
    return '';
  }

  function renderStatus(st) {
    if (!st) {
      return;
    }
    var root = document.getElementById('k2-sim-status');
    if (!root) {
      return;
    }
    var activeEl = root.querySelector('[data-k2-sim-field="active"]');
    var progressEl = root.querySelector('[data-k2-sim-field="progress"]');
    var regEl = root.querySelector('[data-k2-sim-field="registrations"]');
    var onlineEl = root.querySelector('[data-k2-sim-field="online_count"]');
    var liveEl = root.querySelector('[data-k2-sim-field="live_count"]');
    var queuedEl = root.querySelector('[data-k2-sim-field="queued_count"]');
    var wallEl = root.querySelector('[data-k2-sim-field="wall_remaining"]');
    var eventEl = root.querySelector('[data-k2-sim-field="last_event"]');
    if (activeEl) {
      activeEl.textContent = st.active ? 'running' : 'idle';
    }
    if (progressEl) {
      progressEl.textContent = String(st.completed_count || 0) + ' / ' + String(st.game_count || 0);
    }
    if (regEl) {
      regEl.textContent = String(st.registration_count || 0) + ' / ' + String(st.registration_limit || 0);
    }
    if (onlineEl) {
      onlineEl.textContent = String(st.online_count || 0);
    }
    if (liveEl) {
      liveEl.textContent = String(st.live_count || 0);
    }
    if (queuedEl) {
      queuedEl.textContent = String(st.queued_count || 0);
    }
    if (wallEl) {
      wallEl.textContent = st.active
        ? formatWallRemaining(st.wall_seconds_remaining)
        : '—';
    }
    if (eventEl) {
      eventEl.textContent = st.last_event || '—';
    }
    var haltMsg = !st.active ? haltEventMessage(st.last_event) : '';
    if (haltMsg) {
      setMessage(haltMsg);
    }
  }

  function readStartParams() {
    var gamesEl = document.getElementById('k2-sim-games');
    var regEl = document.getElementById('k2-sim-registrations');
    var crashEl = document.getElementById('k2-sim-crash');
    var l1El = document.getElementById('k2-sim-l1');
    var l2El = document.getElementById('k2-sim-l2');
    var l3El = document.getElementById('k2-sim-l3');
    return {
      games: gamesEl ? gamesEl.value : '20',
      registrations: regEl ? regEl.value : '3',
      crash: crashEl ? crashEl.value : '5',
      l1: l1El && l1El.checked ? '1' : '0',
      l2: l2El && l2El.checked ? '1' : '0',
      l3: l3El && l3El.checked ? '1' : '0'
    };
  }

  function fetchJson(url) {
    return fetch(url, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) {
      return r.json();
    });
  }

  function pollStatus() {
    return fetchJson(apiUrl('status')).then(function (data) {
      if (data && data.status) {
        renderStatus(data.status);
      }
      return data;
    }).catch(function () {
      setMessage('Status poll failed.');
    });
  }

  function bindControls() {
    var startBtn = document.getElementById('k2-sim-start');
    var stopBtn = document.getElementById('k2-sim-stop');
    if (startBtn) {
      startBtn.addEventListener('click', function () {
        startBtn.disabled = true;
        setMessage('Starting…');
        fetchJson(apiUrl('start', readStartParams()))
          .then(function (data) {
            if (data && data.ok) {
              setMessage(data.message || 'Started — open Status or keep this page open for ticks.');
              return pollStatus();
            }
            setMessage((data && data.error) ? data.error : 'Start failed.');
          })
          .finally(function () {
            startBtn.disabled = false;
          });
      });
    }
    if (stopBtn) {
      stopBtn.addEventListener('click', function () {
        fetchJson(apiUrl('stop')).then(function (data) {
          setMessage((data && data.message) ? data.message : 'Stopped.');
          return pollStatus();
        });
      });
    }
  }

  function boot() {
    var root = document.getElementById('k2-status-room-live-sim');
    if (!root || root.getAttribute('data-k2-sim-allowed') !== '1') {
      return;
    }
    bindControls();
    pollStatus();
    window.setInterval(pollStatus, 1000);
  }

  if (typeof window.k2OnPageReady === 'function') {
    window.k2OnPageReady(boot);
  } else {
    document.addEventListener('DOMContentLoaded', boot);
  }
}());
