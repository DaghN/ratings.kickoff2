<?php
/**
 * Amiga Countries hub — country roster table.
 *
 * @see docs/amiga-countries-hub-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_table_helpers.php';
require_once __DIR__ . '/lb_column_help.php';
require_once __DIR__ . '/k2_league_table_render.php';
require_once __DIR__ . '/k2_amiga_country_flag.php';
require_once __DIR__ . '/k2_amiga_routes.php';
require_once __DIR__ . '/amiga_player_load.php';
require_once __DIR__ . '/amiga_tournament_lib.php';
require_once __DIR__ . '/amiga_lb_lib.php';

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_countries_render_roster_table(array $rows, string $countryToken): void
{
    $lbSort = k2_lb_table_sort_state(2);
    ?>
<?php k2_table_wrap_open(true); ?>
<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class('k2-table--countries-roster')); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="<?php echo $lbSort['anchor']; ?>" data-k2-default-sort="<?php echo $lbSort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort['sort_dir']); ?>"<?php echo k2_table_skip_initial_sort_attr(2); ?>>
<thead>
    <tr>
        <th<?php echo k2_lb_th(0, $lbSort, ''); ?> data-k2-sort="number">#</th>
        <th<?php echo k2_lb_th(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort="text">Player</th>
        <th<?php echo k2_lb_th_elo(2, $lbSort); ?> data-k2-sort="number"<?php echo k2_lb_elo_column_help_attrs(); ?>>Elo</th>
        <th<?php echo k2_lb_th(3, $lbSort, ''); ?> data-k2-sort="number">Rank</th>
        <th<?php echo k2_lb_th(4, $lbSort, ''); ?> data-k2-sort="number">Games</th>
        <th<?php echo k2_lb_th(5, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_played(), ENT_QUOTES, 'UTF-8'); ?>">WC entries</th>
        <th<?php echo k2_lb_th(6, $lbSort, 'k2-lb-honours-medal-th k2-table-cell--center k2-countries-roster-medal-pad-start'); ?> data-k2-sort="number" data-k2-tooltip-label="WC gold" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_gold(), ENT_QUOTES, 'UTF-8'); ?>"><?php echo k2_status_league_podium_medal(1); ?><span class="visually-hidden">WC gold</span></th>
        <th<?php echo k2_lb_th(7, $lbSort, 'k2-lb-honours-medal-th k2-table-cell--center'); ?> data-k2-sort="number" data-k2-tooltip-label="WC silver" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_silver(), ENT_QUOTES, 'UTF-8'); ?>"><?php echo k2_status_league_podium_medal(2); ?><span class="visually-hidden">WC silver</span></th>
        <th<?php echo k2_lb_th(8, $lbSort, 'k2-lb-honours-medal-th k2-table-cell--center k2-countries-roster-medal-pad-end'); ?> data-k2-sort="number" data-k2-tooltip-label="WC bronze" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_bronze(), ENT_QUOTES, 'UTF-8'); ?>"><?php echo k2_status_league_podium_medal(3); ?><span class="visually-hidden">WC bronze</span></th>
        <th<?php echo k2_lb_th(9, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort="text">Last event</th>
        <th<?php echo k2_lb_th(10, $lbSort, ''); ?> data-k2-sort="date">Last event date</th>
    </tr>
</thead>
<tbody class="black">
<?php
    $rank = 1;
    foreach ($rows as $row) {
        $playerId = (int) $row['player_id'];
        $playerName = (string) ($row['player_name'] ?? '');
        $eloRank = $row['elo_rank'];
        $lastTournamentId = $row['last_tournament_id'];
        $lastEventName = (string) ($row['last_tournament_name'] ?? '');
        $lastEventDate = $row['last_event_date'];
        $lastTournamentCountry = (string) ($row['last_tournament_country'] ?? '');
        ?>
    <tr>
        <td<?php echo k2_lb_td(0, $lbSort); ?>><?php echo $rank; ?></td>
        <td<?php echo k2_lb_td(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort-value="<?php echo k2_h($playerName); ?>"><?php echo k2_amiga_lb_player_cell($playerId, $playerName, $countryToken); ?></td>
        <td<?php echo k2_lb_td(2, $lbSort); ?> data-k2-sort-value="<?php echo k2_h((string) $row['rating_sort']); ?>"><?php echo k2_amiga_lb_rating_cell_link($playerId, $row['rating'], $playerName); ?></td>
        <td<?php echo k2_lb_td(3, $lbSort); ?>><?php echo $eloRank !== null ? '#' . (int) $eloRank : '—'; ?></td>
        <td<?php echo k2_lb_td(4, $lbSort); ?>><?php echo (int) $row['number_games']; ?></td>
        <td<?php echo k2_lb_td(5, $lbSort); ?>><?php echo (int) $row['wc_played']; ?></td>
        <td<?php echo k2_lb_td(6, $lbSort, 'k2-table-cell--center k2-countries-roster-medal-pad-start'); ?>><?php echo (int) $row['wc_gold']; ?></td>
        <td<?php echo k2_lb_td(7, $lbSort, 'k2-table-cell--center'); ?>><?php echo (int) $row['wc_silver']; ?></td>
        <td<?php echo k2_lb_td(8, $lbSort, 'k2-table-cell--center k2-countries-roster-medal-pad-end'); ?>><?php echo (int) $row['wc_bronze']; ?></td>
        <td<?php echo k2_lb_td(9, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort-value="<?php echo k2_h($lastEventName); ?>"><?php
            if ($lastTournamentId !== null && $lastTournamentId > 0 && $lastEventName !== '') {
                echo k2_amiga_lb_tournament_cell($lastTournamentId, $lastEventName, $lastTournamentCountry);
            } else {
                echo '—';
            }
        ?></td>
        <td<?php echo k2_lb_td(10, $lbSort); ?> data-k2-sort-value="<?php echo k2_h($lastEventDate ?? ''); ?>"><?php echo $lastEventDate !== null && $lastEventDate !== '' ? k2_h($lastEventDate) : '—'; ?></td>
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