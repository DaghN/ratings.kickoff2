<?php
/**
 * Activity hub — Geography Hosts wing chart panels (8). Mounted by js/amiga-activity-charts.js.
 *
 * @see docs/amiga-activity-charts-implementation-plan.md §2 (panel registry)
 */
?>
<section class="k2-activity-section k2-amiga-act-geo-host-panels" aria-label="Host country charts">

	<div class="amiga-act-host-games-year-chart k2-chart-panel" data-k2-chart-panel="host-games-year">
		<h3 class="k2-panel-heading">Games hosted per year</h3>
		<p class="k2-chart-panel__status">Loading games hosted per year…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Rated games hosted per calendar year, compared countries"></canvas>
		</div>
	</div>

	<div class="amiga-act-host-games-race-chart k2-chart-panel" data-k2-chart-panel="host-games-race">
		<h3 class="k2-panel-heading">Cumulative games hosted</h3>
		<p class="k2-chart-panel__status">Loading cumulative games hosted…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Cumulative rated games hosted over time by country"></canvas>
		</div>
	</div>

	<div class="amiga-act-host-tournaments-year-chart k2-chart-panel" data-k2-chart-panel="host-tournaments-year">
		<h3 class="k2-panel-heading">Tournaments hosted per year</h3>
		<p class="k2-chart-panel__status">Loading tournaments hosted per year…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Tournaments hosted per calendar year, compared countries"></canvas>
		</div>
	</div>

	<div class="amiga-act-host-tournaments-race-chart k2-chart-panel" data-k2-chart-panel="host-tournaments-race">
		<h3 class="k2-panel-heading">Cumulative tournaments hosted</h3>
		<p class="k2-chart-panel__status">Loading cumulative tournaments hosted…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Cumulative tournaments hosted over time by country"></canvas>
		</div>
	</div>

	<div class="amiga-act-host-goals-year-chart k2-chart-panel" data-k2-chart-panel="host-goals-year">
		<h3 class="k2-panel-heading">Goals hosted per year</h3>
		<p class="k2-chart-panel__status">Loading goals hosted per year…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Goals scored in hosted events per calendar year, compared countries"></canvas>
		</div>
	</div>

	<div class="amiga-act-host-goals-race-chart k2-chart-panel" data-k2-chart-panel="host-goals-race">
		<h3 class="k2-panel-heading">Cumulative goals hosted</h3>
		<p class="k2-chart-panel__status">Loading cumulative goals hosted…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Cumulative goals in hosted events over time by country"></canvas>
		</div>
	</div>

	<div class="amiga-act-host-countries-year-chart k2-chart-panel" data-k2-chart-panel="host-countries-year">
		<h3 class="k2-panel-heading">Distinct host countries per year</h3>
		<p class="k2-chart-panel__status">Loading distinct host countries per year…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Number of distinct host countries with rated games per calendar year"></canvas>
		</div>
	</div>

	<div class="amiga-act-host-countries-cumulative-chart k2-chart-panel" data-k2-chart-panel="host-countries-cumulative">
		<h3 class="k2-panel-heading">Cumulative distinct host countries</h3>
		<p class="k2-chart-panel__status">Loading cumulative distinct host countries…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Cumulative distinct host countries over time, stepped by tournament"></canvas>
		</div>
	</div>
</section>