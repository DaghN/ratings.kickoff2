<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 — Status</title>

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/k2_head.php"; ?>
<script type="text/javascript" src="js/elolist.js"></script>
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>
<script type="text/javascript" src="js/status-period-activity.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/status-period-activity.js'); ?>" defer="defer"></script>

</head>

<body class="k2-site">

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>

<?php
$k2HubTabActive = 'status';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/hub_nav.php";
?>

<section class="k2-status-bridge">
	<div class="k2-status-bridge__intro">
		<div class="k2-status-bridge__copy">
			<p class="k2-status-tagline">Who&rsquo;s on tonight &middot; live games &middot; recent logins</p>

			<div class="k2-card">
				<h2 class="k2-card__title">Live status</h2>
				<p class="k2-card__hint">Who&rsquo;s on, recent logins, and live games are being added here now (same data as the legacy status page). Games-by-period leaderboards below already load from the database.</p>
				<p class="k2-status-bridge__link">Until those panels ship: <a href="https://joshua.kickoff2.net/status.php" target="_blank" rel="noopener">open the current status page ↗</a></p>
			</div>
		</div>

		<aside class="k2-heritage-box" aria-label="Original Amiga box art">
			<img class="k2-heritage-box__art" src="images/KO2BoxFront.jpg" width="132" alt="Kick Off 2 — original Amiga box art, 1990" loading="lazy" decoding="async" />
			<p class="k2-heritage-box__caption">Original Amiga box &middot; 1990</p>
			<p class="k2-heritage-box__note">Original Amiga box art — heritage hook for the live room.</p>
		</aside>
	</div>

	<p class="k2-hub-panel__hint">Default hub landing. Leaderboards, games archive, trends charts, and records stay on their existing pages via the tabs above.</p>

<?php
include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';
$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if (!mysqli_connect_errno()) {
    include $_SERVER['DOCUMENT_ROOT'] . '/includes/period_activity_leaderboards_section.php';
    mysqli_close($con);
    unset($con);
} else {
    echo '<div class="server-period-activity-leaderboards"><p class="server-period-activity-leaderboards-status">Could not load games-by-period tables.</p></div>';
}
?>
</section>

</div><!-- .k2-page-nav -->

</body>
</html>
