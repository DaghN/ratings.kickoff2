<?php
/**
 * Activity chart panels for server1.php (v2 frames).
 * Loaded by activity-charts-v2.js — see docs/activity-charts.md.
 */
?>
<section class="k2-activity-section" aria-labelledby="k2-activity-volume-title">
    <header class="k2-activity-section__head">
        <h2 class="k2-panel-heading" id="k2-activity-volume-title">How much do we play?</h2>
        <p class="k2-activity-section__intro">From the last few weeks to the full archive, this is the pulse of rated play.</p>
    </header>

    <div class="server-games-day-chart k2-chart-panel" data-k2-chart-panel="games-day">
        <h3 class="k2-panel-heading">Games per day · past month</h3>
        <p class="server-games-day-chart-status k2-chart-panel__status">Loading games per day...</p>
        <div class="k2-chart-frame">
            <canvas aria-label="Rated games per day for the past month"></canvas>
        </div>
    </div>

    <div class="server-games-month-chart k2-chart-panel" data-k2-chart-panel="games-month">
        <h3 class="k2-panel-heading">Games per month</h3>
        <p class="server-games-month-chart-status k2-chart-panel__status">Loading games per month…</p>
        <div class="k2-chart-frame">
            <canvas aria-label="Rated games per calendar month"></canvas>
        </div>
    </div>

    <div class="server-games-year-chart k2-chart-panel" data-k2-chart-panel="games-year">
        <h3 class="k2-panel-heading">Games per year</h3>
        <p class="server-games-year-chart-status k2-chart-panel__status">Loading games per year…</p>
        <div class="k2-chart-frame">
            <canvas aria-label="Rated games per calendar year with projection"></canvas>
        </div>
    </div>

    <div class="server-activity-heatmap k2-chart-panel" data-k2-chart-panel="heatmap">
        <h3 class="k2-panel-heading">Daily activity · past 12 months</h3>
        <p class="server-activity-heatmap-status k2-chart-panel__status">Loading activity heatmap…</p>
        <div class="activity-heatmap-wrap"></div>
    </div>
</section>

<section class="k2-activity-section" aria-labelledby="k2-activity-people-title">
    <header class="k2-activity-section__head">
        <h2 class="k2-panel-heading" id="k2-activity-people-title">How many of us are active?</h2>
        <p class="k2-activity-section__intro">Monthly players, daily averages, and unique matchups show whether the room is broadening or concentrating.</p>
    </header>

    <div class="server-active-players-month-chart k2-chart-panel" data-k2-chart-panel="active-month">
        <h3 class="k2-panel-heading">Active players per month</h3>
        <p class="server-active-players-month-chart-status k2-chart-panel__status">Loading active players per month…</p>
        <div class="k2-chart-frame">
            <canvas aria-label="Active players per calendar month"></canvas>
        </div>
    </div>

    <div class="server-daily-active-players-chart k2-chart-panel" data-k2-chart-panel="daily-active">
        <h3 class="k2-panel-heading">Daily active players · 30-day average</h3>
        <p class="server-daily-active-players-chart-status k2-chart-panel__status">Loading daily active players…</p>
        <div class="k2-chart-frame">
            <canvas aria-label="Daily active players smoothed over 30 days, all time"></canvas>
        </div>
    </div>

    <div class="server-matchup-breadth-chart k2-chart-panel" data-k2-chart-panel="matchup">
        <h3 class="k2-panel-heading">Unique matchups per month</h3>
        <p class="k2-chart-block__hint">Distinct player pairings each month — social breadth of the community.</p>
        <p class="server-matchup-breadth-chart-status k2-chart-panel__status">Loading matchup breadth…</p>
        <div class="k2-chart-frame">
            <canvas aria-label="Unique matchups per month"></canvas>
        </div>
    </div>
</section>

<section class="k2-activity-section" aria-labelledby="k2-activity-join-title">
    <header class="k2-activity-section__head">
        <h2 class="k2-panel-heading" id="k2-activity-join-title">Who becomes established?</h2>
        <p class="k2-activity-section__intro">The 20-game mark filters out one-off visits and shows who really joined the ladder.</p>
    </header>

    <div class="server-established-players-year-chart k2-chart-panel" data-k2-chart-panel="established-year">
        <h3 class="k2-panel-heading">New established players per year</h3>
        <p class="k2-chart-block__hint">Players whose 20th rated game fell in that calendar year.</p>
        <p class="server-established-players-year-chart-status k2-chart-panel__status">Loading newly established players per year…</p>
        <div class="k2-chart-frame">
            <canvas aria-label="Newly established players per calendar year"></canvas>
        </div>
    </div>

    <div class="server-cumulative-established-month-chart k2-chart-panel" data-k2-chart-panel="cumulative-established">
        <h3 class="k2-panel-heading">Cumulative established players</h3>
        <p class="k2-chart-block__hint">Steps up by one whenever a player plays their 20th rated game.</p>
        <p class="server-cumulative-established-month-chart-status k2-chart-panel__status">Loading cumulative established players…</p>
        <div class="k2-chart-frame">
            <canvas aria-label="Cumulative established players over time by month"></canvas>
        </div>
    </div>

    <div class="server-established-rating-distribution-chart k2-chart-panel" data-k2-chart-panel="rating-distribution">
        <h3 class="k2-panel-heading">Established player rating distribution</h3>
        <p class="server-established-rating-distribution-chart-status k2-chart-panel__status">Loading rating distribution…</p>
        <div class="k2-chart-frame">
            <canvas aria-label="Distribution of established player ratings"></canvas>
        </div>
    </div>
</section>

<section class="k2-activity-section" aria-labelledby="k2-activity-super-active-title">
    <header class="k2-activity-section__head">
        <h2 class="k2-panel-heading" id="k2-activity-super-active-title">Do the super active hang around?</h2>
        <p class="k2-activity-section__intro">The busiest players come in waves. This view shows whether their runs fade, pause, or return.</p>
    </header>

    <div class="server-top-activity-eras-chart k2-chart-panel" data-k2-chart-panel="top-eras">
        <h3 class="k2-panel-heading">10 most active players ever · 6-month rolling average</h3>
        <p class="k2-chart-block__hint">Each line is a trailing 6-month average of games per month. Hover to highlight.</p>
        <p class="server-top-activity-eras-chart-status k2-chart-panel__status">Loading…</p>
        <div class="k2-chart-frame k2-chart-frame--tall">
            <canvas aria-label="Six-month rolling average of monthly games for the ten most active players ever"></canvas>
        </div>
    </div>
</section>

<section class="k2-activity-section" aria-labelledby="k2-activity-trends-title">
    <header class="k2-activity-section__head">
        <h2 class="k2-panel-heading" id="k2-activity-trends-title">Does play style change?</h2>
        <p class="k2-activity-section__intro">Goals, draws, double digits, and clean sheets move together in odd little rhythms.</p>
    </header>

    <div class="server-play-texture-chart k2-chart-panel" data-k2-chart-panel="play-texture">
        <h3 class="k2-panel-heading">Play texture by month (click to hide graphs for more focus)</h3>
        <p class="k2-chart-block__hint">Normalized rates: goals per game, draw %, double-digit and clean-sheet rates per 100 games.</p>
        <p class="server-play-texture-chart-status k2-chart-panel__status">Loading play texture…</p>
        <div class="k2-chart-frame">
            <canvas aria-label="Monthly play texture rates"></canvas>
        </div>
    </div>
</section>
