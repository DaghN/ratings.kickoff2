<?php
/**
 * World Cups LB inner segments — Honours · Results · Goals · DDs & CSs · Opponents.
 * Set $k2AmigaWcLbView before include: honours | results | goals | dds | opponents
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_amiga_routes.php';
require_once __DIR__ . '/amiga_snapshot_url.php';

$k2AmigaWcLbView = $k2AmigaWcLbView ?? 'honours';

$k2AmigaWcLbTabs = [
    'honours' => [
        'href' => amiga_url_with_context(k2_amiga_route('amiga-lb-world-cups-honours')),
        'label' => 'Honours',
    ],
    'results' => [
        'href' => amiga_url_with_context(k2_amiga_route('amiga-lb-world-cups-results')),
        'label' => 'Results',
    ],
    'goals' => [
        'href' => amiga_url_with_context(k2_amiga_route('amiga-lb-world-cups-goals')),
        'label' => 'Goals',
    ],
    'dds' => [
        'href' => amiga_url_with_context(k2_amiga_route('amiga-lb-world-cups-dds')),
        'label' => 'DDs & CSs',
    ],
    'opponents' => [
        'href' => amiga_url_with_context(k2_amiga_route('amiga-lb-world-cups-opponents')),
        'label' => 'Opponents',
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
