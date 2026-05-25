<?php
/**
 * Leaderboard wing tabs — segment track with outline active cell.
 * Set $k2LbWingActive before include: results | hall-of-fame | goals | dds | streaks | victims | rating
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_player_filters.php';

$k2LbWingActive = $k2LbWingActive ?? 'rating';
$k2LbShowFilters = $k2LbWingActive !== 'hall-of-fame';
$k2LbFilterOpts = k2_lb_filter_opts();
$k2LbFilterQs = k2_lb_filter_query_string($k2LbFilterOpts);
$k2LbWingTabs = [
	'results' => ['href' => 'ranked7.php', 'label' => 'Rating'],
	'goals' => ['href' => 'ranked2.php', 'label' => 'Goals'],
	'dds' => ['href' => 'ranked3.php', 'label' => 'DDs &amp; CSs'],
	'streaks' => ['href' => 'ranked4.php', 'label' => 'Streaks'],
	'victims' => ['href' => 'ranked5.php', 'label' => 'Victims &amp; Culprits'],
	'rating' => ['href' => 'ranked1.php', 'label' => 'Rating records'],
	'hall-of-fame' => ['href' => 'ranked8.php', 'label' => 'Hall of Fame'],
];
?>
<div class="k2-chrome-tabs">
	<nav class="k2-chrome-tabs__bar" aria-label="Leaderboard view">
<?php foreach ($k2LbWingTabs as $id => $tab) {
	$tabQs = ($id === 'hall-of-fame') ? '' : $k2LbFilterQs;
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
				<span class="k2-lb-filter__label">Include provisional (&lt;20 games)</span>
			</a>
		</div>
<?php } ?>
	</nav>
