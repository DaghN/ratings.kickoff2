<?php
/**
 * Activity hub — Growth wing chart panels (7). Mounted by js/amiga-activity-charts.js.
 *
 * @see docs/amiga-activity-charts-implementation-plan.md §2 (panel registry)
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_snapshot_context.php';

/**
 * Era call-outs in the Growth intro — only after the cutoff has lived through each beat.
 *
 * @return list<string>
 */
function amiga_activity_growth_era_phrases(int $cutoffYear): array
{
	$eras = [];
	if ($cutoffYear >= 2008) {
		$eras[] = 'the mid-2000s boom';
	}
	if ($cutoffYear >= 2018) {
		$eras[] = 'the lean mid-2010s';
	}
	if ($cutoffYear >= 2022) {
		$eras[] = 'the modern revival';
	}

	return $eras;
}

function amiga_activity_growth_era_list_text(array $eras): string
{
	$n = count($eras);
	if ($n === 0) {
		return '';
	}
	if ($n === 1) {
		return $eras[0];
	}
	if ($n === 2) {
		return $eras[0] . ' and ' . $eras[1];
	}

	return implode(', ', array_slice($eras, 0, -1)) . ', and ' . $eras[$n - 1];
}

function amiga_activity_growth_section_intro(int $cutoffYear): string
{
	$eras = amiga_activity_growth_era_phrases($cutoffYear);
	$eraList = amiga_activity_growth_era_list_text($eras);
	$rhythm = 'Year bars show the rhythm';
	if ($eraList !== '') {
		$rhythm .= ' — ' . $eraList . ' —';
	}

	return 'Which years were big, and how do the totals pile up? '
		. $rhythm
		. ' and each curve beneath walks tournament by tournament to the total.';
}

$k2ActGrowthCtx = amiga_snapshot_context_peek();
$k2ActGrowthCutoffYear = (int) date('Y');
if ($k2ActGrowthCtx !== null && $k2ActGrowthCtx->isActive()) {
	$k2ActGrowthCutoff = $k2ActGrowthCtx->cutoff();
	if ($k2ActGrowthCutoff !== null) {
		$k2ActGrowthCutoffYear = (int) substr((string) $k2ActGrowthCutoff['event_date'], 0, 4);
	}
}
$k2ActGrowthIntro = amiga_activity_growth_section_intro($k2ActGrowthCutoffYear);
?>
<section class="k2-activity-section" aria-labelledby="k2-act-growth-title">
	<header class="k2-activity-section__head">
		<h2 class="k2-panel-heading" id="k2-act-growth-title">How much Kick Off 2 do we play?</h2>
		<p class="k2-activity-section__intro"><?php echo k2_h($k2ActGrowthIntro); ?></p>
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