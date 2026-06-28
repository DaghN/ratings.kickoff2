<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga player tournaments</title>
<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="/stylesheets/player-feast.css" rel="stylesheet" type="text/css" />
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; ?>
<script type="text/javascript" src="/js/k2-archive-listbox.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-archive-listbox.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/individual3-filters.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/individual3-filters.js'); ?>" defer="defer"></script>
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
$catalogCountries = array_keys(amiga_tournament_index_country_counts($allTournamentRows));
$catalogYears = array_keys(amiga_tournament_index_year_counts($allTournamentRows));

$countryFilter = '';
if (isset($_GET['country']) && is_string($_GET['country'])) {
    $countryCandidate = trim($_GET['country']);
    if ($countryCandidate !== '' && in_array($countryCandidate, $catalogCountries, true)) {
        $countryFilter = $countryCandidate;
    }
}

$yearFilter = 0;
if (isset($_GET['year'])) {
    $yearCandidate = filter_var($_GET['year'], FILTER_VALIDATE_INT);
    if ($yearCandidate !== false && $yearCandidate > 0 && in_array((int) $yearCandidate, $catalogYears, true)) {
        $yearFilter = (int) $yearCandidate;
    }
}

$countryFacetRows = amiga_player_tournament_participation_filter_events(
    $allTournamentRows,
    $eventFilter,
    '',
    $yearFilter
);
$yearFacetRows = amiga_player_tournament_participation_filter_events(
    $allTournamentRows,
    $eventFilter,
    $countryFilter
);
$countryCounts = amiga_tournament_index_inject_selected_country(
    amiga_tournament_index_country_counts($countryFacetRows),
    $countryFilter
);
$yearCounts = amiga_tournament_index_inject_selected_year(
    amiga_tournament_index_year_counts($yearFacetRows),
    $yearFilter
);
$countryChoices = amiga_tournament_index_country_listbox_choices($countryCounts);
$yearChoices = amiga_tournament_index_year_listbox_choices($yearCounts);
$showCountryFilter = count($countryCounts) > 1 || $countryFilter !== '';
$showYearFilter = count($yearCounts) > 1 || $yearFilter > 0;

$tournaments = amiga_player_tournament_participation_filter_events(
    $allTournamentRows,
    $eventFilter,
    $countryFilter,
    $yearFilter
);
$tournamentCount = count($tournaments);
$listSummary = amiga_player_tournaments_list_summary(
    $tournamentCount,
    $eventFilter,
    $countryFilter,
    $allTournamentRows !== [],
    $yearFilter,
);
$tournamentsFilterAction = k2_amiga_route('amiga-player-tournaments');
amiga_player_publish_hero_context($pm, $con);
mysqli_close($con);

?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_wing_hub_nav.inc.php'; ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_hero.php'; ?>

<?php
$k2AmigaPlayerTabActive = 'tournaments';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_nav.php';
?>

<?php
$k2PlayerTournamentsPlayerId = $playerId;
$k2PlayerTournamentsEventFilter = $eventFilter;
$k2PlayerTournamentsCountryFilter = $countryFilter;
$k2PlayerTournamentsYearFilter = $yearFilter;
$k2PlayerTournamentsCountryChoices = $countryChoices;
$k2PlayerTournamentsYearChoices = $yearChoices;
$k2PlayerTournamentsShowCountryFilter = $showCountryFilter;
$k2PlayerTournamentsShowYearFilter = $showYearFilter;
$k2PlayerTournamentsFilterAction = $tournamentsFilterAction;
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_tournaments_filters_nav.php';
?>

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
