<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="stylesheets/player-milestones.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-milestones.css'); ?>" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="/js/k2-archive-listbox.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-archive-listbox.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/individual3-filters.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/individual3-filters.js'); ?>" defer="defer"></script>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; ?>

</head>

<body class="k2-site">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2HubTabActive = '';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/hub_nav.php';
?>

<?php
include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_league_period_page.php';

$request = k2_league_period_parse_request();
$loaded = null;
$queryError = null;
$games = [];
$gamesTotal = 0;
$gamesOffset = 0;
$gamesLimit = k2_league_period_games_page_size();
$gamesError = null;

if ($request !== null) {
    $con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
    $loaded = k2_league_period_load($con, $request['cup'], $request['period'], $request['start']);
    if ($loaded === null) {
        $queryError = 'invalid_period';
    } else {
        $gamesOffset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
        $gamesTotal = k2_league_period_count_games($con, $request['period'], $request['start'], $gamesError);
        if ($gamesOffset >= $gamesTotal && $gamesTotal > 0) {
            $gamesOffset = 0;
        }
        $games = k2_league_period_fetch_games(
            $con,
            $request['period'],
            $request['start'],
            $gamesOffset,
            $gamesLimit,
            $gamesError
        );
    }
}
$leagueCon = isset($con) && $con instanceof mysqli ? $con : null;
?>

<article class="k2-league-period">
<?php if ($request === null) { ?>
	<h1 class="k2-hub-chapter__title">League</h1>
	<p class="k2-ms-meta-hint">Invalid or missing league parameters. Open a league from a milestone card or Status.</p>
<?php } elseif ($queryError !== null) { ?>
	<h1 class="k2-hub-chapter__title">League</h1>
	<p class="k2-ms-meta-hint">Could not resolve that league period.</p>
<?php } else { ?>
<?php k2_league_period_render_intro($loaded); ?>
	<section class="k2-league-period__standings" aria-labelledby="k2-league-period-standings-title">
<?php k2_league_period_render_standings_header($loaded, $leagueCon); ?>
		<div class="k2-league-period__table">
<?php k2_league_period_render_table($loaded); ?>
		</div>
	</section>
<?php k2_league_period_render_games_section($loaded, $games, $gamesTotal, $gamesOffset, $gamesLimit); ?>
<?php } ?>
</article>

<?php if ($leagueCon instanceof mysqli) {
    mysqli_close($leagueCon);
} ?>

</div><!-- .k2-page-nav -->

</body>
</html>
