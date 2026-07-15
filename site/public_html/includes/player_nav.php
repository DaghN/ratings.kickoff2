<?php
/**
 * Player feast pills — Profile · Opponents · Milestones · Games
 * Set $k2PlayerTabActive and $id before include.
 * Segment bar: .k2-chrome-tabs.k2-player-wing-tabs (tint picker on hub bar only).
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_opponents_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_milestones_lib.php';

$k2PlayerTabActive = $k2PlayerTabActive ?? 'profile';
$id = isset($id) ? (int) $id : 0;
$k2PlayerTabs = [
	'profile' => ['href' => k2_route('player-profile', ['id' => $id]), 'label' => 'Profile'],
	'opponents' => ['href' => player_opponents_default_href($id), 'label' => 'Opponents'],
	'milestones' => ['href' => player_milestones_default_href($id), 'label' => 'Milestones'],
	'games' => ['href' => k2_route('player-games', ['id' => $id]), 'label' => 'Games'],
];
?>
<div id="<?php echo k2_h(K2_PLAYER_WING_NAV_ANCHOR); ?>" class="k2-player-wing-nav-anchor" tabindex="-1"></div>
<div class="k2-chrome-tabs k2-player-wing-tabs">
	<nav class="k2-chrome-tabs__bar" data-k2-carry-scroll aria-label="Player sections">
<?php foreach ($k2PlayerTabs as $tabId => $tab) {
	$isActive = $k2PlayerTabActive === $tabId;
	?>
		<a href="<?php echo htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8'); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo $tab['label']; ?></a>
<?php } ?>
	</nav>
</div>
