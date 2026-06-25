<?php
/**
 * All games vault filter UI (`games/all.php`).
 */

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_archive_listbox.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_routes.php';

/**
 * @return list<array{id: int, name: string, rating: int}>
 */
function k2_realm_games_all_fetch_players(mysqli $con): array
{
	$rows = k2_realm_games_all_query_all(
		$con,
		'SELECT ID AS id, Name AS name, ROUND(Rating) AS rating FROM playertable '
			. 'WHERE Display = 1 AND NumberGames > 0 AND Name IS NOT NULL AND Name <> \'\' '
			. 'ORDER BY Name ASC, Rating DESC',
		'',
		[]
	);

	$players = [];
	foreach ($rows as $row) {
		$players[] = [
			'id' => (int) $row['id'],
			'name' => (string) $row['name'],
			'rating' => (int) $row['rating'],
		];
	}

	return $players;
}

/**
 * @return list<int>
 */
function k2_realm_games_all_fetch_years(mysqli $con): array
{
	$rows = k2_realm_games_all_query_all(
		$con,
		'SELECT DISTINCT YEAR(`Date`) AS y FROM ratedresults ORDER BY y DESC',
		'',
		[]
	);

	$years = [];
	foreach ($rows as $row) {
		$years[] = (int) $row['y'];
	}

	return $years;
}

/**
 * @return list<array{value: int, games: int}>
 */
function k2_realm_games_all_fetch_score_values(mysqli $con, string $columnSql): array
{
	$rows = k2_realm_games_all_query_all(
		$con,
		'SELECT ' . $columnSql . ' AS v, COUNT(*) AS games FROM ratedresults GROUP BY v ORDER BY v ASC',
		'',
		[]
	);

	$values = [];
	foreach ($rows as $row) {
		$values[] = [
			'value' => (int) $row['v'],
			'games' => (int) $row['games'],
		];
	}

	return $values;
}

/**
 * @param list<array{value: int, games: int}> $rows
 * @return list<array{value: string, label: string, meta: string}>
 */
function k2_realm_games_all_score_choices(array $rows): array
{
	$choices = [
		['value' => '-1', 'label' => '', 'meta' => ''],
	];
	foreach ($rows as $row) {
		$choices[] = [
			'value' => (string) $row['value'],
			'label' => (string) $row['value'],
			'meta' => (string) $row['games'],
		];
	}

	return $choices;
}

function k2_realm_games_all_gd_label(int $value): string
{
	if ($value > 0) {
		return '+' . $value;
	}

	return (string) $value;
}

/**
 * @param list<array{value: int, games: int}> $rows
 * @return list<array{value: string, label: string, meta: string}>
 */
function k2_realm_games_all_gd_choices(array $rows): array
{
	$choices = [
		['value' => '-1', 'label' => '', 'meta' => ''],
	];
	foreach ($rows as $row) {
		$value = (int) $row['value'];
		$choices[] = [
			'value' => (string) $value,
			'label' => k2_realm_games_all_gd_label($value),
			'meta' => (string) $row['games'],
		];
	}

	return $choices;
}

/**
 * @param list<array{id: int, name: string, rating: int}> $players
 * @return list<array{value: string, label: string, meta: string}>
 */
function k2_realm_games_all_player_rating_choices(array $players): array
{
	$byRating = $players;
	usort(
		$byRating,
		static function (array $a, array $b): int {
			$cmp = $b['rating'] <=> $a['rating'];
			if ($cmp !== 0) {
				return $cmp;
			}

			return strcasecmp((string) $a['name'], (string) $b['name']);
		}
	);

	$choices = [
		[
			'value' => '0',
			'label' => '',
			'meta' => '',
		],
	];
	foreach ($byRating as $player) {
		$choices[] = [
			'value' => (string) $player['id'],
			'label' => $player['name'],
			'meta' => (string) $player['rating'],
		];
	}

	return $choices;
}

/**
 * @param list<array{id: int, name: string, rating: int}> $players
 * @return list<array{value: string, label: string, meta: string}>
 */
function k2_realm_games_all_player_alpha_choices(array $players): array
{
	$choices = [
		['value' => '0', 'label' => '', 'meta' => ''],
	];
	foreach ($players as $player) {
		$choices[] = [
			'value' => (string) $player['id'],
			'label' => $player['name'],
			'meta' => (string) $player['rating'],
		];
	}

	return $choices;
}

/**
 * @param list<array{opponent_id: int, opponent_name: string, games: int}> $rows
 * @return list<array{value: string, label: string, meta: string}>
 */
