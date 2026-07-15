<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_page_preamble.php'; ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga ladder — DDs &amp; CSs</title>
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
include __DIR__ . '/../../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$ctx = amiga_lb_context($con);

$colDoubleDigits = 4;
$lbSort = k2_lb_table_sort_state($colDoubleDigits);
$lbDefaultOrder = amiga_lb_double_digits_default_order_sql();
$lbOrderMap = amiga_lb_double_digits_order_column_map();
$lbSqlOrder = k2_lb_sql_order_from_sort($lbSort, $lbOrderMap, $lbDefaultOrder);

$result = amiga_lb_query_career(
    $con,
    $ctx,
    'SELECT p.id AS ID, p.name AS Name, s.Rating, p.country AS Country, s.NumberGames, s.DoubleDigits, s.CleanSheets, '
    . 's.DoubleDigitsRatio, s.CleanSheetsRatio, s.DoubleDigitsConceded, s.CleanSheetsConceded, '
    . 's.DoubleDigitsConcededRatio, s.CleanSheetsConcededRatio ',
    'ORDER BY ' . $lbSqlOrder['order_clause']
);

mysqli_close($con);

$k2AmigaLbWingActive = 'double-digits';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_nav.php';
?>

<section class="k2-amiga-table-scroll-view">
<?php k2_table_wrap_open(true); ?>

<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="<?php echo $lbSort['anchor']; ?>" data-k2-default-sort="<?php echo $lbSort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort['sort_dir']); ?>"<?php echo k2_lb_table_skip_initial_sort_attr_for_ssr($lbSort, $colDoubleDigits, 'desc', $lbSqlOrder['ssr_applied_url_sort']); ?>>

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
$rank = 1;
while ($row = mysqli_fetch_assoc($result)) {
    $games = (int) $row['NumberGames'];
    $playerId = (int) $row['ID'];
    $playerName = (string) $row['Name'];
    ?>
    <tr>
        <td<?php echo k2_lb_td(0, $lbSort); ?>><?php echo $rank; ?></td>
        <td<?php echo k2_lb_td(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort-value="<?php echo k2_h($playerName); ?>"><?php echo k2_lb_player_row_anchor_markup($playerId); ?><?php echo k2_amiga_lb_player_cell($playerId, $playerName, (string) ($row['Country'] ?? '')); ?></td>
        <td<?php echo k2_lb_td(2, $lbSort); ?>><?php echo k2_amiga_lb_rating_cell_link($playerId, $row['Rating'], $playerName); ?></td>
        <td<?php echo k2_lb_td(3, $lbSort); ?>><?php echo k2_fmt_games_played($games); ?></td>
        <td<?php echo k2_lb_td(4, $lbSort); ?>><span class="blue"><?php echo k2_fmt_count($row['DoubleDigits'], $games); ?></span></td>
        <td<?php echo k2_lb_td(5, $lbSort); ?>><?php echo k2_fmt_count($row['CleanSheets'], $games); ?></td>
        <td<?php echo k2_lb_td(6, $lbSort); ?>><?php echo k2_fmt_pct_from_ratio($row['DoubleDigitsRatio'], $games); ?></td>
        <td<?php echo k2_lb_td(7, $lbSort); ?>><?php echo k2_fmt_pct_from_ratio($row['CleanSheetsRatio'], $games); ?></td>
        <td<?php echo k2_lb_td(8, $lbSort); ?>><span class="red"><?php echo k2_fmt_count($row['DoubleDigitsConceded'], $games); ?></span></td>
        <td<?php echo k2_lb_td(9, $lbSort); ?>><?php echo k2_fmt_count($row['CleanSheetsConceded'], $games); ?></td>
        <td<?php echo k2_lb_td(10, $lbSort); ?>><?php echo k2_fmt_pct_from_ratio($row['DoubleDigitsConcededRatio'], $games); ?></td>
        <td<?php echo k2_lb_td(11, $lbSort); ?>><?php echo k2_fmt_pct_from_ratio($row['CleanSheetsConcededRatio'], $games); ?></td>
    </tr>
    <?php
    $rank++;
}
?>
</tbody>

</table>

</div>
</section>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_site_end.inc.php'; ?>
</body>
</html>