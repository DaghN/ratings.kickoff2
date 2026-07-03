<?php
/**
 * Activity hub — Texture wing chart panels (6). Mounted by js/amiga-activity-charts.js.
 *
 * Each bar carries a dashed all-time average reference line at the cutoff.
 *
 * @see docs/amiga-activity-charts-implementation-plan.md §2 (panel registry)
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/amiga_tournament_lib.php';

/**
 * Full Copenhagen arc once cutoff has reached World Cup XIV; simpler lede before that.
 */
function amiga_activity_texture_show_copenhagen_arc(): bool
{
	$ctx = amiga_snapshot_context_peek();
	if (!$ctx instanceof AmigaSnapshotContext || !$ctx->isActive()) {
		return true;
	}

	$cutoff = $ctx->cutoff();
	if ($cutoff === null) {
		return true;
	}

	include __DIR__ . '/../../config/ko2amiga_config.php';
	$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
	$milestone = amiga_rating_history_cutoff_tournament_by_id(
		$con,
		AMIGA_WORLD_CUP_XIV_COPENHAGEN_TOURNAMENT_ID
	);
	mysqli_close($con);
	unset($con);

	if ($milestone === null) {
		return true;
	}

	return amiga_event_tuple_gte($cutoff, [
		'event_date' => (string) $milestone['event_date'],
		'chrono' => (float) $milestone['chrono'],
		'tournament_id' => (int) $milestone['id'],
	]);
}

function amiga_activity_texture_section_intro_html(): string
{
	$dartfordLink = amiga_tournament_link(AMIGA_FIRST_WORLD_CUP_TOURNAMENT_ID, 'Dartford 2001');
	$tail = ' — here&apos;s how wild or tight each year got.';

	if (amiga_activity_texture_show_copenhagen_arc()) {
		$copenhagenLink = amiga_tournament_link(
			AMIGA_WORLD_CUP_XIV_COPENHAGEN_TOURNAMENT_ID,
			'Copenhagen 2014'
		);

		return 'From the feeble beginnings at ' . $dartfordLink
			. ' through the bloodbath at ' . $copenhagenLink
			. ' to the modern era of ruthless efficiency' . $tail;
	}

	return 'From the feeble beginnings at ' . $dartfordLink . ' to present day' . $tail;
}

$k2ActTextureIntro = amiga_activity_texture_section_intro_html();
?>
<section class="k2-activity-section" aria-labelledby="k2-act-texture-title">
	<header class="k2-activity-section__head">
		<h2 class="k2-panel-heading" id="k2-act-texture-title">What are the games like?</h2>
		<p class="k2-activity-section__intro"><?php echo $k2ActTextureIntro; ?></p>
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