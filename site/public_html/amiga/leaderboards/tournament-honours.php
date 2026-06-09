<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga ladder — Tournament honours</title>
<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<script type="text/javascript" src="/js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2AmigaHubTabActive = 'leaderboards';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_tournament_lib.php';
include __DIR__ . '/../../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");

$honoursRows = amiga_tournament_honours_leaderboard_rows($con);

$playerCount = 0;
$countRes = mysqli_query($con, 'SELECT COUNT(*) AS n FROM amiga_player_tournament_totals WHERE tournaments_played > 0');
if ($countRes) {
    $countRow = mysqli_fetch_assoc($countRes);
    $playerCount = (int) ($countRow['n'] ?? 0);
    mysqli_free_result($countRes);
}

mysqli_close($con);

$k2AmigaLbWingActive = 'tournament-honours';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_nav.php';
?>

<header class="k2-hub-page-intro-head" style="padding:0 1.25rem">
	<p class="k2-hub-page-intro" style="margin:0 0 1rem">Career tournament honours from derived participation — World Cup medals, event wins, and events played. Cup podiums and kitchen marathons are included in win counts where applicable.</p>
</header>

<div class="k2-table-wrap">

<table class="k2-table k2-table--numeric-default k2-table--calm-stats" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="2" data-k2-default-sort="4" data-k2-default-direction="desc">

<thead>
    <tr>
        <th data-k2-sort="number">Rank</th>
        <th class="k2-table-cell--left" data-k2-sort="text">Player</th>
        <th data-k2-sort="text">Country</th>
        <th data-k2-sort="number">WC gold</th>
        <th data-k2-sort="number">WC silver</th>
        <th data-k2-sort="number">WC bronze</th>
        <th data-k2-sort="number">Tournaments won</th>
        <th data-k2-sort="number">Tournaments played</th>
    </tr>
</thead>

<tbody class="black">
<?php
$rank = 1;
foreach ($honoursRows as $row) {
    $playerId = (int) $row['player_id'];
    ?>
    <tr>
        <td><?php echo $rank; ?></td>
        <td class="k2-table-cell--left"><?php echo k2_amiga_player_link($playerId, (string) $row['player_name']); ?></td>
        <td><?php echo k2_h((string) ($row['country'] ?? '')); ?></td>
        <td><?php echo (int) $row['wc_gold']; ?></td>
        <td><?php echo (int) $row['wc_silver']; ?></td>
        <td><?php echo (int) $row['wc_bronze']; ?></td>
        <td><?php echo (int) $row['tournaments_won']; ?></td>
        <td><?php echo (int) $row['tournaments_played']; ?></td>
    </tr>
    <?php
    $rank++;
}
?>
</tbody>

</table>

</div>

<p style="padding:0 1.25rem 2rem;color:var(--k2-text-secondary)"><?php echo number_format($playerCount); ?> players with at least one tournament.</p>

</div><!-- .k2-page-nav -->

</body>
</html>
