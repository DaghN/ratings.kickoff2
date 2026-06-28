<?php
/**
 * Amiga Opponents wing — W/D/L, Goals, and DDs table bodies (stored matchup truth).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/lb_column_help.php';
require_once __DIR__ . '/amiga_player_load.php';
require_once __DIR__ . '/k2_amiga_country_flag.php';
require_once __DIR__ . '/amiga_player_opponents_load.php';
require_once __DIR__ . '/amiga_player_opponents_lib.php';
require_once __DIR__ . '/k2_table_helpers.php';

function amiga_player_opponents_dds_ratio_cell(float $ratio): string
{
    return $ratio == 0.0 ? '0%' : number_format(100 * $ratio, 1) . '%';
}

const AMIGA_PLAYER_OPPONENTS_TABLE_ANCHOR_COL = 3;
const AMIGA_PLAYER_OPPONENTS_TABLE_DEFAULT_SORT_COL = 3;

/** @return array{anchor: int, sort_col: int, sort_dir: string, class: string} */
function amiga_player_opponents_table_sort_state(): array
{
    return [
        'anchor' => AMIGA_PLAYER_OPPONENTS_TABLE_ANCHOR_COL,
        'sort_col' => k2_table_default_sort_col_from_request(AMIGA_PLAYER_OPPONENTS_TABLE_DEFAULT_SORT_COL),
        'sort_dir' => k2_table_default_sort_dir_from_request('desc'),
        'class' => k2_table_ranked_sortable_class('k2-table--player-matchup'),
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_opponents_render_wdl_table_from_rows(array $rows, int $playerId): void
{
    $sort = amiga_player_opponents_table_sort_state();
    ?>
<?php k2_table_wrap_open(true); ?>
<table class="<?php echo k2_h($sort['class']); ?>" data-k2-table="sortable" data-k2-anchor-col="<?php echo $sort['anchor']; ?>" data-k2-default-sort="<?php echo $sort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($sort['sort_dir']); ?>">
<thead>
    <tr>
        <th<?php echo k2_lb_th(0, $sort, 'k2-table-cell--left'); ?> data-k2-sort="text">Opponent</th>
        <th<?php echo k2_lb_th_elo(1, $sort); ?> data-k2-sort="number"<?php echo k2_lb_elo_column_help_attrs(); ?>>Elo</th>
        <th<?php echo k2_lb_th_country(2, $sort); ?> data-k2-sort="text">Country</th>
        <th<?php echo k2_lb_th(3, $sort, ''); ?> data-k2-sort="number" data-k2-help="Rated games against this opponent.">Games</th>
        <th data-k2-sort="number" data-k2-help="Wins against this opponent.">Wins</th>
        <th data-k2-sort="number" data-k2-help="Draws against this opponent.">Draws</th>
        <th data-k2-sort="number" data-k2-help="Losses against this opponent.">Losses</th>
        <th data-k2-sort="number" data-k2-help="Share of games won against this opponent.">Win Ratio</th>
        <th data-k2-sort="number" data-k2-help="Share of games drawn against this opponent.">Draw Ratio</th>
        <th data-k2-sort="number" data-k2-help="Share of games lost against this opponent.">Loss Ratio</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Performance rating" data-k2-help="The Elo level implied by the hero's results against this opponent (frozen pre-game opponent ratings). Needs at least 2 games and a non-perfect record.">Perf. rating</th>
    </tr>
</thead>
<tbody>
	<?php foreach ($rows as $row) {
	    $opponentId = (int) $row['opponent_id'];
	    $opponentName = (string) $row['opponent_name'];
	    $games = (int) $row['games'];
	    $wins = (int) $row['wins'];
	    $draws = (int) $row['draws'];
	    $losses = (int) $row['losses'];
	    $winRatio = amiga_player_opponents_matchup_ratio($wins, $games);
	    $drawRatio = amiga_player_opponents_matchup_ratio($draws, $games);
	    $lossRatio = amiga_player_opponents_matchup_ratio($losses, $games);
	    $perfRating = isset($row['performance_rating']) && $row['performance_rating'] !== null
	        ? (int) round((float) $row['performance_rating'])
	        : null;
	    ?>
    <tr>
        <td<?php echo k2_lb_td(0, $sort, 'k2-table-cell--left'); ?>><?php echo k2_amiga_player_link($opponentId, $opponentName); ?></td>
        <td<?php echo k2_lb_td(1, $sort); ?>><?php echo k2_fmt_int($row['opponent_rating']); ?></td>
        <?php echo k2_lb_td_country_open(2, $sort, (string) ($row['opponent_country'] ?? '')); ?></td>
        <td<?php echo k2_lb_td(3, $sort); ?>><?php echo amiga_player_opponents_games_cell_html($playerId, $opponentId, $games); ?></td>
        <td><?php if ($wins != 0) {
            echo "<span class='blue'>";
            echo $wins;
            echo '</span>';
        } else {
            echo '0';
        } ?></td>
        <td><?php echo $draws; ?></td>
        <td><?php if ($losses != 0) {
            echo "<span class='red'>";
            echo $losses;
            echo '</span>';
        } else {
            echo '0';
        } ?></td>
        <td><?php echo $wins != 0 ? number_format(100 * $winRatio, 1) . '%' : '0%'; ?></td>
        <td><?php echo number_format(100 * $drawRatio, 1);
        echo '%'; ?></td>
        <td><?php echo $losses != 0 ? number_format(100 * $lossRatio, 1) . '%' : '0%'; ?></td>
        <td<?php echo $perfRating === null ? ' data-k2-sort-value="-1"' : ''; ?>><?php echo $perfRating !== null ? k2_fmt_int($perfRating) : '—'; ?></td>
    </tr>
    <?php } ?>
</tbody>
</table>
<?php k2_table_wrap_close(); ?>
    <?php
}

function amiga_player_opponents_render_wdl_table(mysqli $con, int $playerId, ?AmigaSnapshotContext $ctx = null): void
{
    amiga_player_opponents_render_wdl_table_from_rows(
        amiga_player_opponents_matchup_rows($con, $playerId, $ctx),
        $playerId
    );
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_opponents_render_goals_table_from_rows(array $rows, int $playerId): void
{
    $sort = amiga_player_opponents_table_sort_state();
    ?>
<?php k2_table_wrap_open(true); ?>
<table class="<?php echo k2_h($sort['class']); ?>" data-k2-table="sortable" data-k2-anchor-col="<?php echo $sort['anchor']; ?>" data-k2-default-sort="<?php echo $sort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($sort['sort_dir']); ?>">
<thead>
    <tr>
        <th<?php echo k2_lb_th(0, $sort, 'k2-table-cell--left'); ?> data-k2-sort="text">Opponent</th>
        <th<?php echo k2_lb_th_elo(1, $sort); ?> data-k2-sort="number"<?php echo k2_lb_elo_column_help_attrs(); ?>>Elo</th>
        <th<?php echo k2_lb_th_country(2, $sort); ?> data-k2-sort="text">Country</th>
        <th<?php echo k2_lb_th(3, $sort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Goals for" data-k2-help="Goals scored against this opponent.">GF</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Goals against" data-k2-help="Goals conceded against this opponent.">GA</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Goals scored per game" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goals_scored_avg(), ENT_QUOTES, 'UTF-8'); ?>">GF/g</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Goals conceded per game" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goals_conceded_avg(), ENT_QUOTES, 'UTF-8'); ?>">GA/g</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goal_ratio(), ENT_QUOTES, 'UTF-8'); ?>">Ratio</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Total goals per game" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_total_goals_per_game(), ENT_QUOTES, 'UTF-8'); ?>">TG/g</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_most_scored(), ENT_QUOTES, 'UTF-8'); ?>">Max GF</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_most_conceded(), ENT_QUOTES, 'UTF-8'); ?>">Max GA</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_win_margin(), ENT_QUOTES, 'UTF-8'); ?>">Max win</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_loss_margin(), ENT_QUOTES, 'UTF-8'); ?>">Max loss</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goal_sum(), ENT_QUOTES, 'UTF-8'); ?>">Max sum</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_biggest_draw(), ENT_QUOTES, 'UTF-8'); ?>">Draw</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_least_scored(), ENT_QUOTES, 'UTF-8'); ?>">Min GF</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_least_conceded(), ENT_QUOTES, 'UTF-8'); ?>">Min GA</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_least_goal_sum(), ENT_QUOTES, 'UTF-8'); ?>">Min sum</th>
    </tr>
