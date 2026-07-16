<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_page_preamble.php'; ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga ladder — Victims &amp; Culprits</title>
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
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_inverse_count_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_amiga_country_flag.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_column_help.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_chronologies_lib.php';
include __DIR__ . '/../../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$ctx = amiga_lb_context($con);

$colOpponents = 4;
$lbSort = k2_lb_table_sort_state($colOpponents);
$lbDefaultOrder = amiga_lb_victims_default_order_sql();
$lbOrderMap = amiga_lb_victims_order_column_map($ctx->isActive());
$lbSqlOrder = k2_lb_sql_order_from_sort($lbSort, $lbOrderMap, $lbDefaultOrder);

if ($ctx->isActive()) {
    $selectSql = 'SELECT p.id AS ID, p.name AS Name, s.Rating, p.country AS Country, s.NumberGames, s.DifferentOpponents, s.DifferentVictims, '
        . 's.DifferentCulprits, s.DoubleDigitsVictims, s.CleanSheetsVictims, '
        . 'COALESCE(inv.MostGoalsConcededVictims, 0) AS MostGoalsConcededVictims, '
        . 'COALESCE(inv.BiggestLossVictims, 0) AS BiggestLossVictims, '
        . 's.DoubleDigitsCulprits, s.CleanSheetsCulprits, '
        . 'COALESCE(inv.MostGoalsScoredCulprits, 0) AS MostGoalsScoredCulprits, '
        . 'COALESCE(inv.BiggestWinCulprits, 0) AS BiggestWinCulprits ';
    $fromSql = amiga_lb_snapshot_from_sql('s') . "\n" . amiga_inverse_count_latest_join_sql('inv');
    $whereSql = 's.NumberGames > 0';
    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        throw new RuntimeException('Active time travel context missing cutoff.');
    }
    $sql = $selectSql . $fromSql . ' WHERE ' . $whereSql . ' ORDER BY ' . $lbSqlOrder['order_clause'];
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('prepare amiga victims lb: ' . $con->error);
    }
    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tournamentId = $cutoff['tournament_id'];
    // snapshot FROM binds sdi; inverse join binds another sdi
    $stmt->bind_param('sdisdi', $eventDate, $chrono, $tournamentId, $eventDate, $chrono, $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute amiga victims lb: ' . $stmt->error);
    }
    $result = $stmt->get_result();
    if ($result === false) {
        throw new RuntimeException('result amiga victims lb: ' . $stmt->error);
    }
    $stmt->close();
} else {
    $result = amiga_lb_query_career(
        $con,
        $ctx,
        'SELECT p.id AS ID, p.name AS Name, s.Rating, p.country AS Country, s.NumberGames, s.DifferentOpponents, s.DifferentVictims, '
        . 's.DifferentCulprits, s.DoubleDigitsVictims, s.CleanSheetsVictims, s.MostGoalsConcededVictims, s.BiggestLossVictims, '
        . 's.DoubleDigitsCulprits, s.CleanSheetsCulprits, s.MostGoalsScoredCulprits, s.BiggestWinCulprits ',
        'ORDER BY ' . $lbSqlOrder['order_clause']
    );
}

mysqli_close($con);

$k2AmigaLbWingActive = 'victims';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_nav.php';
?>

<?php k2_table_wrap_open(true); ?>

<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="<?php echo $lbSort['anchor']; ?>" data-k2-default-sort="<?php echo $lbSort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort['sort_dir']); ?>"<?php echo k2_lb_table_skip_initial_sort_attr_for_ssr($lbSort, $colOpponents, 'desc', $lbSqlOrder['ssr_applied_url_sort']); ?>>

