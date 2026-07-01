<?php
/**
 * World Cup player career leaderboards — Honours · Results · Goals.
 *
 * Shared by Leaderboards → World Cups and World Cups hub → Player stats (WCH8/WCH9).
 *
 * @see docs/amiga-world-cups-hub-policy.md
 * @see docs/amiga-world-cups-leaderboard-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_table_helpers.php';
require_once __DIR__ . '/lb_column_help.php';
require_once __DIR__ . '/k2_league_table_render.php';
require_once __DIR__ . '/k2_amiga_country_flag.php';
require_once __DIR__ . '/amiga_player_load.php';
require_once __DIR__ . '/amiga_wc_lb_lib.php';
require_once __DIR__ . '/lb_player_filters.php';

/** @var list<string> */
const AMIGA_WC_PLAYERS_VIEWS = ['honours', 'results', 'goals', 'dds', 'opponents'];

function amiga_wc_players_table_shell_open(): void
{
    echo k2_lb_table_anchor_markup();
    k2_table_wrap_open(true);
}

function amiga_wc_players_table_shell_close(): void
{
    k2_table_wrap_close();
}

function amiga_wc_players_render_footnote(int $playerCount): void
{
    ?>
<p class="k2-amiga-wc-players-footnote" style="margin:0 0 2rem;color:var(--k2-text-secondary)"><?php echo number_format($playerCount); ?> players with at least one World Cup.</p>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_wc_players_render_honours(array $rows, int $playerCount): void
{
    $lbSort = k2_lb_table_sort_state(4);
    ?>
<?php amiga_wc_players_table_shell_open(); ?>
<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="<?php echo $lbSort['anchor']; ?>" data-k2-default-sort="<?php echo $lbSort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort['sort_dir']); ?>"<?php echo k2_table_skip_initial_sort_attr(4); ?>>
<thead>
    <tr>
        <th<?php echo k2_lb_th(0, $lbSort, ''); ?> data-k2-sort="number">Rank</th>
        <th<?php echo k2_lb_th(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort="text">Player</th>
        <th<?php echo k2_lb_th_elo(2, $lbSort); ?> data-k2-sort="number"<?php echo k2_lb_elo_column_help_attrs(); ?>>Elo</th>
        <th<?php echo k2_lb_th(3, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_played(), ENT_QUOTES, 'UTF-8'); ?>">WCs</th>
        <th<?php echo k2_lb_th(4, $lbSort, 'k2-lb-honours-medal-th'); ?> data-k2-sort="number" data-k2-tooltip-label="WC gold" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_gold(), ENT_QUOTES, 'UTF-8'); ?>"><?php echo k2_status_league_podium_medal(1); ?><span class="visually-hidden">WC gold</span></th>
        <th<?php echo k2_lb_th(5, $lbSort, 'k2-lb-honours-medal-th'); ?> data-k2-sort="number" data-k2-tooltip-label="WC silver" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_silver(), ENT_QUOTES, 'UTF-8'); ?>"><?php echo k2_status_league_podium_medal(2); ?><span class="visually-hidden">WC silver</span></th>
        <th<?php echo k2_lb_th(6, $lbSort, 'k2-lb-honours-medal-th'); ?> data-k2-sort="number" data-k2-tooltip-label="WC bronze" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_bronze(), ENT_QUOTES, 'UTF-8'); ?>"><?php echo k2_status_league_podium_medal(3); ?><span class="visually-hidden">WC bronze</span></th>
        <th<?php echo k2_lb_th(7, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_podiums(), ENT_QUOTES, 'UTF-8'); ?>">Podiums</th>
        <th<?php echo k2_lb_th(8, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_perfect_events(), ENT_QUOTES, 'UTF-8'); ?>">Perfect</th>
    </tr>
