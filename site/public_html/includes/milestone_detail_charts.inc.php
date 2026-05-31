<?php
/**
 * Chart blocks for milestone.php — expects $k2MilestoneChartIds, $k2MilestoneDefinition.
 */
declare(strict_types=1);

$mKey = (string) $k2MilestoneDefinition['milestone_key'];
$token = (string) $k2MilestoneDefinition['chart_token'];
$chartIds = $k2MilestoneChartIds;
?>
<section class="k2-ms-detail-section k2-ms-detail-charts" aria-labelledby="k2-ms-charts-heading">
	<h2 class="k2-panel-heading" id="k2-ms-charts-heading">Charts</h2>
<?php if (in_array('timeline', $chartIds, true)) { ?>
	<div class="milestone-unlock-timeline-chart k2-ms-detail-chart"
		data-milestone-key="<?php echo k2_h($mKey); ?>"
		data-chart-token="<?php echo k2_h($token); ?>">
		<h3 class="k2-ms-detail-chart__title">Unlocks per month</h3>
		<p class="milestone-unlock-timeline-chart-status k2-ms-detail-chart__status">Loading…</p>
		<canvas width="960" height="271" aria-label="Monthly unlock counts for this milestone"></canvas>
	</div>
<?php } ?>
<?php if (in_array('dd_year', $chartIds, true)) { ?>
	<div class="server-double-digit-merchants-year-chart k2-ms-detail-chart">
		<h3 class="k2-ms-detail-chart__title">New Double Digit Merchants per year</h3>
		<p class="k2-chart-block__hint">Players whose first 10+ goal game fell in that calendar year.</p>
		<p class="server-double-digit-merchants-year-chart-status k2-ms-detail-chart__status">Loading…</p>
		<canvas width="960" height="271" aria-label="New Double Digit Merchants per calendar year"></canvas>
	</div>
<?php } ?>
<?php if (in_array('dd_cumulative', $chartIds, true)) { ?>
	<div class="server-cumulative-double-digit-merchants-chart k2-ms-detail-chart">
		<h3 class="k2-ms-detail-chart__title">Cumulative Double Digit Merchants</h3>
		<p class="server-cumulative-double-digit-merchants-chart-status k2-ms-detail-chart__status">Loading…</p>
		<canvas width="960" height="271" aria-label="Cumulative Double Digit Merchants over time"></canvas>
	</div>
<?php } ?>
<?php if (in_array('dd_rating', $chartIds, true)) { ?>
	<div class="server-double-digit-merchant-rating-distribution-chart k2-ms-detail-chart">
		<h3 class="k2-ms-detail-chart__title">Double Digit Merchant rating distribution</h3>
		<p class="server-double-digit-merchant-rating-distribution-chart-status k2-ms-detail-chart__status">Loading…</p>
		<canvas width="960" height="271" aria-label="Distribution of Double Digit Merchant ratings"></canvas>
	</div>
<?php } ?>
<?php if (in_array('est_year', $chartIds, true)) { ?>
	<div class="server-established-players-year-chart k2-ms-detail-chart">
		<h3 class="k2-ms-detail-chart__title">New established players per year</h3>
		<p class="server-established-players-year-chart-status k2-ms-detail-chart__status">Loading…</p>
		<canvas width="960" height="271" aria-label="New established players per calendar year"></canvas>
	</div>
<?php } ?>
<?php if (in_array('est_cumulative', $chartIds, true)) { ?>
	<div class="server-cumulative-established-month-chart k2-ms-detail-chart">
		<h3 class="k2-ms-detail-chart__title">Cumulative established players</h3>
		<p class="server-cumulative-established-month-chart-status k2-ms-detail-chart__status">Loading…</p>
		<canvas width="960" height="271" aria-label="Cumulative established players over time"></canvas>
	</div>
<?php } ?>
<?php if (in_array('est_rating', $chartIds, true)) { ?>
	<div class="server-established-rating-distribution-chart k2-ms-detail-chart">
		<h3 class="k2-ms-detail-chart__title">Established player rating distribution</h3>
		<p class="server-established-rating-distribution-chart-status k2-ms-detail-chart__status">Loading…</p>
		<canvas width="960" height="271" aria-label="Distribution of established player ratings"></canvas>
	</div>
<?php } ?>
</section>
