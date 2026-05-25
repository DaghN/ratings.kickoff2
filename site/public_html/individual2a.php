<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/k2_head.php"; ?>
<script type="text/javascript" src="js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>

</head>

<body class="k2-site">

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>

<?php 
require_once $_SERVER["DOCUMENT_ROOT"] . "/includes/k2_safety.php";
include $_SERVER["DOCUMENT_ROOT"] . "/../config/ko2unitydb_config.php";

$id = k2_positive_int_param('id', 'Invalid player id.');
$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);

include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_hero_vars.php";
$name = $Name ?? '';

$query = "SELECT opponentID, opponentname, COUNT(*), SUM(win), SUM(draw), SUM(defeat), AVG(win), AVG(draw), AVG(defeat)
FROM(
    (
    SELECT idB AS opponentID, nameB AS opponentname, homewin AS win, draw AS draw, awaywin AS defeat FROM ratedresults 
    WHERE idA = " . $id . "
	)
    UNION ALL
    (
	SELECT idA AS opponentID, nameA AS opponentname, awaywin AS win, draw AS draw, homewin AS defeat FROM ratedresults 
    WHERE idB = " . $id . "
    )
	)AS derivedtable
GROUP BY opponentID,opponentname
ORDER BY COUNT(*) DESC";

$result = k2_query_or_public_error($con, $query, 'individual2a matchup table');

mysqli_close($con);
?>

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_hero.php"; ?>
<?php
$k2PlayerTabActive = 'wins';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_nav.php";
?>

<div class="k2-table-wrap">

<table class="k2-table" data-k2-table="sortable" data-k2-default-sort="1" data-k2-default-direction="desc">

<thead>
	
    <tr style="text-align:right;">
        <th colspan="1" data-k2-sort="text" style="text-align:left;" data-k2-help="Opponent name.">Opponent</th>
        <th data-k2-sort="number" data-k2-help="Rated games against this opponent.">&nbsp;&nbsp;Games</th>
        <th data-k2-sort="number" data-k2-help="Wins against this opponent.">&nbsp;&nbsp;&nbsp;Wins</th>
        <th data-k2-sort="number" data-k2-help="Draws against this opponent.">Draws</th>
        <th data-k2-sort="number" data-k2-help="Losses against this opponent.">Losses</th>
        <th data-k2-sort="number" data-k2-help="Share of games won against this opponent.">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Win Ratio</th>
        <th data-k2-sort="number" data-k2-help="Share of games drawn against this opponent.">Draw Ratio</th>
        <th data-k2-sort="number" data-k2-help="Share of games lost against this opponent.">Loss Ratio</th>
    </tr>
</thead>

<tbody class="black">
	<?php
    $i = "1";
    while ($row = mysqli_fetch_row($result))
    {  
        
    $opponentid = $row[0];
    $opponentname = $row[1];
    $games = $row[2];
    $wins = $row[3];
    $draws = $row[4];
    $losses = $row[5];
    $winratio = $row[6];
    $drawratio = $row[7];
    $lossratio = $row[8];
	?>
    
    <tr style="text-align:right;">
        <td style="text-align:left;"><?php echo k2_player_link($opponentid, $opponentname); ?></td>
        <td><?php echo $games ?></td>
        <td><?php if ($wins!=0) {echo "<span class='blue'>"; echo $wins; echo "</span>"; } else {echo "0";} ?></td>
        <td><?php echo $draws ?></td>
        <td><?php if ($losses!=0) {echo "<span class='red'>"; echo $losses; echo "</span>"; } else {echo "0";} ?></td>
        <td><?php if ($wins!=0) {echo "<span class='blue'>"; echo number_format(100*$winratio, 1); echo "%";} else {echo "0%";} ?></td>
        <td><?php echo number_format(100*$drawratio, 1); echo "%"; ?></td>
        <td><?php if ($losses!=0) {echo "<span class='red'>"; echo number_format(100*$lossratio, 1); echo "%";} else {echo "0%";} ?></td>
    </tr> 
    
    <?php
	$i++; 
    }  
    ?> 
</tbody>

</table>

</div><!-- .k2-table-wrap -->


</div><!-- .k2-page-nav -->
</body>
</html>
