<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php $k2RankedCloak = true; include $_SERVER["DOCUMENT_ROOT"] . "/includes/k2_head.php"; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_lb_sortable_table_head.inc.php'; ?>
<script type="text/javascript" src="/js/player-search.js" defer="defer"></script>

</head>

<body class="k2-site">

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>

<?php
$k2HubTabActive = 'leaderboards';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/hub_nav.php";
?>

<?php 
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
include $_SERVER["DOCUMENT_ROOT"] . "/../config/ko2unitydb_config.php";

	$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_player_filters.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_column_help.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_table_helpers.php';
$query = 'SELECT id, Name, Rating, NumberGames, DoubleDigits, CleanSheets, DoubleDigitsRatio, CleanSheetsRatio, DoubleDigitsConceded, CleanSheetsConceded, DoubleDigitsConcededRatio, CleanSheetsConcededRatio FROM playertable WHERE ' . k2_lb_player_where_sql() . ' ORDER BY DoubleDigits DESC, rating DESC';
$result = k2_query_or_public_error($con, $query, 'ranked3 leaderboard'); 

mysqli_close($con);
?>

<?php
$k2LbWingActive = 'double-digits';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/lb_nav.php";
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
        <th<?php echo k2_lb_th(4, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_double_digits(), ENT_QUOTES, 'UTF-8'); ?>">Double Digits</th>
        <th<?php echo k2_lb_th(5, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_clean_sheets(), ENT_QUOTES, 'UTF-8'); ?>">Clean Sheets</th>
        <th<?php echo k2_lb_th(6, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Double Digits ratio" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_double_digits_ratio(), ENT_QUOTES, 'UTF-8'); ?>">DD Ratio</th>
        <th<?php echo k2_lb_th(7, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Clean Sheets ratio" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_clean_sheets_ratio(), ENT_QUOTES, 'UTF-8'); ?>">CS Ratio</th>
        <th<?php echo k2_lb_th(8, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_double_digits_conceded(), ENT_QUOTES, 'UTF-8'); ?>">DD conceded</th>
        <th<?php echo k2_lb_th(9, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_clean_sheets_conceded(), ENT_QUOTES, 'UTF-8'); ?>">CS conceded</th>
        <th<?php echo k2_lb_th(10, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="DD conceded ratio" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_double_digits_conceded_ratio(), ENT_QUOTES, 'UTF-8'); ?>">DD C Ratio</th>
        <th<?php echo k2_lb_th(11, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="CS conceded ratio" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_clean_sheets_conceded_ratio(), ENT_QUOTES, 'UTF-8'); ?>">CS C Ratio</th>
    </tr>
</thead>


<tbody class="black">
	<?php
    $rank = "1";
    while ($row = mysqli_fetch_row($result))
    {  
    ?>
    
    <tr>
        <td<?php echo k2_lb_td(0, $lbSort); ?>><?php echo $rank ?></td>
        <td<?php echo k2_lb_td(1, $lbSort, 'k2-table-cell--left'); ?>><?php echo k2_player_link($row[0], $row[1]); ?></td>
        <td<?php echo k2_lb_td(2, $lbSort); ?>><?php echo k2_lb_rating_cell_link((int) $row[0], $row[2], (string) $row[1]); ?></td>
        <td<?php echo k2_lb_td(3, $lbSort); ?>><?php echo k2_fmt_games_played($row[3]); ?></td>
        <td<?php echo k2_lb_td(4, $lbSort); ?>><span class="blue"><?php echo k2_fmt_count($row[4], $row[3]); ?></span></td>
        <td<?php echo k2_lb_td(5, $lbSort); ?>><?php echo k2_fmt_count($row[5], $row[3]); ?></td>
        <td<?php echo k2_lb_td(6, $lbSort); ?>><?php echo k2_fmt_pct_from_ratio($row[6], $row[3]); ?></td>
        <td<?php echo k2_lb_td(7, $lbSort); ?>><?php echo k2_fmt_pct_from_ratio($row[7], $row[3]); ?></td>
        <td<?php echo k2_lb_td(8, $lbSort); ?>><span class="red"><?php echo k2_fmt_count($row[8], $row[3]); ?></span></td>
        <td<?php echo k2_lb_td(9, $lbSort); ?>><?php echo k2_fmt_count($row[9], $row[3]); ?></td>
        <td<?php echo k2_lb_td(10, $lbSort); ?>><?php echo k2_fmt_pct_from_ratio($row[10], $row[3]); ?></td>
        <td<?php echo k2_lb_td(11, $lbSort); ?>><?php echo k2_fmt_pct_from_ratio($row[11], $row[3]); ?></td>
    </tr> 
    
    <?php
	$rank++; 
    }  
    ?> 
</tbody>

</table>

<?php k2_table_wrap_close(); ?><!-- .k2-table-wrap -->




<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_site_end.inc.php'; ?>
</body>
</html>