</thead>
<tbody class="black">
<?php
    $rank = 1;
    foreach ($rows as $row) {
        $playerId = (int) $row['player_id'];
        $playerName = (string) $row['player_name'];
        ?>
    <tr>
        <td<?php echo k2_lb_td(0, $lbSort); ?>><?php echo $rank; ?></td>
        <td<?php echo k2_lb_td(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort-value="<?php echo k2_h($playerName); ?>"><?php echo k2_amiga_lb_player_cell($playerId, $playerName, (string) ($row['country'] ?? '')); ?></td>
        <td<?php echo k2_lb_td(2, $lbSort); ?>><?php echo k2_fmt_int($row['rating']); ?></td>
        <td<?php echo k2_lb_td(3, $lbSort); ?>><?php echo (int) $row['wc_played']; ?></td>
        <td<?php echo k2_lb_td(4, $lbSort); ?>><?php echo (int) $row['wc_gold']; ?></td>
        <td<?php echo k2_lb_td(5, $lbSort); ?>><?php echo (int) $row['wc_silver']; ?></td>
        <td<?php echo k2_lb_td(6, $lbSort); ?>><?php echo (int) $row['wc_bronze']; ?></td>
        <td<?php echo k2_lb_td(7, $lbSort); ?>><?php echo (int) $row['wc_podiums']; ?></td>
        <td<?php echo k2_lb_td(8, $lbSort); ?>><?php echo (int) ($row['wc_perfect_events'] ?? 0); ?></td>
    </tr>
        <?php
        $rank++;
    }
    ?>
</tbody>
</table>
<?php amiga_wc_players_table_shell_close(); ?>
<?php amiga_wc_players_render_footnote($playerCount); ?>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_wc_players_render_results(array $rows, int $playerCount): void
{
    $lbSort = k2_lb_table_sort_state(8);
    ?>
<?php amiga_wc_players_table_shell_open(); ?>
<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="<?php echo $lbSort['anchor']; ?>" data-k2-default-sort="<?php echo $lbSort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort['sort_dir']); ?>"<?php echo k2_table_skip_initial_sort_attr(8); ?>>
<thead>
    <tr>
        <th<?php echo k2_lb_th(0, $lbSort, ''); ?> data-k2-sort="number">Rank</th>
        <th<?php echo k2_lb_th(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort="text">Player</th>
        <th<?php echo k2_lb_th_elo(2, $lbSort); ?> data-k2-sort="number"<?php echo k2_lb_elo_column_help_attrs(); ?>>Elo</th>
        <th<?php echo k2_lb_th(3, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_played(), ENT_QUOTES, 'UTF-8'); ?>">WCs</th>
        <th<?php echo k2_lb_th(4, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th<?php echo k2_lb_th(5, $lbSort, ''); ?> data-k2-sort="number">W</th>
        <th<?php echo k2_lb_th(6, $lbSort, ''); ?> data-k2-sort="number">D</th>
        <th<?php echo k2_lb_th(7, $lbSort, ''); ?> data-k2-sort="number">L</th>
        <th<?php echo k2_lb_th(8, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Points" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_match_points(), ENT_QUOTES, 'UTF-8'); ?>">Pts</th>
        <th<?php echo k2_lb_th(9, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_points_per_game(), ENT_QUOTES, 'UTF-8'); ?>">Pts/g</th>
        <th<?php echo k2_lb_th(10, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_win_rate(), ENT_QUOTES, 'UTF-8'); ?>">Win rate</th>
    </tr>
</thead>
<tbody class="black">
<?php
    $rank = 1;
    foreach ($rows as $row) {
        $playerId = (int) $row['player_id'];
        $playerName = (string) $row['player_name'];
        $games = (int) $row['games'];
        $points = (int) $row['points'];
        $ptsPerGame = amiga_wc_lb_points_per_game($points, $games);
        $wins = (int) $row['wins'];
        $draws = (int) $row['draws'];
        $winRate = amiga_wc_lb_win_rate($wins, $draws, $games);
        ?>
    <tr>
        <td<?php echo k2_lb_td(0, $lbSort); ?>><?php echo $rank; ?></td>
        <td<?php echo k2_lb_td(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort-value="<?php echo k2_h($playerName); ?>"><?php echo k2_amiga_lb_player_cell($playerId, $playerName, (string) ($row['country'] ?? '')); ?></td>
        <td<?php echo k2_lb_td(2, $lbSort); ?>><?php echo k2_fmt_int($row['rating']); ?></td>
        <td<?php echo k2_lb_td(3, $lbSort); ?>><?php echo (int) $row['wc_played']; ?></td>
        <td<?php echo k2_lb_td(4, $lbSort); ?>><?php echo $games; ?></td>
        <td<?php echo k2_lb_td(5, $lbSort); ?>><span class="blue"><?php echo $wins; ?></span></td>
        <td<?php echo k2_lb_td(6, $lbSort); ?>><?php echo (int) $row['draws']; ?></td>
        <td<?php echo k2_lb_td(7, $lbSort); ?>><span class="red"><?php echo (int) $row['losses']; ?></span></td>
        <td<?php echo k2_lb_td(8, $lbSort); ?>><?php echo $points; ?></td>
        <td<?php echo k2_lb_td(9, $lbSort); ?>><?php echo $ptsPerGame !== null ? k2_fmt_decimal($ptsPerGame, $games) : k2_fmt_dash(); ?></td>
        <td<?php echo k2_lb_td(10, $lbSort); ?>><?php echo k2_fmt_pct_from_ratio($winRate, $games); ?></td>
    </tr>
        <?php
        $rank++;
    }
    ?>
</tbody>
</table>
<?php amiga_wc_players_table_shell_close(); ?>
<?php amiga_wc_players_render_footnote($playerCount); ?>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_wc_players_render_goals(array $rows, int $playerCount): void
{
    $lbSort = k2_lb_table_sort_state(4);
    ?>
<?php amiga_wc_players_table_shell_open(); ?>
<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="<?php echo $lbSort['anchor']; ?>" data-k2-default-sort="<?php echo $lbSort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort['sort_dir']); ?>"<?php echo k2_table_skip_initial_sort_attr(4); ?>>
<thead>
    <tr>
        <th<?php echo k2_lb_th(0, $lbSort, ''); ?> data-k2-sort="number">Rank</th>
        <th<?php echo k2_lb_th(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort="text">Player</th>
        <th<?php echo k2_lb_th_elo(2, $lbSort); ?> data-k2-sort="number"<?php echo k2_lb_elo_column_help_attrs(); ?>>Elo</th>
        <th<?php echo k2_lb_th(3, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th<?php echo k2_lb_th(4, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Goals for" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_goals_scored(), ENT_QUOTES, 'UTF-8'); ?>">GF</th>
        <th<?php echo k2_lb_th(5, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Goals against" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_goals_conceded(), ENT_QUOTES, 'UTF-8'); ?>">GA</th>
        <th<?php echo k2_lb_th(6, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Goal difference" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_goal_difference(), ENT_QUOTES, 'UTF-8'); ?>">GD</th>
        <th<?php echo k2_lb_th(7, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Goals for per game" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_goals_scored_avg(), ENT_QUOTES, 'UTF-8'); ?>">GF/g</th>
        <th<?php echo k2_lb_th(8, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Goals against per game" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_goals_conceded_avg(), ENT_QUOTES, 'UTF-8'); ?>">GA/g</th>
        <th<?php echo k2_lb_th(9, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Goal difference per game" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_goal_difference_per_game(), ENT_QUOTES, 'UTF-8'); ?>">GD/g</th>
        <th<?php echo k2_lb_th(10, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_goal_ratio(), ENT_QUOTES, 'UTF-8'); ?>">Ratio</th>
        <th<?php echo k2_lb_th(11, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_most_goals_scored(), ENT_QUOTES, 'UTF-8'); ?>">Max GF</th>
        <th<?php echo k2_lb_th(12, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_most_goals_conceded(), ENT_QUOTES, 'UTF-8'); ?>">Max GA</th>
        <th<?php echo k2_lb_th(13, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_win_margin(), ENT_QUOTES, 'UTF-8'); ?>">Max win</th>
        <th<?php echo k2_lb_th(14, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_loss_margin(), ENT_QUOTES, 'UTF-8'); ?>">Max loss</th>
        <th<?php echo k2_lb_th(15, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_goal_sum(), ENT_QUOTES, 'UTF-8'); ?>">Max sum</th>
        <th<?php echo k2_lb_th(16, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_biggest_draw(), ENT_QUOTES, 'UTF-8'); ?>">Max draw</th>
    </tr>
</thead>
<tbody class="black">
<?php
    $rank = 1;
    foreach ($rows as $row) {
        $playerId = (int) $row['player_id'];
        $playerName = (string) $row['player_name'];
        $games = (int) $row['games'];
        $gf = (int) $row['goals_for'];
        $ga = (int) $row['goals_against'];
        $gd = $gf - $ga;
        $gfPer = amiga_wc_lb_goals_per_game($gf, $games);
        $gaPer = amiga_wc_lb_goals_per_game($ga, $games);
        $gdPer = amiga_wc_lb_goals_per_game($gd, $games);
        ?>
    <tr>
        <td<?php echo k2_lb_td(0, $lbSort); ?>><?php echo $rank; ?></td>
        <td<?php echo k2_lb_td(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort-value="<?php echo k2_h($playerName); ?>"><?php echo k2_amiga_lb_player_cell($playerId, $playerName, (string) ($row['country'] ?? '')); ?></td>
        <td<?php echo k2_lb_td(2, $lbSort); ?>><?php echo k2_fmt_int($row['rating']); ?></td>
        <td<?php echo k2_lb_td(3, $lbSort); ?>><?php echo k2_fmt_games_played($games); ?></td>
        <td<?php echo k2_lb_td(4, $lbSort); ?>><span class="blue"><?php echo k2_fmt_count($gf, $games); ?></span></td>
        <td<?php echo k2_lb_td(5, $lbSort); ?>><span class="red"><?php echo k2_fmt_count($ga, $games); ?></span></td>
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
        <td<?php echo k2_lb_td(14, $lbSort); ?>><?php
            if (!k2_derived_games_started($games) || (int) ($row['losses'] ?? 0) === 0) {
                echo k2_fmt_dash();
            } else {
                echo k2_fmt_count($row['biggest_loss_difference'] ?? 0, $games);
            }
        ?></td>
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
<?php amiga_wc_players_table_shell_close(); ?>
<?php amiga_wc_players_render_footnote($playerCount); ?>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_wc_players_render_dds(array $rows, int $playerCount): void
{
    $lbSort = k2_lb_table_sort_state(4);
    ?>
<?php amiga_wc_players_table_shell_open(); ?>
<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="<?php echo $lbSort['anchor']; ?>" data-k2-default-sort="<?php echo $lbSort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort['sort_dir']); ?>"<?php echo k2_table_skip_initial_sort_attr(4); ?>>
<thead>
    <tr>
        <th<?php echo k2_lb_th(0, $lbSort, ''); ?> data-k2-sort="number">Rank</th>
        <th<?php echo k2_lb_th(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort="text">Player</th>
        <th<?php echo k2_lb_th_elo(2, $lbSort); ?> data-k2-sort="number"<?php echo k2_lb_elo_column_help_attrs(); ?>>Elo</th>
        <th<?php echo k2_lb_th(3, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th<?php echo k2_lb_th(4, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_double_digits(), ENT_QUOTES, 'UTF-8'); ?>">Double Digits</th>
        <th<?php echo k2_lb_th(5, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_clean_sheets(), ENT_QUOTES, 'UTF-8'); ?>">Clean Sheets</th>
        <th<?php echo k2_lb_th(6, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Double Digits ratio" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_double_digits_ratio(), ENT_QUOTES, 'UTF-8'); ?>">DD Ratio</th>
        <th<?php echo k2_lb_th(7, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Clean Sheets ratio" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_clean_sheets_ratio(), ENT_QUOTES, 'UTF-8'); ?>">CS Ratio</th>
        <th<?php echo k2_lb_th(8, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="DD conceded" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_double_digits_conceded(), ENT_QUOTES, 'UTF-8'); ?>">DD C</th>
        <th<?php echo k2_lb_th(9, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="CS conceded" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_clean_sheets_conceded(), ENT_QUOTES, 'UTF-8'); ?>">CS C</th>
        <th<?php echo k2_lb_th(10, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="DD conceded ratio" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_double_digits_conceded_ratio(), ENT_QUOTES, 'UTF-8'); ?>">DD C Ratio</th>
        <th<?php echo k2_lb_th(11, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="CS conceded ratio" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_clean_sheets_conceded_ratio(), ENT_QUOTES, 'UTF-8'); ?>">CS C Ratio</th>
    </tr>
</thead>
<tbody class="black">
<?php
    $rank = 1;
    foreach ($rows as $row) {
        $playerId = (int) $row['player_id'];
        $playerName = (string) $row['player_name'];
        $games = (int) $row['games'];
        ?>
    <tr>
        <td<?php echo k2_lb_td(0, $lbSort); ?>><?php echo $rank; ?></td>
        <td<?php echo k2_lb_td(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort-value="<?php echo k2_h($playerName); ?>"><?php echo k2_amiga_lb_player_cell($playerId, $playerName, (string) ($row['country'] ?? '')); ?></td>
        <td<?php echo k2_lb_td(2, $lbSort); ?>><?php echo k2_fmt_int($row['rating']); ?></td>
        <td<?php echo k2_lb_td(3, $lbSort); ?>><?php echo k2_fmt_games_played($games); ?></td>
        <td<?php echo k2_lb_td(4, $lbSort); ?>><span class="blue"><?php echo k2_fmt_count($row['double_digits'] ?? 0, $games); ?></span></td>
        <td<?php echo k2_lb_td(5, $lbSort); ?>><?php echo k2_fmt_count($row['clean_sheets'] ?? 0, $games); ?></td>
        <td<?php echo k2_lb_td(6, $lbSort); ?>><?php echo k2_fmt_pct_from_ratio($row['double_digits_ratio'] ?? null, $games); ?></td>
        <td<?php echo k2_lb_td(7, $lbSort); ?>><?php echo k2_fmt_pct_from_ratio($row['clean_sheets_ratio'] ?? null, $games); ?></td>
        <td<?php echo k2_lb_td(8, $lbSort); ?>><span class="red"><?php echo k2_fmt_count($row['double_digits_conceded'] ?? 0, $games); ?></span></td>
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
<?php amiga_wc_players_table_shell_close(); ?>
<?php amiga_wc_players_render_footnote($playerCount); ?>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_wc_players_render_opponents(array $rows, int $playerCount): void
{
    $lbSort = k2_lb_table_sort_state(4);
?>
<?php amiga_wc_players_table_shell_open(); ?>
<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="<?php echo $lbSort['anchor']; ?>" data-k2-default-sort="<?php echo $lbSort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort['sort_dir']); ?>"<?php echo k2_table_skip_initial_sort_attr(4); ?>>
<thead>
    <tr>
        <th<?php echo k2_lb_th(0, $lbSort, ''); ?> data-k2-sort="number">Rank</th>
        <th<?php echo k2_lb_th(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort="text">Player</th>
        <th<?php echo k2_lb_th_elo(2, $lbSort); ?> data-k2-sort="number"<?php echo k2_lb_elo_column_help_attrs(); ?>>Elo</th>
        <th<?php echo k2_lb_th(3, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th<?php echo k2_lb_th(4, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_opponents(), ENT_QUOTES, 'UTF-8'); ?>">Opponents</th>
        <th<?php echo k2_lb_th(5, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_victims(), ENT_QUOTES, 'UTF-8'); ?>">Victims</th>
        <th<?php echo k2_lb_th(6, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Double Digit victims" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_dd_victims(), ENT_QUOTES, 'UTF-8'); ?>">DD Victims</th>
        <th<?php echo k2_lb_th(7, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Clean Sheet victims" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_cs_victims(), ENT_QUOTES, 'UTF-8'); ?>">CS Victims</th>
        <th<?php echo k2_lb_th(8, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_opponent_countries_faced(), ENT_QUOTES, 'UTF-8'); ?>">Countries faced</th>
        <th<?php echo k2_lb_th(9, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_opponent_countries_beaten(), ENT_QUOTES, 'UTF-8'); ?>">Countries beaten</th>
    </tr>
</thead>
<tbody class="black">
<?php
    $rank = 1;
    foreach ($rows as $row) {
        $playerId = (int) $row['player_id'];
        $playerName = (string) $row['player_name'];
        $games = (int) $row['games'];
        ?>
    <tr>
        <td<?php echo k2_lb_td(0, $lbSort); ?>><?php echo $rank; ?></td>
        <td<?php echo k2_lb_td(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort-value="<?php echo k2_h($playerName); ?>"><?php echo k2_amiga_lb_player_cell($playerId, $playerName, (string) ($row['country'] ?? '')); ?></td>
        <td<?php echo k2_lb_td(2, $lbSort); ?>><?php echo k2_fmt_int($row['rating']); ?></td>
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
<?php amiga_wc_players_table_shell_close(); ?>
<?php amiga_wc_players_render_footnote($playerCount); ?>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_wc_players_render_view(string $view, array $rows, int $playerCount): void
{
    if ($view === 'honours') {
        amiga_wc_players_render_honours($rows, $playerCount);

        return;
    }
    if ($view === 'results') {
        amiga_wc_players_render_results($rows, $playerCount);

        return;
    }
    if ($view === 'goals') {
        amiga_wc_players_render_goals($rows, $playerCount);

        return;
    }
    if ($view === 'dds') {
        amiga_wc_players_render_dds($rows, $playerCount);

        return;
    }
    if ($view === 'opponents') {
        amiga_wc_players_render_opponents($rows, $playerCount);

        return;
    }
    throw new InvalidArgumentException('Unknown World Cup players view: ' . $view);
}
