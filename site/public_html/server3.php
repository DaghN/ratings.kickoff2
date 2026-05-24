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

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>

<?php
$k2HubTabActive = 'games';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/hub_nav.php";
?>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_rated_game_row.php';

include $_SERVER["DOCUMENT_ROOT"] . "/../config/ko2unitydb_config.php";

//mysql_connect(localhost,$username,$password);
//@mysql_select_db($database) or die( "Unable to select database");
	$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
	if (mysqli_connect_errno())
  	{
  		die("Failed to connect to MySQL: " . mysqli_connect_error());
  	}

$minGames = 50;
$countResult = mysqli_query($con, "SELECT COUNT(*) FROM ratedresults WHERE `Date` >= DATE_SUB(NOW(), INTERVAL 7 DAY)")
	or die("SELECT Error: " . mysqli_error($con));
$weekCount = (int) mysqli_fetch_row($countResult)[0];

if ($weekCount >= $minGames) {
	$query = "SELECT * FROM ratedresults WHERE `Date` >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY id DESC";
} else {
	$query = "SELECT * FROM ratedresults ORDER BY id DESC LIMIT " . $minGames;
}
$result = mysqli_query($con, $query) or die("SELECT Error: " . mysqli_error($con));

mysqli_close($con);
?>

<div class="k2-table-wrap">

<table class="k2-table table-autosort">

<thead>
	<tr style="text-align:right;">
    	<th class="table-sortable:numeric" style="text-align:left;">ID</th>
        <th class="table-sortable:date" style="text-align:left;">&nbsp;Date</th>
        <th class="table-sortable:ignorecase">Team A</th>
        <th class="table-sortable:numeric"></th>
        <th class="table-sortable:numeric"></th>
        <th class="table-sortable:ignorecase" style="text-align:left;">Team B</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;Diff</th>
        <th class="table-sortable:numeric">Sum</th>
        <th class="table-sortable:ignorecase" style="text-align:left;">&nbsp;&nbsp;&nbsp;&nbsp;Winner</th>
        <th class="table-sortable:numeric">Rating A</th>
        <th class="table-sortable:numeric">Rating B</th>
        <th class="table-sortable:numeric">Rating Diff</th>
       	<th class="table-sortable:numeric">ES Winner</th> 
        <th class="table-sortable:ignorecase" style="text-align:left;">Adjustment</th>
        <th class="table-sortable:ignorecase"></th>
	</tr>
</thead>

<tbody class="black">
<?php while ($row = mysqli_fetch_assoc($result)) { ?>
	<?php echo k2_rated_game_row_html($row, ['id_mode' => 'link']); ?>
<?php } ?>
</tbody>

</table>

</div><!-- .k2-table-wrap -->


</div><!-- .k2-page-nav -->
</body>
</html>




