<?php
/**
 * Activity hub — People wing chart panels (5). Mounted by js/amiga-activity-charts.js.
 *
 * Cumulative players = Q-VOL-004 + Q-SHP-010 merge (policy §4) — each step is a debut.
 *
 * @see docs/amiga-activity-charts-implementation-plan.md §2 (panel registry)
 */
?>
<section class="k2-activity-section" aria-labelledby="k2-act-people-title">
	<header class="k2-activity-section__head">
		<h2 class="k2-panel-heading" id="k2-act-people-title">Who's playing?</h2>
		<p class="k2-activity-section__intro">How many players are active each year, and how many are fresh faces? Read the two bar charts as a pair — then follow the curves to see the roster grow, tournament by tournament.</p>
	</header>

	<div class="amiga-act-active-players-year-chart k2-chart-panel" data-k2-chart-panel="active-players-year">
		<h3 class="k2-panel-heading">Active players per year</h3>
		<p class="k2-chart-panel__status">Loading active players per year…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Active players per calendar year"></canvas>
		</div>
	</div>

	<div class="amiga-act-debuts-year-chart k2-chart-panel" data-k2-chart-panel="debuts-year">
		<h3 class="k2-panel-heading">New players per year</h3>
		<p class="k2-chart-panel__status">Loading new players per year…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Player debuts per calendar year"></canvas>
		</div>
	</div>

	<div class="amiga-act-players-cumulative-chart k2-chart-panel" data-k2-chart-panel="players-cumulative">
		<h3 class="k2-panel-heading">Cumulative players</h3>
		<p class="k2-chart-panel__status">Loading cumulative players…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Cumulative players across all tournaments"></canvas>
		</div>
	</div>

	<div class="amiga-act-pairs-year-chart k2-chart-panel" data-k2-chart-panel="pairs-year">
		<h3 class="k2-panel-heading">Distinct opponent pairs per year</h3>
		<p class="k2-chart-panel__status">Loading distinct pairs per year…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Distinct opponent pairings per calendar year"></canvas>
		</div>
	</div>

	<div class="amiga-act-pairs-cumulative-chart k2-chart-panel" data-k2-chart-panel="pairs-cumulative">
		<h3 class="k2-panel-heading">Cumulative distinct pairs</h3>
		<p class="k2-chart-panel__status">Loading cumulative distinct pairs…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Cumulative distinct opponent pairings over time"></canvas>
		</div>
	</div>
</section>