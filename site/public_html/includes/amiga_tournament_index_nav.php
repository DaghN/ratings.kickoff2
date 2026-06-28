<?php
/**
 * Amiga tournaments index — filter segment bars.
 *
 * Set $k2AmigaTournamentIndexWcFilter before include: '' | world-cup | not-world-cup
 * Set $k2AmigaTournamentIndexFilter before include: '' | league | cup | league-cup
 * Set $k2AmigaTournamentIndexVideosFilter before include: '' | with-videos
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_tournament_lib.php';

$k2AmigaTournamentIndexWcFilter = $k2AmigaTournamentIndexWcFilter ?? '';
$k2AmigaTournamentIndexFilter = $k2AmigaTournamentIndexFilter ?? '';
$k2AmigaTournamentIndexVideosFilter = $k2AmigaTournamentIndexVideosFilter ?? '';

$k2AmigaTournamentIndexWcTabs = [
    '' => ['href' => amiga_tournament_index_filter_url($k2AmigaTournamentIndexFilter, $k2AmigaTournamentIndexVideosFilter, ''), 'label' => 'All'],
    'world-cup' => ['href' => amiga_tournament_index_filter_url($k2AmigaTournamentIndexFilter, $k2AmigaTournamentIndexVideosFilter, 'world-cup'), 'label' => 'World Cups'],
    'not-world-cup' => ['href' => amiga_tournament_index_filter_url($k2AmigaTournamentIndexFilter, $k2AmigaTournamentIndexVideosFilter, 'not-world-cup'), 'label' => 'Not World Cups'],
];

$k2AmigaTournamentIndexTabs = [
    '' => ['href' => amiga_tournament_index_filter_url('', $k2AmigaTournamentIndexVideosFilter, $k2AmigaTournamentIndexWcFilter), 'label' => 'All'],
    'league' => ['href' => amiga_tournament_index_filter_url('league', $k2AmigaTournamentIndexVideosFilter, $k2AmigaTournamentIndexWcFilter), 'label' => 'Leagues'],
    'cup' => ['href' => amiga_tournament_index_filter_url('cup', $k2AmigaTournamentIndexVideosFilter, $k2AmigaTournamentIndexWcFilter), 'label' => 'Cups'],
    'league-cup' => ['href' => amiga_tournament_index_filter_url('league-cup', $k2AmigaTournamentIndexVideosFilter, $k2AmigaTournamentIndexWcFilter), 'label' => 'League + cup'],
];

$k2AmigaTournamentIndexVideosTabs = [
    '' => ['href' => amiga_tournament_index_filter_url($k2AmigaTournamentIndexFilter, '', $k2AmigaTournamentIndexWcFilter), 'label' => 'All'],
    'with-videos' => ['href' => amiga_tournament_index_filter_url($k2AmigaTournamentIndexFilter, 'with-videos', $k2AmigaTournamentIndexWcFilter), 'label' => 'With videos'],
];
?>
<div class="k2-chrome-tabs k2-amiga-tournament-index-tabs">
	<nav class="k2-chrome-tabs__bar" data-k2-carry-scroll aria-label="Filter by World Cup">
<?php foreach ($k2AmigaTournamentIndexWcTabs as $filterId => $tab) {
    $isActive = $k2AmigaTournamentIndexWcFilter === $filterId;
    ?>
		<a href="<?php echo htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8'); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo $tab['label']; ?></a>
<?php } ?>
	</nav>
</div>
<div class="k2-chrome-tabs k2-amiga-tournament-index-tabs k2-amiga-tournament-index-tabs--stacked">
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
<div class="k2-chrome-tabs k2-amiga-tournament-index-tabs k2-amiga-tournament-index-tabs--stacked">
	<nav class="k2-chrome-tabs__bar k2-chrome-tabs__bar--compact" data-k2-carry-scroll aria-label="Filter by videos">
<?php foreach ($k2AmigaTournamentIndexVideosTabs as $filterId => $tab) {
    $isActive = $k2AmigaTournamentIndexVideosFilter === $filterId;
    ?>
		<a href="<?php echo htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8'); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo $tab['label']; ?></a>
<?php } ?>
	</nav>
</div>
