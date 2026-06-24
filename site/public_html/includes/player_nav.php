<?php
/**
 * Player feast pills — Profile · Opponents · Milestones · Games
 * Set $k2PlayerTabActive and $id before include.
 * Tint picker: hub bar via player_wing_hub_nav.inc.php (not here).
 */
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
<div class="k2-player-nav-bar">
	<nav class="k2-player-nav k2-nav-pills" data-k2-carry-scroll aria-label="Player sections">
		<div class="k2-player-nav__links">
<?php foreach ($k2PlayerTabs as $tabId => $tab) { ?>
			<a href="<?php echo $tab['href']; ?>" class="k2-player-nav__btn<?php echo $k2PlayerTabActive === $tabId ? ' is-active' : ''; ?>"><?php echo $tab['label']; ?></a>
<?php } ?>
		</div>
	</nav>
</div>
