<?php
/**
 * Activity chart panels for server1.php (v2 frames).
 * Loaded by activity-charts-v2.js — see docs/activity-charts.md.
 */
?>
<div class="server-games-day-chart k2-chart-panel" data-k2-chart-panel="games-day">
    <h2 class="k2-panel-heading">Games per day · past month</h2>
    <p class="server-games-day-chart-status k2-chart-panel__status">Loading games per day...</p>
    <div class="k2-chart-frame">
        <canvas aria-label="Rated games per day for the past month"></canvas>
    </div>
</div>

<div class="server-games-month-chart k2-chart-panel" data-k2-chart-panel="games-month">
    <h2 class="k2-panel-heading">Games per month</h2>
    <p class="server-games-month-chart-status k2-chart-panel__status">Loading games per month…</p>
    <div class="k2-chart-frame">
        <canvas aria-label="Rated games per calendar month"></canvas>
    </div>
</div>

<div class="server-games-year-chart k2-chart-panel" data-k2-chart-panel="games-year">
    <h2 class="k2-panel-heading">Games per year</h2>
    <p class="server-games-year-chart-status k2-chart-panel__status">Loading games per year…</p>
    <div class="k2-chart-frame">
        <canvas aria-label="Rated games per calendar year with projection"></canvas>
    </div>
</div>

<?php /* TEMP A/B: daily activity heatmap hidden — see activity-charts-v2.js PANELS */ ?>
<!--
<div class="server-activity-heatmap k2-chart-panel" data-k2-chart-panel="heatmap">
    <h2 class="k2-panel-heading">Daily activity · past 12 months</h2>
    <p class="server-activity-heatmap-status k2-chart-panel__status">Loading activity heatmap…</p>
    <div class="activity-heatmap-wrap"></div>
</div>
-->

<div class="server-active-players-month-chart k2-chart-panel" data-k2-chart-panel="active-month">
    <h2 class="k2-panel-heading">Active players per month</h2>
    <p class="server-active-players-month-chart-status k2-chart-panel__status">Loading active players per month…</p>
    <div class="k2-chart-frame">
        <canvas aria-label="Active players per calendar month"></canvas>
    </div>
</div>

<div class="server-daily-active-players-chart k2-chart-panel" data-k2-chart-panel="daily-active">
    <h2 class="k2-panel-heading">Daily active players · 30-day average</h2>
    <p class="server-daily-active-players-chart-status k2-chart-panel__status">Loading daily active players…</p>
    <div class="k2-chart-frame">
        <canvas aria-label="Daily active players smoothed over 30 days, all time"></canvas>
    </div>
</div>

<div class="server-matchup-breadth-chart k2-chart-panel" data-k2-chart-panel="matchup">
    <h2 class="k2-panel-heading">Unique matchups per month</h2>
    <p class="k2-chart-block__hint">Distinct player pairings each month — social breadth of the community.</p>
    <p class="server-matchup-breadth-chart-status k2-chart-panel__status">Loading matchup breadth…</p>
    <div class="k2-chart-frame">
        <canvas aria-label="Unique matchups per month"></canvas>
    </div>
</div>

<div class="server-established-players-year-chart k2-chart-panel" data-k2-chart-panel="established-year">
    <h2 class="k2-panel-heading">New established players per year</h2>
    <p class="k2-chart-block__hint">Players whose 20th rated game fell in that calendar year.</p>
    <p class="server-established-players-year-chart-status k2-chart-panel__status">Loading newly established players per year…</p>
    <div class="k2-chart-frame">
        <canvas aria-label="Newly established players per calendar year"></canvas>
    </div>
</div>

<div class="server-cumulative-established-month-chart k2-chart-panel" data-k2-chart-panel="cumulative-established">
    <h2 class="k2-panel-heading">Cumulative established players</h2>
    <p class="k2-chart-block__hint">Steps up by one whenever a player plays their 20th rated game.</p>
    <p class="server-cumulative-established-month-chart-status k2-chart-panel__status">Loading cumulative established players…</p>
    <div class="k2-chart-frame">
        <canvas aria-label="Cumulative established players over time by month"></canvas>
    </div>
</div>

<div class="server-established-rating-distribution-chart k2-chart-panel" data-k2-chart-panel="rating-distribution">
    <h2 class="k2-panel-heading">Established player rating distribution</h2>
    <p class="server-established-rating-distribution-chart-status k2-chart-panel__status">Loading rating distribution…</p>
    <div class="k2-chart-frame">
        <canvas aria-label="Distribution of established player ratings"></canvas>
    </div>
</div>

<div class="server-top-activity-eras-chart k2-chart-panel" data-k2-chart-panel="top-eras">
    <h2 class="k2-panel-heading">10 most active players ever · 6-month rolling average</h2>
    <p class="k2-chart-block__hint">Each line is a trailing 6-month average of games per month. Hover to highlight.</p>
    <p class="server-top-activity-eras-chart-status k2-chart-panel__status">Loading…</p>
    <div class="k2-chart-frame k2-chart-frame--tall">
        <canvas aria-label="Six-month rolling average of monthly games for the ten most active players ever"></canvas>
    </div>
</div>

<div class="server-play-texture-chart k2-chart-panel" data-k2-chart-panel="play-texture">
    <h2 class="k2-panel-heading">Play texture by month (click to hide graphs for more focus)</h2>
    <p class="k2-chart-block__hint">Normalized rates: goals per game, draw %, double-digit and clean-sheet rates per 100 games.</p>
    <p class="server-play-texture-chart-status k2-chart-panel__status">Loading play texture…</p>
    <div class="k2-chart-frame">
        <canvas aria-label="Monthly play texture rates"></canvas>
    </div>
</div>
