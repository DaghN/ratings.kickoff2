<?php
/**
 * Amiga realm hub tabs — Ladder · Tournaments · Hall of Fame.
 *
 * Set $k2AmigaHubTabActive before include: ladder | tournaments | hall-of-fame
 *
 * Leaderboards wing nav (/amiga/leaderboards/*) ships in a later phase — Rating wing
 * lives under Ladder for now (see docs/amiga-realm-vision.md).
 *
 * Tint picker matches online hub (realm-neutral). Peer pill scroll: data-k2-carry-scroll.
 */
$k2AmigaHubTabActive = $k2AmigaHubTabActive ?? '';

$k2AmigaHubTabs = [
	'ladder' => ['href' => '/amiga/rating.php', 'label' => 'Ladder'],
	'tournaments' => ['href' => '/amiga/tournaments.php', 'label' => 'Tournaments'],
	'hall-of-fame' => ['href' => '/amiga/hall-of-fame.php', 'label' => 'Hall of Fame'],
];

include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_accent_pills.inc.php';
?>
<div class="k2-hub-bar">
	<nav class="k2-hub-tabs k2-nav-pills" data-k2-carry-scroll aria-label="Amiga 500 hub">
		<div class="k2-hub-tabs__links">
<?php foreach ($k2AmigaHubTabs as $id => $tab) {
	$hrefEsc = htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8');
	$labelEsc = htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8');
	$activeClass = $k2AmigaHubTabActive === $id ? ' is-active' : '';
?>
			<a href="<?php echo $hrefEsc; ?>" class="k2-hub-tabs__btn<?php echo $activeClass; ?>"><?php echo $labelEsc; ?></a>
<?php } ?>
		</div>
		<div class="k2-hub-tabs__tune k2-nav-tune">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_tint_picker.php'; ?>
		</div>
	</nav>
</div>
<script type="text/javascript" src="/js/k2-tint-toggle.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-tint-toggle.js'); ?>" defer="defer"></script>
