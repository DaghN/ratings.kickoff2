<?php
/**
 * Hub primary tabs — Status · Activity · Leaderboards · Milestones · Hall of Fame
 * Set $k2HubTabActive before include: status | activity | leaderboards | milestones | records
 *
 * Full match log: server3.php (linked from Status recent games — not a hub tab).
 * Tint picker: Amber · Pitch · Chrome · Holo — hidden by default; Show/Hide tint.
 * Peer pill clicks: data-k2-carry-scroll + js/k2-carry-scroll.js (keep window scrollY).
 */
$k2HubTabActive = $k2HubTabActive ?? '';
$k2HubTabs = [
	'status' => ['href' => 'status.php', 'label' => 'Status'],
	'activity' => ['href' => 'server1.php', 'label' => 'Activity'],
	'leaderboards' => ['href' => 'ranked7.php', 'label' => 'Leaderboards'],
	'milestones' => ['href' => 'milestones.php', 'label' => 'Milestones'],
	'records' => ['href' => 'server2.php', 'label' => 'Hall of Fame'],
];
include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_accent_pills.inc.php';
?>
<div class="k2-hub-bar">
	<nav class="k2-hub-tabs k2-nav-pills" data-k2-carry-scroll aria-label="Online hub">
		<div class="k2-hub-tabs__links">
<?php foreach ($k2HubTabs as $id => $tab) { ?>
			<a href="<?php echo $tab['href']; ?>" class="k2-hub-tabs__btn<?php echo $k2HubTabActive === $id ? ' is-active' : ''; ?>"><?php echo $tab['label']; ?></a>
<?php } ?>
		</div>
		<div class="k2-hub-tabs__tune k2-nav-tune">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_tint_picker.php'; ?>
		</div>
	</nav>
</div>
<script type="text/javascript" src="js/k2-tint-toggle.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-tint-toggle.js'); ?>" defer="defer"></script>
