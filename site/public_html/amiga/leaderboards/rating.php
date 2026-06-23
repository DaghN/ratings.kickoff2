<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga ladder — Elo rating</title>
<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_table_helpers.php'; k2_table_sortable_assets_enqueue(true); ?>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2AmigaHubTabActive = 'leaderboards';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';
?>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_snapshot_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_column_help.php';
include __DIR__ . '/../../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$ctx = amiga_lb_context($con);

$result = amiga_lb_query_career(
    $con,
    $ctx,
    'SELECT p.id AS ID, p.name AS Name, s.Rating, s.NumberGames, s.NumberWins, s.NumberDraws, s.NumberLosses, '
    . 's.WinRatio, s.DrawRatio, s.LossRatio, s.AverageOpponentRating, p.country AS Country ',
    'ORDER BY s.Rating DESC'
);
$gameCount = amiga_lb_games_count($con, $ctx);

mysqli_close($con);

$k2AmigaLbWingActive = 'rating';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_nav.php';
?>

<?php k2_table_wrap_open(true); ?>

<?php
$k2LbAnchorCol = 2;
$k2LbDefaultSortCol = 2;
?>
<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="2" data-k2-default-sort="2" data-k2-default-direction="desc">

<thead>
    <tr>
        <th data-k2-sort="number">Rank</th>
        <th class="k2-table-cell--left" data-k2-sort="text">Player</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_elo_rating(), ENT_QUOTES, 'UTF-8'); ?>">Elo</th>
        <th data-k2-sort="text">Country</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th data-k2-sort="number">Wins</th>
        <th data-k2-sort="number">Draws</th>
        <th data-k2-sort="number">Losses</th>
        <th data-k2-sort="number">Win %</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_opponent_avg(), ENT_QUOTES, 'UTF-8'); ?>">Opp. avg.</th>
    </tr>
</thead>

<tbody class="black">
<?php
$rank = 1;
while ($row = mysqli_fetch_assoc($result)) {
    $games = (int) $row['NumberGames'];
    ?>
    <tr>
        <td<?php echo k2_table_body_td_attr(0, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo (int) $rank; ?></td>
        <td<?php echo k2_table_body_td_attr(1, $k2LbAnchorCol, $k2LbDefaultSortCol, 'k2-table-cell--left'); ?>><?php echo k2_amiga_player_link((int) $row['ID'], (string) $row['Name']); ?></td>
        <td<?php echo k2_table_body_td_attr(2, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_int($row['Rating']); ?></td>
        <td<?php echo k2_table_body_td_attr(3, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_h($row['Country']); ?></td>
        <td<?php echo k2_table_body_td_attr(4, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_games_played($games); ?></td>
        <td<?php echo k2_table_body_td_attr(5, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_count($row['NumberWins'], $games); ?></td>
        <td<?php echo k2_table_body_td_attr(6, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_count($row['NumberDraws'], $games); ?></td>
        <td<?php echo k2_table_body_td_attr(7, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_count($row['NumberLosses'], $games); ?></td>
        <td<?php echo k2_table_body_td_attr(8, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_pct_from_ratio($row['WinRatio'], $games); ?></td>
        <td<?php echo k2_table_body_td_attr(9, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_lb_stat($row['AverageOpponentRating'], $games); ?></td>
    </tr>
    <?php
    $rank++;
}
?>
</tbody>

</table>

</div>

<p style="padding:0 1.25rem 2rem;color:var(--k2-text-secondary)"><?php echo number_format($gameCount); ?> rated games in database.</p>

</div><!-- .k2-page-nav -->

</body>
</html>
