<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/k2_head.php"; ?>
<script type="text/javascript" src="js/elolist.js" ></script>
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>

</head>

<body class="k2-site">

<?php
$playerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($playerId < 1) {
    exit();
}

function individual3_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function individual3_player_link(int $id, string $name): string
{
    return '<a href="individual1.php?id=' . $id . '">' . individual3_h($name) . '</a>';
}
?>

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>

<?php 
include $_SERVER["DOCUMENT_ROOT"] . "/../config/ko2unitydb_config.php";
//mysql_connect(localhost,$username,$password);
//@mysql_select_db($database) or die( "Unable to select database");
	$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
	if (mysqli_connect_errno())
  	{
  		die("Failed to connect to MySQL: " . mysqli_connect_error());
  	}
    $con->set_charset('utf8mb4');

$id = $playerId;
include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_hero_vars.php";
$name = $Name ?? '';

$games = [];
$query = 'SELECT id, Date, idA, NameA, idB, NameB, RatingA, RatingB, GoalsA, GoalsB, ExpectedScoreA, ExpectedScoreB, ActualScore, AdjustmentA, AdjustmentB, SumOfGoals, GoalDifference '
    . 'FROM ratedresults WHERE idA = ? OR idB = ? ORDER BY id DESC';
$stmt = mysqli_prepare($con, $query);
if (!$stmt) {
    die("SELECT Error: " . mysqli_error($con));
}
mysqli_stmt_bind_param($stmt, 'ii', $playerId, $playerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if (!$result) {
    die("SELECT Error: " . mysqli_error($con));
}
while ($row = mysqli_fetch_assoc($result)) {
    $games[] = $row;
}
mysqli_stmt_close($stmt);

mysqli_close($con);
?>

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_hero.php"; ?>
<?php
$k2PlayerTabActive = 'games';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_nav.php";
?>

<div class="k2-table-wrap">

<table class="k2-table table-autosort table-autofilter table-autopage:100 table-page-number:tablepage table-page-count:tablepages">

<thead>
	<tr style="text-align:right;">
        <th class="filtercell"></th>
        <th class="filtercell"></th>
        <th class="filtercell"></th>
      <th class="filtercell"></th>
      <th class="filtercell"></th>
        <th class="filtercell"></th>
        <th class="table-filterable filtercell"></th>
        <th class="table-filterable filtercell"></th>
        <th class="filtercell"></th>
        <th class="filtercell"></th>
        <th class="filtercell"></th>
        <th class="filtercell"></th>
        <th class="filtercell"></th>
        <th class="filtercell"></th>
        <th class="filtercell"></th>
        <th class="filtercell"></th>
    </tr>
    
<tr style="text-align:right;">
    	<th class="table-sortable:numeric" style="text-align:left;">ID</th>
        <th class="table-sortable:date" style="text-align:left;">&nbsp;Date</th>
        <th class="table-sortable:ignorecase">Team A</th>
        <th class="table-sortable:numeric"></th>
        <th class="table-sortable:numeric"></th>
<th class="table-sortable:ignorecase" style="text-align:left;">Team B</th>
        <th class="table-sortable:ignorecase" style="text-align:left;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Result</th>
        <th class="table-sortable:ignorecase" style="text-align:left;">Opponent</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;F</th>
        <th class="table-sortable:numeric">A</th>
      <th class="table-sortable:numeric">Diff</th>
        <th class="table-sortable:numeric">Sum</th>
<th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;<?php echo individual3_h($name); ?></th>
        <th class="table-sortable:numeric">Opponent</th>
       	<th class="table-sortable:numeric">ES <?php echo individual3_h($name); ?></th> 
        <th class="table-sortable:numeric">Adjustment</th>   
    </tr>
</thead>

<tfoot>
	<tr> 
        <td colspan="2" class="table-page:previous" style="cursor:pointer;">&lt;&lt; Previous</td> 
		<td colspan="12" style="text-align:center;">Page <span id="tablepage"></span>&nbsp;of <span id="tablepages"></span></td> 
		<td colspan="2" class="table-page:next" style="cursor:pointer; text-align:right;">Next &gt;&gt;</td> 
	</tr>
<!--
	<tr>
        <td colspan="8" style="text-align:center;"><span id="tablefiltercount"></span>&nbsp;out of <span id="tableallcount"></span>&nbsp;goals in filter</td> 	
    </tr>
-->    
</tfoot>

<tbody class="black">
	<?php
    foreach ($games as $game)
    {
	$gameid = (int) $game['id'];
	$Date = (string) $game['Date'];
	$idA = (int) $game['idA'];
	$NameA = (string) $game['NameA'];
	$idB = (int) $game['idB'];
	$NameB = (string) $game['NameB'];
	$RatingA = (float) $game['RatingA'];
	$RatingB = (float) $game['RatingB'];
	$GoalsA = (int) $game['GoalsA'];
	$GoalsB = (int) $game['GoalsB'];
	$ExpectedScoreA = (float) $game['ExpectedScoreA'];
	$ExpectedScoreB = (float) $game['ExpectedScoreB'];
	$ActualScore = (float) $game['ActualScore'];
	$AdjustmentA = (float) $game['AdjustmentA'];
	$AdjustmentB = (float) $game['AdjustmentB'];
	$SumOfGoals = (int) $game['SumOfGoals'];
	$GoalDifference = (int) $game['GoalDifference'];
    $isPlayerA = $idA === $playerId;
    $opponentId = $isPlayerA ? $idB : $idA;
    $opponentName = $isPlayerA ? $NameB : $NameA;
    $goalsFor = $isPlayerA ? $GoalsA : $GoalsB;
    $goalsAgainst = $isPlayerA ? $GoalsB : $GoalsA;
    $playerRating = $isPlayerA ? $RatingA : $RatingB;
    $opponentRating = $isPlayerA ? $RatingB : $RatingA;
    $expectedScore = $isPlayerA ? $ExpectedScoreA : $ExpectedScoreB;
    $adjustment = $isPlayerA ? $AdjustmentA : $AdjustmentB;
    $isDraw = abs($ActualScore - 0.5) < 0.001;
    $isWin = !$isDraw && (($isPlayerA && abs($ActualScore - 1.0) < 0.001) || (!$isPlayerA && abs($ActualScore) < 0.001));
	?>
    
    <tr style="text-align:right;">
        
        <td><a href="game.php?id=<?php echo $gameid ?>"><?php echo $gameid ?></a></td>
        <td>&nbsp;<?php echo date('M d Y, H:i', strtotime($Date)) ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
        <td><?php echo individual3_player_link($idA, $NameA); ?></td>
        <td><?php echo $GoalsA ?></td>
        <td style="text-align:left;"><?php echo $GoalsB ?></td>
        <td style="text-align:left;"><?php echo individual3_player_link($idB, $NameB); ?></td>
        
        <td style="text-align:left;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?php 	
			if ($isWin)
				{echo "<span class='blue'>Win</span>";} 
			elseif ($isDraw) 
				{echo "-";}
			else 
				{echo "<span class='red'>Loss</span>";}
		?></td> 
        
        <td style="text-align:left;"><?php echo individual3_player_link($opponentId, $opponentName); ?></td>
        
        <td><?php echo $goalsFor; ?></td>
        <td><?php echo $goalsAgainst; ?></td>
        <td><?php 
			if ($isDraw)
				{echo $GoalDifference;}
			elseif (!$isWin)
				{echo "<span class='red'>"; echo -$GoalDifference; echo "</span>";} 
			else 
				{echo "<span class='blue'>"; echo $GoalDifference; echo "</span>";}
        ?></td>
        <td><?php echo $SumOfGoals ?></td>
      <td><?php echo round($playerRating); ?></td>
        <td><?php echo round($opponentRating); ?></td>
    
        
        <td><?php echo number_format(100 * $expectedScore, 1); echo "%"; ?></td>
        <td><?php 
			if ($adjustment >= 0)
				{echo "<span class='blue'>"; echo number_format($adjustment, 1); echo "</span>";}
			else
				{echo "<span class='red'>"; echo number_format($adjustment, 1); echo "</span>";}
		?></td>
    </tr> 
    
    
    
    <?php
    }  
    ?> 
</tbody>

</table>

</div><!-- .k2-table-wrap -->

</div><!-- .k2-page-nav -->
</body>
</html>




