<?php
/**
 * Amiga tournaments index — format filter segment bar (All · World Cups · Leagues · …).
 *
 * Set $k2AmigaTournamentIndexFilter before include: '' | world-cup | league | cup | league-cup
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_tournament_lib.php';

$k2AmigaTournamentIndexFilter = $k2AmigaTournamentIndexFilter ?? '';

$k2AmigaTournamentIndexTabs = [
    '' => ['href' => amiga_tournament_index_filter_url(), 'label' => 'All'],
    'world-cup' => ['href' => amiga_tournament_index_filter_url('world-cup'), 'label' => 'World Cups'],
    'league' => ['href' => amiga_tournament_index_filter_url('league'), 'label' => 'Leagues'],
    'cup' => ['href' => amiga_tournament_index_filter_url('cup'), 'label' => 'Cups'],
    'league-cup' => ['href' => amiga_tournament_index_filter_url('league-cup'), 'label' => 'League + cup'],
];
?>
<div class="k2-chrome-tabs k2-amiga-tournament-index-tabs">
	<nav class="k2-chrome-tabs__bar" data-k2-carry-scroll aria-label="Filter by format">
<?php foreach ($k2AmigaTournamentIndexTabs as $filterId => $tab) {
    $isActive = $k2AmigaTournamentIndexFilter === $filterId;
    ?>
		<a href="<?php echo htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8'); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo $tab['label']; ?></a>
<?php } ?>
	</nav>
</div>
