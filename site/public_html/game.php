<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>

</head>

<body class="k2-site">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if (mysqli_connect_errno()) {
    die('Failed to connect to MySQL: ' . mysqli_connect_error());
}

$stmt = mysqli_prepare($con, 'SELECT * FROM ratedresults WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = $result ? mysqli_fetch_assoc($result) : null;
mysqli_free_result($result);
mysqli_stmt_close($stmt);
mysqli_close($con);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_rated_game_row.php';
?>

<div class="k2-table-wrap">

<?php if ($row === null) { ?>
<p>Game not found.</p>
<?php } else { ?>
<table class="k2-table">

<thead>
	<tr style="text-align:right;">
    	<th style="text-align:left;">ID</th>
        <th style="text-align:left;">&nbsp;Date</th>
        <th style="text-align:left;">Team A</th>
        <th></th>
        <th></th>
        <th style="text-align:left;">Team B</th>
        <th>&nbsp;&nbsp;&nbsp;Diff</th>
        <th>Sum</th>
        <th style="text-align:left;">&nbsp;&nbsp;&nbsp;&nbsp;Winner</th>
        <th>Rating A</th>
        <th>Rating B</th>
        <th>Rating Diff</th>
       	<th>ES Winner</th>
        <th style="text-align:left;">Adjustment</th>
        <th></th>
	</tr>
</thead>

<tbody class="black">
	<?php echo k2_rated_game_row_html($row, ['id_mode' => 'plain']); ?>
</tbody>

</table>
<?php } ?>

</div><!-- .k2-table-wrap -->

</div><!-- .k2-page-nav -->
</body>
</html>
