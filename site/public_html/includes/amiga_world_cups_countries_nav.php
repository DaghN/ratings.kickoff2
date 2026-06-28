<?php
/**
 * World Cups hub — Country stats inner segments.
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_amiga_routes.php';
require_once __DIR__ . '/amiga_snapshot_url.php';

$k2AmigaWorldCupsCountriesView = $k2AmigaWorldCupsCountriesView ?? 'honours';

$k2AmigaWorldCupsCountriesTabs = [
    'honours' => [
        'href' => amiga_url_with_context(k2_amiga_route('amiga-world-cups-countries-honours')),
        'label' => 'Honours',
    ],
    'results' => [
        'href' => amiga_url_with_context(k2_amiga_route('amiga-world-cups-countries-results')),
        'label' => 'Results',
    ],
    'participation' => [
        'href' => amiga_url_with_context(k2_amiga_route('amiga-world-cups-countries-participation')),
        'label' => 'Participation',
    ],
    'goals' => [
        'href' => amiga_url_with_context(k2_amiga_route('amiga-world-cups-countries-goals')),
        'label' => 'Goals',
    ],
    'dds' => [
        'href' => amiga_url_with_context(k2_amiga_route('amiga-world-cups-countries-dds')),
        'label' => 'DDs & CSs',
    ],
    'opponents' => [
        'href' => amiga_url_with_context(k2_amiga_route('amiga-world-cups-countries-opponents')),
        'label' => 'Opponents',
    ],
];
?>
<div class="k2-chrome-tabs k2-amiga-world-cups-countries-tabs">
	<nav class="k2-chrome-tabs__bar" data-k2-carry-scroll aria-label="World Cup country stats">
<?php foreach ($k2AmigaWorldCupsCountriesTabs as $viewId => $tab) {
    $isActive = $k2AmigaWorldCupsCountriesView === $viewId;
    ?>
		<a href="<?php echo htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8'); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo $tab['label']; ?></a>
<?php } ?>
	</nav>
</div>
