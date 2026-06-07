<?php
/**
 * Hub primary tabs — Status · Activity · Leaderboards · Milestones · Hall of Fame · Play & Setup
 * Set $k2HubTabActive before include: status | activity | leaderboards | milestones | hall-of-fame | join
 *
 * Full match log: games.php (linked from Status recent games — not a hub tab).
 * Tint picker: Tint disclosure — Amber · Pitch · Chrome · Holo (closed by default).
 * Peer pill clicks: data-k2-carry-scroll + js/k2-carry-scroll.js (keep window scrollY).
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_routes.php';

$k2HubTabActive = $k2HubTabActive ?? '';
$k2HubTabs = [
	'status' => ['href' => k2_route('status'), 'label' => 'Status'],
	'activity' => ['href' => k2_route('activity'), 'label' => 'Activity'],
	'leaderboards' => ['href' => k2_route('lb-rating'), 'label' => 'Leaderboards'],
	'milestones' => ['href' => k2_route('milestones'), 'label' => 'Milestones'],
	'hall-of-fame' => ['href' => k2_route('hall-of-fame'), 'label' => 'Hall of Fame'],
	'join' => ['href' => k2_route('join'), 'label' => 'Play & Setup'],
];
include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_accent_pills.inc.php';
?>
<div class="k2-hub-bar">
	<nav class="k2-hub-tabs k2-nav-pills" data-k2-carry-scroll aria-label="Online hub">
		<div class="k2-hub-tabs__links">
<?php foreach ($k2HubTabs as $hubTabId => $tab) { ?>
			<a href="<?php echo $tab['href']; ?>" class="k2-hub-tabs__btn<?php echo $k2HubTabActive === $hubTabId ? ' is-active' : ''; ?>"><?php echo $tab['label']; ?></a>
<?php } ?>
		</div>
		<div class="k2-hub-tabs__tune k2-nav-tune">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_tint_picker.php'; ?>
		</div>
	</nav>
</div>
<script type="text/javascript" src="/js/k2-tint-toggle.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-tint-toggle.js'); ?>" defer="defer"></script>
