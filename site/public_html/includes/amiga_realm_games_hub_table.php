<?php
/**
 * Amiga realm Games hub table — tournament games layout with Date + Tournament columns.
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_table_helpers.php';
require_once __DIR__ . '/k2_rated_game_row.php';
require_once __DIR__ . '/k2_player_game_row.php';
require_once __DIR__ . '/amiga_rated_game_row.php';
require_once __DIR__ . '/k2_amiga_country_flag.php';
require_once __DIR__ . '/amiga_player_load.php';
require_once __DIR__ . '/amiga_tournament_lib.php';
require_once __DIR__ . '/amiga_realm_games_all.php';

const AMIGA_REALM_GAMES_HUB_ID_SORT_COL = 0;
const AMIGA_REALM_GAMES_HUB_DEFAULT_SORT_COL = 1;
const AMIGA_REALM_GAMES_HUB_DATE_SORT_COL = 1;
const AMIGA_REALM_GAMES_HUB_DATE_SORT_COL_RANKED = 2;

/**
 * @param list<array<string, mixed>> $rows
 * @param array{
 *   show_rank?: bool,
 *   server_state?: ?array,
 *   default_sort_col?: int,
 *   default_sort_dir?: string,
 *   empty_message?: string,
 *   skip_initial_sort?: bool,
 *   con?: ?mysqli,
 * } $opts
 */
