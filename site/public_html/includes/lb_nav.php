<?php
/**
 * Leaderboard wing tabs — segment track with outline active cell.
 * Set $k2LbWingActive before include: rating | goals | double-digits | streaks | victims | peak-rating | activity-peaks | league-honours | milestones
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_routes.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_player_filters.php';

$k2LbWingActive = $k2LbWingActive ?? 'rating';
$k2LbShowFilters = $k2LbWingActive !== 'activity-peaks';
$k2LbFilterOpts = k2_lb_filter_opts();
$k2LbFilterQs = k2_lb_filter_query_string($k2LbFilterOpts);
$k2LbWingTabs = [
	'rating' => ['href' => k2_route('lb-rating'), 'label' => 'Rating'],
	'goals' => ['href' => k2_route('lb-goals'), 'label' => 'Goals'],
	'double-digits' => ['href' => k2_route('lb-double-digits'), 'label' => 'DDs &amp; CSs'],
	'streaks' => ['href' => k2_route('lb-streaks'), 'label' => 'Streaks'],
	'victims' => ['href' => k2_route('lb-victims'), 'label' => 'Victims &amp; Culprits'],
	'league-honours' => ['href' => k2_route('lb-league-honours'), 'label' => 'League honours'],
	'milestones' => ['href' => k2_route('lb-milestones'), 'label' => 'Milestones'],
	'activity-peaks' => ['href' => k2_route('lb-activity-peaks'), 'label' => 'Activity peaks'],
	'peak-rating' => ['href' => k2_route('lb-peak-rating'), 'label' => 'Peak rating'],
];
?>
<div class="k2-chrome-tabs">
	<nav class="k2-chrome-tabs__bar" data-k2-carry-scroll aria-label="Leaderboard view">
<?php foreach ($k2LbWingTabs as $id => $tab) {
	$tabQs = ($id === 'activity-peaks') ? '' : $k2LbFilterQs;
?>
		<a href="<?php echo htmlspecialchars($tab['href'] . $tabQs, ENT_QUOTES, 'UTF-8'); ?>" class="k2-chrome-tabs__tab<?php echo $k2LbWingActive === $id ? ' is-active' : ''; ?>"><?php echo $tab['label']; ?></a>
<?php } ?>
<?php if ($k2LbShowFilters) { ?>
		<div class="k2-chrome-tabs__filters" role="group" aria-label="Leaderboard filters">
			<a href="<?php echo htmlspecialchars(k2_lb_filter_toggle_href('inactive'), ENT_QUOTES, 'UTF-8'); ?>" class="k2-lb-filter<?php echo !empty($k2LbFilterOpts['include_inactive']) ? ' is-on' : ''; ?>" aria-pressed="<?php echo !empty($k2LbFilterOpts['include_inactive']) ? 'true' : 'false'; ?>">
				<span class="k2-lb-filter__dot" aria-hidden="true"></span>
				<span class="k2-lb-filter__label">Include inactive (+1 year)</span>
			</a>
			<a href="<?php echo htmlspecialchars(k2_lb_filter_toggle_href('provisional'), ENT_QUOTES, 'UTF-8'); ?>" class="k2-lb-filter<?php echo !empty($k2LbFilterOpts['include_provisional']) ? ' is-on' : ''; ?>" aria-pressed="<?php echo !empty($k2LbFilterOpts['include_provisional']) ? 'true' : 'false'; ?>">
				<span class="k2-lb-filter__dot" aria-hidden="true"></span>
				<span class="k2-lb-filter__label">Include provisional (&lt;<?php echo (int) k2_established_min_games(); ?> games)</span>
			</a>
		</div>
<?php } ?>
	</nav>
</div>
