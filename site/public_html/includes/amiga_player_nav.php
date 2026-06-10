<?php
/**
 * Amiga player pills — Profile · Games (v0).
 * Set $k2AmigaPlayerTabActive and $id before include.
 */
declare(strict_types=1);

$k2AmigaPlayerTabActive = $k2AmigaPlayerTabActive ?? 'profile';
$id = isset($id) ? (int) $id : 0;
$k2AmigaPlayerTabs = [
    'profile' => ['href' => '/amiga/profile.php?id=' . $id, 'label' => 'Profile'],
    'tournaments' => ['href' => '/amiga/player-tournaments.php?id=' . $id, 'label' => 'Tournaments'],
    'games' => ['href' => '/amiga/games.php?id=' . $id, 'label' => 'Games'],
];
?>
<div class="k2-player-nav-bar">
	<nav class="k2-player-nav k2-nav-pills" data-k2-carry-scroll aria-label="Player sections">
		<div class="k2-player-nav__links">
<?php foreach ($k2AmigaPlayerTabs as $tabId => $tab) { ?>
			<a href="<?php echo htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8'); ?>" class="k2-player-nav__btn<?php echo $k2AmigaPlayerTabActive === $tabId ? ' is-active' : ''; ?>"><?php echo htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8'); ?></a>
<?php } ?>
		</div>
		<div class="k2-player-nav__tune k2-nav-tune">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_tint_picker.php'; ?>
		</div>
	</nav>
</div>
<script type="text/javascript" src="/js/k2-tint-toggle.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-tint-toggle.js'); ?>" defer="defer"></script>
