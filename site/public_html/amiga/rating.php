<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga ladder — Elo rating</title>
<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<script type="text/javascript" src="/js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<div class="k2-page-nav" style="padding:1rem 1.25rem 0">
  <p class="k2-hub-intro" style="margin:0 0 1rem">Amiga 500 offline ladder — Elo rating (K=32, start 1600). Imported from historical tournament results.</p>
</div>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_load.php';
include __DIR__ . '/../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);

$query = 'SELECT ID, Name, Rating, NumberGames, NumberWins, NumberDraws, NumberLosses, WinRatio, DrawRatio, LossRatio, AverageOpponentRating, Country '
    . 'FROM playertable WHERE NumberGames > 0 ORDER BY Rating DESC';
$result = k2_query_or_public_error($con, $query, 'amiga rating leaderboard');

$gameCount = 0;
$gcRes = mysqli_query($con, 'SELECT COUNT(*) AS n FROM ratedresults');
if ($gcRes) {
    $gcRow = mysqli_fetch_assoc($gcRes);
    $gameCount = (int) ($gcRow['n'] ?? 0);
    mysqli_free_result($gcRes);
}

mysqli_close($con);
?>

<div class="k2-table-wrap">

<table class="k2-table k2-table--numeric-default k2-table--calm-stats" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="2" data-k2-default-sort="3" data-k2-default-direction="desc">

<thead>
    <tr>
        <th data-k2-sort="number">Rank</th>
        <th class="k2-table-cell--left" data-k2-sort="text">Player</th>
        <th data-k2-sort="number">Elo</th>
        <th data-k2-sort="text">Country</th>
        <th data-k2-sort="number">Games</th>
        <th data-k2-sort="number">Wins</th>
        <th data-k2-sort="number">Draws</th>
        <th data-k2-sort="number">Losses</th>
        <th data-k2-sort="number">Win %</th>
        <th data-k2-sort="number">Opp. avg.</th>
    </tr>
</thead>

<tbody class="black">
<?php
$rank = 1;
while ($row = mysqli_fetch_assoc($result)) {
    $games = (int) $row['NumberGames'];
    ?>
    <tr>
        <td><?php echo (int) $rank; ?></td>
        <td class="k2-table-cell--left"><?php echo k2_amiga_player_link((int) $row['ID'], (string) $row['Name']); ?></td>
        <td><?php echo k2_fmt_int($row['Rating']); ?></td>
        <td><?php echo k2_h($row['Country']); ?></td>
        <td><?php echo k2_fmt_games_played($games); ?></td>
        <td><?php echo k2_fmt_count($row['NumberWins'], $games); ?></td>
        <td><?php echo k2_fmt_count($row['NumberDraws'], $games); ?></td>
        <td><?php echo k2_fmt_count($row['NumberLosses'], $games); ?></td>
        <td><?php echo k2_fmt_pct_from_ratio($row['WinRatio'], $games); ?></td>
        <td><?php echo k2_fmt_lb_stat($row['AverageOpponentRating'], $games); ?></td>
    </tr>
    <?php
    $rank++;
}
?>
</tbody>

</table>

</div>

<p style="padding:0 1.25rem 2rem;color:var(--k2-text-secondary)"><?php echo number_format($gameCount); ?> rated games in database.</p>

</body>
</html>
