<?php
/**
 * Hub primary tabs — Status · Leaderboards · Games · Trends · Records
 * Set $k2HubTabActive before include: status | leaderboards | games | trends | records
 */
$k2HubTabActive = $k2HubTabActive ?? '';
$k2HubTabs = [
	'status' => ['href' => 'status.php', 'label' => 'Status'],
	'leaderboards' => ['href' => 'ranked1.php', 'label' => 'Leaderboards'],
	'games' => ['href' => 'server3.php', 'label' => 'Games'],
	'trends' => ['href' => 'server1.php', 'label' => 'Trends'],
	'records' => ['href' => 'server2.php', 'label' => 'Records'],
];
?>
<nav class="k2-hub-tabs k2-nav-pills" aria-label="Online hub">
<?php foreach ($k2HubTabs as $id => $tab) { ?>
	<a href="<?php echo $tab['href']; ?>" class="k2-hub-tabs__btn<?php echo $k2HubTabActive === $id ? ' is-active' : ''; ?>"><?php echo $tab['label']; ?></a>
<?php } ?>
</nav>
