<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga player profile</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="/stylesheets/player-feast.css" rel="stylesheet" type="text/css" />
<script src="/js/chart.umd.min.js"></script>
<script src="/js/chartjs-adapter-date-fns.bundle.min.js"></script>
<script src="/js/chart-theme.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/chart-theme.js'); ?>"></script>
<script src="/js/chart-date-range.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/chart-date-range.js'); ?>"></script>
<script type="text/javascript" src="/js/player-rating-history.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-rating-history.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-rating-chart.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-rating-chart.js'); ?>" defer="defer"></script>
</head>
<body class="k2-site player-feast-body">

<?php
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    http_response_code(404);
    exit('Player not found.');
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
include __DIR__ . '/../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_profile_blocks.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_matchup_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_tournament_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_lib.php';

try {
    $pm = amiga_player_load($con, $id);
} catch (RuntimeException $e) {
    mysqli_close($con);
    http_response_code(404);
    exit('Player not found.');
}

$recentTournaments = amiga_player_recent_tournaments($con, $id, 5);
$topOpponents = amiga_player_top_opponents($con, $id, 10);
$tournamentTotals = amiga_player_tournament_totals_row($con, $id);
$totalTournaments = $tournamentTotals !== null
    ? (int) ($tournamentTotals['tournaments_played'] ?? 0)
    : count($recentTournaments);
mysqli_close($con);

$Name = $pm['name'];
$Rating = $pm['rating'];
$NumberGames = $pm['games'];
$Display = $pm['display'] ? 1 : 0;
$rank = $pm['rank'];
$Country = $pm['country'];
$playerId = $pm['id'];
?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<div class="k2-page-nav">

<p style="padding:0.75rem 1.25rem 0;margin:0">
	<a class="k2-link-star" href="/amiga/rating.php">← Amiga ladder</a>
	· <a class="k2-link-star" href="/amiga/tournaments.php">Tournaments</a>
</p>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_hero.php'; ?>

<?php
$k2AmigaPlayerTabActive = 'profile';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_nav.php';

amiga_profile_render_career($pm);
amiga_profile_render_recent_tournaments($recentTournaments, $playerId, $totalTournaments);
amiga_profile_render_top_opponents($topOpponents);
amiga_profile_render_rating_chart($playerId);
?>

</div>

</body>
</html>
