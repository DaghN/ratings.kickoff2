(function () {
  'use strict';

  function apiUrl(action) {
    return '/api/status_room_live_sim.php?action=' + encodeURIComponent(action);
  }

  function setMessage(text) {
    var el = document.getElementById('k2-sim-message');
    if (el) {
      el.textContent = text || '';
    }
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
    var liveEl = root.querySelector('[data-k2-sim-field="live_count"]');
    var queuedEl = root.querySelector('[data-k2-sim-field="queued_count"]');
    var eventEl = root.querySelector('[data-k2-sim-field="last_event"]');
    if (activeEl) {
      activeEl.textContent = st.active ? 'running' : 'idle';
    }
    if (progressEl) {
      progressEl.textContent = String(st.completed_count || 0) + ' / ' + String(st.game_count || 0);
    }
    if (liveEl) {
      liveEl.textContent = String(st.live_count || 0);
    }
    if (queuedEl) {
      queuedEl.textContent = String(st.queued_count || 0);
    }
    if (eventEl) {
      eventEl.textContent = st.last_event || '—';
    }
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
        fetchJson(apiUrl('start'))
          .then(function (data) {
            if (data && data.ok) {
              setMessage(data.message || 'Started — open Status or stay here to keep ticks running.');
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