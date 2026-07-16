<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_page_preamble.php'; ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga ladder — Calendar &amp; geography</title>
<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; ?>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2AmigaHubTabActive = 'leaderboards';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_table_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_league_table_render.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_column_help.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_chronologies_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_tournament_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_amiga_country_flag.php';
include __DIR__ . '/../../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");
$ctx = amiga_lb_context($con);

$colPeakGames = 3;
$lbSort = k2_lb_table_sort_state($colPeakGames);
$calendarAlias = $ctx->isActive() ? 's' : 't';
$lbDefaultOrder = amiga_lb_calendar_geo_default_order_sql($calendarAlias);
$lbOrderMap = amiga_lb_calendar_geo_order_column_map($calendarAlias);
$lbSqlOrder = k2_lb_sql_order_from_sort($lbSort, $lbOrderMap, $lbDefaultOrder);

$rows = amiga_calendar_geo_leaderboard_rows($con, $ctx, $lbSqlOrder['order_clause']);
mysqli_close($con);

$k2AmigaLbWingActive = 'calendar-geo';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_nav.php';
?>

<?php k2_table_wrap_open(true); ?>
<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="<?php echo $lbSort['anchor']; ?>" data-k2-default-sort="<?php echo $lbSort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort['sort_dir']); ?>"<?php echo k2_lb_table_skip_initial_sort_attr_for_ssr($lbSort, $colPeakGames, 'desc', $lbSqlOrder['ssr_applied_url_sort']); ?>>
<thead>
    <tr>
        <th<?php echo k2_lb_th(0, $lbSort, ''); ?> data-k2-sort="number">Rank</th>
        <th<?php echo k2_lb_th(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort="text">Player</th>
        <th<?php echo k2_lb_th_elo(2, $lbSort); ?> data-k2-sort="number"<?php echo k2_lb_elo_column_help_attrs(); ?>>Elo</th>
        <th<?php echo k2_lb_th(3, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_peak_year_games(), ENT_QUOTES, 'UTF-8'); ?>">Peak games</th>
        <th<?php echo k2_lb_th(4, $lbSort, ''); ?> data-k2-sort="number">Year</th>
        <th<?php echo k2_lb_th(5, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_peak_year_tournaments(), ENT_QUOTES, 'UTF-8'); ?>">Peak events</th>
        <th<?php echo k2_lb_th(6, $lbSort, ''); ?> data-k2-sort="number">Year</th>
        <th<?php echo k2_lb_th(7, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_countries_played_in(), ENT_QUOTES, 'UTF-8'); ?>">Host countries</th>
        <th<?php echo k2_lb_th(8, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_opponent_countries_faced(), ENT_QUOTES, 'UTF-8'); ?>">Countries faced</th>
        <th<?php echo k2_lb_th(9, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_opponent_countries_beaten(), ENT_QUOTES, 'UTF-8'); ?>">Countries beaten</th>
        <th<?php echo k2_lb_th(10, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_opponent_countries_beaten_by(), ENT_QUOTES, 'UTF-8'); ?>">Countries beaten by</th>
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
        <td<?php echo k2_lb_td(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort-value="<?php echo k2_h($playerName); ?>"><?php echo k2_lb_player_row_anchor_markup($playerId); ?><?php echo k2_amiga_lb_player_cell($playerId, $playerName, (string) ($row['country'] ?? '')); ?></td>
        <td<?php echo k2_lb_td(2, $lbSort); ?>><?php echo k2_amiga_lb_rating_cell_link($playerId, $row['rating'], $playerName); ?></td>
        <td<?php echo k2_lb_td(3, $lbSort); ?>><span class="blue"><?php echo (int) $row['peak_year_games']; ?></span></td>
        <td<?php echo k2_lb_td(4, $lbSort); ?>><?php echo $row['peak_year_games_year'] !== null ? (int) $row['peak_year_games_year'] : '—'; ?></td>
        <td<?php echo k2_lb_td(5, $lbSort); ?>><?php echo (int) $row['peak_year_tournaments']; ?></td>
        <td<?php echo k2_lb_td(6, $lbSort); ?>><?php echo $row['peak_year_tournaments_year'] !== null ? (int) $row['peak_year_tournaments_year'] : '—'; ?></td>
        <td<?php echo k2_lb_td(7, $lbSort); ?>><?php
            $hostCountries = (int) $row['countries_played_in'];
            echo amiga_lb_chronology_inventory_cell_html(
                $playerId,
                $hostCountries,
                amiga_player_chronology_host_countries_entry_href($playerId)
            );
        ?></td>
        <td<?php echo k2_lb_td(8, $lbSort); ?>><?php
            $countriesFaced = (int) $row['opponent_countries_faced'];
            echo amiga_lb_chronology_inventory_cell_html(
                $playerId,
                $countriesFaced,
                amiga_player_chronology_countries_faced_entry_href($playerId)
            );
        ?></td>
        <td<?php echo k2_lb_td(9, $lbSort); ?>><?php
            $countriesBeaten = (int) $row['opponent_countries_beaten'];
            echo amiga_lb_chronology_inventory_cell_html(
                $playerId,
                $countriesBeaten,
                amiga_player_chronology_countries_beaten_entry_href($playerId)
            );
        ?></td>
        <td<?php echo k2_lb_td(10, $lbSort); ?>><?php
            $countriesBeatenBy = (int) ($row['opponent_countries_beaten_by'] ?? 0);
            echo amiga_lb_chronology_inventory_cell_html(
                $playerId,
                $countriesBeatenBy,
                amiga_player_chronology_countries_beaten_by_entry_href($playerId)
            );
        ?></td>
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
