<?php
/**
 * Player feast pills — Profile · Games · Wins · Goals · DDs
 * Set $k2PlayerTabActive and $id before include.
 */
$k2PlayerTabActive = $k2PlayerTabActive ?? 'profile';
$id = isset($id) ? (int) $id : 0;
$k2PlayerTabs = [
	'profile' => ['href' => 'individual1.php?id=' . $id, 'label' => 'Profile'],
	'games' => ['href' => 'individual3.php?id=' . $id, 'label' => 'Games'],
	'wins' => ['href' => 'individual2a.php?id=' . $id, 'label' => 'Wins'],
	'goals' => ['href' => 'individual2b.php?id=' . $id, 'label' => 'Goals'],
	'dds' => ['href' => 'individual2c.php?id=' . $id, 'label' => 'DDs'],
];
?>
<nav class="k2-player-nav k2-nav-pills" aria-label="Player sections">
<?php foreach ($k2PlayerTabs as $tabId => $tab) { ?>
	<a href="<?php echo $tab['href']; ?>" class="k2-player-nav__btn<?php echo $k2PlayerTabActive === $tabId ? ' is-active' : ''; ?>"><?php echo $tab['label']; ?></a>
<?php } ?>
</nav>
