<?php
/**
 * World Cups LB inner segments — Honours · Results · Goals.
 * Set $k2AmigaWcLbView before include: honours | results | goals
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_amiga_routes.php';

$k2AmigaWcLbView = $k2AmigaWcLbView ?? 'honours';

$k2AmigaWcLbTabs = [
    'honours' => [
        'href' => k2_amiga_route('amiga-lb-world-cups-honours'),
        'label' => 'Honours',
    ],
    'results' => [
        'href' => k2_amiga_route('amiga-lb-world-cups-results'),
        'label' => 'Results',
    ],
    'goals' => [
        'href' => k2_amiga_route('amiga-lb-world-cups-goals'),
        'label' => 'Goals',
    ],
];
?>
<div class="k2-chrome-tabs k2-lb-wc-tabs">
	<nav class="k2-chrome-tabs__bar" data-k2-carry-scroll aria-label="World Cups views">
<?php foreach ($k2AmigaWcLbTabs as $viewId => $tab) {
    $isActive = $k2AmigaWcLbView === $viewId;
    ?>
		<a href="<?php echo htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8'); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo $tab['label']; ?></a>
<?php } ?>
	</nav>
</div>
