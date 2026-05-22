/**
 * Activity heatmap — weekly rated games (profile mock).
 * api/profile_mock/player_activity_heatmap.php
 */
(function () {
    'use strict';

    var API = 'api/profile_mock/player_activity_heatmap.php';

    function levelClass(games, maxGames) {
        if (!games || games < 1) {
            return 'pm2-heat__cell--0';
        }
        if (maxGames < 2) {
            return 'pm2-heat__cell--1';
        }
        var ratio = games / maxGames;
        if (ratio >= 0.75) {
            return 'pm2-heat__cell--4';
        }
        if (ratio >= 0.5) {
            return 'pm2-heat__cell--3';
        }
        if (ratio >= 0.25) {
            return 'pm2-heat__cell--2';
        }
        return 'pm2-heat__cell--1';
    }

    function initRoot(root) {
        var playerId = root.getAttribute('data-player-id');
        var grid = root.querySelector('.pm2-heat__grid');
        var status = root.querySelector('.pm2-heat__status');
        if (!playerId || !grid) {
            return;
        }

        fetch(API + '?id=' + encodeURIComponent(playerId) + '&weeks=104', { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('bad_status');
                }
                return r.json();
            })
            .then(function (data) {
                var weeks = data.weeks || [];
                var maxGames = data.maxGames || 1;
                grid.innerHTML = '';
                if (!weeks.length) {
                    if (status) {
                        status.textContent = 'No rated games in this window.';
                    }
                    return;
                }
                weeks.forEach(function (w) {
                    var cell = document.createElement('span');
                    cell.className = 'pm2-heat__cell ' + levelClass(w.games, maxGames);
                    cell.title = (w.weekStart || '') + ': ' + w.games + ' game' + (w.games === 1 ? '' : 's');
                    cell.setAttribute('aria-label', cell.title);
                    grid.appendChild(cell);
                });
                if (status) {
                    status.textContent = '';
                }
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load activity heatmap.';
                }
            });
    }

    function boot() {
        document.querySelectorAll('.pm2-heat').forEach(initRoot);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
