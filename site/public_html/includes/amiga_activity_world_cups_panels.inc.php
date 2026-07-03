<?php
/**
 * Activity hub — World Cups wing chart panels (7). Mounted by js/amiga-activity-charts.js.
 *
 * @see docs/amiga-activity-charts-implementation-plan.md §2 (panel registry)
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_amiga_routes.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_snapshot_url.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';

$k2ActWcHubHref = amiga_url_with_context(k2_amiga_route('amiga-world-cups'));
$k2ActWcStatsHref = amiga_url_with_context(k2_amiga_route('amiga-world-cups-stats'));
?>
<section class="k2-activity-section" aria-labelledby="k2-act-world-cups-title">
	<header class="k2-activity-section__head">
		<h2 class="k2-panel-heading" id="k2-act-world-cups-title">How big was the big stage?</h2>
		<p class="k2-activity-section__intro">World Cup games against the whole scene — share of each year, nations and players at the crown-jewel events. Per-event tables live on the <a href="<?php echo k2_h($k2ActWcHubHref); ?>">World Cups hub</a> and its <a href="<?php echo k2_h($k2ActWcStatsHref); ?>">Tournament stats</a> wing; these charts are the community-wide calendar lens. Cumulative curves click through to each tournament.</p>
	</header>

	<div class="amiga-act-wc-players-year-chart k2-chart-panel" data-k2-chart-panel="wc-players-year">
		<h3 class="k2-panel-heading">Participants per year</h3>
		<p class="k2-chart-panel__status">Loading participants per year…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="World Cup participants per calendar year"></canvas>
		</div>
	</div>

	<div class="amiga-act-wc-nations-year-chart k2-chart-panel" data-k2-chart-panel="wc-nations-year">
		<h3 class="k2-panel-heading">Nations at the World Cup per year</h3>
		<p class="k2-chart-panel__status">Loading WC nations per year…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Distinct nationalities at the World Cup per calendar year"></canvas>
		</div>
	</div>

	<div class="amiga-act-wc-games-year-chart k2-chart-panel" data-k2-chart-panel="wc-games-year">
		<h3 class="k2-panel-heading">World Cup games per year</h3>
		<p class="k2-chart-block__hint">Click the crossed-out legend label to compare World Cup games with all rated games in each year.</p>
		<p class="k2-chart-panel__status">Loading WC games per year…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="World Cup rated games per calendar year with all rated games behind"></canvas>
		</div>
	</div>

	<div class="amiga-act-wc-share-year-chart k2-chart-panel" data-k2-chart-panel="wc-share-year">
		<h3 class="k2-panel-heading">WC share of each year&apos;s games</h3>
		<p class="k2-chart-panel__status">Loading WC share…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="World Cup games as a share of all rated games per calendar year"></canvas>
		</div>
	</div>

	<div class="amiga-act-wc-games-per-player-year-chart k2-chart-panel" data-k2-chart-panel="wc-games-per-player-year">
		<h3 class="k2-panel-heading">Average games per participant</h3>
		<p class="k2-chart-panel__status">Loading average games per participant…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Average World Cup games per participant per calendar year"></canvas>
		</div>
	</div>

	<div class="amiga-act-wc-goals-per-game-year-chart k2-chart-panel" data-k2-chart-panel="wc-goals-per-game-year">
		<h3 class="k2-panel-heading">WC goals per game</h3>
		<p class="k2-chart-panel__status">Loading WC goals per game…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="World Cup goals per game per calendar year with realm rate overlaid"></canvas>
		</div>
	</div>

	<div class="amiga-act-wc-games-cumulative-chart k2-chart-panel" data-k2-chart-panel="wc-games-cumulative">
		<h3 class="k2-panel-heading">Cumulative World Cup games</h3>
		<p class="k2-chart-panel__status">Loading cumulative WC games…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Cumulative World Cup rated games across all tournaments"></canvas>
		</div>
	</div>
</section>