<?php
/**
 * Activity hub wing navigation — Growth · People · Geography · World Cups · Texture · Shape.
 *
 * Set $k2AmigaActivityWingView before include: growth | people | geography | world-cups | texture | shape
 *
 * @see docs/amiga-activity-charts-policy.md §3
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_amiga_routes.php';

$k2AmigaActivityWingView = $k2AmigaActivityWingView ?? 'growth';

$k2AmigaActivityWingTabs = [
    'growth' => [
        'href' => k2_amiga_route('amiga-activity-growth'),
        'label' => 'Growth',
    ],
    'people' => [
        'href' => k2_amiga_route('amiga-activity-people'),
        'label' => 'People',
    ],
    'geography' => [
        'href' => k2_amiga_route('amiga-activity-geography'),
        'label' => 'Geography',
    ],
    'world-cups' => [
        'href' => k2_amiga_route('amiga-activity-world-cups'),
        'label' => 'World Cups',
    ],
    'texture' => [
        'href' => k2_amiga_route('amiga-activity-texture'),
        'label' => 'Texture',
    ],
    'shape' => [
        'href' => k2_amiga_route('amiga-activity-shape'),
        'label' => 'Shape',
    ],
];
?>
<div class="k2-chrome-tabs k2-amiga-activity-hub-tabs">
	<nav class="k2-chrome-tabs__bar" data-k2-carry-scroll aria-label="Activity">
<?php foreach ($k2AmigaActivityWingTabs as $viewId => $tab) {
    $isActive = $k2AmigaActivityWingView === $viewId;
    ?>
		<a href="<?php echo htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8'); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo $tab['label']; ?></a>
<?php } ?>
	</nav>
</div>