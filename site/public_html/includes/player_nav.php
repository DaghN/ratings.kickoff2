<?php
/**
 * Player feast pills — Profile · Games · W/D/L · Goals · DDs
 * Set $k2PlayerTabActive and $id before include.
 * Tint picker on the right (parity with hub_nav.php).
 */
$k2PlayerTabActive = $k2PlayerTabActive ?? 'profile';
$id = isset($id) ? (int) $id : 0;
$k2PlayerTabs = [
	'profile' => ['href' => 'individual1.php?id=' . $id, 'label' => 'Profile'],
	'games' => ['href' => 'individual3.php?id=' . $id, 'label' => 'Games'],
	'wins' => ['href' => 'individual2a.php?id=' . $id, 'label' => 'W/D/L'],
	'goals' => ['href' => 'individual2b.php?id=' . $id, 'label' => 'Goals'],
	'dds' => ['href' => 'individual2c.php?id=' . $id, 'label' => 'DDs'],
];
?>
<div class="k2-player-nav-bar">
	<nav class="k2-player-nav k2-nav-pills" aria-label="Player sections">
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
<script type="text/javascript" src="js/k2-hub-nav-tune.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-hub-nav-tune.js'); ?>" defer="defer"></script>
