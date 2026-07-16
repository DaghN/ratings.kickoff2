<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_page_preamble.php'; ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga ladder — Elo rating</title>
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
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_wc_lb_lib.php';
include __DIR__ . '/../../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$ctx = amiga_lb_context($con);

$deltaBundle = amiga_lb_rating_delta_column_bundle($con, $ctx);
$showRatingDelta = $deltaBundle['show_rating_delta'];
$deltaByPlayer = $deltaBundle['delta_by_player'];
$lastWcForDeltaHelp = $deltaBundle['last_wc_for_delta_help'];
$deltaLinkTournamentId = null;
if ($showRatingDelta && $ctx->wing() === 'event') {
    $deltaCutoff = $ctx->cutoff();
    if ($deltaCutoff !== null) {
        $deltaLinkTournamentId = (int) $deltaCutoff['tournament_id'];
        if ($deltaLinkTournamentId < 1) {
            $deltaLinkTournamentId = null;
        }
    }
}
$deltaColumnHelpAttrs = $showRatingDelta
    ? k2_lb_amiga_rating_delta_column_help_attrs()
    : k2_lb_amiga_wc_start_rating_delta_column_help_attrs($lastWcForDeltaHelp);

$colElo = 2;
$colDelta = AMIGA_LB_RATING_COL_DELTA;
$colGames = AMIGA_LB_RATING_COL_GAMES;
$colWins = AMIGA_LB_RATING_COL_WINS;
$colDraws = 6;
$colLosses = 7;
$colWinRate = AMIGA_LB_RATING_COL_WIN_RATE;
$colOppAvg = AMIGA_LB_RATING_COL_OPP_AVG;

$lbSort = k2_lb_table_sort_state($colElo);
$lbDefaultOrder = 's.Rating DESC, p.id ASC';
$lbOrderMap = [
    1 => 'p.name',
    $colElo => 's.Rating',
    $colGames => 's.NumberGames',
    $colWins => 's.NumberWins',
    $colDraws => 's.NumberDraws',
    $colLosses => 's.NumberLosses',
    $colWinRate => 's.WinRatio',
    $colOppAvg => 's.AverageOpponentRating',
];
$lbSqlOrder = k2_lb_sql_order_from_sort($lbSort, $lbOrderMap, $lbDefaultOrder);

$result = amiga_lb_query_career(
    $con,
    $ctx,
    'SELECT p.id AS ID, p.name AS Name, s.Rating, s.NumberGames, s.NumberWins, s.NumberDraws, s.NumberLosses, '
    . 's.WinRatio, s.DrawRatio, s.LossRatio, s.AverageOpponentRating, p.country AS Country ',
    'ORDER BY ' . $lbSqlOrder['order_clause']
);

amiga_lb_chapter_lede_html_for_request($con, $ctx);

mysqli_close($con);

$k2AmigaLbWingActive = 'rating';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_nav.php';
?>

<section class="k2-amiga-table-scroll-view">
<?php k2_table_wrap_open(true); ?>

<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="<?php echo $lbSort['anchor']; ?>" data-k2-default-sort="<?php echo $lbSort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort['sort_dir']); ?>"<?php echo k2_lb_table_skip_initial_sort_attr_for_ssr($lbSort, $colElo, 'desc', $lbSqlOrder['ssr_applied_url_sort']); ?>>

