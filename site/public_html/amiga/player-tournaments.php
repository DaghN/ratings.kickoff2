<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga player tournaments</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="/stylesheets/player-feast.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="/js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>
</head>
<body class="k2-site player-feast-body">

<?php
$playerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($playerId < 1) {
    http_response_code(404);
    exit('Player not found.');
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_profile_blocks.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_lib.php';

include __DIR__ . '/../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");

try {
    $pm = amiga_player_load($con, $playerId);
} catch (RuntimeException $e) {
    mysqli_close($con);
    http_response_code(404);
    exit('Player not found.');
}

$eventFilter = isset($_GET['filter']) && $_GET['filter'] === 'world-cup' ? 'world-cup' : 'all';
$tournaments = amiga_player_all_tournaments($con, $playerId, $eventFilter);
$tournamentCount = count($tournaments);
mysqli_close($con);

$id = $playerId;
$Name = $pm['name'];
$Rating = $pm['rating'];
$NumberGames = $pm['games'];
$Display = $pm['display'] ? 1 : 0;
$rank = $pm['rank'];
$Country = $pm['country'];
?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<div class="k2-page-nav">

<p style="padding:0.75rem 1.25rem 0;margin:0">
	<a class="k2-link-star" href="/amiga/rating.php">← Amiga ladder</a>
	· <a class="k2-link-star" href="/amiga/tournaments.php">Tournament index</a>
</p>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_hero.php'; ?>

<?php
$k2AmigaPlayerTabActive = 'tournaments';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_nav.php';
?>

<nav class="k2-player-nav k2-nav-pills" aria-label="Filter events" style="padding:0 1.25rem 1rem;margin:0">
	<div class="k2-player-nav__links">
		<a href="/amiga/player-tournaments.php?id=<?php echo $playerId; ?>" class="k2-player-nav__btn<?php echo $eventFilter === 'all' ? ' is-active' : ''; ?>">All</a>
		<a href="/amiga/player-tournaments.php?id=<?php echo $playerId; ?>&amp;filter=world-cup" class="k2-player-nav__btn<?php echo $eventFilter === 'world-cup' ? ' is-active' : ''; ?>">World Cups</a>
	</div>
</nav>

<div class="k2-player-games-status" style="padding:0 1.25rem 1rem">
	<?php echo (int) $tournamentCount; ?> tournament<?php echo $tournamentCount === 1 ? '' : 's'; ?><?php
        if ($eventFilter === 'world-cup') {
            echo ' (World Cups)';
        }
    ?> — newest first (click column headers to re-sort).
</div>

<?php
if ($tournaments === []) {
    echo '<p style="padding:0 1.25rem 2rem">No tournament participation on record.</p>';
} else {
    amiga_profile_render_tournament_history_table($tournaments);
}
?>

</div>

</body>
</html>
