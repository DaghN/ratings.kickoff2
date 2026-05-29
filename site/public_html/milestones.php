<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings — Milestones</title>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="stylesheets/player-milestones.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-milestones.css'); ?>" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>

</head>

<body class="k2-site">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2HubTabActive = 'milestones';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/hub_nav.php';
?>

<main class="k2-milestones-hub-stub" id="main">
	<h1 class="k2-panel-heading k2-milestones-hub-stub__title">Milestones</h1>

	<p class="k2-milestones-hub-stub__lede">
		Shared landmarks on this ladder — many players can earn the same milestone.
		That is different from the <a href="server2.php">Hall of Fame</a>, where each record has a single holder.
	</p>

	<p class="k2-ms-meta-hint">
		This hub is under construction. Coming next: recent unlocks, a catalog of all milestones, and achiever lists with match links.
	</p>

	<h2 class="k2-panel-heading">Explore now</h2>
	<ul class="k2-milestones-hub-stub__links">
		<li><a href="ranked10.php">Leaderboards &rarr; Milestones</a> — who has unlocked the most</li>
		<li>Open any <strong>player profile</strong> &rarr; <strong>Milestones</strong> pill for a personal garden (<span class="k2-lb-ms-tier--pitch">aspirational</span> &middot;
			<span class="k2-lb-ms-tier--chrome">dedicated</span> &middot;
			<span class="k2-lb-ms-tier--amber">accomplished</span> &middot;
			<span class="k2-lb-ms-tier--holo">legendary</span>)</li>
		<li><a href="server2.php#k2-ms-achievers-heading">Hall of Fame &rarr; Milestone achievers</a> — trial list (Double Digit Merchant) until this hub hosts achievers</li>
		<li><a href="server1.php">Activity</a> — server charts and recent milestone digest (until charts move here)</li>
	</ul>

	<p class="k2-ms-meta-hint k2-milestones-hub-stub__planned">
		Planned sub-navigation: <strong>Home</strong> &middot; <strong>Story</strong> &middot; <strong>Charts</strong> — not wired yet.
	</p>
</main>

</body>
</html>
