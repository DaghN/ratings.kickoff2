<?php
/**
 * Amiga All games vault filter UI (`amiga/games/all.php`).
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_archive_listbox.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_amiga_routes.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_realm_games_all.php';

/**
 * @param array<string, mixed> $state
 * @param list<array{id: int, name: string, rating: int}> $players
 * @param list<array{opponent_id: int, opponent_name: string, games: int}> $opponentRows
 * @param array{
 *     gd: list<array{value: string, label: string, meta: string}>,
 *     gs: list<array{value: string, label: string, meta: string}>,
 *     ts: list<array{value: string, label: string, meta: string}>
 * } $scoreLineChoices
 * @param list<int> $years
 * @param list<array{value: string, label: string, meta: string}> $hostCountryChoices
 */
function amiga_realm_games_all_render_filters(
    array $state,
    array $players,
    array $opponentRows,
    array $scoreLineChoices,
    array $years,
    array $hostCountryChoices,
): void {
    $playerId = (int) ($state['player'] ?? 0);
    $opponentId = (int) ($state['opponent'] ?? 0);
    $playerVia = (string) ($state['player_via'] ?? '');
    $opponentVia = (string) ($state['opponent_via'] ?? '');
    $eventFilter = (string) ($state['event'] ?? 'all');
    $videosFilter = (string) ($state['videos'] ?? '');
    $hostFilter = trim((string) ($state['host'] ?? ''));

    $playerName = amiga_realm_games_all_name_from_players($playerId, $players);
    $playerSearchName = $playerId > 0 && $playerVia === 'search' ? $playerName : '';
    $playerRatingSelected = amiga_realm_games_all_listbox_selected_id($playerId, $playerVia, 'rating');
    $playerAlphaSelected = amiga_realm_games_all_listbox_selected_id($playerId, $playerVia, 'alpha');
    $opponentName = amiga_realm_games_all_name_from_opponents($opponentId, $opponentRows, $players);
    $opponentSearchName = $opponentId > 0 && $opponentVia === 'search' ? $opponentName : '';
    $opponentGamesSelected = amiga_realm_games_all_listbox_selected_id($opponentId, $opponentVia, 'games');
    $opponentAlphaSelected = amiga_realm_games_all_listbox_selected_id($opponentId, $opponentVia, 'alpha');

    $filterBase = amiga_realm_games_all_build_url(amiga_realm_games_all_query_params($state, false));
    $opponentRowHidden = $playerId <= 0;
    $yearModeFieldHidden = (int) ($state['year'] ?? 0) <= 0;
    $searchUid = 'k2-realm-games-player-search';
    $opponentSearchUid = 'k2-realm-games-opponent-search';
    $playerSearchInputClass = amiga_realm_games_all_active_search_input_class(
        'player-search-input k2-header-search__input',
        $playerSearchName !== ''
    );
    $opponentSearchInputClass = amiga_realm_games_all_active_search_input_class(
        'player-search-input k2-header-search__input',
        $opponentSearchName !== ''
    );

    $eventTabs = [
        'all' => ['label' => 'All', 'event' => 'all'],
        'world-cup' => ['label' => 'World Cup', 'event' => 'world-cup'],
    ];
    $videoTabs = [
        'all' => ['label' => 'All', 'videos' => ''],
        'with-videos' => ['label' => 'Videos', 'videos' => 'with-videos'],
    ];
    ?>
<div class="k2-player-games-filters k2-realm-games-filters k2-amiga-realm-games-all-filters">
<div class="k2-amiga-realm-games-all-segment-filters">
<?php foreach ([$eventTabs, $videoTabs] as $tabSet) { ?>
<div class="k2-chrome-tabs k2-amiga-tournament-index-tabs">
	<nav class="k2-chrome-tabs__bar k2-chrome-tabs__bar--compact" data-k2-carry-scroll aria-label="Filter games scope">
<?php foreach ($tabSet as $tabKey => $tab) {
    $isEventSet = isset($tab['event']);
    if ($isEventSet) {
        $isActive = $tabKey === 'world-cup'
            ? $eventFilter === 'world-cup'
            : $eventFilter !== 'world-cup';
        $href = amiga_realm_games_all_segment_url($state, (string) $tab['event'], $videosFilter);
    } else {
        $isActive = $tabKey === 'with-videos'
            ? $videosFilter === 'with-videos'
            : $videosFilter !== 'with-videos';
        $href = amiga_realm_games_all_segment_url($state, $eventFilter, (string) $tab['videos']);
    }
    ?>
		<a href="<?php echo amiga_realm_games_all_h($href); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo amiga_realm_games_all_h($tab['label']); ?></a>
<?php } ?>
	</nav>
</div>
<?php } ?>
</div>
<form
	class="k2-realm-games-filters__form"
	method="get"
	action="<?php echo amiga_realm_games_all_h(amiga_realm_games_all_build_url([])); ?>"
	data-k2-carry-scroll
	data-k2-realm-games-filter-base="<?php echo amiga_realm_games_all_h($filterBase); ?>"
	data-k2-realm-games-realm="amiga"
>
	<div class="k2-realm-games-filters__meta">
		<input type="hidden" name="player" id="k2-realm-games-player" value="<?php echo (int) $playerId; ?>" />
		<input type="hidden" name="opponent" id="k2-realm-games-opponent" value="<?php echo (int) $opponentId; ?>" />
		<input type="hidden" name="player_via" id="k2-realm-games-player-via" value="<?php echo amiga_realm_games_all_h($playerVia); ?>" />
		<input type="hidden" name="opponent_via" id="k2-realm-games-opponent-via" value="<?php echo amiga_realm_games_all_h($opponentVia); ?>" />
		<?php if (($state['country'] ?? '') !== '') { ?>
		<input type="hidden" name="country" value="<?php echo amiga_realm_games_all_h((string) $state['country']); ?>" />
		<?php } ?>
		<?php if (($state['rival'] ?? '') !== '') { ?>
		<input type="hidden" name="rival" value="<?php echo amiga_realm_games_all_h((string) $state['rival']); ?>" />
		<?php } ?>
		<?php if ($eventFilter === 'world-cup') { ?>
		<input type="hidden" name="filter" value="world-cup" />
		<?php } ?>
		<?php if ($videosFilter === 'with-videos') { ?>
		<input type="hidden" name="videos" value="with-videos" />
		<?php } ?>
		<?php if ($state['sort'] !== 'id') { ?>
		<input type="hidden" name="sort" value="<?php echo amiga_realm_games_all_h((string) $state['sort']); ?>" />
		<?php } ?>
		<?php if ($state['dir'] !== 'desc') { ?>
		<input type="hidden" name="dir" value="<?php echo amiga_realm_games_all_h((string) $state['dir']); ?>" />
		<?php } ?>
	</div>

	<div class="k2-realm-games-filters__row k2-realm-games-filters__row--stack-narrow">
		<span class="k2-realm-games-filters__row-label">Host country</span>
		<div class="k2-player-games-controls__fields k2-realm-games-filters__picker-fields">
			<div class="k2-player-games-controls__field">
				<span class="player-search-label">Country</span>
				<?php k2_archive_listbox_render(
					'host',
					'k2-realm-games-host-country',
					$hostFilter,
					$hostCountryChoices,
					'Filter by tournament host country',
					'k2-realm-games-filters__host-pick',
					'',
					false,
					''
				); ?>
			</div>
		</div>
	</div>

	<div class="k2-realm-games-filters__row k2-realm-games-filters__row--pickers">
		<span class="k2-realm-games-filters__row-label">Player</span>
		<div class="k2-player-games-controls__fields k2-realm-games-filters__picker-fields">
			<div class="k2-player-games-controls__field k2-realm-games-filters__search-field">
				<div
					class="player-search"
					data-player-search-realm="amiga"
					data-player-search-mode="filter"
					data-player-search-filter-href="<?php echo amiga_realm_games_all_h($filterBase); ?>"
					data-player-search-filter-param="player"
					role="search"
				>
					<label class="player-search-label" for="<?php echo amiga_realm_games_all_h($searchUid); ?>">Search</label>
					<input
						id="<?php echo amiga_realm_games_all_h($searchUid); ?>"
						class="<?php echo amiga_realm_games_all_h($playerSearchInputClass); ?>"
						type="search"
						maxlength="32"
						autocomplete="off"
						spellcheck="false"
						placeholder="Player name…"
						value="<?php echo amiga_realm_games_all_h($playerSearchName); ?>"
						aria-expanded="false"
						aria-controls="<?php echo amiga_realm_games_all_h($searchUid); ?>-results"
					/>
					<ul
						id="<?php echo amiga_realm_games_all_h($searchUid); ?>-results"
						class="player-search-results"
						role="listbox"
						hidden
					></ul>
				</div>
			</div>
			<div class="k2-player-games-controls__field">
				<span class="player-search-label" id="k2-realm-games-player-rating-label">Rating</span>
				<?php k2_archive_listbox_render(
					'',
					'k2-realm-games-player-rating',
					$playerRatingSelected,
					amiga_realm_games_all_player_rating_choices($players),
					'Choose player by rating list',
					'k2-realm-games-filters__player-pick',
					'',
					false,
					'0'
				); ?>
			</div>
			<div class="k2-player-games-controls__field">
				<span class="player-search-label" id="k2-realm-games-player-alpha-label">A–Z</span>
				<?php k2_archive_listbox_render(
					'',
					'k2-realm-games-player-alpha',
					$playerAlphaSelected,
					amiga_realm_games_all_player_alpha_choices($players),
					'Choose player A to Z',
					'',
					'',
					false,
					'0'
				); ?>
			</div>
		</div>
	</div>

	<div class="k2-realm-games-filters__row k2-realm-games-filters__row--pickers" data-k2-realm-games-opponent-row<?php echo $opponentRowHidden ? ' hidden' : ''; ?>>
		<span class="k2-realm-games-filters__row-label">Opponent</span>
		<div class="k2-player-games-controls__fields k2-realm-games-filters__picker-fields">
			<div
				class="k2-player-games-controls__field k2-realm-games-filters__search-field k2-realm-games-filters__opponent-search"
				data-k2-realm-games-opponent-search
				data-player-id="<?php echo (int) $playerId; ?>"
			>
				<div class="player-search" role="search">
					<label class="player-search-label" for="<?php echo amiga_realm_games_all_h($opponentSearchUid); ?>">Search</label>
					<input
						id="<?php echo amiga_realm_games_all_h($opponentSearchUid); ?>"
						class="<?php echo amiga_realm_games_all_h($opponentSearchInputClass); ?>"
						type="search"
						maxlength="32"
						autocomplete="off"
						spellcheck="false"
						placeholder="Opponent name…"
						value="<?php echo amiga_realm_games_all_h($opponentSearchName); ?>"
					/>
					<ul class="player-search-results" role="listbox" hidden></ul>
				</div>
			</div>
			<div class="k2-player-games-controls__field">
				<span class="player-search-label" id="k2-realm-games-opponent-games-label">By games</span>
				<?php k2_archive_listbox_render(
					'',
					'k2-realm-games-opponent-games',
					$opponentGamesSelected,
					amiga_realm_games_all_opponent_games_choices($opponentRows),
					'Choose opponent by games played',
					'k2-realm-games-filters__opponent-pick',
					'',
					false,
					'0'
				); ?>
			</div>
			<div class="k2-player-games-controls__field">
				<span class="player-search-label" id="k2-realm-games-opponent-alpha-label">A–Z</span>
				<?php k2_archive_listbox_render(
					'',
					'k2-realm-games-opponent-alpha',
					$opponentAlphaSelected,
					amiga_realm_games_all_opponent_alpha_choices($opponentRows),
					'Choose opponent A to Z',
					'k2-realm-games-filters__opponent-pick',
					'',
					false,
					'0'
				); ?>
			</div>
		</div>
	</div>

	<div class="k2-realm-games-filters__row k2-realm-games-filters__row--stack-narrow">
		<span class="k2-realm-games-filters__row-label">Score-line</span>
		<div class="k2-player-games-controls__fields k2-realm-games-filters__score-fields">
			<div class="k2-player-games-controls__field">
				<span class="player-search-label">GD</span>
				<?php k2_archive_listbox_render(
					'gd',
					'k2-realm-games-gd',
					(string) $state['gd'],
					$scoreLineChoices['gd'],
					'Filter by goal difference',
					'',
					'',
					false,
					'-1'
				); ?>
			</div>
			<div class="k2-player-games-controls__field">
				<span class="player-search-label">Sum</span>
				<?php k2_archive_listbox_render(
					'gs',
					'k2-realm-games-gs',
					(string) $state['gs'],
					$scoreLineChoices['gs'],
					'Filter by goal sum',
					'',
					'',
					false,
					'-1'
				); ?>
			</div>
			<div class="k2-player-games-controls__field">
				<span class="player-search-label">TS</span>
				<?php k2_archive_listbox_render(
					'ts',
					'k2-realm-games-ts',
					(string) $state['ts'],
					$scoreLineChoices['ts'],
					'Filter by top score',
					'',
					'',
					false,
					'-1'
				); ?>
			</div>
		</div>
	</div>

	<div class="k2-realm-games-filters__row k2-realm-games-filters__row--stack-narrow">
		<span class="k2-realm-games-filters__row-label">Year</span>
		<div class="k2-player-games-controls__fields k2-realm-games-filters__year-fields">
			<div class="k2-player-games-controls__field">
				<span class="player-search-label">Year</span>
				<?php k2_archive_listbox_render(
					'year',
					'k2-realm-games-year',
					(string) $state['year'],
					amiga_realm_games_all_year_choices($years),
					'Filter by year',
					'',
					'',
					false,
					'0'
				); ?>
			</div>
			<div class="k2-player-games-controls__field"<?php echo $yearModeFieldHidden ? ' hidden' : ''; ?>>
				<span class="player-search-label">Mode</span>
				<?php k2_archive_listbox_render(
					$yearModeFieldHidden ? '' : 'year_mode',
					'k2-realm-games-year-mode',
					(string) $state['year_mode'],
					amiga_realm_games_all_year_mode_choices(),
					'Year filter mode',
					'',
					'',
					false,
					null,
					true
				); ?>
			</div>
		</div>
	</div>
</form>
</div>
    <?php
}