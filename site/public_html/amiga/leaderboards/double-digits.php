<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga ladder — DDs &amp; CSs</title>
<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<script type="text/javascript" src="/js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2AmigaHubTabActive = 'leaderboards';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_column_help.php';
include __DIR__ . '/../../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);

$query = 'SELECT p.id AS ID, p.name AS Name, s.Rating, s.NumberGames, s.DoubleDigits, s.CleanSheets, '
    . 's.DoubleDigitsRatio, s.CleanSheetsRatio, s.DoubleDigitsConceded, s.CleanSheetsConceded, '
    . 's.DoubleDigitsConcededRatio, s.CleanSheetsConcededRatio '
    . amiga_player_base_from_sql() . ' WHERE ' . amiga_lb_player_where_sql() . ' ORDER BY s.DoubleDigits DESC, s.Rating DESC';
$result = k2_query_or_public_error($con, $query, 'amiga double-digits leaderboard');

mysqli_close($con);

$k2AmigaLbWingActive = 'double-digits';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_nav.php';
?>

<div class="k2-table-wrap">

<?php
$k2LbAnchorCol = 2;
$k2LbDefaultSortCol = 4;
?>
<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="2" data-k2-default-sort="4" data-k2-default-direction="desc">

<thead>
    <tr>
        <th data-k2-sort="number">#</th>
        <th class="k2-table-cell--left" data-k2-sort="text">Player</th>
        <th data-k2-sort="number">ELO rating</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
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

<tbody class="black">
<?php
$rank = 1;
while ($row = mysqli_fetch_assoc($result)) {
    $games = (int) $row['NumberGames'];
    ?>
    <tr>
        <td<?php echo k2_table_body_td_attr(0, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo $rank; ?></td>
        <td<?php echo k2_table_body_td_attr(1, $k2LbAnchorCol, $k2LbDefaultSortCol, 'k2-table-cell--left'); ?>><?php echo k2_amiga_player_link((int) $row['ID'], (string) $row['Name']); ?></td>
        <td<?php echo k2_table_body_td_attr(2, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_int($row['Rating']); ?></td>
        <td<?php echo k2_table_body_td_attr(3, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_games_played($games); ?></td>
        <td<?php echo k2_table_body_td_attr(4, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_count($row['DoubleDigits'], $games); ?></td>
        <td<?php echo k2_table_body_td_attr(5, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_count($row['CleanSheets'], $games); ?></td>
        <td<?php echo k2_table_body_td_attr(6, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_pct_from_ratio($row['DoubleDigitsRatio'], $games); ?></td>
        <td<?php echo k2_table_body_td_attr(7, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_pct_from_ratio($row['CleanSheetsRatio'], $games); ?></td>
        <td<?php echo k2_table_body_td_attr(8, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_count($row['DoubleDigitsConceded'], $games); ?></td>
        <td<?php echo k2_table_body_td_attr(9, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_count($row['CleanSheetsConceded'], $games); ?></td>
        <td<?php echo k2_table_body_td_attr(10, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_pct_from_ratio($row['DoubleDigitsConcededRatio'], $games); ?></td>
        <td<?php echo k2_table_body_td_attr(11, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_pct_from_ratio($row['CleanSheetsConcededRatio'], $games); ?></td>
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
