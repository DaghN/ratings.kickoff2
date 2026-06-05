<?php
/**
 * Player feast pills — Profile · Games · W/D/L · Goals · DDs · Milestones
 * Set $k2PlayerTabActive and $id before include.
 * Tint picker on the right (parity with hub_nav.php).
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_routes.php';

$k2PlayerTabActive = $k2PlayerTabActive ?? 'profile';
$id = isset($id) ? (int) $id : 0;
$k2PlayerTabs = [
	'profile' => ['href' => k2_route('player-profile', ['id' => $id]), 'label' => 'Profile'],
	'games' => ['href' => k2_route('player-games', ['id' => $id]), 'label' => 'Games'],
	'wins' => ['href' => k2_route('player-wdl', ['id' => $id]), 'label' => 'W/D/L'],
	'goals' => ['href' => k2_route('player-goals', ['id' => $id]), 'label' => 'Goals'],
	'double-digits' => ['href' => k2_route('player-double-digits', ['id' => $id]), 'label' => 'DDs'],
	'milestones' => ['href' => k2_route('player-milestones', ['id' => $id]), 'label' => 'Milestones'],
];
?>
<div class="k2-player-nav-bar">
	<nav class="k2-player-nav k2-nav-pills" data-k2-carry-scroll aria-label="Player sections">
		<div class="k2-player-nav__links">
<?php foreach ($k2PlayerTabs as $tabId => $tab) { ?>
			<a href="<?php echo $tab['href']; ?>" class="k2-player-nav__btn<?php echo $k2PlayerTabActive === $tabId ? ' is-active' : ''; ?>"><?php echo $tab['label']; ?></a>
<?php } ?>
		</div>
		<div class="k2-player-nav__tune k2-nav-tune">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_tint_picker.php'; ?>
		</div>
	</nav>
</div>
<script type="text/javascript" src="/js/k2-tint-toggle.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-tint-toggle.js'); ?>" defer="defer"></script>