<thead>
    <tr>
        <th<?php echo k2_lb_th(0, $lbSort, ''); ?> data-k2-sort="number">#</th>
        <th<?php echo k2_lb_th(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort="text">Player</th>
        <th<?php echo k2_lb_th_elo(2, $lbSort); ?> data-k2-sort="number"<?php echo k2_lb_elo_column_help_attrs(); ?>>Elo</th>
        <th<?php echo k2_lb_th(3, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th<?php echo k2_lb_th(4, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_opponents(), ENT_QUOTES, 'UTF-8'); ?>">Opponents</th>
        <th<?php echo k2_lb_th(5, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_victims(), ENT_QUOTES, 'UTF-8'); ?>">Victims</th>
        <th<?php echo k2_lb_th(6, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_culprits(), ENT_QUOTES, 'UTF-8'); ?>">Culprits</th>
        <th<?php echo k2_lb_th(7, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Double Digit victims" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_dd_victims(), ENT_QUOTES, 'UTF-8'); ?>">DD Victims</th>
        <th<?php echo k2_lb_th(8, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Double Digit culprits" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_dd_culprits(), ENT_QUOTES, 'UTF-8'); ?>">DD Culprits</th>
        <th<?php echo k2_lb_th(9, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Clean Sheet victims" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_cs_victims(), ENT_QUOTES, 'UTF-8'); ?>">CS Victims</th>
        <th<?php echo k2_lb_th(10, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Clean Sheet culprits" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_cs_culprits(), ENT_QUOTES, 'UTF-8'); ?>">CS Culprits</th>
        <th<?php echo k2_lb_th(11, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Most Goals Conceded victims" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_mgc_victims(), ENT_QUOTES, 'UTF-8'); ?>">MGC Victims</th>
        <th<?php echo k2_lb_th(12, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Biggest Loss victims" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_bl_victims(), ENT_QUOTES, 'UTF-8'); ?>">BL Victims</th>
        <th<?php echo k2_lb_th(13, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Most Goals Scored culprits" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_mgs_culprits(), ENT_QUOTES, 'UTF-8'); ?>">MGS Culprits</th>
        <th<?php echo k2_lb_th(14, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Biggest Win culprits" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_bw_culprits(), ENT_QUOTES, 'UTF-8'); ?>">BW Culprits</th>
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
        <td<?php echo k2_lb_td(4, $lbSort); ?>><?php echo amiga_lb_victims_chronology_cell_html($playerId, $row['DifferentOpponents'], $games, amiga_player_chronology_opponents_entry_href($playerId), true); ?></td>
        <td<?php echo k2_lb_td(5, $lbSort); ?>><?php echo amiga_lb_victims_chronology_cell_html($playerId, $row['DifferentVictims'], $games, amiga_player_chronology_victims_entry_href($playerId)); ?></td>
        <td<?php echo k2_lb_td(6, $lbSort); ?>><?php echo amiga_lb_victims_chronology_cell_html($playerId, $row['DifferentCulprits'], $games, amiga_player_chronology_culprits_entry_href($playerId)); ?></td>
        <td<?php echo k2_lb_td(7, $lbSort); ?>><?php echo amiga_lb_victims_chronology_cell_html($playerId, $row['DoubleDigitsVictims'], $games, amiga_player_chronology_dd_victims_entry_href($playerId)); ?></td>
        <td<?php echo k2_lb_td(8, $lbSort); ?>><?php echo amiga_lb_victims_chronology_cell_html($playerId, $row['DoubleDigitsCulprits'], $games, amiga_player_chronology_dd_culprits_entry_href($playerId)); ?></td>
        <td<?php echo k2_lb_td(9, $lbSort); ?>><?php echo amiga_lb_victims_chronology_cell_html($playerId, $row['CleanSheetsVictims'], $games, amiga_player_chronology_cs_victims_entry_href($playerId)); ?></td>
        <td<?php echo k2_lb_td(10, $lbSort); ?>><?php echo amiga_lb_victims_chronology_cell_html($playerId, $row['CleanSheetsCulprits'], $games, amiga_player_chronology_cs_culprits_entry_href($playerId)); ?></td>
        <td<?php echo k2_lb_td(11, $lbSort); ?>><?php echo amiga_lb_victims_chronology_cell_html($playerId, $row['MostGoalsConcededVictims'], $games, amiga_player_chronology_mgc_victims_entry_href($playerId)); ?></td>
        <td<?php echo k2_lb_td(12, $lbSort); ?>><?php echo amiga_lb_victims_chronology_cell_html($playerId, $row['BiggestLossVictims'], $games, amiga_player_chronology_bl_victims_entry_href($playerId)); ?></td>
        <td<?php echo k2_lb_td(13, $lbSort); ?>><?php echo amiga_lb_victims_chronology_cell_html($playerId, $row['MostGoalsScoredCulprits'], $games, amiga_player_chronology_mgs_culprits_entry_href($playerId)); ?></td>
        <td<?php echo k2_lb_td(14, $lbSort); ?>><?php echo amiga_lb_victims_chronology_cell_html($playerId, $row['BiggestWinCulprits'], $games, amiga_player_chronology_bw_culprits_entry_href($playerId)); ?></td>
    </tr>
    <?php
    $rank++;
}
?>
</tbody>

</table>

</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_site_end.inc.php'; ?>
</body>
</html>
