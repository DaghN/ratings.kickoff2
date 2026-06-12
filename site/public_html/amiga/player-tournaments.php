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
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_tournament_lib.php';

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

$eventFilter = 'all';
if (isset($_GET['filter']) && is_string($_GET['filter'])) {
    if (in_array($_GET['filter'], ['world-cup', 'cups'], true)) {
        $eventFilter = $_GET['filter'];
    }
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
mysqli_close($con);

$id = $playerId;
$Name = $pm['name'];
$Rating = $pm['rating'];
$NumberGames = $pm['games'];
$Display = $pm['display'] ? 1 : 0;
$rank = $pm['rank'];
$Country = $pm['country'];

$filterLabelParts = [];
if ($eventFilter === 'world-cup') {
    $filterLabelParts[] = 'World Cups';
} elseif ($eventFilter === 'cups') {
    $filterLabelParts[] = 'Cups';
}
if ($countryFilter !== '') {
    $filterLabelParts[] = $countryFilter;
}
$filterLabelSuffix = $filterLabelParts === [] ? '' : ' (' . implode(' · ', $filterLabelParts) . ')';
?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<div class="k2-page-nav">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_hero.php'; ?>

<?php
$k2AmigaPlayerTabActive = 'tournaments';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_nav.php';
?>

<nav class="k2-player-nav k2-nav-pills" aria-label="Filter events" style="padding:0 1.25rem 0.5rem;margin:0">
	<div class="k2-player-nav__links">
		<a href="<?php echo k2_h(amiga_player_tournaments_filter_url($playerId, 'all', $countryFilter)); ?>" class="k2-player-nav__btn<?php echo $eventFilter === 'all' ? ' is-active' : ''; ?>">All</a>
		<a href="<?php echo k2_h(amiga_player_tournaments_filter_url($playerId, 'world-cup', $countryFilter)); ?>" class="k2-player-nav__btn<?php echo $eventFilter === 'world-cup' ? ' is-active' : ''; ?>">World Cups</a>
		<a href="<?php echo k2_h(amiga_player_tournaments_filter_url($playerId, 'cups', $countryFilter)); ?>" class="k2-player-nav__btn<?php echo $eventFilter === 'cups' ? ' is-active' : ''; ?>">Cups</a>
	</div>
</nav>
<?php if ($countryOptions !== []) { ?>
<nav class="k2-player-nav k2-nav-pills" aria-label="Filter by event location" style="padding:0 0 1rem 1.25rem;margin:0">
	<div class="k2-player-nav__links">
		<a href="<?php echo k2_h(amiga_player_tournaments_filter_url($playerId, $eventFilter)); ?>" class="k2-player-nav__btn<?php echo $countryFilter === '' ? ' is-active' : ''; ?>">All locations</a>
		<?php foreach ($countryOptions as $countryName) { ?>
		<a href="<?php echo k2_h(amiga_player_tournaments_filter_url($playerId, $eventFilter, $countryName)); ?>" class="k2-player-nav__btn<?php echo $countryFilter === $countryName ? ' is-active' : ''; ?>"><?php echo k2_h($countryName); ?></a>
		<?php } ?>
	</div>
</nav>
<?php } ?>

<div class="k2-player-games-status" style="padding:0 1.25rem 1rem">
	<?php echo (int) $tournamentCount; ?> tournament<?php echo $tournamentCount === 1 ? '' : 's'; ?><?php
        echo k2_h($filterLabelSuffix);
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
