<?php
/**
 * Amiga Countries hub — index table.
 *
 * @see docs/amiga-countries-hub-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_table_helpers.php';
require_once __DIR__ . '/lb_column_help.php';
require_once __DIR__ . '/k2_league_table_render.php';
require_once __DIR__ . '/k2_amiga_country_flag.php';
require_once __DIR__ . '/amiga_wc_podium_th.php';
require_once __DIR__ . '/amiga_countries_lib.php';

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_countries_render_index_table(array $rows): void
{
    $lbSort = k2_lb_table_sort_state(2, 1);
    ?>
<?php k2_table_wrap_open(true); ?>
<table class="<?php echo k2_h(k2_table_ranked_sortable_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-sort-tie-order="match" data-k2-anchor-col="<?php echo $lbSort['anchor']; ?>" data-k2-default-sort="<?php echo $lbSort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort['sort_dir']); ?>"<?php echo k2_table_skip_initial_sort_attr(2); ?>>
<thead>
    <tr>
        <th<?php echo k2_lb_th(0, $lbSort, ''); ?> data-k2-sort="number">Rank</th>
        <th<?php echo k2_lb_th(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort="text">Country</th>
        <th<?php echo k2_lb_th(2, $lbSort, ''); ?> data-k2-sort="number">Players</th>
        <th<?php echo k2_lb_th(3, $lbSort, ''); ?> data-k2-sort="number">Games</th>
        <th<?php echo k2_lb_th(4, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_countries_games_per_player(), ENT_QUOTES, 'UTF-8'); ?>">Games / player</th>
        <th<?php echo k2_lb_th(5, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_countries_wc_players_index(), ENT_QUOTES, 'UTF-8'); ?>">WC players</th>
        <th<?php echo k2_lb_th(6, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_countries_wc_entries_index(), ENT_QUOTES, 'UTF-8'); ?>">WC entries</th>
        <th<?php echo k2_lb_th(7, $lbSort, 'k2-lb-honours-medal-th'); ?> data-k2-sort="number" data-k2-sort-tie-cols="8,9" data-k2-tooltip-label="WC gold" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_gold(), ENT_QUOTES, 'UTF-8'); ?>"><?php echo k2_lb_honours_medal_th(1); ?><span class="visually-hidden">WC gold</span></th>
        <th<?php echo k2_lb_th(8, $lbSort, 'k2-lb-honours-medal-th'); ?> data-k2-sort="number" data-k2-tooltip-label="WC silver" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_silver(), ENT_QUOTES, 'UTF-8'); ?>"><?php echo k2_lb_honours_medal_th(2); ?><span class="visually-hidden">WC silver</span></th>
        <th<?php echo k2_lb_th(9, $lbSort, 'k2-lb-honours-medal-th'); ?> data-k2-sort="number" data-k2-tooltip-label="WC bronze" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_bronze(), ENT_QUOTES, 'UTF-8'); ?>"><?php echo k2_lb_honours_medal_th(3); ?><span class="visually-hidden">WC bronze</span></th>
    </tr>
</thead>
<tbody class="black">
<?php
    $rank = 1;
    foreach ($rows as $row) {
        $countryToken = (string) $row['country_token'];
        ?>
    <tr data-k2-sort-tie-value="<?php echo (int) $row['games']; ?>">
        <td<?php echo k2_lb_td(0, $lbSort); ?>><?php echo $rank; ?></td>
        <td<?php echo k2_lb_td(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort-value="<?php echo k2_h($countryToken); ?>"><?php echo k2_amiga_lb_country_cell($countryToken); ?></td>
        <td<?php echo k2_lb_td(2, $lbSort); ?>><span class="blue"><?php echo (int) $row['players']; ?></span></td>
        <td<?php echo k2_lb_td(3, $lbSort); ?>><?php echo (int) $row['games']; ?></td>
        <td<?php echo k2_lb_td(4, $lbSort); ?>><?php echo k2_h(number_format((float) $row['games_per_player'], 1, '.', '')); ?></td>
        <td<?php echo k2_lb_td(5, $lbSort); ?>><?php echo (int) $row['wc_players']; ?></td>
        <td<?php echo k2_lb_td(6, $lbSort); ?>><?php echo (int) $row['wc_entries']; ?></td>
        <td<?php echo k2_lb_td(7, $lbSort, 'k2-lb-honours-medal-td'); ?>><?php echo amiga_wc_podium_medal_value_markup((int) $row['wc_gold'], 1); ?></td>
        <td<?php echo k2_lb_td(8, $lbSort, 'k2-lb-honours-medal-td'); ?>><?php echo amiga_wc_podium_medal_value_markup((int) $row['wc_silver'], 2); ?></td>
        <td<?php echo k2_lb_td(9, $lbSort, 'k2-lb-honours-medal-td'); ?>><?php echo amiga_wc_podium_medal_value_markup((int) $row['wc_bronze'], 3); ?></td>
    </tr>
        <?php
        $rank++;
    }
    ?>
</tbody>
</table>
<?php k2_table_wrap_close(); ?>
    <?php
}
