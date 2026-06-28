<?php
/**
 * Amiga Games hub sub-navigation — Recent · Highlights · All games.
 * Set $k2AmigaGamesHubView before include: recent | highlights | all.
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_amiga_routes.php';

$k2AmigaGamesHubView = $k2AmigaGamesHubView ?? 'recent';
$k2AmigaGamesHubTabs = [
    'recent' => ['href' => k2_amiga_route('amiga-games-recent'), 'label' => 'Recent'],
    'highlights' => ['href' => k2_amiga_route('amiga-games-highlights'), 'label' => 'Highlights'],
    'all' => ['href' => k2_amiga_route('amiga-games-all'), 'label' => 'All games'],
];
?>
<div class="k2-chrome-tabs k2-games-hub-tabs">
	<nav class="k2-chrome-tabs__bar" data-k2-carry-scroll aria-label="Games">
<?php foreach ($k2AmigaGamesHubTabs as $viewId => $tab) {
    $isActive = $k2AmigaGamesHubView === $viewId;
    ?>
		<a href="<?php echo htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8'); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo $tab['label']; ?></a>
<?php } ?>
	</nav>
</div>
