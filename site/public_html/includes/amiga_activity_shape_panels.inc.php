<?php
/**
 * Activity hub — Shape wing chart panels (9). Mounted by js/amiga-activity-charts.js.
 *
 * @see docs/amiga-activity-charts-implementation-plan.md §2 (panel registry)
 */
?>
<section class="k2-activity-section" aria-labelledby="k2-act-shape-title">
	<header class="k2-activity-section__head">
		<h2 class="k2-panel-heading" id="k2-act-shape-title">What are we made of?</h2>
		<p class="k2-activity-section__intro">Histograms of careers, opponents, active years, countries, World Cups, ratings, scorelines and tournament sizes.</p>
	</header>

	<div class="amiga-act-career-games-histogram k2-chart-panel" data-k2-chart-panel="career-games-histogram">
		<h3 class="k2-panel-heading">Players by career games</h3>
		<p class="k2-chart-panel__status">Loading career games…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Players by career games played"></canvas>
		</div>
	</div>

	<div class="amiga-act-tournaments-played-histogram k2-chart-panel" data-k2-chart-panel="tournaments-played-histogram">
		<h3 class="k2-panel-heading">Players by tournaments played</h3>
		<p class="k2-chart-panel__status">Loading tournaments played…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Players by tournaments played"></canvas>
		</div>
	</div>

	<div class="amiga-act-distinct-opponents-histogram k2-chart-panel" data-k2-chart-panel="distinct-opponents-histogram">
		<h3 class="k2-panel-heading">Players by distinct opponents</h3>
		<p class="k2-chart-panel__status">Loading distinct opponents…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Players by distinct opponents faced"></canvas>
		</div>
	</div>

	<div class="amiga-act-active-years-histogram k2-chart-panel" data-k2-chart-panel="active-years-histogram">
		<h3 class="k2-panel-heading">Players by active calendar years</h3>
		<p class="k2-chart-panel__status">Loading active years…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Players by active calendar years"></canvas>
		</div>
	</div>

	<div class="amiga-act-countries-played-histogram k2-chart-panel" data-k2-chart-panel="countries-played-histogram">
		<h3 class="k2-panel-heading">Players by countries played in</h3>
		<p class="k2-chart-panel__status">Loading countries played…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Players by countries played in"></canvas>
		</div>
	</div>

	<div class="amiga-act-wcs-played-histogram k2-chart-panel" data-k2-chart-panel="wcs-played-histogram">
		<h3 class="k2-panel-heading">Players by World Cups played</h3>
		<p class="k2-chart-panel__status">Loading World Cups played…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Players by World Cups played"></canvas>
		</div>
	</div>

	<div class="amiga-act-rating-distribution-histogram k2-chart-panel" data-k2-chart-panel="rating-distribution-histogram">
		<h3 class="k2-panel-heading">Rating distribution</h3>
		<p class="k2-chart-panel__status">Loading rating distribution…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Player rating distribution"></canvas>
		</div>
	</div>

	<div class="amiga-act-goal-sum-histogram k2-chart-panel" data-k2-chart-panel="goal-sum-histogram">
		<h3 class="k2-panel-heading">Games by total goals</h3>
		<p class="k2-chart-panel__status">Loading goals per game…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Games by total goals scored"></canvas>
		</div>
	</div>

	<div class="amiga-act-tournament-size-histogram k2-chart-panel" data-k2-chart-panel="tournament-size-histogram">
		<h3 class="k2-panel-heading">Tournaments by game count</h3>
		<p class="k2-chart-panel__status">Loading tournament sizes…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Tournaments by rated games played"></canvas>
		</div>
	</div>
</section>