function amiga_realm_games_hub_render_table(array $rows, array $opts = []): void
{
    $showRank = (bool) ($opts['show_rank'] ?? false);
    $serverState = $opts['server_state'] ?? null;
    $hubCon = ($opts['con'] ?? null) instanceof mysqli ? $opts['con'] : null;
    $serverSorted = is_array($serverState);
    $defaultSortCol = (int) ($opts['default_sort_col'] ?? ($showRank ? AMIGA_REALM_GAMES_HUB_DATE_SORT_COL_RANKED : AMIGA_REALM_GAMES_HUB_DATE_SORT_COL));
    $defaultSortDir = (string) ($opts['default_sort_dir'] ?? 'desc');
    $emptyMessage = (string) ($opts['empty_message'] ?? 'No games match this filter.');
    $skipInitialSort = (bool) ($opts['skip_initial_sort'] ?? false);

    if (!$serverSorted) {
        $defaultSortCol = k2_table_default_sort_col_from_request($defaultSortCol);
        $defaultSortDir = k2_table_default_sort_dir_from_request($defaultSortDir);
        if (!$skipInitialSort) {
            $skipInitialSort = $defaultSortCol === ($showRank ? AMIGA_REALM_GAMES_HUB_DATE_SORT_COL_RANKED : AMIGA_REALM_GAMES_HUB_DATE_SORT_COL)
                && $defaultSortDir === 'desc'
                && k2_table_sort_query_params() === [];
        }
    }

    $showFlags = amiga_tournament_games_show_flags($rows);

    $col = 0;
    $rankCol = $showRank ? $col++ : -1;
    $idCol = $col++;
    $dateCol = $col++;
    $tournamentCol = $col++;
    $phaseCol = $col++;
    $teamACol = $col++;
    $goalsACol = $col++;
    $goalsBCol = $col++;
    $teamBCol = $col++;
    $gdCol = $col++;
    $sumCol = $col++;
    $tsCol = $col++;
    $ratingACol = $col++;
    $ratingBCol = $col++;
    $eloDiffCol = $col++;
    $favEsCol = $col++;
    $adjWinCol = $col++;
    $adjLoseCol = $col++;
    $colCount = $col;

    $anchorCol = $idCol;
    $sortedColIndex = $serverSorted
        ? amiga_realm_games_all_sort_col_index((string) ($serverState['sort'] ?? 'id'), $showRank)
        : $defaultSortCol;
    $tableClass = k2_table_ranked_sortable_class('k2-table--tournament-games', $showRank);
    if ($serverSorted) {
        $tableClass = 'k2-table k2-table--numeric-default k2-table--calm-stats k2-table--tournament-games k2-table--realm-games-all';
    }
    ?>
<?php k2_table_wrap_open(true); ?>
<table class="<?php echo k2_h($tableClass); ?>"<?php if (!$serverSorted) { ?> data-k2-table="sortable" data-k2-anchor-col="<?php echo $anchorCol; ?>" data-k2-default-sort="<?php echo $defaultSortCol; ?>" data-k2-default-direction="<?php echo k2_h($defaultSortDir); ?>"<?php echo $showRank ? ' data-k2-autorank="true"' : ''; ?><?php echo $skipInitialSort ? ' data-k2-skip-initial-sort="1"' : ''; ?><?php } ?>>
	<thead>
		<tr>
			<?php if ($showRank) { ?>
			<th class="k2-table-cell--left" data-k2-sort="number" data-k2-help="Rank in this board. Equal scores tie-break to lower game ID.">#</th>
			<?php } ?>
			<?php if ($serverSorted) { ?>
			<?php echo amiga_realm_games_all_sort_header('id', 'ID', 'left', $serverState, 'Rated game ID. Opens the single-game detail page.', '', 'k2-table-cell--left', $showRank); ?>
			<?php echo amiga_realm_games_all_sort_header('date', 'Date', 'left', $serverState, 'Event day the rated game was played.', 'Date', 'k2-table-cell--pad-left-xs', $showRank); ?>
			<?php echo amiga_realm_games_all_sort_header('tournament', 'Tournament', 'left', $serverState, 'Official KOA tournament that hosted this game.', 'Tournament', 'k2-table-cell--left', $showRank); ?>
			<?php echo amiga_realm_games_all_sort_header('phase', 'Phase', 'left', $serverState, 'Bracket phase when recorded (group, final, etc.).', 'Phase', 'k2-table-cell--left', $showRank); ?>
			<?php echo amiga_realm_games_all_sort_header('team_a', 'Player A', 'right', $serverState, 'Player listed as Team A in the result row.', '', 'k2-table-cell--right', $showRank); ?>
			<?php echo amiga_realm_games_all_sort_header('goals_a', 'A', 'right', $serverState, 'Goals scored by Team A.', 'Goals A', '', $showRank); ?>
			<?php echo amiga_realm_games_all_sort_header('goals_b', 'B', 'left', $serverState, 'Goals scored by Team B.', 'Goals B', 'k2-table-cell--left', $showRank); ?>
			<?php echo amiga_realm_games_all_sort_header('team_b', 'Player B', 'left', $serverState, 'Player listed as Team B in the result row.', '', 'k2-table-cell--left', $showRank); ?>
			<?php echo amiga_realm_games_all_sort_header('gd', 'GD', 'right', $serverState, 'Absolute goal margin in the game. A 7-4 result has GD 3.', 'Goal difference', 'k2-table-cell--pad-left-md', $showRank); ?>
			<?php echo amiga_realm_games_all_sort_header('sum', 'Sum', 'right', $serverState, 'Total goals scored by both players. A 7-4 result has Sum 11.', 'Goal sum', '', $showRank); ?>
			<?php echo amiga_realm_games_all_sort_header('top_score', 'TS', 'right', $serverState, 'Top score — the most goals either player scored in this game (e.g. 10 in 10–2).', 'Top score', '', $showRank); ?>
			<?php echo amiga_realm_games_all_sort_header('rating_a', 'Rating A', 'right', $serverState, 'Player A\'s Elo rating before this game.', '', 'k2-table-cell--pad-left-md', $showRank); ?>
			<?php echo amiga_realm_games_all_sort_header('rating_b', 'Rating B', 'right', $serverState, 'Player B\'s Elo rating before this game.', '', '', $showRank); ?>
			<?php echo amiga_realm_games_all_sort_header('elo_diff', 'Elo Diff', 'right', $serverState, 'Absolute pre-game Elo rating difference between the two players. Larger gaps mean a stronger favorite.', 'Elo difference', '', $showRank); ?>
			<?php echo amiga_realm_games_all_sort_header('fav_es', 'Fav ES', 'right', $serverState, "Elo maps the rating difference to an expected score for the favorite:\n\nES = 1 / (1 + 10^(-diff/400))\n\nThe actual score will be one of win = 1, draw = 0.5, loss = 0.", 'Favorite expected score', 'k2-table-cell--pad-right-xs', $showRank); ?>
			<?php echo amiga_realm_games_all_sort_header('adjustment', 'Adjustment', 'left', $serverState, "The expected score and actual score are used to calculate the rating change:\n\nRating change = 32 * (actual score - expected score)", 'Adjustment', 'k2-table-cell--left', $showRank); ?>
			<th class="k2-table-cell--left"><span class="visually-hidden">Adjustment lost</span></th>
			<?php } else { ?>
			<th<?php echo k2_table_sortable_th_attr($idCol, $defaultSortCol, $defaultSortDir, 'k2-table-cell--left'); ?> data-k2-sort="number" data-k2-help="Rated game ID. Opens the single-game detail page.">ID</th>
			<th<?php echo k2_table_sortable_th_attr($dateCol, $defaultSortCol, $defaultSortDir, 'k2-table-cell--left k2-table-cell--pad-left-xs'); ?> data-k2-sort="number">Date</th>
			<th<?php echo k2_table_sortable_th_attr($tournamentCol, $defaultSortCol, $defaultSortDir, 'k2-table-cell--left'); ?> data-k2-sort="text">Tournament</th>
			<th<?php echo k2_table_sortable_th_attr($phaseCol, $defaultSortCol, $defaultSortDir, 'k2-table-cell--left'); ?> data-k2-sort="text" data-k2-help="Bracket phase when recorded (group, final, etc.).">Phase</th>
			<th<?php echo k2_table_sortable_th_attr($teamACol, $defaultSortCol, $defaultSortDir, 'k2-table-cell--right'); ?> data-k2-sort="text">Player A</th>
			<th<?php echo k2_table_sortable_th_attr($goalsACol, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number">A</th>
			<th<?php echo k2_table_sortable_th_attr($goalsBCol, $defaultSortCol, $defaultSortDir, 'k2-table-cell--left'); ?> data-k2-sort="number">B</th>
			<th<?php echo k2_table_sortable_th_attr($teamBCol, $defaultSortCol, $defaultSortDir, 'k2-table-cell--left'); ?> data-k2-sort="text">Player B</th>
			<th<?php echo k2_table_sortable_th_attr($gdCol, $defaultSortCol, $defaultSortDir, 'k2-table-cell--pad-left-md'); ?> data-k2-sort="number" data-k2-tooltip-label="Goal difference" data-k2-help="Absolute goal margin in the game. A 7-4 result has GD 3.">GD</th>
			<th<?php echo k2_table_sortable_th_attr($sumCol, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-tooltip-label="Goal sum" data-k2-help="Total goals scored by both players. A 7-4 result has Sum 11.">Sum</th>
			<th<?php echo k2_table_sortable_th_attr($tsCol, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-tooltip-label="Top score" data-k2-help="Top score — the most goals either player scored in this game (e.g. 10 in 10–2).">TS</th>
			<th<?php echo k2_table_sortable_th_attr($ratingACol, $defaultSortCol, $defaultSortDir, 'k2-table-cell--pad-left-md'); ?> data-k2-sort="number" data-k2-help="Player A's Elo rating before this game.">Rating A</th>
			<th<?php echo k2_table_sortable_th_attr($ratingBCol, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Player B's Elo rating before this game.">Rating B</th>
			<th<?php echo k2_table_sortable_th_attr($eloDiffCol, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Absolute pre-game Elo rating difference between the two players. Larger gaps mean a stronger favorite.">Elo Diff</th>
			<th<?php echo k2_table_sortable_th_attr($favEsCol, $defaultSortCol, $defaultSortDir, 'k2-table-cell--pad-right-xs'); ?> data-k2-sort="number" data-k2-tooltip-label="Favorite expected score" data-k2-help="Elo maps the rating difference to an expected score for the favorite.">Fav ES</th>
			<th<?php echo k2_table_sortable_th_attr($adjWinCol, $defaultSortCol, $defaultSortDir, 'k2-table-cell--left'); ?> data-k2-sort="number" data-k2-tooltip-label="Adjustment" data-k2-help="Rating change from expected vs actual score.">Adjustment</th>
			<th class="k2-table-cell--left"><span class="visually-hidden">Adjustment lost</span></th>
			<?php } ?>
		</tr>
	</thead>
	<tbody class="black">
	<?php if ($rows === []) { ?>
		<tr>
			<td colspan="<?php echo (int) $colCount; ?>" class="k2-table-cell--left" style="color:var(--k2-text-secondary)"><?php echo k2_h($emptyMessage); ?></td>
		</tr>
	<?php } ?>
	<?php foreach ($rows as $row) {
        amiga_realm_games_hub_render_row(
            $row,
            $showRank,
            $showFlags,
            $rankCol,
            $idCol,
            $dateCol,
            $tournamentCol,
            $phaseCol,
            $teamACol,
            $goalsACol,
            $goalsBCol,
            $teamBCol,
            $gdCol,
            $sumCol,
            $tsCol,
            $ratingACol,
            $ratingBCol,
            $eloDiffCol,
            $favEsCol,
            $adjWinCol,
            $adjLoseCol,
            $anchorCol,
            $sortedColIndex,
            $hubCon,
        );
    } ?>
	</tbody>
</table>
<?php k2_table_wrap_close(); ?>
    <?php
}

/**
 * Recent hub — one table per tournament section (heading outside table; online Recent parity).
 *
 * @param list<array{heading: string, rows: list<array<string, mixed>>}> $sections
 * @param array{
 *   show_rank?: bool,
 *   default_sort_col?: int,
 *   default_sort_dir?: string,
 *   empty_message?: string,
 *   skip_initial_sort?: bool,
 * } $opts
 */
function amiga_realm_games_hub_render_sectioned_table(array $sections, array $opts = []): void
{
    $emptyMessage = (string) ($opts['empty_message'] ?? 'No rated games in this tournament.');

    foreach ($sections as $section) {
        ?>
	<div class="k2-games-day">
		<h2 class="k2-panel-heading k2-games-day__heading"><?php echo $section['heading']; ?></h2>
        <?php
        amiga_realm_games_hub_render_table($section['rows'], array_merge($opts, [
            'empty_message' => $emptyMessage,
        ]));
        ?>
	</div>
        <?php
    }
}

/**
 * @param array<string, mixed> $row
 */
function amiga_realm_games_hub_render_row(
    array $row,
    bool $showRank,
    bool $showFlags,
    int $rankCol,
    int $idCol,
    int $dateCol,
    int $tournamentCol,
    int $phaseCol,
    int $teamACol,
    int $goalsACol,
    int $goalsBCol,
    int $teamBCol,
    int $gdCol,
    int $sumCol,
    int $tsCol,
    int $ratingACol,
    int $ratingBCol,
    int $eloDiffCol,
    int $favEsCol,
    int $adjWinCol,
    int $adjLoseCol,
    int $anchorCol,
    int $sortedColIndex,
    ?mysqli $con = null,
): void {
    $processed = k2_rated_game_is_processed($row);
    $game = k2_player_game_normalize_row($row);
    $countryA = trim((string) ($row['country_a'] ?? ''));
    $countryB = trim((string) ($row['country_b'] ?? ''));
    $hostCountry = trim((string) ($row['tournament_country'] ?? ''));
    $tournamentId = (int) ($row['tournament_id'] ?? 0);
    $tournamentName = (string) ($row['tournament_name'] ?? '');
    $dash = k2_fmt_dash();

    $goalsA = (int) $game['GoalsA'];
    $goalsB = (int) $game['GoalsB'];
    if ($processed) {
        $aWin = k2_rated_game_is_a_win($game);
        $bWin = k2_rated_game_is_b_win($game);
    } else {
        $aWin = $goalsA > $goalsB;
        $bWin = $goalsB > $goalsA;
    }

    $goalDiff = $processed ? (int) $game['GoalDifference'] : abs($goalsA - $goalsB);
    $sumGoals = $processed ? (int) $game['SumOfGoals'] : $goalsA + $goalsB;
    $topScore = max($goalsA, $goalsB);

    if ($processed) {
        $esCell = k2_rated_game_es_winner_html($game);
        $favEs = k2_rated_game_favorite_expected_score($game);
        $winnerAdj = k2_game_rating_adjustment_pick($game, 'winner');
        $loserAdj = k2_game_rating_adjustment_pick($game, 'loser');
        $adjWinCell = amiga_rated_game_adjustment_html($game, 'winner');
        $adjLoseCell = amiga_rated_game_adjustment_html($game, 'loser');
        $ratingACell = (string) (int) round((float) $game['RatingA']);
        $ratingBCell = (string) (int) round((float) $game['RatingB']);
        $eloDiffCell = number_format(abs((float) ($row['RatingDifference'] ?? 0)), 0);
    } else {
        $esCell = $dash;
        $favEs = -1.0;
        $winnerAdj = ['adj' => 0.0];
        $loserAdj = ['adj' => 0.0];
        $adjWinCell = $dash;
        $adjLoseCell = $dash;
        $ratingACell = $dash;
        $ratingBCell = $dash;
        $eloDiffCell = $dash;
    }

    $flagA = $showFlags ? k2_amiga_country_flag_link($countryA) : '';
    $flagB = $showFlags ? k2_amiga_country_flag_link($countryB) : '';
    $hostFlag = k2_amiga_country_flag_link($hostCountry);
    $teamACell = '<span class="k2-amiga-tgame-side k2-amiga-tgame-side--a">' . $flagA
        . k2_amiga_player_link((int) $game['idA'], (string) $game['NameA']) . '</span>';
    $teamBCell = '<span class="k2-amiga-tgame-side k2-amiga-tgame-side--b">'
        . k2_amiga_player_link((int) $game['idB'], (string) $game['NameB']) . $flagB . '</span>';
    $tournamentCell = $tournamentId > 0 && $tournamentName !== ''
        ? '<span class="k2-amiga-tgame-side k2-amiga-tgame-side--tournament">' . $hostFlag . amiga_tournament_link($tournamentId, $tournamentName) . '</span>'
        : $dash;
    $phaseCell = amiga_rated_game_phase_cell($row, $con);

    $goalsAClass = $aWin ? 'k2-amiga-tgame-goal--win' : '';
    $goalsBClass = 'k2-table-cell--left' . ($bWin ? ' k2-amiga-tgame-goal--win' : '');
    $goalsACell = $aWin ? '<span class="blue">' . $goalsA . '</span>' : (string) $goalsA;
    $goalsBCell = $bWin ? '<span class="blue">' . $goalsB . '</span>' : (string) $goalsB;
    $dateCell = amiga_player_game_date_html((string) ($game['Date'] ?? ''));
    ?>
		<tr data-k2-sort-tie-value="<?php echo (int) $game['id']; ?>">
			<?php if ($showRank) { ?>
			<td<?php echo k2_table_body_td_attr($rankCol, $anchorCol, $sortedColIndex, 'k2-table-cell--left'); ?>></td>
			<?php } ?>
			<td<?php echo k2_table_body_td_attr($idCol, $anchorCol, $sortedColIndex, 'k2-table-cell--left'); ?>><?php echo amiga_rated_game_id_html((int) $game['id']); ?></td>
			<td<?php echo k2_table_body_td_attr($dateCol, $anchorCol, $sortedColIndex, 'k2-table-cell--left k2-table-cell--pad-left-xs k2-amiga-player-games-date'); ?>><?php echo $dateCell; ?></td>
			<td<?php echo k2_table_body_td_attr($tournamentCol, $anchorCol, $sortedColIndex, 'k2-table-cell--left k2-amiga-tgame-team'); ?>><?php echo $tournamentCell; ?></td>
			<td<?php echo k2_table_body_td_attr($phaseCol, $anchorCol, $sortedColIndex, 'k2-table-cell--left'); ?>><?php echo $phaseCell; ?></td>
			<td<?php echo k2_table_body_td_attr($teamACol, $anchorCol, $sortedColIndex, 'k2-table-cell--right k2-amiga-tgame-team k2-amiga-tgame-team--a'); ?>><?php echo $teamACell; ?></td>
			<td<?php echo k2_table_body_td_attr($goalsACol, $anchorCol, $sortedColIndex, $goalsAClass); ?>><?php echo $goalsACell; ?></td>
			<td<?php echo k2_table_body_td_attr($goalsBCol, $anchorCol, $sortedColIndex, $goalsBClass); ?>><?php echo $goalsBCell; ?></td>
			<td<?php echo k2_table_body_td_attr($teamBCol, $anchorCol, $sortedColIndex, 'k2-table-cell--left k2-amiga-tgame-team k2-amiga-tgame-team--b'); ?>><?php echo $teamBCell; ?></td>
			<td<?php echo k2_table_body_td_attr($gdCol, $anchorCol, $sortedColIndex, 'k2-table-cell--pad-left-md'); ?> data-k2-sort-value="<?php echo $goalDiff; ?>"><?php echo $goalDiff; ?></td>
			<td<?php echo k2_table_body_td_attr($sumCol, $anchorCol, $sortedColIndex); ?>><?php echo $sumGoals; ?></td>
			<td<?php echo k2_table_body_td_attr($tsCol, $anchorCol, $sortedColIndex); ?> data-k2-sort-value="<?php echo $topScore; ?>"><?php echo $topScore; ?></td>
			<td<?php echo k2_table_body_td_attr($ratingACol, $anchorCol, $sortedColIndex, 'k2-table-cell--pad-left-md'); ?>><?php echo $ratingACell; ?></td>
			<td<?php echo k2_table_body_td_attr($ratingBCol, $anchorCol, $sortedColIndex); ?>><?php echo $ratingBCell; ?></td>
			<td<?php echo k2_table_body_td_attr($eloDiffCol, $anchorCol, $sortedColIndex); ?>><?php echo $eloDiffCell; ?></td>
			<td<?php echo k2_table_body_td_attr($favEsCol, $anchorCol, $sortedColIndex, 'k2-table-cell--pad-right-xs'); ?> data-k2-sort-value="<?php echo $favEs; ?>"><?php echo $esCell; ?></td>
			<td<?php echo k2_table_body_td_attr($adjWinCol, $anchorCol, $sortedColIndex, 'k2-table-cell--left'); ?> data-k2-sort-value="<?php echo (float) $winnerAdj['adj']; ?>"><?php echo $adjWinCell; ?></td>
			<td<?php echo k2_table_body_td_attr($adjLoseCol, $anchorCol, $sortedColIndex, 'k2-table-cell--left'); ?> data-k2-sort-value="<?php echo (float) $loserAdj['adj']; ?>"><?php echo $adjLoseCell; ?></td>
		</tr>
    <?php
}
