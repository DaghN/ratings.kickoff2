<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<link href="stylesheets/main2.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/elolist.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/theme.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/elolist.js" ></script>
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>

</head>

<body class="k2-site">

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>

<ul id="aboutmenu">
        <li><a href="server1.php" title="" class="noncurrent">Server Stats</a></li>
        <li><a href="ranked1.php" title="" class="noncurrent">Player Ranks</a></li>
        <?php $playerSearchAsNavItem = true; include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_search_bar.php"; ?>
</ul>

<br />
<br />

<ul id="aboutmenu">
        <li><a href="individualA.php?id=<?php if (isset($id)) echo $id ?>" title="" class="noncurrent">Profile</a></li>
        <li><a href="individualB.php?id=<?php if (isset($id)) echo $id ?>" title="" class="noncurrent">Opponents</a></li>
        <li><a href="individualC.php?id=<?php if (isset($id)) echo $id ?>" title="" class="current">Games</a></li>
</ul>

<br />
<br />


</div><!-- .k2-page-nav -->
</body>
</html>
