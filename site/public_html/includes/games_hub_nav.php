<?php
/**
 * Games area sub-navigation — Recent · Highlights (not a hub tab).
 * Set $k2GamesHubView before include: recent | highlights.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_routes.php';

$k2GamesHubView = $k2GamesHubView ?? 'recent';
$k2GamesHubTabs = [
	'recent' => ['href' => k2_route('games'), 'label' => 'Recent'],
	'highlights' => ['href' => k2_route('games', ['view' => 'highlights']), 'label' => 'Highlights'],
];
?>
<div class="k2-chrome-tabs k2-games-hub-tabs">
	<nav class="k2-chrome-tabs__bar" data-k2-carry-scroll aria-label="Games">
<?php foreach ($k2GamesHubTabs as $id => $tab) {
	$isActive = $k2GamesHubView === $id;
	?>
		<a href="<?php echo htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8'); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo $tab['label']; ?></a>
<?php } ?>
	</nav>
</div>
