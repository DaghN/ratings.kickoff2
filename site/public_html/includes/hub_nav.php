<?php
/**
 * Hub primary tabs — Status · Leaderboards · Games · Trends · Records
 * Set $k2HubTabActive before include: status | leaderboards | games | trends | records
 */
$k2HubTabActive = $k2HubTabActive ?? '';
$k2HubTabs = [
	'status' => ['href' => 'status.php', 'label' => 'Status'],
	'leaderboards' => ['href' => 'ranked7.php', 'label' => 'Leaderboards'],
	'games' => ['href' => 'server3.php', 'label' => 'Games'],
	'trends' => ['href' => 'server1.php', 'label' => 'Trends'],
	'records' => ['href' => 'server2.php', 'label' => 'Records'],
];
$k2AccentPills = [
	'chrome' => ['label' => 'Chrome', 'title' => 'Rain-slick chrome blue — LA night reflections'],
	'signal' => ['label' => 'Signal', 'title' => 'Holo cyan — comms readout / Tyrell UI'],
	'lagoon' => ['label' => 'Lagoon', 'title' => 'Deep teal — off-world sea glow'],
	'phosphor' => ['label' => 'Phosphor', 'title' => 'Terminal lime — CRT phosphor trace'],
	'pulse' => ['label' => 'Pulse', 'title' => 'Neon magenta — club sign pulse'],
	'holo' => ['label' => 'Holo', 'title' => 'Violet hologram — advert shimmer'],
	'ember' => ['label' => 'Ember', 'title' => 'Warm coral neon — tail-light ember'],
];
?>
<div class="k2-hub-bar">
	<nav class="k2-hub-tabs k2-nav-pills" aria-label="Online hub">
<?php foreach ($k2HubTabs as $id => $tab) { ?>
		<a href="<?php echo $tab['href']; ?>" class="k2-hub-tabs__btn<?php echo $k2HubTabActive === $id ? ' is-active' : ''; ?>"><?php echo $tab['label']; ?></a>
<?php } ?>
	</nav>
	<nav class="k2-accent-pills" aria-label="Accent preview">
<?php foreach ($k2AccentPills as $id => $pill) { ?>
		<button type="button" class="k2-accent-pills__btn" data-k2-accent="<?php echo $id; ?>" title="<?php echo htmlspecialchars($pill['title'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo $pill['label']; ?></button>
<?php } ?>
	</nav>
</div>
