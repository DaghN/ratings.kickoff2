<?php
/**
 * Status Leagues — Weekly "Games this week" table markup (Recent-style, thinner columns).
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_table_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_rated_game_row.php';

if (!function_exists('k2_status_h')) {
    function k2_status_h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

/** @return array<string, mixed> */
function k2_status_week_game_row_options(): array
{
    return [
        'id_mode' => 'link',
        'sorted_col_index' => 0,
        'show_winner' => false,
        'highlight_winner_goal' => true,
        'team_a_align' => 'right',
        'show_gd_column' => false,
        'show_sum_column' => false,
        'show_ts_column' => false,
        'show_elo_diff_column' => false,
        'show_fav_es_column' => false,
        'show_adjustment_columns' => false,
        'rating_inline_adjustment' => true,
    ];
}

/**
 * @param list<array<string, mixed>> $games
 */
function k2_status_render_week_games_day_table(array $games): void
{
    $tableClass = k2_table_ranked_sortable_class('k2-table--calm-stats k2-status-week-games-table', false);
    $rowOpts = k2_status_week_game_row_options();
    k2_table_wrap_open(false);
    ?>
<table class="<?php echo k2_status_h($tableClass); ?>" data-k2-table="sortable" data-k2-default-sort="0" data-k2-default-direction="desc">
<thead>
	<tr>
		<th class="k2-table-cell--left" data-k2-sort="number" data-k2-help="Rated game ID. Opens the single-game detail page.">ID</th>
		<th class="k2-table-cell--left k2-table-cell--pad-left-xs" data-k2-sort="number">Date</th>
		<th class="k2-table-cell--right" data-k2-sort="text" data-k2-help="Player listed as Team A in the result row.">Team A</th>
		<th data-k2-sort="number" data-k2-tooltip-label="Goals A" data-k2-help="Goals scored by Team A.">A</th>
		<th class="k2-table-cell--left" data-k2-sort="number" data-k2-tooltip-label="Goals B" data-k2-help="Goals scored by Team B.">B</th>
		<th class="k2-table-cell--left" data-k2-sort="text" data-k2-help="Player listed as Team B in the result row.">Team B</th>
		<th class="k2-table-cell--pad-left-md" data-k2-sort="number" data-k2-help="Team A's Elo rating before this game. The signed value in parentheses is the rating adjustment after the game.">Rating A</th>
		<th data-k2-sort="number" data-k2-help="Team B's Elo rating before this game. The signed value in parentheses is the rating adjustment after the game.">Rating B</th>
	</tr>
</thead>
<tbody class="black">
<?php if ($games === []) { ?>
	<tr>
		<td colspan="8" class="k2-games-day__empty k2-table-cell--left">No rated games on this day.</td>
	</tr>
<?php } else { ?>
<?php foreach ($games as $row) { ?>
	<?php echo k2_rated_game_row_html($row, $rowOpts); ?>
<?php } ?>
<?php } ?>
</tbody>
</table>
<?php
    k2_table_wrap_close();
}

/**
 * @param list<array{ymd: string, weekday: string, games: list<array<string, mixed>>}> $days
 */
function k2_status_render_week_games_list(array $days): void
{
    if ($days === []) {
        echo '<p class="k2-status-panel__empty">No rated games in this week.</p>';

        return;
    }
    $anyGames = false;
    foreach ($days as $day) {
        if (($day['games'] ?? []) !== []) {
            $anyGames = true;
            break;
        }
    }
    if (!$anyGames) {
        echo '<p class="k2-status-panel__empty">No rated games in this week.</p>';

        return;
    }
    ?>
							<div class="k2-games-list k2-status-week-games-list">
<?php foreach ($days as $day) { ?>
								<div class="k2-games-day">
									<h4 class="k2-panel-heading k2-games-day__heading"><?php echo k2_status_h((string) ($day['weekday'] ?? '')); ?></h4>
<?php k2_status_render_week_games_day_table(is_array($day['games'] ?? null) ? $day['games'] : []); ?>
								</div>
<?php } ?>
							</div>
<?php
}

/**
 * @param list<array{ymd: string, weekday: string, games: list<array<string, mixed>>}> $days
 */
function k2_status_week_games_html(array $days): string
{
    ob_start();
    k2_status_render_week_games_list($days);

    return (string) ob_get_clean();
}