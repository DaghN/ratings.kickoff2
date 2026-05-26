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

$query = "SELECT opponentID, opponentname, COUNT(*), SUM(DD), SUM(DDC), SUM(CS), SUM(CSC), AVG(DD), AVG(DDC), AVG(CS), AVG(CSC)
FROM(
    (
    SELECT idB AS opponentID, nameB AS opponentname, DDPlayerA AS DD, DDPlayerB AS DDC, CSPlayerA AS CS, CSPlayerB AS CSC FROM ratedresults 
    WHERE idA = " . $id . "
	)
    UNION ALL
    (
	SELECT idA AS opponentID, nameA AS opponentname, DDPlayerB AS DD, DDPlayerA AS DDC, CSPlayerB AS CS, CSPlayerA AS CSC FROM ratedresults 
    WHERE idB = " . $id . "
    )
	)AS derivedtable
GROUP BY opponentID,opponentname
ORDER BY COUNT(*) DESC";

$result = k2_query_or_public_error($con, $query, 'individual2c matchup table');

mysqli_close($con);
?>

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_hero.php"; ?>
<?php
$k2PlayerTabActive = 'dds';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_nav.php";
?>

<div class="k2-table-wrap">

<table class="k2-table k2-table--numeric-default" data-k2-table="sortable" data-k2-default-sort="1" data-k2-default-direction="desc">

<thead>
    <tr>
        <th class="k2-table-cell--left" data-k2-sort="text">Opponent</th>
        <th class="k2-table-cell--pad-left-sm" data-k2-sort="number" data-k2-help="Rated games against this opponent.">Games</th>
        <th class="k2-table-cell--pad-left-xxl" data-k2-sort="number" data-k2-help="Double digits: games where this player scored 10 or more against this opponent.">DD</th>
        <th class="k2-table-cell--pad-left-xs" data-k2-sort="number" data-k2-help="Double digits conceded: games where this player conceded 10 or more against this opponent.">DD C</th>
        <th class="k2-table-cell--pad-left-xxl" data-k2-sort="number" data-k2-help="Clean sheets: games where this player held this opponent to zero goals.">CS</th>
        <th class="k2-table-cell--pad-left-xs" data-k2-sort="number" data-k2-help="Clean sheets conceded: games where this player scored no goals against this opponent.">CS C</th>
        <th class="k2-table-cell--pad-left-md" data-k2-sort="number" data-k2-help="Share of games against this opponent where this player scored 10 or more.">DD Ratio</th>
        <th data-k2-sort="number" data-k2-help="Share of games against this opponent where this player conceded 10 or more.">DD C Ratio</th>
        <th class="k2-table-cell--pad-left-lg" data-k2-sort="number" data-k2-help="Share of games against this opponent where this player kept a clean sheet.">CS Ratio</th>
        <th data-k2-sort="number" data-k2-help="Share of games against this opponent where this player was held to zero goals.">CS C Ratio</th>
    </tr>
</thead>

<tbody class="black">
	<?php
    $rank = "1";
    while ($row = mysqli_fetch_row($result))
    {  
    ?>
    
    <tr>
        
        <td class="k2-table-cell--left"><?php echo k2_player_link($row[0], $row[1]); ?></td>
        <td><?php echo $row[2] ?></td>
       	<td><?php if ($row[3] == 0) {echo "0";} else {?><span class="blue"><?php echo $row[3];?></span><?php } ?></td>
        <td><?php if ($row[4] == 0) {echo "0";} else {?><span class="red"><?php echo $row[4];?></span><?php } ?></td>
        <td><?php if ($row[5] == 0) {echo "0";} else {?><span class="blue"><?php echo $row[5];?></span><?php } ?></td>
        <td><?php if ($row[6] == 0) {echo "0";} else {?><span class="red"><?php echo $row[6];?></span><?php } ?></td>
        <td><?php if ($row[7] == 0) {echo "0%";} else {echo "<span class='blue'>"; echo number_format(100*$row[7], 1); echo "%";} ?></td>
        <td><?php if ($row[8] == 0) {echo "0%";} else {echo "<span class='red'>"; echo number_format(100*$row[8], 1); echo "%";} ?></td>
        <td><?php if ($row[9] == 0) {echo "0%";} else {echo "<span class='blue'>"; echo number_format(100*$row[9], 1); echo "%";} ?></td>
        <td><?php if ($row[10] == 0) {echo "0%";} else {echo "<span class='red'>"; echo number_format(100*$row[10], 1); echo "%";} ?></td>
    </tr> 
    
    <?php
	$rank++; 
    }  
    ?> 
</tbody>

</table>

</div><!-- .k2-table-wrap -->


</div><!-- .k2-page-nav -->
</body>
</html>
