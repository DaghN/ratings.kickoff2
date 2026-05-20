<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 — Status</title>

<link href="stylesheets/main2.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/elolist.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/theme.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/elolist.js"></script>
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>

</head>

<body class="k2-site">

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>

<?php
$k2HubTabActive = 'status';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/hub_nav.php";
?>

<section class="k2-status-bridge">
	<p class="k2-status-tagline">Who&rsquo;s on tonight &middot; live games &middot; recent logins</p>

	<div class="k2-card">
		<h2 class="k2-card__title">Live status</h2>
		<p class="k2-card__hint">The full online room (server pulse, recent logins, games in progress) will land here in Phase B once the live feed is wired.</p>
		<p class="k2-status-bridge__link">For now: <a href="https://joshua.kickoff2.net/status.php" target="_blank" rel="noopener">open the current status page ↗</a></p>
	</div>

	<p class="k2-hub-panel__hint">Default hub landing. Leaderboards, games archive, trends charts, and records stay on their existing pages via the tabs above.</p>
</section>

</div><!-- .k2-page-nav -->

</body>
</html>
