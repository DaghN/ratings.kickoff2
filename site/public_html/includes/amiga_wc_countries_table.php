<?php
/**
 * World Cup country career leaderboards — Honours · Results · Goals · DDs · Opponents.
 *
 * Shared by World Cups hub → Country stats (wing 4).
 *
 * @see docs/amiga-world-cups-country-slice-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_table_helpers.php';
require_once __DIR__ . '/lb_column_help.php';
require_once __DIR__ . '/k2_league_table_render.php';
require_once __DIR__ . '/k2_amiga_country_flag.php';
require_once __DIR__ . '/amiga_wc_countries_lb_lib.php';

/** @var list<string> */
const AMIGA_WC_COUNTRIES_VIEWS = ['honours', 'results', 'goals', 'dds', 'opponents'];

function amiga_wc_countries_table_shell_open(): void
{
    k2_table_wrap_open(true);
}

function amiga_wc_countries_table_shell_close(): void
{
    k2_table_wrap_close();
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_wc_countries_render_honours(array $rows, int $countryCount): void
{
    $lbSort = k2_lb_table_sort_state(4, 1);
    ?>
<?php amiga_wc_countries_table_shell_open(); ?>
<table class="<?php echo k2_h(k2_table_ranked_sortable_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="<?php echo $lbSort['anchor']; ?>" data-k2-default-sort="<?php echo $lbSort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort['sort_dir']); ?>"<?php echo k2_table_skip_initial_sort_attr(4); ?>>
<thead>
    <tr>
        <th<?php echo k2_lb_th(0, $lbSort, ''); ?> data-k2-sort="number">Rank</th>
        <th<?php echo k2_lb_th(1, $lbSort, 'k2-table-cell--center'); ?> data-k2-sort="text">Country</th>
        <th<?php echo k2_lb_th(2, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_players(), ENT_QUOTES, 'UTF-8'); ?>">Players</th>
        <th<?php echo k2_lb_th(3, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_wcs(), ENT_QUOTES, 'UTF-8'); ?>">WCs</th>
        <th<?php echo k2_lb_th(4, $lbSort, 'k2-lb-honours-medal-th'); ?> data-k2-sort="number" data-k2-tooltip-label="WC gold" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_gold(), ENT_QUOTES, 'UTF-8'); ?>"><?php echo k2_status_league_podium_medal(1); ?><span class="visually-hidden">WC gold</span></th>
        <th<?php echo k2_lb_th(5, $lbSort, 'k2-lb-honours-medal-th'); ?> data-k2-sort="number" data-k2-tooltip-label="WC silver" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_silver(), ENT_QUOTES, 'UTF-8'); ?>"><?php echo k2_status_league_podium_medal(2); ?><span class="visually-hidden">WC silver</span></th>
        <th<?php echo k2_lb_th(6, $lbSort, 'k2-lb-honours-medal-th'); ?> data-k2-sort="number" data-k2-tooltip-label="WC bronze" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_bronze(), ENT_QUOTES, 'UTF-8'); ?>"><?php echo k2_status_league_podium_medal(3); ?><span class="visually-hidden">WC bronze</span></th>
        <th<?php echo k2_lb_th(7, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_podiums(), ENT_QUOTES, 'UTF-8'); ?>">Podiums</th>
    </tr>
</thead>
<tbody class="black">
<?php
    $rank = 1;
    foreach ($rows as $row) {
        $countryToken = (string) $row['country_token'];
        ?>
    <tr>
        <td<?php echo k2_lb_td(0, $lbSort); ?>><?php echo $rank; ?></td>
        <td<?php echo k2_lb_td(1, $lbSort, 'k2-table-cell--center'); ?> data-k2-sort-value="<?php echo k2_h($countryToken); ?>"><?php echo k2_amiga_country_table_cell($countryToken, true); ?></td>
        <td<?php echo k2_lb_td(2, $lbSort); ?>><?php echo (int) $row['players']; ?></td>
        <td<?php echo k2_lb_td(3, $lbSort); ?>><?php echo (int) $row['tournaments_with_nation']; ?></td>
        <td<?php echo k2_lb_td(4, $lbSort); ?>><?php echo (int) $row['gold']; ?></td>
        <td<?php echo k2_lb_td(5, $lbSort); ?>><?php echo (int) $row['silver']; ?></td>
        <td<?php echo k2_lb_td(6, $lbSort); ?>><?php echo (int) $row['bronze']; ?></td>
        <td<?php echo k2_lb_td(7, $lbSort); ?>><?php echo (int) $row['podiums']; ?></td>
    </tr>
        <?php
        $rank++;
    }
    ?>