function k2_realm_games_all_opponent_games_choices(array $rows): array
{
	$choices = [
		['value' => '0', 'label' => '', 'meta' => ''],
	];
	foreach ($rows as $row) {
		$choices[] = [
			'value' => (string) (int) $row['opponent_id'],
			'label' => (string) $row['opponent_name'],
			'meta' => k2_realm_games_all_games_meta_label((int) $row['games']),
		];
	}

	return $choices;
}

/**
 * @param list<array{opponent_id: int, opponent_name: string, games: int}> $rows
 * @return list<array{value: string, label: string}>
 */
function k2_realm_games_all_opponent_alpha_choices(array $rows): array
{
	$byAlpha = $rows;
	usort(
		$byAlpha,
		static function (array $a, array $b): int {
			return strcasecmp((string) $a['opponent_name'], (string) $b['opponent_name']);
		}
	);

	$choices = [
		['value' => '0', 'label' => ''],
	];
	foreach ($byAlpha as $row) {
		$choices[] = [
			'value' => (string) (int) $row['opponent_id'],
			'label' => (string) $row['opponent_name'],
		];
	}

	return $choices;
}

function k2_realm_games_all_games_meta_label(int $games): string
{
	return $games . ' game' . ($games === 1 ? '' : 's');
}

/**
 * @param list<int> $years
 * @return list<array{value: string, label: string}>
 */
function k2_realm_games_all_year_choices(array $years): array
{
	$choices = [
		['value' => '0', 'label' => ''],
	];
	foreach ($years as $year) {
		$choices[] = [
			'value' => (string) $year,
			'label' => (string) $year,
		];
	}

	return $choices;
}

/** @return list<array{value: string, label: string}> */
function k2_realm_games_all_year_mode_choices(): array
{
	return [
		['value' => 'in', 'label' => 'Just this year'],
		['value' => 'since', 'label' => 'Since this year'],
		['value' => 'until', 'label' => 'Until this year'],
	];
}

function k2_realm_games_all_name_from_players(int $playerId, array $players): string
{
	if ($playerId <= 0) {
		return '';
	}
	foreach ($players as $player) {
		if ((int) $player['id'] === $playerId) {
			return (string) $player['name'];
		}
	}

	return '';
}

function k2_realm_games_all_name_from_opponents(int $opponentId, array $opponentRows, array $players): string
{
	if ($opponentId <= 0) {
		return '';
	}
	foreach ($opponentRows as $row) {
		if ((int) $row['opponent_id'] === $opponentId) {
			return (string) $row['opponent_name'];
		}
	}

	return k2_realm_games_all_name_from_players($opponentId, $players);
}

function k2_realm_games_all_listbox_selected_id(int $entityId, string $via, string $expectedVia): string
{
	return $entityId > 0 && $via === $expectedVia ? (string) $entityId : '0';
}

function k2_realm_games_all_active_search_input_class(string $baseClass, bool $active): string
{
	return $active ? trim($baseClass . ' k2-link-star') : $baseClass;
}

/**
 * @param array{
 *     sort: string,
 *     dir: string,
 *     offset: int,
 *     player: int,
 *     opponent: int,
 *     gd: int,
 *     gs: int,
 *     ts: int,
 *     year: int,
 *     year_mode: string,
 *     player_via: string,
 *     opponent_via: string
 * } $state
 * @param list<array{id: int, name: string, rating: int}> $players
 * @param list<array{opponent_id: int, opponent_name: string, games: int}> $opponentRows
 * @param array{
 *     gd: list<array{value: string, label: string, meta: string}>,
 *     gs: list<array{value: string, label: string, meta: string}>,
 *     ts: list<array{value: string, label: string, meta: string}>
 * } $scoreLineChoices
 * @param list<int> $years
 */
