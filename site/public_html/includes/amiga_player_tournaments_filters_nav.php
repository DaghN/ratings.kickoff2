<?php
/**
 * Amiga player tournament history — filter segment bars + listbox row.
 *
 * Set $k2PlayerTournamentsPlayerId (int)
 * Set $k2PlayerTournamentsEventFilter: 'all' | 'world-cup'
 * Set $k2PlayerTournamentsPerfectFilter: '' | with-participant
 * Set $k2PlayerTournamentsWinnerFilter: '' | with-win
 * Set $k2PlayerTournamentsPodiumFilter: '' | with-podium
 * Set $k2PlayerTournamentsCountryFilter: '' | host country name
 * Set $k2PlayerTournamentsYearFilter: 0 | calendar year
 * Set $k2PlayerTournamentsCountryChoices / $k2PlayerTournamentsYearChoices
 * Set $k2PlayerTournamentsShowCountryFilter / $k2PlayerTournamentsShowYearFilter (bool)
 * Set $k2PlayerTournamentsFilterAction (form action URL)
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_player_tournament_lib.php';
require_once __DIR__ . '/k2_archive_listbox.php';
require_once __DIR__ . '/k2_table_helpers.php';

$k2PlayerTournamentsPlayerId = (int) ($k2PlayerTournamentsPlayerId ?? 0);
$k2PlayerTournamentsEventFilter = $k2PlayerTournamentsEventFilter ?? 'all';
$k2PlayerTournamentsPerfectFilter = $k2PlayerTournamentsPerfectFilter ?? '';
$k2PlayerTournamentsWinnerFilter = $k2PlayerTournamentsWinnerFilter ?? '';
$k2PlayerTournamentsPodiumFilter = $k2PlayerTournamentsPodiumFilter ?? '';
$k2PlayerTournamentsCountryFilter = $k2PlayerTournamentsCountryFilter ?? '';
$k2PlayerTournamentsYearFilter = (int) ($k2PlayerTournamentsYearFilter ?? 0);
$k2PlayerTournamentsCountryChoices = $k2PlayerTournamentsCountryChoices ?? [['value' => '', 'label' => '', 'meta' => '']];
$k2PlayerTournamentsYearChoices = $k2PlayerTournamentsYearChoices ?? [['value' => '0', 'label' => '', 'meta' => '']];
$k2PlayerTournamentsShowCountryFilter = !empty($k2PlayerTournamentsShowCountryFilter);
$k2PlayerTournamentsShowYearFilter = !empty($k2PlayerTournamentsShowYearFilter);
$k2PlayerTournamentsFilterAction = (string) ($k2PlayerTournamentsFilterAction ?? '');

$k2PlayerTournamentsSegmentUrl = static function (
    string $eventFilter,
    string $perfectFilter,
    string $winnerFilter,
    string $podiumFilter
) use (
    $k2PlayerTournamentsPlayerId,
    $k2PlayerTournamentsCountryFilter,
    $k2PlayerTournamentsYearFilter
): string {
    return amiga_player_tournaments_filter_url(
        $k2PlayerTournamentsPlayerId,
        $eventFilter,
        $k2PlayerTournamentsCountryFilter,
        $k2PlayerTournamentsYearFilter,
        $perfectFilter,
        $winnerFilter,
        $podiumFilter
    );
};

$ef = $k2PlayerTournamentsEventFilter;
$pf = $k2PlayerTournamentsPerfectFilter;
$wf = $k2PlayerTournamentsWinnerFilter;
$pdm = $k2PlayerTournamentsPodiumFilter;

$k2PlayerTournamentsEventTabs = [
    'all' => [
        'href' => $k2PlayerTournamentsSegmentUrl('all', $pf, $wf, $pdm),
        'label' => 'All',
    ],
    'world-cup' => [
        'href' => $k2PlayerTournamentsSegmentUrl('world-cup', $pf, $wf, $pdm),
        'label' => 'World Cups',
    ],
];

$k2PlayerTournamentsPerfectTabs = [
    '' => [
        'href' => $k2PlayerTournamentsSegmentUrl($ef, '', $wf, $pdm),
        'label' => 'All',
    ],
    'with-participant' => [
        'href' => $k2PlayerTournamentsSegmentUrl($ef, 'with-participant', $wf, $pdm),
        'label' => 'Perfect run',
    ],
];

$k2PlayerTournamentsWinnerTabs = [
    '' => [
        'href' => $k2PlayerTournamentsSegmentUrl($ef, $pf, '', $pdm),
        'label' => 'All',
    ],
    'with-win' => [
        'href' => $k2PlayerTournamentsSegmentUrl($ef, $pf, 'with-win', $pdm),
        'label' => 'Wins',
    ],
];

$k2PlayerTournamentsPodiumTabs = [
    '' => [
        'href' => $k2PlayerTournamentsSegmentUrl($ef, $pf, $wf, ''),
        'label' => 'All',
    ],
    'with-podium' => [
        'href' => $k2PlayerTournamentsSegmentUrl($ef, $pf, $wf, 'with-podium'),
        'label' => 'Podiums',
    ],
];

$k2PlayerTournamentsSortParams = k2_table_sort_query_params();
?>
<div class="k2-player-games-filters k2-amiga-player-tournaments-filters">
<div class="k2-amiga-tournament-index-segment-filters k2-amiga-player-tournaments-segment-filters">
<div class="k2-chrome-tabs k2-amiga-player-tournaments-tabs">
	<nav class="k2-chrome-tabs__bar" data-k2-carry-scroll aria-label="Filter events">
<?php foreach ($k2PlayerTournamentsEventTabs as $filterId => $tab) {
    $isActive = $k2PlayerTournamentsEventFilter === $filterId;
    ?>
		<a href="<?php echo htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8'); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo $tab['label']; ?></a>
<?php } ?>
	</nav>
</div>
<div class="k2-chrome-tabs k2-amiga-player-tournaments-tabs">
	<nav class="k2-chrome-tabs__bar k2-chrome-tabs__bar--compact" data-k2-carry-scroll aria-label="Filter by perfect run">
<?php foreach ($k2PlayerTournamentsPerfectTabs as $filterId => $tab) {
    $isActive = $k2PlayerTournamentsPerfectFilter === $filterId;
    ?>
		<a href="<?php echo htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8'); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo $tab['label']; ?></a>
<?php } ?>
	</nav>
</div>
<div class="k2-chrome-tabs k2-amiga-player-tournaments-tabs">
	<nav class="k2-chrome-tabs__bar k2-chrome-tabs__bar--compact" data-k2-carry-scroll aria-label="Filter by wins">
<?php foreach ($k2PlayerTournamentsWinnerTabs as $filterId => $tab) {
    $isActive = $k2PlayerTournamentsWinnerFilter === $filterId;
    ?>
		<a href="<?php echo htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8'); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo $tab['label']; ?></a>
<?php } ?>
	</nav>
</div>
<div class="k2-chrome-tabs k2-amiga-player-tournaments-tabs">
	<nav class="k2-chrome-tabs__bar k2-chrome-tabs__bar--compact" data-k2-carry-scroll aria-label="Filter by podiums">
<?php foreach ($k2PlayerTournamentsPodiumTabs as $filterId => $tab) {
    $isActive = $k2PlayerTournamentsPodiumFilter === $filterId;
    ?>
		<a href="<?php echo htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8'); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo $tab['label']; ?></a>
<?php } ?>
	</nav>
</div>
</div>
<?php if ($k2PlayerTournamentsShowCountryFilter || $k2PlayerTournamentsShowYearFilter) { ?>
<form class="k2-player-games-controls" method="get" action="<?php echo htmlspecialchars($k2PlayerTournamentsFilterAction, ENT_QUOTES, 'UTF-8'); ?>" data-k2-carry-scroll>
	<div class="k2-player-games-controls__meta">
		<input type="hidden" name="id" value="<?php echo $k2PlayerTournamentsPlayerId; ?>" />
<?php if ($k2PlayerTournamentsEventFilter !== 'all') { ?>
		<input type="hidden" name="filter" value="<?php echo htmlspecialchars($k2PlayerTournamentsEventFilter, ENT_QUOTES, 'UTF-8'); ?>" />
<?php } ?>
<?php if ($k2PlayerTournamentsPerfectFilter !== '') { ?>
		<input type="hidden" name="perfect" value="<?php echo htmlspecialchars($k2PlayerTournamentsPerfectFilter, ENT_QUOTES, 'UTF-8'); ?>" />
<?php } ?>
<?php if ($k2PlayerTournamentsWinnerFilter !== '') { ?>
		<input type="hidden" name="winner" value="<?php echo htmlspecialchars($k2PlayerTournamentsWinnerFilter, ENT_QUOTES, 'UTF-8'); ?>" />
<?php } ?>
<?php if ($k2PlayerTournamentsPodiumFilter !== '') { ?>
		<input type="hidden" name="podium" value="<?php echo htmlspecialchars($k2PlayerTournamentsPodiumFilter, ENT_QUOTES, 'UTF-8'); ?>" />
<?php } ?>
<?php if (!$k2PlayerTournamentsShowCountryFilter && $k2PlayerTournamentsCountryFilter !== '') { ?>
		<input type="hidden" name="country" value="<?php echo htmlspecialchars($k2PlayerTournamentsCountryFilter, ENT_QUOTES, 'UTF-8'); ?>" />
<?php } ?>
<?php if (!$k2PlayerTournamentsShowYearFilter && $k2PlayerTournamentsYearFilter > 0) { ?>
		<input type="hidden" name="year" value="<?php echo (int) $k2PlayerTournamentsYearFilter; ?>" />
<?php } ?>
<?php foreach ($k2PlayerTournamentsSortParams as $sortKey => $sortValue) { ?>
		<input type="hidden" name="<?php echo htmlspecialchars((string) $sortKey, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars((string) $sortValue, ENT_QUOTES, 'UTF-8'); ?>" />
<?php } ?>
	</div>
	<div class="k2-amiga-player-games-filter-rows">
		<div class="k2-player-games-controls__fields k2-amiga-player-games-filter-row">
<?php if ($k2PlayerTournamentsShowCountryFilter) { ?>
			<div class="k2-player-games-controls__field">
				<span class="server-period-activity-leaderboard__picker-label">Host country</span>
				<?php k2_archive_listbox_render('country', 'k2-player-tournaments-country', $k2PlayerTournamentsCountryFilter, $k2PlayerTournamentsCountryChoices, 'Filter by host country', '', '', false, ''); ?>
			</div>
<?php } ?>
<?php if ($k2PlayerTournamentsShowYearFilter) { ?>
			<div class="k2-player-games-controls__field">
				<span class="server-period-activity-leaderboard__picker-label">Year</span>
				<?php k2_archive_listbox_render('year', 'k2-player-tournaments-year', (string) $k2PlayerTournamentsYearFilter, $k2PlayerTournamentsYearChoices, 'Events from this calendar year', '', '', false, '0'); ?>
			</div>
<?php } ?>
		</div>
	</div>
</form>
<?php } ?>
</div>