<thead>
    <tr>
        <th<?php echo k2_lb_th(0, $lbSort, ''); ?> data-k2-sort="number">Rank</th>
        <th<?php echo k2_lb_th(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort="text">Player</th>
        <th<?php echo k2_lb_th_elo($colElo, $lbSort); ?> data-k2-sort="number"<?php echo k2_lb_elo_column_help_attrs(); ?>>Elo</th>
        <th<?php echo k2_lb_th_delta($colDelta, $lbSort); ?> data-k2-sort="number"<?php echo $deltaColumnHelpAttrs; ?>>&#916;</th>
        <th<?php echo k2_lb_th($colGames, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th<?php echo k2_lb_th($colWins, $lbSort, ''); ?> data-k2-sort="number">Wins</th>
        <th<?php echo k2_lb_th($colDraws, $lbSort, ''); ?> data-k2-sort="number">Draws</th>
        <th<?php echo k2_lb_th($colLosses, $lbSort, ''); ?> data-k2-sort="number">Losses</th>
        <th<?php echo k2_lb_th($colWinRate, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_win_rate(), ENT_QUOTES, 'UTF-8'); ?>">Win rate</th>
        <th<?php echo k2_lb_th($colOppAvg, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Opponent Average" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_opponent_avg(), ENT_QUOTES, 'UTF-8'); ?>">Opponent Average</th>
    </tr>
</thead>

<tbody class="black">
<?php
$rank = 1;
while ($row = mysqli_fetch_assoc($result)) {
    $games = (int) $row['NumberGames'];
    $playerId = (int) $row['ID'];
    $playerName = (string) $row['Name'];
    $delta = $deltaByPlayer[$playerId] ?? null;
    $wins = (int) $row['NumberWins'];
    $draws = (int) $row['NumberDraws'];
    $winRate = amiga_wc_lb_win_rate($wins, $draws, $games);
    ?>
    <tr>
        <td<?php echo k2_lb_td(0, $lbSort); ?>><?php echo (int) $rank; ?></td>
        <td<?php echo k2_lb_td(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort-value="<?php echo k2_h($playerName); ?>"><?php echo k2_lb_player_row_anchor_markup($playerId); ?><?php echo k2_amiga_lb_player_cell($playerId, $playerName, (string) ($row['Country'] ?? '')); ?></td>
        <td<?php echo k2_lb_td($colElo, $lbSort); ?>><?php echo k2_amiga_lb_rating_cell_link($playerId, $row['Rating'], $playerName); ?></td>
        <td<?php echo k2_lb_td($colDelta, $lbSort, 'k2-table-cell--center'); ?> data-k2-sort-value="<?php echo k2_h(amiga_lb_rating_delta_sort_value($delta)); ?>"><?php echo amiga_lb_rating_delta_cell($delta, $deltaLinkTournamentId); ?></td>
        <td<?php echo k2_lb_td($colGames, $lbSort); ?>><?php echo amiga_lb_rating_games_inventory_cell_html($playerId, $games); ?></td>
        <td<?php echo k2_lb_td($colWins, $lbSort); ?>><?php echo amiga_lb_rating_games_inventory_cell_html($playerId, $games, 'win', 'win', $row['NumberWins']); ?></td>
        <td<?php echo k2_lb_td($colDraws, $lbSort); ?>><?php echo amiga_lb_rating_games_inventory_cell_html($playerId, $games, 'draw', null, $row['NumberDraws']); ?></td>
        <td<?php echo k2_lb_td($colLosses, $lbSort); ?>><?php echo amiga_lb_rating_games_inventory_cell_html($playerId, $games, 'loss', 'loss', $row['NumberLosses']); ?></td>
        <td<?php echo k2_lb_td($colWinRate, $lbSort); ?>><?php echo k2_fmt_pct_from_ratio($winRate, $games); ?></td>
        <td<?php echo k2_lb_td($colOppAvg, $lbSort); ?>><?php echo k2_fmt_lb_stat($row['AverageOpponentRating'], $games); ?></td>
    </tr>
    <?php
    $rank++;
}
?>
</tbody>

</table>

</div>

<div class="k2-amiga-table-scroll-pad" aria-hidden="true"></div>
</section>

</div><!-- .k2-page-nav -->

<script type="text/javascript" src="/js/amiga-lb-rating-page.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/amiga-lb-rating-page.js'); ?>" defer="defer"></script>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/site_footer.php';
k2_site_footer_render();
?>

</body>
</html>