</tbody>
</table>
<?php amiga_wc_countries_table_shell_close(); ?>
<p class="k2-amiga-wc-countries-footnote" style="margin:0 0 2rem;color:var(--k2-text-secondary)"><?php echo number_format($countryCount); ?> countries with at least one World Cup player.</p>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_wc_countries_render_results(array $rows, int $countryCount): void
{
    $lbSort = k2_lb_table_sort_state(8, 1);
    ?>
<?php amiga_wc_countries_table_shell_open(); ?>
<table class="<?php echo k2_h(k2_table_ranked_sortable_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="<?php echo $lbSort['anchor']; ?>" data-k2-default-sort="<?php echo $lbSort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort['sort_dir']); ?>"<?php echo k2_table_skip_initial_sort_attr(8); ?>>
<thead>
    <tr>
        <th<?php echo k2_lb_th(0, $lbSort, ''); ?> data-k2-sort="number">Rank</th>
        <th<?php echo k2_lb_th(1, $lbSort, 'k2-table-cell--center'); ?> data-k2-sort="text">Country</th>
        <th<?php echo k2_lb_th(2, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_players(), ENT_QUOTES, 'UTF-8'); ?>">Players</th>
        <th<?php echo k2_lb_th(3, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_wcs(), ENT_QUOTES, 'UTF-8'); ?>">WCs</th>
        <th<?php echo k2_lb_th(4, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th<?php echo k2_lb_th(5, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_wins(), ENT_QUOTES, 'UTF-8'); ?>">W</th>
        <th<?php echo k2_lb_th(6, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_draws(), ENT_QUOTES, 'UTF-8'); ?>">D</th>
        <th<?php echo k2_lb_th(7, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_losses(), ENT_QUOTES, 'UTF-8'); ?>">L</th>
        <th<?php echo k2_lb_th(8, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_points(), ENT_QUOTES, 'UTF-8'); ?>">Pts</th>
        <th<?php echo k2_lb_th(9, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_points_per_game(), ENT_QUOTES, 'UTF-8'); ?>">Pts/g</th>
        <th<?php echo k2_lb_th(10, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_win_rate(), ENT_QUOTES, 'UTF-8'); ?>">Win rate</th>
        <th<?php echo k2_lb_th(11, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_avg_opponent_rating(), ENT_QUOTES, 'UTF-8'); ?>">Avg opp. rating</th>
        <th<?php echo k2_lb_th(12, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_performance_rating(), ENT_QUOTES, 'UTF-8'); ?>">Perf. rating</th>
        <th<?php echo k2_lb_th(13, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_points_per_realm_wc(), ENT_QUOTES, 'UTF-8'); ?>">Pts per WC</th>
        <th<?php echo k2_lb_th(14, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_participations(), ENT_QUOTES, 'UTF-8'); ?>">Entries</th>
        <th<?php echo k2_lb_th(15, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_participations_per_player(), ENT_QUOTES, 'UTF-8'); ?>">Entries / player</th>
        <th<?php echo k2_lb_th(16, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_games_per_player(), ENT_QUOTES, 'UTF-8'); ?>">Games / player</th>
        <th<?php echo k2_lb_th(17, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_domestic_games(), ENT_QUOTES, 'UTF-8'); ?>">Domestic</th>
        <th<?php echo k2_lb_th(18, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_domestic_share(), ENT_QUOTES, 'UTF-8'); ?>">Domestic %</th>
        <th<?php echo k2_lb_th(19, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_international_games(), ENT_QUOTES, 'UTF-8'); ?>">International</th>
        <th<?php echo k2_lb_th(20, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_international_share(), ENT_QUOTES, 'UTF-8'); ?>">International %</th>
        <th<?php echo k2_lb_th(21, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_games_share(), ENT_QUOTES, 'UTF-8'); ?>">Games %</th>
        <th<?php echo k2_lb_th(22, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_goals_share(), ENT_QUOTES, 'UTF-8'); ?>">Goals %</th>
    </tr>
</thead>
<tbody class="black">
<?php
    $rank = 1;
    foreach ($rows as $row) {
        $countryToken = (string) $row['country_token'];
        $players = (int) $row['players'];
        $games = (int) $row['games'];
        $points = (int) $row['points'];
        $realmWcs = (int) ($row['realm_wc_tournament_count'] ?? 0);
        $ptsPerGame = amiga_wc_country_points_per_game($points, $games);
        ?>
    <tr>
        <td<?php echo k2_lb_td(0, $lbSort); ?>><?php echo $rank; ?></td>
        <td<?php echo k2_lb_td(1, $lbSort, 'k2-table-cell--center'); ?> data-k2-sort-value="<?php echo k2_h($countryToken); ?>"><?php echo k2_amiga_country_table_cell($countryToken, true); ?></td>
        <td<?php echo k2_lb_td(2, $lbSort); ?>><?php echo $players; ?></td>
        <td<?php echo k2_lb_td(3, $lbSort); ?>><?php echo (int) $row['tournaments_with_nation']; ?></td>
        <td<?php echo k2_lb_td(4, $lbSort); ?>><?php echo $games; ?></td>
        <td<?php echo k2_lb_td(5, $lbSort); ?>><?php echo (int) $row['wins']; ?></td>
        <td<?php echo k2_lb_td(6, $lbSort); ?>><?php echo (int) $row['draws']; ?></td>
        <td<?php echo k2_lb_td(7, $lbSort); ?>><?php echo (int) $row['losses']; ?></td>
        <td<?php echo k2_lb_td(8, $lbSort); ?>><?php echo $points; ?></td>
        <td<?php echo k2_lb_td(9, $lbSort); ?>><?php echo $ptsPerGame !== null ? k2_fmt_decimal($ptsPerGame, $games) : k2_fmt_dash(); ?></td>
        <td<?php echo k2_lb_td(10, $lbSort); ?>><?php echo k2_fmt_pct_from_ratio($row['win_rate'] ?? null, $games); ?></td>
        <td<?php echo k2_lb_td(11, $lbSort); ?>><?php echo k2_fmt_int($row['average_opponent_rating'] ?? null); ?></td>
        <td<?php echo k2_lb_td(12, $lbSort); ?>><?php echo k2_fmt_int($row['performance_rating'] ?? null); ?></td>
        <td<?php echo k2_lb_td(13, $lbSort); ?>><?php echo k2_fmt_decimal($row['points_per_realm_wc'] ?? null, $realmWcs > 0 ? $realmWcs : null); ?></td>
        <td<?php echo k2_lb_td(14, $lbSort); ?>><?php echo (int) $row['wc_participations']; ?></td>
        <td<?php echo k2_lb_td(15, $lbSort); ?>><?php echo k2_fmt_decimal($row['wc_participations_per_player'] ?? null, $players > 0 ? $players : null); ?></td>
        <td<?php echo k2_lb_td(16, $lbSort); ?>><?php echo k2_fmt_decimal($row['games_per_player'] ?? null, $players > 0 ? $players : null); ?></td>
        <td<?php echo k2_lb_td(17, $lbSort); ?>><?php echo k2_fmt_count($row['domestic_games'] ?? 0, $games); ?></td>
        <td<?php echo k2_lb_td(18, $lbSort); ?>><?php echo k2_fmt_pct_from_ratio($row['domestic_game_share'] ?? null, $games); ?></td>
        <td<?php echo k2_lb_td(19, $lbSort); ?>><?php echo k2_fmt_count($row['international_games'] ?? 0, $games); ?></td>
        <td<?php echo k2_lb_td(20, $lbSort); ?>><?php echo k2_fmt_pct_from_ratio($row['international_game_share'] ?? null, $games); ?></td>
        <td<?php echo k2_lb_td(21, $lbSort); ?>><?php echo k2_fmt_pct_from_ratio($row['games_share'] ?? null, $games); ?></td>
        <td<?php echo k2_lb_td(22, $lbSort); ?>><?php echo k2_fmt_pct_from_ratio($row['goals_share'] ?? null, $games); ?></td>
    </tr>
        <?php
        $rank++;
    }
    ?>
</tbody>
</table>
<?php amiga_wc_countries_table_shell_close(); ?>
<p class="k2-amiga-wc-countries-footnote" style="margin:0 0 2rem;color:var(--k2-text-secondary)"><?php echo number_format($countryCount); ?> countries with at least one World Cup player. Match points: 3 for a win, 1 for a draw.</p>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_wc_countries_render_goals(array $rows, int $countryCount): void
{
    $lbSort = k2_lb_table_sort_state(4, 1);
    ?>
<?php amiga_wc_countries_table_shell_open(); ?>
<table class="<?php echo k2_h(k2_table_ranked_sortable_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="<?php echo $lbSort['anchor']; ?>" data-k2-default-sort="<?php echo $lbSort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort['sort_dir']); ?>"<?php echo k2_table_skip_initial_sort_attr(4); ?>>
<thead>
    <tr>
        <th<?php echo k2_lb_th(0, $lbSort, ''); ?> data-k2-sort="number">Rank</th>
        <th<?php echo k2_lb_th(1, $lbSort, 'k2-table-cell--center'); ?> data-k2-sort="text">Country</th>
        <th<?php echo k2_lb_th(2, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_players(), ENT_QUOTES, 'UTF-8'); ?>">Players</th>
        <th<?php echo k2_lb_th(3, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th<?php echo k2_lb_th(4, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Goals for" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_goals_for(), ENT_QUOTES, 'UTF-8'); ?>">GF</th>
        <th<?php echo k2_lb_th(5, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Goals against" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_goals_against(), ENT_QUOTES, 'UTF-8'); ?>">GA</th>
        <th<?php echo k2_lb_th(6, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Goal difference" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_goal_difference(), ENT_QUOTES, 'UTF-8'); ?>">GD</th>
        <th<?php echo k2_lb_th(7, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Goals for per game" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_goals_for_per_game(), ENT_QUOTES, 'UTF-8'); ?>">GF/g</th>
        <th<?php echo k2_lb_th(8, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Goals against per game" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_goals_against_per_game(), ENT_QUOTES, 'UTF-8'); ?>">GA/g</th>
        <th<?php echo k2_lb_th(9, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Goal difference per game" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_goal_difference_per_game(), ENT_QUOTES, 'UTF-8'); ?>">GD/g</th>
        <th<?php echo k2_lb_th(10, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_goal_ratio(), ENT_QUOTES, 'UTF-8'); ?>">GF / GA</th>
        <th<?php echo k2_lb_th(11, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_most_goals_scored(), ENT_QUOTES, 'UTF-8'); ?>">Best GF</th>
        <th<?php echo k2_lb_th(12, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_most_goals_conceded(), ENT_QUOTES, 'UTF-8'); ?>">Worst GA</th>
        <th<?php echo k2_lb_th(13, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_biggest_win(), ENT_QUOTES, 'UTF-8'); ?>">Best win</th>
        <th<?php echo k2_lb_th(14, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_biggest_loss(), ENT_QUOTES, 'UTF-8'); ?>">Worst loss</th>
        <th<?php echo k2_lb_th(15, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_biggest_sum(), ENT_QUOTES, 'UTF-8'); ?>">Highest total</th>
        <th<?php echo k2_lb_th(16, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_biggest_draw(), ENT_QUOTES, 'UTF-8'); ?>">Highest draw</th>
    </tr>
</thead>
<tbody class="black">
<?php
    $rank = 1;
    foreach ($rows as $row) {
        $countryToken = (string) $row['country_token'];
        $games = (int) $row['games'];
        $gf = (int) $row['goals_for'];
        $ga = (int) $row['goals_against'];
        $gd = $gf - $ga;
        $gfPer = amiga_wc_country_goals_per_game($gf, $games);
        $gaPer = amiga_wc_country_goals_per_game($ga, $games);
        $gdPer = amiga_wc_country_goals_per_game($gd, $games);
        ?>
    <tr>
        <td<?php echo k2_lb_td(0, $lbSort); ?>><?php echo $rank; ?></td>
        <td<?php echo k2_lb_td(1, $lbSort, 'k2-table-cell--center'); ?> data-k2-sort-value="<?php echo k2_h($countryToken); ?>"><?php echo k2_amiga_country_table_cell($countryToken, true); ?></td>
        <td<?php echo k2_lb_td(2, $lbSort); ?>><?php echo (int) $row['players']; ?></td>
        <td<?php echo k2_lb_td(3, $lbSort); ?>><?php echo k2_fmt_games_played($games); ?></td>
        <td<?php echo k2_lb_td(4, $lbSort); ?>><?php echo k2_fmt_count($gf, $games); ?></td>
        <td<?php echo k2_lb_td(5, $lbSort); ?>><?php echo k2_fmt_count($ga, $games); ?></td>
        <td<?php echo k2_lb_td(6, $lbSort); ?>><?php echo k2_fmt_count($gd, $games); ?></td>
        <td<?php echo k2_lb_td(7, $lbSort); ?>><?php echo $gfPer !== null ? k2_fmt_decimal($gfPer, $games) : k2_fmt_dash(); ?></td>
        <td<?php echo k2_lb_td(8, $lbSort); ?>><?php echo $gaPer !== null ? k2_fmt_decimal($gaPer, $games) : k2_fmt_dash(); ?></td>
        <td<?php echo k2_lb_td(9, $lbSort); ?>><?php echo $gdPer !== null ? k2_fmt_decimal($gdPer, $games) : k2_fmt_dash(); ?></td>
        <td<?php echo k2_lb_td(10, $lbSort); ?>><?php
            if (!k2_derived_games_started($games) || k2_db_is_null($row['goal_ratio'] ?? null)) {
                echo k2_fmt_dash();
            } else {
                echo k2_fmt_decimal($row['goal_ratio'], $games);
            }
        ?></td>
        <td<?php echo k2_lb_td(11, $lbSort); ?>><?php echo k2_fmt_count($row['most_goals_scored'] ?? 0, $games); ?></td>
        <td<?php echo k2_lb_td(12, $lbSort); ?>><?php echo k2_fmt_count($row['most_goals_conceded'] ?? 0, $games); ?></td>
        <td<?php echo k2_lb_td(13, $lbSort); ?>><?php echo k2_fmt_count($row['biggest_win_difference'] ?? 0, $games); ?></td>
        <td<?php echo k2_lb_td(14, $lbSort); ?>><?php echo k2_fmt_count($row['biggest_loss_difference'] ?? 0, $games); ?></td>
        <td<?php echo k2_lb_td(15, $lbSort); ?>><?php echo k2_fmt_count($row['biggest_sum_of_goals'] ?? 0, $games); ?></td>
        <td<?php echo k2_lb_td(16, $lbSort); ?>><?php
            if (!k2_derived_games_started($games) || (int) ($row['draws'] ?? 0) === 0) {
                echo k2_fmt_dash();
            } else {
                $drawSum = k2_db_is_null($row['biggest_draw_sum'] ?? null) ? 0 : (int) $row['biggest_draw_sum'];
                $half = (int) ($drawSum / 2);
                echo $half . '-' . $half;
            }
        ?></td>
    </tr>
        <?php
        $rank++;
    }
    ?>
</tbody>
</table>
<?php amiga_wc_countries_table_shell_close(); ?>
<p class="k2-amiga-wc-countries-footnote" style="margin:0 0 2rem;color:var(--k2-text-secondary)"><?php echo number_format($countryCount); ?> countries with at least one World Cup player. Best GF / worst GA and other extremes are single-game records by any national, not national totals.</p>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_wc_countries_render_dds(array $rows, int $countryCount): void
{
    $lbSort = k2_lb_table_sort_state(4, 1);
    ?>
<?php amiga_wc_countries_table_shell_open(); ?>
<table class="<?php echo k2_h(k2_table_ranked_sortable_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="<?php echo $lbSort['anchor']; ?>" data-k2-default-sort="<?php echo $lbSort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort['sort_dir']); ?>"<?php echo k2_table_skip_initial_sort_attr(4); ?>>
<thead>
    <tr>
        <th<?php echo k2_lb_th(0, $lbSort, ''); ?> data-k2-sort="number">Rank</th>
        <th<?php echo k2_lb_th(1, $lbSort, 'k2-table-cell--center'); ?> data-k2-sort="text">Country</th>
        <th<?php echo k2_lb_th(2, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_players(), ENT_QUOTES, 'UTF-8'); ?>">Players</th>
        <th<?php echo k2_lb_th(3, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th<?php echo k2_lb_th(4, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_double_digits(), ENT_QUOTES, 'UTF-8'); ?>">Double digits</th>
        <th<?php echo k2_lb_th(5, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_clean_sheets(), ENT_QUOTES, 'UTF-8'); ?>">Clean sheets</th>
        <th<?php echo k2_lb_th(6, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_double_digits_ratio(), ENT_QUOTES, 'UTF-8'); ?>">DD %</th>
        <th<?php echo k2_lb_th(7, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_clean_sheets_ratio(), ENT_QUOTES, 'UTF-8'); ?>">CS %</th>
        <th<?php echo k2_lb_th(8, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_double_digits_conceded(), ENT_QUOTES, 'UTF-8'); ?>">DD against</th>
        <th<?php echo k2_lb_th(9, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_clean_sheets_conceded(), ENT_QUOTES, 'UTF-8'); ?>">Scoreless</th>
        <th<?php echo k2_lb_th(10, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_double_digits_conceded_ratio(), ENT_QUOTES, 'UTF-8'); ?>">DD against %</th>
        <th<?php echo k2_lb_th(11, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_clean_sheets_conceded_ratio(), ENT_QUOTES, 'UTF-8'); ?>">Scoreless %</th>
    </tr>
</thead>
<tbody class="black">
<?php
    $rank = 1;
    foreach ($rows as $row) {
        $countryToken = (string) $row['country_token'];
        $games = (int) $row['games'];
        ?>
    <tr>
        <td<?php echo k2_lb_td(0, $lbSort); ?>><?php echo $rank; ?></td>
        <td<?php echo k2_lb_td(1, $lbSort, 'k2-table-cell--center'); ?> data-k2-sort-value="<?php echo k2_h($countryToken); ?>"><?php echo k2_amiga_country_table_cell($countryToken, true); ?></td>
        <td<?php echo k2_lb_td(2, $lbSort); ?>><?php echo (int) $row['players']; ?></td>
        <td<?php echo k2_lb_td(3, $lbSort); ?>><?php echo k2_fmt_games_played($games); ?></td>
        <td<?php echo k2_lb_td(4, $lbSort); ?>><?php echo k2_fmt_count($row['double_digits'] ?? 0, $games); ?></td>
        <td<?php echo k2_lb_td(5, $lbSort); ?>><?php echo k2_fmt_count($row['clean_sheets'] ?? 0, $games); ?></td>
        <td<?php echo k2_lb_td(6, $lbSort); ?>><?php echo k2_fmt_pct_from_ratio($row['double_digits_ratio'] ?? null, $games); ?></td>
        <td<?php echo k2_lb_td(7, $lbSort); ?>><?php echo k2_fmt_pct_from_ratio($row['clean_sheets_ratio'] ?? null, $games); ?></td>
        <td<?php echo k2_lb_td(8, $lbSort); ?>><?php echo k2_fmt_count($row['double_digits_conceded'] ?? 0, $games); ?></td>
        <td<?php echo k2_lb_td(9, $lbSort); ?>><?php echo k2_fmt_count($row['clean_sheets_conceded'] ?? 0, $games); ?></td>
        <td<?php echo k2_lb_td(10, $lbSort); ?>><?php echo k2_fmt_pct_from_ratio($row['double_digits_conceded_ratio'] ?? null, $games); ?></td>
        <td<?php echo k2_lb_td(11, $lbSort); ?>><?php echo k2_fmt_pct_from_ratio($row['clean_sheets_conceded_ratio'] ?? null, $games); ?></td>
    </tr>
        <?php
        $rank++;
    }
    ?>
</tbody>
</table>
<?php amiga_wc_countries_table_shell_close(); ?>
<p class="k2-amiga-wc-countries-footnote" style="margin:0 0 2rem;color:var(--k2-text-secondary)"><?php echo number_format($countryCount); ?> countries with at least one World Cup player.</p>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_wc_countries_render_opponents(array $rows, int $countryCount): void
{
    $lbSort = k2_lb_table_sort_state(4, 1);
?>
<?php amiga_wc_countries_table_shell_open(); ?>
<table class="<?php echo k2_h(k2_table_ranked_sortable_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="<?php echo $lbSort['anchor']; ?>" data-k2-default-sort="<?php echo $lbSort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort['sort_dir']); ?>"<?php echo k2_table_skip_initial_sort_attr(4); ?>>
<thead>
    <tr>
        <th<?php echo k2_lb_th(0, $lbSort, ''); ?> data-k2-sort="number">Rank</th>
        <th<?php echo k2_lb_th(1, $lbSort, 'k2-table-cell--center'); ?> data-k2-sort="text">Country</th>
        <th<?php echo k2_lb_th(2, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_players(), ENT_QUOTES, 'UTF-8'); ?>">Players</th>
        <th<?php echo k2_lb_th(3, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th<?php echo k2_lb_th(4, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_opponents(), ENT_QUOTES, 'UTF-8'); ?>">Opponents</th>
        <th<?php echo k2_lb_th(5, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_victims(), ENT_QUOTES, 'UTF-8'); ?>">Victims</th>
        <th<?php echo k2_lb_th(6, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_dd_victims(), ENT_QUOTES, 'UTF-8'); ?>">DD victims</th>
        <th<?php echo k2_lb_th(7, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_cs_victims(), ENT_QUOTES, 'UTF-8'); ?>">CS victims</th>
        <th<?php echo k2_lb_th(8, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_opponent_countries_faced(), ENT_QUOTES, 'UTF-8'); ?>">Countries faced</th>
        <th<?php echo k2_lb_th(9, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_country_opponent_countries_beaten(), ENT_QUOTES, 'UTF-8'); ?>">Countries beaten</th>
    </tr>
</thead>
<tbody class="black">
<?php
    $rank = 1;
    foreach ($rows as $row) {
        $countryToken = (string) $row['country_token'];
        $games = (int) $row['games'];
        ?>
    <tr>
        <td<?php echo k2_lb_td(0, $lbSort); ?>><?php echo $rank; ?></td>
        <td<?php echo k2_lb_td(1, $lbSort, 'k2-table-cell--center'); ?> data-k2-sort-value="<?php echo k2_h($countryToken); ?>"><?php echo k2_amiga_country_table_cell($countryToken, true); ?></td>
        <td<?php echo k2_lb_td(2, $lbSort); ?>><?php echo (int) $row['players']; ?></td>
        <td<?php echo k2_lb_td(3, $lbSort); ?>><?php echo k2_fmt_games_played($games); ?></td>
        <td<?php echo k2_lb_td(4, $lbSort); ?>><?php echo k2_fmt_count($row['different_opponents'] ?? 0, $games); ?></td>
        <td<?php echo k2_lb_td(5, $lbSort); ?>><?php echo k2_fmt_count($row['different_victims'] ?? 0, $games); ?></td>
        <td<?php echo k2_lb_td(6, $lbSort); ?>><?php echo k2_fmt_count($row['double_digits_victims'] ?? 0, $games); ?></td>
        <td<?php echo k2_lb_td(7, $lbSort); ?>><?php echo k2_fmt_count($row['clean_sheets_victims'] ?? 0, $games); ?></td>
        <td<?php echo k2_lb_td(8, $lbSort); ?>><?php echo k2_fmt_count($row['opponent_countries_faced'] ?? 0, $games); ?></td>
        <td<?php echo k2_lb_td(9, $lbSort); ?>><?php echo k2_fmt_count($row['opponent_countries_beaten'] ?? 0, $games); ?></td>
    </tr>
        <?php
        $rank++;
    }
    ?>
</tbody>
</table>
<?php amiga_wc_countries_table_shell_close(); ?>
<p class="k2-amiga-wc-countries-footnote" style="margin:0 0 2rem;color:var(--k2-text-secondary)"><?php echo number_format($countryCount); ?> countries with at least one World Cup player. Geography and network counts are World Cup games only.</p>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_wc_countries_render_view(string $view, array $rows, int $countryCount): void
{
    if ($view === 'honours') {
        amiga_wc_countries_render_honours($rows, $countryCount);

        return;
    }
    if ($view === 'results') {
        amiga_wc_countries_render_results($rows, $countryCount);

        return;
    }
    if ($view === 'goals') {
        amiga_wc_countries_render_goals($rows, $countryCount);

        return;
    }
    if ($view === 'dds') {
        amiga_wc_countries_render_dds($rows, $countryCount);

        return;
    }
    if ($view === 'opponents') {
        amiga_wc_countries_render_opponents($rows, $countryCount);

        return;
    }
    throw new InvalidArgumentException('Unknown World Cup countries view: ' . $view);
}
