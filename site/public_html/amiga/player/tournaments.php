<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga player tournaments</title>
<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="/stylesheets/player-feast.css" rel="stylesheet" type="text/css" />
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; ?>
</head>
<body class="k2-site k2-player-wing player-feast-body">

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
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_tournament_lib.php';

include __DIR__ . '/../../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");

try {
    $pm = amiga_player_load($con, $playerId);
} catch (RuntimeException $e) {
    mysqli_close($con);
    http_response_code(404);
    exit('Player not found.');
}

$eventFilter = 'all';
if (isset($_GET['filter']) && $_GET['filter'] === 'world-cup') {
    $eventFilter = 'world-cup';
}

$allTournamentRows = amiga_player_tournament_participation_all($con, $playerId);
$countryOptions = amiga_player_tournament_participation_countries($allTournamentRows);
$countryFilter = '';
if (isset($_GET['country']) && is_string($_GET['country'])) {
    $countryCandidate = trim($_GET['country']);
    if ($countryCandidate !== '' && in_array($countryCandidate, $countryOptions, true)) {
        $countryFilter = $countryCandidate;
    }
}

$tournaments = amiga_player_tournament_participation_filter_events(
    $allTournamentRows,
    $eventFilter,
    $countryFilter
);
$tournamentCount = count($tournaments);
$listSummary = amiga_player_tournaments_list_summary(
    $tournamentCount,
    $eventFilter,
    $countryFilter,
    $allTournamentRows !== [],
);
mysqli_close($con);

amiga_player_publish_hero_context($pm);

?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_hero.php'; ?>

<?php
$k2AmigaPlayerTabActive = 'tournaments';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_nav.php';
?>

<div class="k2-player-tournament-filters">
	<div class="k2-player-tournament-filters__row">
		<span class="server-period-activity-leaderboard__picker-label">Event</span>
		<nav class="k2-player-tournament-filters__pills" data-k2-carry-scroll aria-label="Filter events">
			<a href="<?php echo k2_h(amiga_player_tournaments_filter_url($playerId, 'all', $countryFilter)); ?>" class="k2-player-nav__btn<?php echo $eventFilter === 'all' ? ' is-active' : ''; ?>">All</a>
			<a href="<?php echo k2_h(amiga_player_tournaments_filter_url($playerId, 'world-cup', $countryFilter)); ?>" class="k2-player-nav__btn<?php echo $eventFilter === 'world-cup' ? ' is-active' : ''; ?>">World Cups</a>
		</nav>
	</div>
<?php if ($countryOptions !== []) { ?>
	<div class="k2-player-tournament-filters__row">
		<span class="server-period-activity-leaderboard__picker-label">Location</span>
		<nav class="k2-player-tournament-filters__pills" data-k2-carry-scroll aria-label="Filter by event location">
			<a href="<?php echo k2_h(amiga_player_tournaments_filter_url($playerId, $eventFilter)); ?>" class="k2-player-nav__btn<?php echo $countryFilter === '' ? ' is-active' : ''; ?>">All locations</a>
<?php foreach ($countryOptions as $countryName) { ?>
			<a href="<?php echo k2_h(amiga_player_tournaments_filter_url($playerId, $eventFilter, $countryName)); ?>" class="k2-player-nav__btn<?php echo $countryFilter === $countryName ? ' is-active' : ''; ?>"><?php echo k2_h($countryName); ?></a>
<?php } ?>
		</nav>
	</div>
<?php } ?>
</div>

<div class="k2-player-games-status">
	<?php echo k2_h($listSummary); ?>
</div>

<?php
if ($tournaments !== []) {
    amiga_profile_render_tournament_history_table($tournaments);
}
?>

</div><!-- .k2-page-nav -->

</body>
</html>
