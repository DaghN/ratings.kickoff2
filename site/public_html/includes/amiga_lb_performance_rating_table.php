<?php
/**
 * Performance rating LB table (Best · Top 100 · Perfect).
 *
 * @see docs/amiga-performance-rating-leaderboard-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_table_helpers.php';
require_once __DIR__ . '/k2_league_table_render.php';
require_once __DIR__ . '/lb_column_help.php';
require_once __DIR__ . '/amiga_performance_rating.php';
require_once __DIR__ . '/amiga_profile_blocks.php';
require_once __DIR__ . '/amiga_tournament_lib.php';
require_once __DIR__ . '/amiga_player_load.php';
require_once __DIR__ . '/k2_amiga_country_flag.php';

const AMIGA_LB_PERF_RATING_COL_PERF = 3;
const AMIGA_LB_PERF_RATING_COL_DATE = 9;

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_lb_performance_rating_render_table(string $view, array $rows): void
{
    $view = in_array($view, ['best', 'top', 'perfect'], true) ? $view : 'best';
    $isPerfect = $view === 'perfect';
    $defaultSortCol = $isPerfect ? AMIGA_LB_PERF_RATING_COL_DATE : AMIGA_LB_PERF_RATING_COL_PERF;
    $lbSort = k2_lb_table_sort_state($defaultSortCol);

    $perfHelp = amiga_perf_rating_column_help();
    $infinityHelp = amiga_perf_rating_perfect_infinity_help();
    $gamesHelp = 'Games in the listed event.';
    $wHelp = 'Wins in this event (all phases).';
    $dHelp = 'Draws in this event (all phases).';
    $lHelp = 'Losses in this event (all phases).';

    k2_table_wrap_open(true);
    ?>
<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="<?php echo $lbSort['anchor']; ?>" data-k2-default-sort="<?php echo $lbSort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort['sort_dir']); ?>"<?php echo k2_table_skip_initial_sort_attr($defaultSortCol); ?>>

<thead>
    <tr>
        <th<?php echo k2_lb_th(0, $lbSort, ''); ?> data-k2-sort="number">#</th>
        <th<?php echo k2_lb_th(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort="text">Player</th>
        <th<?php echo k2_lb_th_elo(2, $lbSort); ?> data-k2-sort="number"<?php echo k2_lb_elo_column_help_attrs(); ?>>Elo</th>
        <th<?php echo k2_lb_th(3, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars($isPerfect ? $infinityHelp : $perfHelp, ENT_QUOTES, 'UTF-8'); ?>">Perf. rating</th>
        <th<?php echo k2_lb_th(4, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars($gamesHelp, ENT_QUOTES, 'UTF-8'); ?>">Event games</th>
        <th<?php echo k2_lb_th(5, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars($wHelp, ENT_QUOTES, 'UTF-8'); ?>">W</th>
        <th<?php echo k2_lb_th(6, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars($dHelp, ENT_QUOTES, 'UTF-8'); ?>">D</th>
        <th<?php echo k2_lb_th(7, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars($lHelp, ENT_QUOTES, 'UTF-8'); ?>">L</th>
        <th<?php echo k2_lb_th(8, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort="text">Event</th>
        <th<?php echo k2_lb_th(9, $lbSort, 'k2-table-cell--right'); ?> data-k2-sort="number">Date</th>
    </tr>
</thead>

<tbody class="black">
<?php
    $rank = 1;
    foreach ($rows as $row) {
        $playerId = (int) $row['player_id'];
        $playerName = (string) $row['player_name'];
        $eventGames = (int) ($row['event_games'] ?? 0);
        $wins = (int) ($row['event_wins'] ?? 0);
        $draws = (int) ($row['event_draws'] ?? 0);
        $losses = (int) ($row['event_losses'] ?? 0);
        $dateSortValue = $isPerfect
            ? amiga_lb_perf_rating_date_sort_value($row)
            : amiga_profile_event_date_sort_value($row);
        ?>
    <tr>
        <td<?php echo k2_lb_td(0, $lbSort); ?>><?php echo $rank; ?></td>
        <td<?php echo k2_lb_td(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort-value="<?php echo k2_h($playerName); ?>"><?php echo k2_amiga_lb_player_cell($playerId, $playerName, (string) ($row['country'] ?? '')); ?></td>
        <td<?php echo k2_lb_td(2, $lbSort); ?>><?php echo k2_fmt_int($row['Rating']); ?></td>
        <td<?php echo k2_lb_td(3, $lbSort); ?><?php echo $isPerfect ? ' data-k2-sort-value="0"' : ''; ?>><?php
            if ($isPerfect) {
                echo performance_rating_infinity_cell_html();
            } else {
                echo amiga_profile_tournament_rating_cell($row['performance_rating'] ?? null);
            }
        ?></td>
        <td<?php echo k2_lb_td(4, $lbSort); ?>><?php echo k2_fmt_games_played($eventGames); ?></td>
        <td<?php echo k2_lb_td(5, $lbSort); ?>><?php echo $wins; ?></td>
        <td<?php echo k2_lb_td(6, $lbSort); ?>><?php echo $draws; ?></td>
        <td<?php echo k2_lb_td(7, $lbSort); ?>><?php echo $losses; ?></td>
        <td<?php echo k2_lb_td(8, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort-value="<?php echo k2_h((string) ($row['tournament_name'] ?? '')); ?>"><?php
            echo k2_amiga_lb_tournament_cell(
                (int) ($row['tournament_id'] ?? 0),
                (string) ($row['tournament_name'] ?? ''),
                (string) ($row['host_country'] ?? '')
            );
        ?></td>
        <td<?php echo k2_lb_td(9, $lbSort, 'k2-table-cell--right'); ?> data-k2-sort-value="<?php echo k2_h($dateSortValue); ?>"><?php echo amiga_profile_format_event_date($row['event_date'] ?? null); ?></td>
    </tr>
        <?php
        $rank++;
    }
    ?>
</tbody>

</table>
    <?php
    k2_table_wrap_close();
}