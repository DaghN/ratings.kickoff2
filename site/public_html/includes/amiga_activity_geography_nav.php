<?php
/**
 * Activity hub — Geography inner segments (Host nations · Nationalities).
 *
 * Set $k2AmigaActivityGeographyView before include: hosts | nations
 *
 * @see docs/amiga-activity-charts-policy.md §3
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_amiga_routes.php';

$k2AmigaActivityGeographyView = $k2AmigaActivityGeographyView ?? 'hosts';

$k2AmigaActivityGeographyTabs = [
    'hosts' => [
        'href' => k2_amiga_route('amiga-activity-geography-hosts'),
        'label' => 'Host nations',
    ],
    'nations' => [
        'href' => k2_amiga_route('amiga-activity-geography-nations'),
        'label' => 'Nationalities',
    ],
];
?>
<div class="k2-chrome-tabs k2-amiga-activity-geography-tabs">
	<nav class="k2-chrome-tabs__bar" data-k2-carry-scroll aria-label="Activity geography">
<?php foreach ($k2AmigaActivityGeographyTabs as $viewId => $tab) {
    $isActive = $k2AmigaActivityGeographyView === $viewId;
    ?>
		<a href="<?php echo htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8'); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo $tab['label']; ?></a>
<?php } ?>
	</nav>
</div>