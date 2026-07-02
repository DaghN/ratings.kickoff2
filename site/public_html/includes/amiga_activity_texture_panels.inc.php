<?php
/**
 * Activity hub — Texture wing chart panels (6). Mounted by js/amiga-activity-charts.js.
 *
 * Each bar carries a dashed all-time average reference line at the cutoff.
 *
 * @see docs/amiga-activity-charts-implementation-plan.md §2 (panel registry)
 */
?>
<section class="k2-activity-section" aria-labelledby="k2-act-texture-title">
	<header class="k2-activity-section__head">
		<h2 class="k2-panel-heading" id="k2-act-texture-title">How did the games feel, year by year?</h2>
		<p class="k2-activity-section__intro">Goals, draws, double digits, clean sheets, low-scoring grinds and high-scoring thrillers — each as a yearly rate. The dashed line is the all-time average at the current cutoff, so every bar reads as tighter or wilder than the era norm.</p>
	</header>

	<div class="amiga-act-goals-per-game-year-chart k2-chart-panel" data-k2-chart-panel="goals-per-game-year">
		<h3 class="k2-panel-heading">Goals per game</h3>
		<p class="k2-chart-panel__status">Loading goals per game…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Goals per game per calendar year"></canvas>
		</div>
	</div>

	<div class="amiga-act-draw-rate-year-chart k2-chart-panel" data-k2-chart-panel="draw-rate-year">
		<h3 class="k2-panel-heading">Draw rate</h3>
		<p class="k2-chart-panel__status">Loading draw rate…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Draw rate per calendar year"></canvas>
		</div>
	</div>

	<div class="amiga-act-dd-rate-year-chart k2-chart-panel" data-k2-chart-panel="dd-rate-year">
		<h3 class="k2-panel-heading">Double-digit rate</h3>
		<p class="k2-chart-panel__status">Loading double-digit rate…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Double-digit games per 100 rated games per calendar year"></canvas>
		</div>
	</div>

	<div class="amiga-act-cs-rate-year-chart k2-chart-panel" data-k2-chart-panel="cs-rate-year">
		<h3 class="k2-panel-heading">Clean-sheet rate</h3>
		<p class="k2-chart-panel__status">Loading clean-sheet rate…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Clean-sheet games per 100 rated games per calendar year"></canvas>
		</div>
	</div>

	<div class="amiga-act-low-scoring-rate-year-chart k2-chart-panel" data-k2-chart-panel="low-scoring-rate-year">
		<h3 class="k2-panel-heading">Low-scoring rate</h3>
		<p class="k2-chart-block__hint">Games with three or fewer goals scored (both sides), per 100 rated games.</p>
		<p class="k2-chart-panel__status">Loading low-scoring rate…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Low-scoring games (three or fewer goals) per 100 rated games per calendar year"></canvas>
		</div>
	</div>

	<div class="amiga-act-high-scoring-rate-year-chart k2-chart-panel" data-k2-chart-panel="high-scoring-rate-year">
		<h3 class="k2-panel-heading">High-scoring rate</h3>
		<p class="k2-chart-block__hint">Games with ten or more goals scored (both sides), per 100 rated games.</p>
		<p class="k2-chart-panel__status">Loading high-scoring rate…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="High-scoring games (ten or more goals) per 100 rated games per calendar year"></canvas>
		</div>
	</div>
</section>