<?php
/**
 * Games area sub-navigation — Recent · Highlights · All games (Games hub tab).
 * Set $k2GamesHubView before include: recent | highlights | all.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_routes.php';

$k2GamesHubView = $k2GamesHubView ?? 'recent';
$k2GamesHubTabs = [
	'recent' => ['href' => k2_route('games-recent'), 'label' => 'Recent'],
	'highlights' => ['href' => k2_route('games-highlights'), 'label' => 'Highlights'],
	'all' => ['href' => k2_route('games-all'), 'label' => 'All games'],
];
?>
<div class="k2-chrome-tabs k2-games-hub-tabs">
	<nav class="k2-chrome-tabs__bar" data-k2-carry-scroll aria-label="Games">
<?php foreach ($k2GamesHubTabs as $viewId => $tab) {
	$isActive = $k2GamesHubView === $viewId;
	?>
		<a href="<?php echo htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8'); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo $tab['label']; ?></a>
<?php } ?>
	</nav>
</div>
