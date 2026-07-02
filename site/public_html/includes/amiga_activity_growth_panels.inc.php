<?php
/**
 * Activity hub — Growth wing chart panels (7). Mounted by js/amiga-activity-charts.js.
 *
 * @see docs/amiga-activity-charts-implementation-plan.md §2 (panel registry)
 */
?>
<section class="k2-activity-section" aria-labelledby="k2-act-growth-title">
	<header class="k2-activity-section__head">
		<h2 class="k2-panel-heading" id="k2-act-growth-title">How much Kick Off 2 have we been playing?</h2>
		<p class="k2-activity-section__intro">Which years were big, and how did the totals pile up? Year bars show the rhythm — the mid-2000s boom, the lean mid-2010s, the modern revival — and each curve beneath walks tournament by tournament to the total. Every point on a curve is a tournament: click one to open it.</p>
	</header>

	<div class="amiga-act-games-year-chart k2-chart-panel" data-k2-chart-panel="games-year">
		<h3 class="k2-panel-heading">Games per year</h3>
		<p class="k2-chart-panel__status">Loading games per year…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Rated games per calendar year"></canvas>
		</div>
	</div>

	<div class="amiga-act-games-cumulative-chart k2-chart-panel" data-k2-chart-panel="games-cumulative">
		<h3 class="k2-panel-heading">Cumulative games</h3>
		<p class="k2-chart-panel__status">Loading cumulative games…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Cumulative rated games across all tournaments"></canvas>
		</div>
	</div>

	<div class="amiga-act-tournaments-year-chart k2-chart-panel" data-k2-chart-panel="tournaments-year">
		<h3 class="k2-panel-heading">Tournaments per year</h3>
		<p class="k2-chart-panel__status">Loading tournaments per year…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Tournaments per calendar year"></canvas>
		</div>
	</div>

	<div class="amiga-act-tournaments-cumulative-chart k2-chart-panel" data-k2-chart-panel="tournaments-cumulative">
		<h3 class="k2-panel-heading">Cumulative tournaments</h3>
		<p class="k2-chart-panel__status">Loading cumulative tournaments…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Cumulative tournaments over time"></canvas>
		</div>
	</div>

	<div class="amiga-act-goals-year-chart k2-chart-panel" data-k2-chart-panel="goals-year">
		<h3 class="k2-panel-heading">Goals per year</h3>
		<p class="k2-chart-panel__status">Loading goals per year…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Goals scored per calendar year"></canvas>
		</div>
	</div>

	<div class="amiga-act-goals-cumulative-chart k2-chart-panel" data-k2-chart-panel="goals-cumulative">
		<h3 class="k2-panel-heading">Cumulative goals</h3>
		<p class="k2-chart-panel__status">Loading cumulative goals…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Cumulative goals scored over time"></canvas>
		</div>
	</div>

	<div class="amiga-act-games-per-tournament-year-chart k2-chart-panel" data-k2-chart-panel="games-per-tournament-year">
		<h3 class="k2-panel-heading">Average games per tournament</h3>
		<p class="k2-chart-panel__status">Loading games per tournament…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Average rated games per tournament per calendar year"></canvas>
		</div>
	</div>
</section>