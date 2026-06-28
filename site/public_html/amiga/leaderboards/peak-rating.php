<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga ladder — Peak rating</title>
<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; ?>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2AmigaHubTabActive = 'leaderboards';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_snapshot_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_amiga_country_flag.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_column_help.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_profile_blocks.php';
include __DIR__ . '/../../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$ctx = amiga_lb_context($con);

$result = amiga_lb_query_peak_rating($con, $ctx);

mysqli_close($con);

$k2AmigaLbWingActive = 'peak-rating';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_nav.php';
?>

<?php k2_table_wrap_open(true); ?>

<?php $lbSort = k2_lb_table_sort_state(4); ?>
<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="<?php echo $lbSort['anchor']; ?>" data-k2-default-sort="<?php echo $lbSort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort['sort_dir']); ?>"<?php echo k2_table_skip_initial_sort_attr(4); ?>>

<thead>
    <tr>
        <th<?php echo k2_lb_th(0, $lbSort, ''); ?> data-k2-sort="number">#</th>
        <th<?php echo k2_lb_th(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort="text">Player</th>
        <th<?php echo k2_lb_th_elo(2, $lbSort); ?> data-k2-sort="number"<?php echo k2_lb_elo_column_help_attrs(); ?>>Elo</th>
        <th<?php echo k2_lb_th(3, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th<?php echo k2_lb_th(4, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_peak(), ENT_QUOTES, 'UTF-8'); ?>">Peak</th>
        <th<?php echo k2_lb_th(5, $lbSort, 'k2-table-cell--right'); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_peak_rating_date(), ENT_QUOTES, 'UTF-8'); ?>">Peak date</th>
        <th<?php echo k2_lb_th(6, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_peak_elo_rank(), ENT_QUOTES, 'UTF-8'); ?>">Peak rank</th>
        <th<?php echo k2_lb_th(7, $lbSort, 'k2-table-cell--right'); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_peak_elo_rank_date(), ENT_QUOTES, 'UTF-8'); ?>">Peak rank date</th>
        <th<?php echo k2_lb_th(8, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_nadir(), ENT_QUOTES, 'UTF-8'); ?>">Nadir</th>
        <th<?php echo k2_lb_th(9, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_opponent_avg(), ENT_QUOTES, 'UTF-8'); ?>">Opponent Avg.</th>
        <th<?php echo k2_lb_th(10, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_highest_victim(), ENT_QUOTES, 'UTF-8'); ?>">Highest Victim</th>
        <th<?php echo k2_lb_th(11, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_lowest_culprit(), ENT_QUOTES, 'UTF-8'); ?>">Lowest Culprit</th>
    </tr>
</thead>

<tbody class="black">
<?php
$rank = 1;
while ($row = mysqli_fetch_assoc($result)) {
    $games = (int) $row['NumberGames'];
    $playerName = (string) $row['Name'];
    ?>
    <tr>
        <td<?php echo k2_lb_td(0, $lbSort); ?>><?php echo $rank; ?></td>
        <td<?php echo k2_lb_td(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort-value="<?php echo k2_h($playerName); ?>"><?php echo k2_amiga_lb_player_cell((int) $row['ID'], $playerName, (string) ($row['Country'] ?? '')); ?></td>
        <td<?php echo k2_lb_td(2, $lbSort); ?>><?php echo k2_fmt_int($row['Rating']); ?></td>
        <td<?php echo k2_lb_td(3, $lbSort); ?>><?php echo k2_fmt_games_played($games); ?></td>
        <td<?php echo k2_lb_td(4, $lbSort); ?>><?php echo k2_fmt_peak_rating($row['PeakRating']); ?></td>
        <td<?php echo k2_lb_td(5, $lbSort, 'k2-table-cell--right'); ?> data-k2-sort-value="<?php echo amiga_profile_event_date_sort_value(['event_date' => $row['peak_rating_date'] ?? null]); ?>"><?php echo amiga_profile_format_event_date($row['peak_rating_date'] ?? null); ?></td>
        <td<?php echo k2_lb_td(6, $lbSort); ?><?php echo amiga_lb_peak_elo_rank_cell_sort_attrs($row['peak_elo_rank'] ?? null, $row['peak_elo_rank_date'] ?? null); ?>><?php echo k2_fmt_peak_elo_rank($row['peak_elo_rank'] ?? null); ?></td>
        <td<?php echo k2_lb_td(7, $lbSort, 'k2-table-cell--right'); ?> data-k2-sort-value="<?php echo amiga_profile_event_date_sort_value(['event_date' => $row['peak_elo_rank_date'] ?? null]); ?>"><?php echo amiga_profile_format_event_date($row['peak_elo_rank_date'] ?? null); ?></td>
        <td<?php echo k2_lb_td(8, $lbSort); ?>><?php echo k2_fmt_nadir_rating($row['LowestRating']); ?></td>
        <td<?php echo k2_lb_td(9, $lbSort); ?>><?php echo k2_fmt_lb_stat($row['AverageOpponentRating'], $games); ?></td>
        <td<?php echo k2_lb_td(10, $lbSort); ?>><?php echo k2_fmt_lb_stat($row['HighestRatedVictim'], $games); ?></td>
        <td<?php echo k2_lb_td(11, $lbSort); ?>><?php echo k2_fmt_lb_stat($row['LowestRatedCulprit'], $games, 5000.0); ?></td>
    </tr>
    <?php
    $rank++;
}
?>
</tbody>

</table>

</div>

</div><!-- .k2-page-nav -->

</body>
</html>