</thead>
<tbody>
	<?php foreach ($rows as $row) {
	    $opponentId = (int) $row['opponent_id'];
	    $opponentName = (string) $row['opponent_name'];
	    $games = (int) $row['games'];
	    $goalsFor = (int) $row['goals_for'];
	    $goalsAgainst = (int) $row['goals_against'];
	    $averageFor = $games > 0 ? $goalsFor / $games : 0.0;
	    $averageAgainst = $games > 0 ? $goalsAgainst / $games : 0.0;
	    $averageTotal = $games > 0 ? ($goalsFor + $goalsAgainst) / $games : 0.0;
	    $goalRatio = amiga_player_opponents_goal_ratio($goalsFor, $goalsAgainst);
	    $mostScored = (int) ($row['max_goals_for'] ?? 0);
	    $mostConceded = (int) ($row['max_goals_against'] ?? 0);
	    $leastScored = (int) ($row['min_goals_for'] ?? 0);
	    $leastConceded = (int) ($row['min_goals_against'] ?? 0);
	    $biggestWin = isset($row['max_win_margin']) && $row['max_win_margin'] !== null ? (int) $row['max_win_margin'] : null;
	    $biggestLoss = isset($row['max_loss_margin']) && $row['max_loss_margin'] !== null ? (int) $row['max_loss_margin'] : null;
	    $biggestGoalSum = (int) ($row['max_goal_sum'] ?? 0);
	    $smallestGoalSum = (int) ($row['min_goal_sum'] ?? 0);
	    $biggestDraw = isset($row['max_draw_goals']) && $row['max_draw_goals'] !== null ? (int) $row['max_draw_goals'] : null;
	    $numberDraws = (int) ($row['draws'] ?? 0);
	    $drawSort = $numberDraws > 0 && $biggestDraw !== null ? $biggestDraw : -1;
	    $drawDisplay = $numberDraws > 0 && $biggestDraw !== null ? $biggestDraw . '-' . $biggestDraw : '-';
	    ?>
    <tr>
        <td<?php echo k2_lb_td(0, $sort, 'k2-table-cell--left'); ?>><?php echo k2_amiga_player_link($opponentId, $opponentName); ?></td>
        <td<?php echo k2_lb_td(1, $sort); ?>><?php echo k2_fmt_int($row['opponent_rating']); ?></td>
        <?php echo k2_lb_td_country_open(2, $sort, (string) ($row['opponent_country'] ?? '')); ?></td>
        <td<?php echo k2_lb_td(3, $sort); ?>><?php echo amiga_player_opponents_games_cell_html($playerId, $opponentId, $games); ?></td>
        <td><?php if ($goalsFor != 0) {
            echo "<span class='blue'>";
            echo $goalsFor;
            echo '</span>';
        } else {
            echo '0';
        } ?></td>
        <td><?php if ($goalsAgainst != 0) {
            echo "<span class='red'>";
            echo $goalsAgainst;
            echo '</span>';
        } else {
            echo '0';
        } ?></td>
        <td><?php echo number_format($averageFor, 2); ?></td>
        <td><?php echo number_format($averageAgainst, 2); ?></td>
        <td><?php
            if ($goalRatio < 0) {
                echo '-';
            } else {
                echo number_format($goalRatio, 2);
            }
        ?></td>
        <td><?php echo number_format($averageTotal, 2); ?></td>
        <td><?php echo (string) $mostScored; ?></td>
        <td><?php echo (string) $mostConceded; ?></td>
        <td><?php echo $biggestWin !== null ? (string) $biggestWin : '—'; ?></td>
        <td><?php echo $biggestLoss !== null ? (string) $biggestLoss : '—'; ?></td>
        <td><?php echo (string) $biggestGoalSum; ?></td>
        <td data-k2-sort-value="<?php echo $drawSort; ?>"><?php echo $drawDisplay; ?></td>
        <td><?php echo (string) $leastScored; ?></td>
        <td><?php echo (string) $leastConceded; ?></td>
        <td><?php echo (string) $smallestGoalSum; ?></td>
    </tr>
    <?php } ?>
