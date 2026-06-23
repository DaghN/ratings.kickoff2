<?php
/**
 * World Cups hub — Tournament stats inner segments.
 *
 * Set $k2AmigaWorldCupsStatsView before include: goals | dds | participation | geography | podium
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_amiga_routes.php';

$k2AmigaWorldCupsStatsView = $k2AmigaWorldCupsStatsView ?? 'goals';

$k2AmigaWorldCupsStatsTabs = [
    'goals' => [
        'href' => k2_amiga_route('amiga-world-cups-stats-goals'),
        'label' => 'Goals',
    ],
    'dds' => [
        'href' => k2_amiga_route('amiga-world-cups-stats-dds'),
        'label' => 'DDs &amp; CSs',
    ],
    'participation' => [
        'href' => k2_amiga_route('amiga-world-cups-stats-participation'),
        'label' => 'Participation',
    ],
    'geography' => [
        'href' => k2_amiga_route('amiga-world-cups-stats-geography'),
        'label' => 'Geography',
    ],
    'podium' => [
        'href' => k2_amiga_route('amiga-world-cups-stats-podium'),
        'label' => 'Podium',
    ],
];
?>
<div class="k2-chrome-tabs k2-amiga-world-cups-stats-tabs">
	<nav class="k2-chrome-tabs__bar" data-k2-carry-scroll aria-label="World Cup tournament stats">
<?php foreach ($k2AmigaWorldCupsStatsTabs as $viewId => $tab) {
    $isActive = $k2AmigaWorldCupsStatsView === $viewId;
    ?>
		<a href="<?php echo htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8'); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo $tab['label']; ?></a>
<?php } ?>
	</nav>
</div>
