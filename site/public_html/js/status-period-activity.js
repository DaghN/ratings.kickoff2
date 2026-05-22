/**
 * Status tab — games-by-day / month / year tables (picker + API refresh).
 */
(function () {
    'use strict';

    var API_PATH = 'api/server_period_activity_leaderboard.php';

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function pluralGames(n) {
        return n === 1 ? ' game' : ' games';
    }

    function buildTbody(entries) {
        if (!entries || !entries.length) {
            return '<tbody class="black"><tr><td colspan="3" style="text-align:left;">No rated games in this period.</td></tr></tbody>';
        }
        var html = '<tbody class="black">';
        for (var i = 0; i < entries.length; i++) {
            var e = entries[i];
            html += '<tr style="text-align:right;">';
            html += '<td>' + e.rank + '</td>';
            html += '<td style="text-align:left;"><a href="individual1.php?id=' + e.player_id + '">'
                + escapeHtml(e.player_name) + '</a></td>';
            html += '<td>' + e.games + '</td>';
            html += '</tr>';
        }
        html += '</tbody>';
        return html;
    }

    function panelKey(panel) {
        var input = panel.querySelector('.server-period-activity-leaderboard__input');
        return input ? input.value : '';
    }

    function updateSummary(panel, data) {
        var summary = panel.querySelector('[data-summary]');
        if (!summary) {
            return;
        }
        var total = typeof data.total_games === 'number' ? data.total_games : 0;
        var label = data.label || data.key || '';
        summary.innerHTML = '<strong>' + total + '</strong> rated' + pluralGames(total)
            + ' <span class="server-period-activity-leaderboard__summary-period">· '
            + escapeHtml(label) + '</span>';
    }

    function setPanelLoading(panel, loading) {
        panel.classList.toggle('server-period-activity-leaderboard--loading', loading);
        var input = panel.querySelector('.server-period-activity-leaderboard__input');
        if (input) {
            input.disabled = loading;
        }
    }

    function fetchPanel(root, panel) {
        var period = panel.getAttribute('data-period');
        var key = panelKey(panel);
        var limit = root.getAttribute('data-limit') || '50';
        var globalStatus = root.querySelector('.server-period-activity-leaderboards-status--global');

        if (!period || !key) {
            return;
        }

        setPanelLoading(panel, true);
        if (globalStatus) {
            globalStatus.hidden = true;
            globalStatus.textContent = '';
        }

        var url = API_PATH + '?period=' + encodeURIComponent(period)
            + '&key=' + encodeURIComponent(key)
            + '&limit=' + encodeURIComponent(limit);

        fetch(url, { credentials: 'same-origin' })
            .then(function (res) {
                return res.json().then(function (body) {
                    return { ok: res.ok, body: body };
                });
            })
            .then(function (result) {
                if (!result.ok || result.body.error) {
                    throw new Error(result.body.error || 'request_failed');
                }
                var table = panel.querySelector('.server-period-activity-leaderboard__table');
                if (table) {
                    var oldBody = table.querySelector('tbody');
                    if (oldBody) {
                        oldBody.remove();
                    }
                    table.insertAdjacentHTML('beforeend', buildTbody(result.body.entries));
                }
                updateSummary(panel, result.body);
            })
            .catch(function () {
                if (globalStatus) {
                    globalStatus.hidden = false;
                    globalStatus.textContent = 'Could not refresh games for the selected period. Try again.';
                }
            })
            .finally(function () {
                setPanelLoading(panel, false);
            });
    }

    function bindPanel(root, panel) {
        var input = panel.querySelector('.server-period-activity-leaderboard__input');
        if (!input) {
            return;
        }
        input.addEventListener('change', function () {
            fetchPanel(root, panel);
        });
    }

    function init() {
        var roots = document.querySelectorAll('.server-period-activity-leaderboards');
        for (var r = 0; r < roots.length; r++) {
            var root = roots[r];
            var panels = root.querySelectorAll('.server-period-activity-leaderboard');
            for (var p = 0; p < panels.length; p++) {
                bindPanel(root, panels[p]);
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