</tbody>
</table>
<?php k2_table_wrap_close(); ?>
    <?php
}

function amiga_player_opponents_render_goals_table(mysqli $con, int $playerId, ?AmigaSnapshotContext $ctx = null): void
{
    amiga_player_opponents_render_goals_table_from_rows(
        amiga_player_opponents_matchup_rows($con, $playerId, $ctx),
        $playerId
    );
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_opponents_render_dds_table_from_rows(array $rows, int $playerId): void
{
    $sort = amiga_player_opponents_table_sort_state();
    ?>
<?php k2_table_wrap_open(true); ?>
<table class="<?php echo k2_h($sort['class']); ?>" data-k2-table="sortable" data-k2-anchor-col="<?php echo $sort['anchor']; ?>" data-k2-default-sort="<?php echo $sort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($sort['sort_dir']); ?>">
<thead>
    <tr>
        <th<?php echo k2_lb_th(0, $sort, 'k2-table-cell--left'); ?> data-k2-sort="text">Opponent</th>
        <th<?php echo k2_lb_th_elo(1, $sort); ?> data-k2-sort="number"<?php echo k2_lb_elo_column_help_attrs(); ?>>Elo</th>
        <th<?php echo k2_lb_th_country(2, $sort); ?> data-k2-sort="text">Country</th>
        <th<?php echo k2_lb_th(3, $sort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_double_digits(), ENT_QUOTES, 'UTF-8'); ?>">Double Digits</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_clean_sheets(), ENT_QUOTES, 'UTF-8'); ?>">Clean Sheets</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Double Digits ratio" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_double_digits_ratio(), ENT_QUOTES, 'UTF-8'); ?>">DD Ratio</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Clean Sheets ratio" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_clean_sheets_ratio(), ENT_QUOTES, 'UTF-8'); ?>">CS Ratio</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_double_digits_conceded(), ENT_QUOTES, 'UTF-8'); ?>">DD conceded</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_clean_sheets_conceded(), ENT_QUOTES, 'UTF-8'); ?>">CS conceded</th>
        <th data-k2-sort="number" data-k2-tooltip-label="DD conceded ratio" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_double_digits_conceded_ratio(), ENT_QUOTES, 'UTF-8'); ?>">DD C Ratio</th>
        <th data-k2-sort="number" data-k2-tooltip-label="CS conceded ratio" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_clean_sheets_conceded_ratio(), ENT_QUOTES, 'UTF-8'); ?>">CS C Ratio</th>
    </tr>
