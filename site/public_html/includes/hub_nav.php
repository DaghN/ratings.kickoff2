<?php
/**
 * Hub primary tabs — Status · Leaderboards · Activity · Games · Records
 * Set $k2HubTabActive before include: status | leaderboards | activity | games | records
 *
 * Hub nav style: segment default; ?k2_hub_nav= override (theme_boot_head.php).
 * Tint picker: Amber · Pitch · Chrome · Pulse · Holo — hidden by default; Show/Hide tint.
 */
$k2HubTabActive = $k2HubTabActive ?? '';
$k2HubTabs = [
	'status' => ['href' => 'status.php', 'label' => 'Status'],
	'leaderboards' => ['href' => 'ranked7.php', 'label' => 'Leaderboards'],
	'activity' => ['href' => 'server1.php', 'label' => 'Activity'],
	'games' => ['href' => 'server3.php', 'label' => 'Games'],
	'records' => ['href' => 'server2.php', 'label' => 'Records'],
];
$k2AccentPills = [
	'amber' => ['label' => 'Amber', 'title' => 'Warm amber — default site tint'],
	'pitch' => ['label' => 'Pitch', 'title' => 'Pitch green — chart / Amiga-era accent'],
	'chrome' => ['label' => 'Chrome', 'title' => 'Rain-slick chrome blue — LA night reflections'],
	'pulse' => ['label' => 'Pulse', 'title' => 'Neon magenta — club sign pulse'],
	'holo' => ['label' => 'Holo', 'title' => 'Violet hologram — advert shimmer'],
];
?>
<div class="k2-hub-bar">
	<nav class="k2-hub-tabs k2-nav-pills" aria-label="Online hub">
		<div class="k2-hub-tabs__links">
<?php foreach ($k2HubTabs as $id => $tab) { ?>
			<a href="<?php echo $tab['href']; ?>" class="k2-hub-tabs__btn<?php echo $k2HubTabActive === $id ? ' is-active' : ''; ?>"><?php echo $tab['label']; ?></a>
<?php } ?>
		</div>
		<div class="k2-hub-tabs__tune">
			<nav class="k2-accent-pills" aria-label="Tint">
<?php foreach ($k2AccentPills as $id => $pill) { ?>
				<button type="button" class="k2-accent-pills__btn" data-k2-accent="<?php echo $id; ?>" title="<?php echo htmlspecialchars($pill['title'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo $pill['label']; ?></button>
<?php } ?>
			</nav>
			<button type="button" class="k2-accent-pills-toggle" aria-pressed="false" title="Hide tint picker">Hide tint</button>
		</div>
	</nav>
</div>
<script type="text/javascript" src="js/k2-hub-nav-tune.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-hub-nav-tune.js'); ?>" defer="defer"></script>
