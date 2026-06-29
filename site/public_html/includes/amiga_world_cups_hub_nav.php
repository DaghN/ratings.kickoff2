<?php
/**
 * World Cups hub sub-navigation — Chronology · Player stats · Country stats · Tournament stats.
 *
 * Set $k2AmigaWorldCupsHubView before include: chronology | stats | players | countries
 *
 * @see docs/amiga-world-cups-hub-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_amiga_routes.php';

$k2AmigaWorldCupsHubView = $k2AmigaWorldCupsHubView ?? 'chronology';

$k2AmigaWorldCupsHubTabs = [
    'chronology' => [
        'href' => k2_amiga_route('amiga-world-cups-chronology'),
        'label' => 'Chronology',
    ],
    'players' => [
        'href' => k2_amiga_route('amiga-world-cups-players'),
        'label' => 'Player stats',
    ],
    'countries' => [
        'href' => k2_amiga_route('amiga-world-cups-countries'),
        'label' => 'Country stats',
    ],
    'stats' => [
        'href' => k2_amiga_route('amiga-world-cups-stats'),
        'label' => 'Tournament stats',
    ],
];
?>
<div class="k2-chrome-tabs k2-amiga-world-cups-hub-tabs">
	<nav class="k2-chrome-tabs__bar" data-k2-carry-scroll aria-label="World Cups">
<?php foreach ($k2AmigaWorldCupsHubTabs as $viewId => $tab) {
    $isActive = $k2AmigaWorldCupsHubView === $viewId;
    ?>
		<a href="<?php echo htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8'); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo $tab['label']; ?></a>
<?php } ?>
	</nav>
</div>
