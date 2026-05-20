<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<link href="stylesheets/main2.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/elolist.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/theme.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/elolist.js" ></script>

</head>

<body class="k2-site">

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>

<?php $id=$_GET['id']; ?>




<?php 
include $_SERVER["DOCUMENT_ROOT"] . "/../config/ko2unitydb_config.php";

//mysql_connect(localhost,$username,$password);
//@mysql_select_db($database) or die( "Unable to select database");
	$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
	if (mysqli_connect_errno())
  	{
  		die("Failed to connect to MySQL: " . mysqli_connect_error());
  	}

	$query = "SELECT * FROM ratedresults WHERE id='$id'";
	//$result = mysql_query($query) or die("SELECT Error: ".mysql_error()); 
	$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 

	mysqli_close($con);
?>

<table class="example table-autosort table-autofilter table-stripeclass:alternate table-autostripe table-rowshade-alternate table-autopage:50 table-page-number:tablepage table-page-count:tablepages table-filtered-rowcount:tablefiltercount table-rowcount:tableallcount"> 

<thead>
	<tr style="text-align:right;">
    	<th style="text-align:left;">ID</th>
        <th style="text-align:left;">&nbsp;Date</th>
        <th style="text-align:left;">Team A</th>
        <th></th>
        <th></th>
        <th style="text-align:left;">Team B</th>
        <th >&nbsp;&nbsp;&nbsp;Diff</th>
        <th >Sum</th>
        <th style="text-align:left;">&nbsp;&nbsp;&nbsp;&nbsp;Winner</th>
        <th>Rating A</th>
        <th>Rating B</th>
        <th>Rating Diff</th>
       	<th>ES Winner</th> 
        <th>Adjustment</th>
	</tr>
</thead>

<tbody class="black">
	<?php
    $i = "1";
    while ($row = mysqli_fetch_row($result))
    {  
    
	$id = $row[0];
	$Date = $row[1];
	$idA = $row[2];
	$NameA = $row[3];
	$idB = $row[4];
	$NameB = $row[5];
	$RatingA = $row[6];
	$RatingB = $row[7];
	$RatingDifference = $row[8];
	$GoalsA = $row[9];
	$GoalsB = $row[10];
	$ExpectedScoreA = $row[18];
	$ExpectedScoreB = $row[19];
	$ActualScore = $row[20];
	$AdjustmentA = $row[21];
	$SumOfGoals = $row[25];
	$GoalDifference = $row[26];
	?>
    
    <tr style="text-align:right;">
        <td><?php echo $id ?></td>
        <td>&nbsp;<?php echo $Date ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
        <td><a href="individual1.php?id=<?php echo $idA ?>"><?php echo $NameA ?></a></td>
        <td><?php echo $GoalsA ?></td>
        <td style="text-align:left;"><?php echo $GoalsB ?></td>
        <td style="text-align:left;"><a href="individual1.php?id=<?php echo $idB ?>"><?php echo $NameB ?></a></td>
        <td><?php echo $GoalDifference ?></td>
        <td><?php echo $SumOfGoals ?></td>
        <td style="text-align:left;">&nbsp;&nbsp;&nbsp;&nbsp;
			<?php	if 		($ActualScore == 1) {echo ("<a href=\"players1.php?id=" .$idA. "\">" .$NameA. "</a>");}
			      	elseif 	($ActualScore == 0) {echo ("<a href=\"players1.php?id=" .$idB. "\">" .$NameB. "</a>");}
					else 	{echo "Draw";}
			?></td>
        <td><?php echo round($RatingA) ?></td>
        <td><?php echo round($RatingB) ?></td>
        <td><?php echo number_format(abs($RatingDifference), 1) ?></td>
        <td><?php	if 		($ActualScore == 1) {echo number_format(100*$ExpectedScoreA, 1); echo "%";}
			      	elseif 	($ActualScore == 0) {echo number_format(100*$ExpectedScoreB, 1); echo "%";}
					else 	{echo number_format(min(100*$ExpectedScoreA, 100*$ExpectedScoreB), 1); echo "%";}
			?></td>       
        <td><?php echo "&#177; "; echo number_format(abs($AdjustmentA), 1); ?></td>
	</tr> 
    
    <?php
	$i++; 
    }  
    ?> 
</tbody>

</table> 


</div><!-- .k2-page-nav -->
</body>
</html>




