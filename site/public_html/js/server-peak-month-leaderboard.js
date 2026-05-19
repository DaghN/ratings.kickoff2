/**
 * Server top players by personal peak month (rated games in one calendar month).
 * Expects api/server_peak_month_leaderboard.php
 */
(function () {
    'use strict';

    var API_PATH = 'api/server_peak_month_leaderboard.php';

    function formatMonth(ym) {
        if (!ym || ym.length < 7) {
            return ym || '';
        }
        var d = new Date(ym + '-01T00:00:00');
        if (isNaN(d.getTime())) {
            return ym;
        }
        return d.toLocaleString(undefined, { month: 'short', year: 'numeric' });
    }

    function escapeHtml(text) {
        var el = document.createElement('div');
        el.textContent = text == null ? '' : String(text);
        return el.innerHTML;
    }

    function initRoot(root) {
        var status = root.querySelector('.server-peak-month-leaderboard-status');
        var tableWrap = root.querySelector('.server-peak-month-leaderboard-table-wrap');

        if (!tableWrap) {
            if (status) {
                status.textContent = 'Table container missing.';
            }
            return;
        }

        if (status) {
            status.textContent = 'Loading peak month leaderboard…';
        }

        var url = API_PATH + '?realm=online&limit=50';

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('bad_status');
                }
                return r.json();
            })
            .then(function (data) {
                var entries = data.entries || [];
                if (!entries.length) {
                    if (status) {
                        status.textContent = 'No rated games to rank yet.';
                    }
                    tableWrap.innerHTML = '';
                    return;
                }

                if (status) {
                    status.textContent = '';
                }

                var html = '<table class="example table-stripeclass:alternate table-autostripe table-rowshade-alternate" '
                    + 'style="width:100%;"><thead><tr>'
                    + '<th style="text-align:right;width:3rem;">#</th>'
                    + '<th style="text-align:left;">Player</th>'
                    + '<th style="text-align:left;">Peak month</th>'
                    + '<th style="text-align:right;">Games</th>'
                    + '</tr></thead><tbody class="black">';

                for (var i = 0; i < entries.length; i++) {
                    var e = entries[i];
                    var profileUrl = 'individual1.php?id=' + encodeURIComponent(e.player_id);
                    html += '<tr style="text-align:left;">'
                        + '<td style="text-align:right;">' + escapeHtml(e.rank) + '</td>'
                        + '<td><a href="' + escapeHtml(profileUrl) + '">' + escapeHtml(e.player_name) + '</a></td>'
                        + '<td>' + escapeHtml(formatMonth(e.month)) + '</td>'
                        + '<td style="text-align:right;">' + escapeHtml(e.games) + '</td>'
                        + '</tr>';
                }

                html += '</tbody></table>';
                tableWrap.innerHTML = html;
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load peak month leaderboard.';
                }
                tableWrap.innerHTML = '';
            });
    }

    function boot() {
        var roots = document.querySelectorAll('.server-peak-month-leaderboard');
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