function k2_realm_games_all_render_filters(
	array $state,
	array $players,
	array $opponentRows,
	array $scoreLineChoices,
	array $years
): void {
	$playerId = $state['player'];
	$opponentId = $state['opponent'];
	$playerVia = (string) ($state['player_via'] ?? '');
	$opponentVia = (string) ($state['opponent_via'] ?? '');
	$playerName = k2_realm_games_all_name_from_players($playerId, $players);
	$playerSearchName = $playerId > 0 && $playerVia === 'search' ? $playerName : '';
	$playerRatingSelected = k2_realm_games_all_listbox_selected_id($playerId, $playerVia, 'rating');
	$playerAlphaSelected = k2_realm_games_all_listbox_selected_id($playerId, $playerVia, 'alpha');
	$opponentName = k2_realm_games_all_name_from_opponents($opponentId, $opponentRows, $players);
	$opponentSearchName = $opponentId > 0 && $opponentVia === 'search' ? $opponentName : '';
	$opponentGamesSelected = k2_realm_games_all_listbox_selected_id($opponentId, $opponentVia, 'games');
	$opponentAlphaSelected = k2_realm_games_all_listbox_selected_id($opponentId, $opponentVia, 'alpha');

	$filterBase = k2_realm_games_all_build_url(k2_realm_games_all_query_params($state, false));
	$opponentRowHidden = $playerId <= 0;
	$yearModeFieldHidden = $state['year'] <= 0;
	$searchUid = 'k2-realm-games-player-search';
	$opponentSearchUid = 'k2-realm-games-opponent-search';
	$playerSearchInputClass = k2_realm_games_all_active_search_input_class(
		'player-search-input k2-header-search__input',
		$playerSearchName !== ''
	);
	$opponentSearchInputClass = k2_realm_games_all_active_search_input_class(
		'player-search-input k2-header-search__input',
		$opponentSearchName !== ''
	);
	?>
<div class="k2-player-games-filters k2-realm-games-filters">
<form
	class="k2-realm-games-filters__form"
	method="get"
	action="<?php echo k2_realm_games_all_h(k2_route('games-all')); ?>"
	data-k2-carry-scroll
	data-k2-realm-games-filter-base="<?php echo k2_realm_games_all_h($filterBase); ?>"
>
	<div class="k2-realm-games-filters__meta">
		<input type="hidden" name="player" id="k2-realm-games-player" value="<?php echo (int) $playerId; ?>" />
		<input type="hidden" name="opponent" id="k2-realm-games-opponent" value="<?php echo (int) $opponentId; ?>" />
		<input type="hidden" name="player_via" id="k2-realm-games-player-via" value="<?php echo k2_realm_games_all_h($playerVia); ?>" />
		<input type="hidden" name="opponent_via" id="k2-realm-games-opponent-via" value="<?php echo k2_realm_games_all_h($opponentVia); ?>" />
		<?php if ($state['sort'] !== 'id') { ?>
		<input type="hidden" name="sort" value="<?php echo k2_realm_games_all_h($state['sort']); ?>" />
		<?php } ?>
		<?php if ($state['dir'] !== 'desc') { ?>
		<input type="hidden" name="dir" value="<?php echo k2_realm_games_all_h($state['dir']); ?>" />
		<?php } ?>
	</div>

	<div class="k2-realm-games-filters__row k2-realm-games-filters__row--pickers">
		<span class="k2-realm-games-filters__row-label">Player</span>
		<div class="k2-player-games-controls__fields k2-realm-games-filters__picker-fields">
			<div class="k2-player-games-controls__field k2-realm-games-filters__search-field">
				<div
					class="player-search"
					data-player-search-realm="online"
					data-player-search-mode="filter"
					data-player-search-filter-href="<?php echo k2_realm_games_all_h($filterBase); ?>"
					data-player-search-filter-param="player"
					role="search"
				>
					<label class="player-search-label" for="<?php echo k2_realm_games_all_h($searchUid); ?>">Search</label>
					<input
						id="<?php echo k2_realm_games_all_h($searchUid); ?>"
						class="<?php echo k2_realm_games_all_h($playerSearchInputClass); ?>"
						type="search"
						maxlength="32"
						autocomplete="off"
						spellcheck="false"
						placeholder="Player name…"
						value="<?php echo k2_realm_games_all_h($playerSearchName); ?>"
						aria-expanded="false"
						aria-controls="<?php echo k2_realm_games_all_h($searchUid); ?>-results"
					/>
					<ul
						id="<?php echo k2_realm_games_all_h($searchUid); ?>-results"
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
					k2_realm_games_all_player_rating_choices($players),
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
					k2_realm_games_all_player_alpha_choices($players),
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
					<label class="player-search-label" for="<?php echo k2_realm_games_all_h($opponentSearchUid); ?>">Search</label>
					<input
						id="<?php echo k2_realm_games_all_h($opponentSearchUid); ?>"
						class="<?php echo k2_realm_games_all_h($opponentSearchInputClass); ?>"
						type="search"
						maxlength="32"
						autocomplete="off"
						spellcheck="false"
						placeholder="Opponent name…"
						value="<?php echo k2_realm_games_all_h($opponentSearchName); ?>"
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
					k2_realm_games_all_opponent_games_choices($opponentRows),
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
					k2_realm_games_all_opponent_alpha_choices($opponentRows),
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
					k2_realm_games_all_year_choices($years),
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
					$state['year_mode'],
					k2_realm_games_all_year_mode_choices(),
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
