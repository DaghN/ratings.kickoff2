<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga tournaments</title>
<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="/stylesheets/amiga-tournament.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/amiga-tournament.css'); ?>" rel="stylesheet" type="text/css" />
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; ?>
<script type="text/javascript" src="/js/k2-archive-listbox.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-archive-listbox.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/individual3-filters.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/individual3-filters.js'); ?>" defer="defer"></script>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2AmigaHubTabActive = 'tournaments';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_videos_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_profile_blocks.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_snapshot_url.php';
include __DIR__ . '/../../config/ko2amiga_config.php';

$wcFilter = isset($_GET['wc']) ? (string) $_GET['wc'] : '';
if (!in_array($wcFilter, ['', 'world-cup', 'not-world-cup'], true)) {
    $wcFilter = '';
}

$typeFilter = isset($_GET['type']) ? (string) $_GET['type'] : '';
if (!in_array($typeFilter, ['', 'league', 'cup', 'league-cup'], true)) {
    $typeFilter = '';
}
// Legacy: ?type=world-cup → wc=world-cup
if (isset($_GET['type']) && (string) $_GET['type'] === 'world-cup') {
    $wcFilter = 'world-cup';
    $typeFilter = '';
}

$videosFilter = isset($_GET['videos']) ? (string) $_GET['videos'] : '';
if ($videosFilter !== 'with-videos') {
    $videosFilter = '';
}

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");
$ctx = amiga_lb_context($con);
$GLOBALS['_amiga_snapshot_context'] = $ctx;

$allRows = amiga_tournament_index_rows($con, 0, 0, $ctx);
mysqli_close($con);

$catalogCountries = array_keys(amiga_tournament_index_country_counts($allRows));
$catalogYears = array_keys(amiga_tournament_index_year_counts($allRows));
$catalogWinners = amiga_tournament_index_winner_counts($allRows);
$catalogWinnerCountries = array_keys(amiga_tournament_index_winner_country_counts($allRows));

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

$winnerFilter = 0;
$winnerFilterName = '';
if (isset($_GET['winner'])) {
    $winnerCandidate = filter_var($_GET['winner'], FILTER_VALIDATE_INT);
    if ($winnerCandidate !== false && $winnerCandidate > 0 && isset($catalogWinners[(int) $winnerCandidate])) {
        $winnerFilter = (int) $winnerCandidate;
        $winnerFilterName = (string) $catalogWinners[$winnerFilter]['name'];
    }
}

$winnerCountryFilter = '';
if (isset($_GET['winner_country']) && is_string($_GET['winner_country'])) {
    $winnerCountryCandidate = trim($_GET['winner_country']);
    if ($winnerCountryCandidate !== '' && in_array($winnerCountryCandidate, $catalogWinnerCountries, true)) {
        $winnerCountryFilter = $winnerCountryCandidate;
    }
}

$countryFacetRows = amiga_tournament_index_filter_rows(
    $allRows,
    $wcFilter,
    $typeFilter,
    $videosFilter,
    $countryFilter,
    $yearFilter,
    true,
    false,
    '',
    $winnerFilter,
    $winnerCountryFilter,
    false,
    false
);
$yearFacetRows = amiga_tournament_index_filter_rows(
    $allRows,
    $wcFilter,
    $typeFilter,
    $videosFilter,
    $countryFilter,
    $yearFilter,
    false,
    true,
    '',
    $winnerFilter,
    $winnerCountryFilter,
    false,
    false
);
$winnerFacetRows = amiga_tournament_index_filter_rows(
    $allRows,
    $wcFilter,
    $typeFilter,
    $videosFilter,
    $countryFilter,
    $yearFilter,
    false,
    false,
    '',
    $winnerFilter,
    $winnerCountryFilter,
    true,
    false
);
$winnerCountryFacetRows = amiga_tournament_index_filter_rows(
    $allRows,
    $wcFilter,
    $typeFilter,
    $videosFilter,
    $countryFilter,
    $yearFilter,
    false,
    false,
    '',
    $winnerFilter,
    $winnerCountryFilter,
    false,
    true
);

$countryCounts = amiga_tournament_index_inject_selected_country(
    amiga_tournament_index_country_counts($countryFacetRows),
    $countryFilter
);
$yearCounts = amiga_tournament_index_inject_selected_year(
    amiga_tournament_index_year_counts($yearFacetRows),
    $yearFilter
);
$winnerCounts = amiga_tournament_index_inject_selected_winner(
    amiga_tournament_index_winner_counts($winnerFacetRows),
    $winnerFilter,
    $winnerFilterName
);
$winnerCountryCounts = amiga_tournament_index_inject_selected_winner_country(
    amiga_tournament_index_winner_country_counts($winnerCountryFacetRows),
    $winnerCountryFilter
);

$countryChoices = amiga_tournament_index_country_listbox_choices($countryCounts);
$yearChoices = amiga_tournament_index_year_listbox_choices($yearCounts);
$winnerChoices = amiga_tournament_index_winner_listbox_choices($winnerCounts);
$winnerCountryChoices = amiga_tournament_index_winner_country_listbox_choices($winnerCountryCounts);

$rows = amiga_tournament_index_filter_rows(
    $allRows,
    $wcFilter,
    $typeFilter,
    $videosFilter,
    $countryFilter,
    $yearFilter,
    false,
    false,
    '',
    $winnerFilter,
    $winnerCountryFilter,
    false,
    false
);
$listSummary = amiga_tournament_index_list_summary(
    count($rows),
    $allRows !== [],
    $wcFilter,
    $typeFilter,
    $videosFilter,
    $countryFilter,
    $yearFilter,
    '',
    $winnerFilter,
    $winnerCountryFilter,
    $winnerFilterName
);
$filtersActive = amiga_tournament_index_filters_active(
    $wcFilter,
    $typeFilter,
    $videosFilter,
    $countryFilter,
    $yearFilter,
    '',
    $winnerFilter,
    $winnerCountryFilter
);
?>

<?php
$k2HubChapterTitle = 'Tournaments';
$k2HubChapterLede = amiga_tournament_index_chapter_lede_html(count($allRows));
include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_hub_chapter.inc.php';

$k2AmigaTournamentIndexWcFilter = $wcFilter;
$k2AmigaTournamentIndexFilter = $typeFilter;
$k2AmigaTournamentIndexVideosFilter = $videosFilter;
$k2AmigaTournamentIndexCountryFilter = $countryFilter;
$k2AmigaTournamentIndexYearFilter = $yearFilter;
$k2AmigaTournamentIndexWinnerFilter = $winnerFilter;
$k2AmigaTournamentIndexWinnerCountryFilter = $winnerCountryFilter;
$k2AmigaTournamentIndexCountryChoices = $countryChoices;
$k2AmigaTournamentIndexYearChoices = $yearChoices;
$k2AmigaTournamentIndexWinnerChoices = $winnerChoices;
$k2AmigaTournamentIndexWinnerCountryChoices = $winnerCountryChoices;
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_index_nav.php';
?>

<div class="k2-player-games-status" data-k2-carry-scroll>
	<?php echo k2_h($listSummary); ?>
	<a class="k2-player-games-reset<?php echo $filtersActive ? '' : ' is-idle'; ?>" href="<?php echo k2_h(amiga_tournament_index_reset_url()); ?>"<?php echo $filtersActive ? '' : ' aria-disabled="true" tabindex="-1"'; ?>>Reset filters</a>
</div>

<?php amiga_tournament_index_render_table($rows); ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_site_end.inc.php'; ?>
</body>
</html>
