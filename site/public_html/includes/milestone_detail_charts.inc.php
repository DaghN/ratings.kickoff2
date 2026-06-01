<?php

/**
 * Chart blocks for milestone.php — expects $k2MilestoneChartIds, $k2MilestoneDefinition.
 */

declare(strict_types=1);

$mKey = (string) $k2MilestoneDefinition['milestone_key'];
$token = (string) $k2MilestoneDefinition['chart_token'];
$chartIds = $k2MilestoneChartIds;
$k2MsDetailChartsHeading = $k2MsDetailChartsHeading ?? true;
?>
<section class="k2-ms-detail-section k2-ms-detail-charts" aria-labelledby="k2-ms-detail-panel-graphs">
<?php if ($k2MsDetailChartsHeading) { ?>
	<h2 class="k2-panel-heading" id="k2-ms-charts-heading">Charts</h2>
<?php } else { ?>
	<h2 class="k2-panel-heading visually-hidden" id="k2-ms-detail-panel-graphs">Graphs</h2>
<?php } ?>
	<p class="k2-ms-detail-charts__empty-note" id="k2-ms-charts-empty-note" hidden></p>

<?php if (in_array('ms_year', $chartIds, true)) { ?>
	<div class="milestone-unlocks-year-chart k2-ms-detail-chart"
		data-milestone-key="<?php echo k2_h($mKey); ?>"
		data-chart-token="<?php echo k2_h($token); ?>">
		<h2 class="k2-panel-heading">New unlocks per year</h2>
		<p class="milestone-unlocks-year-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
		<canvas width="960" height="271" aria-label="New unlocks per calendar year for this milestone"></canvas>
	</div>
<?php } ?>

<?php if (in_array('ms_cumulative', $chartIds, true)) { ?>
	<div class="milestone-cumulative-unlocks-chart k2-ms-detail-chart"
		data-milestone-key="<?php echo k2_h($mKey); ?>"
		data-chart-token="<?php echo k2_h($token); ?>">
		<h2 class="k2-panel-heading">Cumulative unlocks</h2>
		<p class="k2-chart-block__hint">Steps up by one whenever a player unlocks this milestone.</p>
		<p class="milestone-cumulative-unlocks-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
		<canvas width="960" height="271" aria-label="Cumulative unlocks for this milestone over time"></canvas>
	</div>
<?php } ?>
</section>
