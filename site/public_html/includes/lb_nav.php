<?php
/**
 * Leaderboard wing tabs — segment track with outline active cell.
 * Set $k2LbWingActive before include: rating | results | goals | dds | streaks | victims
 */
$k2LbWingActive = $k2LbWingActive ?? 'rating';
$k2LbWingTabs = [
	'results' => ['href' => 'ranked7.php', 'label' => 'Results'],
	'goals' => ['href' => 'ranked2.php', 'label' => 'Goals'],
	'dds' => ['href' => 'ranked3.php', 'label' => 'DDs &amp; CSs'],
	'streaks' => ['href' => 'ranked4.php', 'label' => 'Streaks'],
	'victims' => ['href' => 'ranked5.php', 'label' => 'Victims &amp; Culprits'],
	'rating' => ['href' => 'ranked1.php', 'label' => 'Rating records'],
];
?>
<div class="k2-chrome-tabs">
	<nav class="k2-chrome-tabs__bar" aria-label="Leaderboard view">
<?php foreach ($k2LbWingTabs as $id => $tab) { ?>
		<a href="<?php echo $tab['href']; ?>" class="k2-chrome-tabs__tab<?php echo $k2LbWingActive === $id ? ' is-active' : ''; ?>"><?php echo $tab['label']; ?></a>
<?php } ?>
	</nav>
