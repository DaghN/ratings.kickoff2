<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga head-to-head</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="/stylesheets/player-feast.css" rel="stylesheet" type="text/css" />
</head>
<body class="k2-site player-feast-body">

<?php
$id1 = isset($_GET['id1']) ? (int) $_GET['id1'] : 0;
$id2 = isset($_GET['id2']) ? (int) $_GET['id2'] : 0;
if ($id1 < 1 || $id2 < 1 || $id1 === $id2) {
    http_response_code(404);
    exit('Head-to-head pair not found.');
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_matchup_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_amiga_routes.php';
include __DIR__ . '/../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");

$playerA = amiga_player_identity_row($con, $id1);
$playerB = amiga_player_identity_row($con, $id2);
if ($playerA === null || $playerB === null) {
    mysqli_close($con);
    http_response_code(404);
    exit('Head-to-head pair not found.');
}

$rowAB = amiga_player_matchup_directed_row($con, $id1, $id2);
$rowBA = amiga_player_matchup_directed_row($con, $id2, $id1);
$totalsAB = amiga_player_matchup_totals_from_row($rowAB);
$totalsBA = amiga_player_matchup_totals_from_row($rowBA);
mysqli_close($con);

$pairGames = max($totalsAB['games'], $totalsBA['games']);
$pageTitle = $playerA['name'] . ' vs ' . $playerB['name'];
?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<div class="k2-page-nav">

<p style="padding:0.75rem 1.25rem 0;margin:0">
	<a class="k2-link-star" href="<?php echo k2_h(k2_amiga_route('amiga-player-profile', ['id' => $id1])); ?>">← <?php echo k2_h($playerA['name']); ?></a>
	· <a class="k2-link-star" href="<?php echo k2_h(k2_amiga_route('amiga-player-profile', ['id' => $id2])); ?>"><?php echo k2_h($playerB['name']); ?></a>
</p>

<header style="padding:0.75rem 1.25rem 0">
	<h1 class="k2-panel-heading" style="margin:0 0 0.35rem"><?php echo k2_h($pageTitle); ?></h1>
	<p class="k2-hub-page-intro" style="margin:0">Directed summaries from stored head-to-head totals — not a live scan of every game.</p>
</header>

<?php if ($pairGames < 1) { ?>
<p style="padding:1rem 1.25rem;margin:0" class="k2-hub-page-intro">No rated games between these players in the Amiga ladder.</p>
<?php } else { ?>
<section style="padding:0 1.25rem 1.5rem">
	<h2 class="k2-panel-heading"><?php echo k2_h($playerA['name']); ?> vs <?php echo k2_h($playerB['name']); ?></h2>
	<dl class="k2-amiga-profile-dl" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(9rem,1fr));gap:0.75rem 1.25rem;margin:0">
		<div><dt style="opacity:0.75;font-size:0.85rem">W – D – L</dt><dd style="margin:0;font-variant-numeric:tabular-nums"><?php
            echo $totalsAB['wins'] . ' – ' . $totalsAB['draws'] . ' – ' . $totalsAB['losses'];
        ?></dd></div>
		<div><dt style="opacity:0.75;font-size:0.85rem">Goals</dt><dd style="margin:0;font-variant-numeric:tabular-nums"><?php
            echo $totalsAB['goals_for'] . ' – ' . $totalsAB['goals_against'];
        ?></dd></div>
		<div><dt style="opacity:0.75;font-size:0.85rem">Games</dt><dd style="margin:0;font-variant-numeric:tabular-nums"><?php echo $totalsAB['games']; ?></dd></div>
	</dl>
	<p style="margin:0.75rem 0 0">
		<a class="k2-link-star" href="<?php echo k2_h(k2_amiga_route('amiga-player-games', ['id' => $id1, 'opponent' => $id2])); ?>">All games — <?php echo k2_h($playerA['name']); ?> vs <?php echo k2_h($playerB['name']); ?></a>
	</p>
</section>

<section style="padding:0 1.25rem 2rem">
	<h2 class="k2-panel-heading"><?php echo k2_h($playerB['name']); ?> vs <?php echo k2_h($playerA['name']); ?></h2>
	<dl class="k2-amiga-profile-dl" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(9rem,1fr));gap:0.75rem 1.25rem;margin:0">
		<div><dt style="opacity:0.75;font-size:0.85rem">W – D – L</dt><dd style="margin:0;font-variant-numeric:tabular-nums"><?php
            echo $totalsBA['wins'] . ' – ' . $totalsBA['draws'] . ' – ' . $totalsBA['losses'];
        ?></dd></div>
		<div><dt style="opacity:0.75;font-size:0.85rem">Goals</dt><dd style="margin:0;font-variant-numeric:tabular-nums"><?php
            echo $totalsBA['goals_for'] . ' – ' . $totalsBA['goals_against'];
        ?></dd></div>
		<div><dt style="opacity:0.75;font-size:0.85rem">Games</dt><dd style="margin:0;font-variant-numeric:tabular-nums"><?php echo $totalsBA['games']; ?></dd></div>
	</dl>
	<p style="margin:0.75rem 0 0">
		<a class="k2-link-star" href="<?php echo k2_h(k2_amiga_route('amiga-player-games', ['id' => $id2, 'opponent' => $id1])); ?>">All games — <?php echo k2_h($playerB['name']); ?> vs <?php echo k2_h($playerA['name']); ?></a>
	</p>
</section>
<?php } ?>

</div>

</body>
</html>
