<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="stylesheets/player-milestones.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-milestones.css'); ?>" rel="stylesheet" type="text/css" />

</head>

<body class="k2-site">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<div class="k2-page-nav">

<?php
include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_league_period_page.php';

$request = k2_league_period_parse_request();
$loaded = null;
$queryError = null;

if ($request !== null) {
    $con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
    $loaded = k2_league_period_load($con, $request['cup'], $request['period'], $request['start']);
    if ($loaded === null) {
        $queryError = 'invalid_period';
    }
    mysqli_close($con);
}
?>

<article class="k2-league-period">
<?php if ($request === null) { ?>
	<h1 class="k2-panel-heading">League</h1>
	<p class="k2-ms-meta-hint">Invalid or missing league parameters. Open a league from a milestone card or Status.</p>
<?php } elseif ($queryError !== null) { ?>
	<h1 class="k2-panel-heading">League</h1>
	<p class="k2-ms-meta-hint">Could not resolve that league period.</p>
<?php } else { ?>
	<h1 class="k2-panel-heading"><?php echo k2_h($loaded['title']); ?></h1>
	<p class="k2-league-period__meta k2-ms-meta-hint"><?php echo k2_h($loaded['subtitle']); ?></p>
<?php if ((int) $loaded['total_games'] > 0) { ?>
	<p class="k2-league-period__games"><?php echo (int) $loaded['total_games']; ?> rated games in this period</p>
<?php } ?>
	<div class="k2-league-period__table">
<?php k2_league_period_render_table($loaded); ?>
	</div>
	<p class="k2-league-period__footer k2-ms-meta-hint">
		<a href="status.php">Current leagues on Status</a>
		· Same standings layout as the Status hub
	</p>
<?php } ?>
</article>

</div><!-- .k2-page-nav -->

</body>
</html>