</thead>
<tbody>
	<?php foreach ($rows as $row) {
	    $opponentId = (int) $row['opponent_id'];
	    $opponentName = (string) $row['opponent_name'];
	    $games = (int) $row['games'];
	    $doubleDigits = (int) ($row['double_digits'] ?? 0);
	    $doubleDigitsConceded = (int) ($row['double_digits_conceded'] ?? 0);
	    $cleanSheets = (int) ($row['clean_sheets'] ?? 0);
	    $cleanSheetsConceded = (int) ($row['clean_sheets_conceded'] ?? 0);
	    $ddRatio = amiga_player_opponents_matchup_ratio($doubleDigits, $games);
	    $ddConcededRatio = amiga_player_opponents_matchup_ratio($doubleDigitsConceded, $games);
	    $csRatio = amiga_player_opponents_matchup_ratio($cleanSheets, $games);
	    $csConcededRatio = amiga_player_opponents_matchup_ratio($cleanSheetsConceded, $games);
	    ?>
    <tr>
        <td<?php echo k2_lb_td(0, $sort, 'k2-table-cell--left'); ?>><?php echo k2_amiga_player_link($opponentId, $opponentName); ?></td>
        <td<?php echo k2_lb_td(1, $sort); ?>><?php echo k2_fmt_int($row['opponent_rating']); ?></td>
        <?php echo k2_lb_td_country_open(2, $sort, (string) ($row['opponent_country'] ?? '')); ?></td>
        <td<?php echo k2_lb_td(3, $sort); ?>><?php echo amiga_player_opponents_games_cell_html($playerId, $opponentId, $games); ?></td>
        <td><?php if ($doubleDigits != 0) {
            echo "<span class='blue'>";
            echo $doubleDigits;
            echo '</span>';
        } else {
            echo '0';
        } ?></td>
        <td><?php echo $cleanSheets; ?></td>
        <td><?php echo amiga_player_opponents_dds_ratio_cell($ddRatio); ?></td>
        <td><?php echo amiga_player_opponents_dds_ratio_cell($csRatio); ?></td>
        <td><?php if ($doubleDigitsConceded != 0) {
            echo "<span class='red'>";
            echo $doubleDigitsConceded;
            echo '</span>';
        } else {
            echo '0';
        } ?></td>
        <td><?php echo $cleanSheetsConceded; ?></td>
        <td><?php echo amiga_player_opponents_dds_ratio_cell($ddConcededRatio); ?></td>
        <td><?php echo amiga_player_opponents_dds_ratio_cell($csConcededRatio); ?></td>
    </tr>
    <?php } ?>
</tbody>
</table>
<?php k2_table_wrap_close(); ?>
    <?php
}

function amiga_player_opponents_render_dds_table(mysqli $con, int $playerId, ?AmigaSnapshotContext $ctx = null): void
{
    amiga_player_opponents_render_dds_table_from_rows(
        amiga_player_opponents_matchup_rows($con, $playerId, $ctx),
        $playerId
    );
}
