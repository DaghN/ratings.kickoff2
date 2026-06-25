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
?>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_snapshot_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_amiga_country_flag.php';
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

$showRatingDelta = $ctx->isActive();
$showWcStartDelta = !$showRatingDelta;
if ($showRatingDelta) {
    $deltaByPlayer = amiga_lb_rating_delta_map($con, $ctx);
} elseif ($showWcStartDelta) {
    $deltaByPlayer = amiga_lb_wc_start_rating_delta_map($con);
    if ($deltaByPlayer === []) {
        $showWcStartDelta = false;
    }
} else {
    $deltaByPlayer = [];
}
$showDeltaColumn = $showRatingDelta || $showWcStartDelta;
$deltaColumnHelpAttrs = $showRatingDelta
    ? k2_lb_amiga_rating_delta_column_help_attrs()
    : k2_lb_amiga_wc_start_rating_delta_column_help_attrs();

mysqli_close($con);

$colElo = 2;
$colOffset = $showDeltaColumn ? 1 : 0;
$colDelta = $showDeltaColumn ? 3 : null;
$colCountry = 3 + $colOffset;
$colGames = 4 + $colOffset;
$colWins = 5 + $colOffset;
$colDraws = 6 + $colOffset;
$colLosses = 7 + $colOffset;
$colWinPct = 8 + $colOffset;
$colOppAvg = 9 + $colOffset;

$k2AmigaLbWingActive = 'rating';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_nav.php';
?>

<?php k2_table_wrap_open(true); ?>

<?php $lbSort = k2_lb_table_sort_state($colElo); ?>
<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="<?php echo $lbSort['anchor']; ?>" data-k2-default-sort="<?php echo $lbSort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort['sort_dir']); ?>"<?php echo k2_table_skip_initial_sort_attr($colElo); ?>>

<thead>
    <tr>
        <th<?php echo k2_lb_th(0, $lbSort, ''); ?> data-k2-sort="number">Rank</th>
        <th<?php echo k2_lb_th(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort="text">Player</th>
        <th<?php echo k2_lb_th_elo($colElo, $lbSort); ?> data-k2-sort="number"<?php echo k2_lb_elo_column_help_attrs(); ?>>Elo</th>
<?php if ($showDeltaColumn) { ?>
        <th<?php echo k2_lb_th($colDelta, $lbSort, 'k2-table-cell--center'); ?> data-k2-sort="number"<?php echo $deltaColumnHelpAttrs; ?>>&#916;</th>
<?php } ?>
        <th<?php echo k2_lb_th_country($colCountry, $lbSort); ?> data-k2-sort="text">Country</th>
        <th<?php echo k2_lb_th($colGames, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th<?php echo k2_lb_th($colWins, $lbSort, ''); ?> data-k2-sort="number">Wins</th>
        <th<?php echo k2_lb_th($colDraws, $lbSort, ''); ?> data-k2-sort="number">Draws</th>
        <th<?php echo k2_lb_th($colLosses, $lbSort, ''); ?> data-k2-sort="number">Losses</th>
        <th<?php echo k2_lb_th($colWinPct, $lbSort, ''); ?> data-k2-sort="number">Win %</th>
        <th<?php echo k2_lb_th($colOppAvg, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_opponent_avg(), ENT_QUOTES, 'UTF-8'); ?>">Opp. avg.</th>
    </tr>
</thead>

<tbody class="black">
<?php
$rank = 1;
while ($row = mysqli_fetch_assoc($result)) {
    $games = (int) $row['NumberGames'];
    $playerId = (int) $row['ID'];
    $delta = $showDeltaColumn ? ($deltaByPlayer[$playerId] ?? null) : null;
    ?>
    <tr>
        <td<?php echo k2_lb_td(0, $lbSort); ?>><?php echo (int) $rank; ?></td>
        <td<?php echo k2_lb_td(1, $lbSort, 'k2-table-cell--left'); ?>><?php echo k2_amiga_player_link($playerId, (string) $row['Name']); ?></td>
        <td<?php echo k2_lb_td($colElo, $lbSort); ?>><?php echo k2_fmt_int($row['Rating']); ?></td>
<?php if ($showDeltaColumn) { ?>
        <td<?php echo k2_lb_td($colDelta, $lbSort, 'k2-table-cell--center'); ?> data-k2-sort-value="<?php echo k2_h(amiga_lb_rating_delta_sort_value($delta)); ?>"><?php echo amiga_lb_rating_delta_cell($delta); ?></td>
<?php } ?>
        <td<?php echo k2_lb_td($colCountry, $lbSort, 'k2-table-cell--center'); ?> data-k2-sort-value="<?php echo k2_h((string) ($row['Country'] ?? '')); ?>"><?php echo k2_amiga_country_table_cell((string) ($row['Country'] ?? '')); ?></td>
        <td<?php echo k2_lb_td($colGames, $lbSort); ?>><?php echo k2_fmt_games_played($games); ?></td>
        <td<?php echo k2_lb_td($colWins, $lbSort); ?>><?php echo k2_fmt_count($row['NumberWins'], $games); ?></td>
        <td<?php echo k2_lb_td($colDraws, $lbSort); ?>><?php echo k2_fmt_count($row['NumberDraws'], $games); ?></td>
        <td<?php echo k2_lb_td($colLosses, $lbSort); ?>><?php echo k2_fmt_count($row['NumberLosses'], $games); ?></td>
        <td<?php echo k2_lb_td($colWinPct, $lbSort); ?>><?php echo k2_fmt_pct_from_ratio($row['WinRatio'], $games); ?></td>
        <td<?php echo k2_lb_td($colOppAvg, $lbSort); ?>><?php echo k2_fmt_lb_stat($row['AverageOpponentRating'], $games); ?></td>
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
