<?php
/**
 * World Cups hub — Player stats inner segments (Honours · Results · Goals).
 *
 * Set $k2AmigaWorldCupsPlayersView before include: honours | results | goals
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_amiga_routes.php';

$k2AmigaWorldCupsPlayersView = $k2AmigaWorldCupsPlayersView ?? 'honours';

$k2AmigaWorldCupsPlayersTabs = [
    'honours' => [
        'href' => k2_amiga_route('amiga-world-cups-players-honours'),
        'label' => 'Honours',
    ],
    'results' => [
        'href' => k2_amiga_route('amiga-world-cups-players-results'),
        'label' => 'Results',
    ],
    'goals' => [
        'href' => k2_amiga_route('amiga-world-cups-players-goals'),
        'label' => 'Goals',
    ],
];
?>
<div class="k2-chrome-tabs k2-amiga-world-cups-players-tabs">
	<nav class="k2-chrome-tabs__bar" data-k2-carry-scroll aria-label="World Cup player stats">
<?php foreach ($k2AmigaWorldCupsPlayersTabs as $viewId => $tab) {
    $isActive = $k2AmigaWorldCupsPlayersView === $viewId;
    ?>
		<a href="<?php echo htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8'); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo $tab['label']; ?></a>
<?php } ?>
	</nav>
</div>
