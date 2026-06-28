<?php
/**
 * Amiga tournaments index — filter segment bars + listbox row.
 *
 * Set $k2AmigaTournamentIndexWcFilter before include: '' | world-cup | not-world-cup
 * Set $k2AmigaTournamentIndexFilter before include: '' | league | cup | league-cup
 * Set $k2AmigaTournamentIndexVideosFilter before include: '' | with-videos
 * Set $k2AmigaTournamentIndexCountryFilter before include: '' | host country name
 * Set $k2AmigaTournamentIndexYearFilter before include: 0 | calendar year
 * Set $k2AmigaTournamentIndexCountryChoices / $k2AmigaTournamentIndexYearChoices (listbox rows)
 * Set $k2AmigaTournamentIndexShowCountryFilter / $k2AmigaTournamentIndexShowYearFilter (bool)
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_tournament_lib.php';
require_once __DIR__ . '/k2_archive_listbox.php';
require_once __DIR__ . '/k2_table_helpers.php';

$k2AmigaTournamentIndexWcFilter = $k2AmigaTournamentIndexWcFilter ?? '';
$k2AmigaTournamentIndexFilter = $k2AmigaTournamentIndexFilter ?? '';
$k2AmigaTournamentIndexVideosFilter = $k2AmigaTournamentIndexVideosFilter ?? '';
$k2AmigaTournamentIndexCountryFilter = $k2AmigaTournamentIndexCountryFilter ?? '';
$k2AmigaTournamentIndexYearFilter = (int) ($k2AmigaTournamentIndexYearFilter ?? 0);
$k2AmigaTournamentIndexCountryChoices = $k2AmigaTournamentIndexCountryChoices ?? [['value' => '', 'label' => '', 'meta' => '']];
$k2AmigaTournamentIndexYearChoices = $k2AmigaTournamentIndexYearChoices ?? [['value' => '0', 'label' => '', 'meta' => '']];
$k2AmigaTournamentIndexShowCountryFilter = !empty($k2AmigaTournamentIndexShowCountryFilter);
$k2AmigaTournamentIndexShowYearFilter = !empty($k2AmigaTournamentIndexShowYearFilter);

$k2AmigaTournamentIndexWcTabs = [
    '' => ['href' => amiga_tournament_index_filter_url($k2AmigaTournamentIndexFilter, $k2AmigaTournamentIndexVideosFilter, '', $k2AmigaTournamentIndexCountryFilter, $k2AmigaTournamentIndexYearFilter), 'label' => 'All'],
    'world-cup' => ['href' => amiga_tournament_index_filter_url($k2AmigaTournamentIndexFilter, $k2AmigaTournamentIndexVideosFilter, 'world-cup', $k2AmigaTournamentIndexCountryFilter, $k2AmigaTournamentIndexYearFilter), 'label' => 'World Cups'],
    'not-world-cup' => ['href' => amiga_tournament_index_filter_url($k2AmigaTournamentIndexFilter, $k2AmigaTournamentIndexVideosFilter, 'not-world-cup', $k2AmigaTournamentIndexCountryFilter, $k2AmigaTournamentIndexYearFilter), 'label' => 'Not World Cups'],
];

$k2AmigaTournamentIndexTabs = [
    '' => ['href' => amiga_tournament_index_filter_url('', $k2AmigaTournamentIndexVideosFilter, $k2AmigaTournamentIndexWcFilter, $k2AmigaTournamentIndexCountryFilter, $k2AmigaTournamentIndexYearFilter), 'label' => 'All'],
    'league' => ['href' => amiga_tournament_index_filter_url('league', $k2AmigaTournamentIndexVideosFilter, $k2AmigaTournamentIndexWcFilter, $k2AmigaTournamentIndexCountryFilter, $k2AmigaTournamentIndexYearFilter), 'label' => 'Leagues'],
    'cup' => ['href' => amiga_tournament_index_filter_url('cup', $k2AmigaTournamentIndexVideosFilter, $k2AmigaTournamentIndexWcFilter, $k2AmigaTournamentIndexCountryFilter, $k2AmigaTournamentIndexYearFilter), 'label' => 'Cups'],
    'league-cup' => ['href' => amiga_tournament_index_filter_url('league-cup', $k2AmigaTournamentIndexVideosFilter, $k2AmigaTournamentIndexWcFilter, $k2AmigaTournamentIndexCountryFilter, $k2AmigaTournamentIndexYearFilter), 'label' => 'League + cup'],
];

$k2AmigaTournamentIndexVideosTabs = [
    '' => ['href' => amiga_tournament_index_filter_url($k2AmigaTournamentIndexFilter, '', $k2AmigaTournamentIndexWcFilter, $k2AmigaTournamentIndexCountryFilter, $k2AmigaTournamentIndexYearFilter), 'label' => 'All'],
    'with-videos' => ['href' => amiga_tournament_index_filter_url($k2AmigaTournamentIndexFilter, 'with-videos', $k2AmigaTournamentIndexWcFilter, $k2AmigaTournamentIndexCountryFilter, $k2AmigaTournamentIndexYearFilter), 'label' => 'With videos'],
];

$k2AmigaTournamentIndexSortParams = k2_table_sort_query_params();
?>
<div class="k2-player-games-filters k2-amiga-tournament-index-filters">
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
<?php if ($k2AmigaTournamentIndexShowCountryFilter || $k2AmigaTournamentIndexShowYearFilter) { ?>
<form class="k2-player-games-controls" method="get" action="/amiga/tournaments.php" data-k2-carry-scroll>
	<div class="k2-player-games-controls__meta">
<?php if ($k2AmigaTournamentIndexWcFilter !== '') { ?>
		<input type="hidden" name="wc" value="<?php echo htmlspecialchars($k2AmigaTournamentIndexWcFilter, ENT_QUOTES, 'UTF-8'); ?>" />
<?php } ?>
<?php if ($k2AmigaTournamentIndexFilter !== '') { ?>
		<input type="hidden" name="type" value="<?php echo htmlspecialchars($k2AmigaTournamentIndexFilter, ENT_QUOTES, 'UTF-8'); ?>" />
<?php } ?>
<?php if ($k2AmigaTournamentIndexVideosFilter !== '') { ?>
		<input type="hidden" name="videos" value="<?php echo htmlspecialchars($k2AmigaTournamentIndexVideosFilter, ENT_QUOTES, 'UTF-8'); ?>" />
<?php } ?>
<?php if (!$k2AmigaTournamentIndexShowCountryFilter && $k2AmigaTournamentIndexCountryFilter !== '') { ?>
		<input type="hidden" name="country" value="<?php echo htmlspecialchars($k2AmigaTournamentIndexCountryFilter, ENT_QUOTES, 'UTF-8'); ?>" />
<?php } ?>
<?php if (!$k2AmigaTournamentIndexShowYearFilter && $k2AmigaTournamentIndexYearFilter > 0) { ?>
		<input type="hidden" name="year" value="<?php echo (int) $k2AmigaTournamentIndexYearFilter; ?>" />
<?php } ?>
<?php foreach ($k2AmigaTournamentIndexSortParams as $sortKey => $sortValue) { ?>
		<input type="hidden" name="<?php echo htmlspecialchars((string) $sortKey, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars((string) $sortValue, ENT_QUOTES, 'UTF-8'); ?>" />
<?php } ?>
	</div>
	<div class="k2-amiga-player-games-filter-rows">
		<div class="k2-player-games-controls__fields k2-amiga-player-games-filter-row">
<?php if ($k2AmigaTournamentIndexShowCountryFilter) { ?>
			<div class="k2-player-games-controls__field">
				<span class="server-period-activity-leaderboard__picker-label">Host country</span>
				<?php k2_archive_listbox_render('country', 'k2-amiga-tournament-index-country', $k2AmigaTournamentIndexCountryFilter, $k2AmigaTournamentIndexCountryChoices, 'Filter by host country', '', '', false, ''); ?>
			</div>
<?php } ?>
<?php if ($k2AmigaTournamentIndexShowYearFilter) { ?>
			<div class="k2-player-games-controls__field">
				<span class="server-period-activity-leaderboard__picker-label">Year</span>
				<?php k2_archive_listbox_render('year', 'k2-amiga-tournament-index-year', (string) $k2AmigaTournamentIndexYearFilter, $k2AmigaTournamentIndexYearChoices, 'Tournaments from this calendar year', '', '', false, '0'); ?>
			</div>
<?php } ?>
		</div>
	</div>
</form>
<?php } ?>
</div>
