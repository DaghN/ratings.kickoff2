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
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_videos_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_profile_blocks.php';
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

$perfectFilter = isset($_GET['perfect']) ? (string) $_GET['perfect'] : '';
if ($perfectFilter !== 'with-participant') {
    $perfectFilter = '';
}

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");

$allRows = amiga_tournament_index_rows($con);
mysqli_close($con);

$catalogCountries = array_keys(amiga_tournament_index_country_counts($allRows));
$catalogYears = array_keys(amiga_tournament_index_year_counts($allRows));

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

$countryFacetRows = amiga_tournament_index_filter_rows(
    $allRows,
    $wcFilter,
    $typeFilter,
    $videosFilter,
    $countryFilter,
    $yearFilter,
    false,
    false,
    $perfectFilter
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
    $perfectFilter
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

$rows = amiga_tournament_index_filter_rows(
    $allRows,
    $wcFilter,
    $typeFilter,
    $videosFilter,
    $countryFilter,
    $yearFilter,
    false,
    false,
    $perfectFilter
);
$listSummary = amiga_tournament_index_list_summary(
    count($rows),
    $allRows !== [],
    $wcFilter,
    $typeFilter,
    $videosFilter,
    $countryFilter,
    $yearFilter,
    $perfectFilter,
);
?>

<?php
$k2HubChapterTitle = 'Tournaments';
$k2HubChapterLede = 'Every rated tournament in the Amiga realm.';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_hub_chapter.inc.php';

$k2AmigaTournamentIndexWcFilter = $wcFilter;
$k2AmigaTournamentIndexFilter = $typeFilter;
$k2AmigaTournamentIndexVideosFilter = $videosFilter;
$k2AmigaTournamentIndexPerfectFilter = $perfectFilter;
$k2AmigaTournamentIndexCountryFilter = $countryFilter;
$k2AmigaTournamentIndexYearFilter = $yearFilter;
$k2AmigaTournamentIndexCountryChoices = $countryChoices;
$k2AmigaTournamentIndexYearChoices = $yearChoices;
$k2AmigaTournamentIndexShowCountryFilter = $showCountryFilter;
$k2AmigaTournamentIndexShowYearFilter = $showYearFilter;
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_index_nav.php';
?>

<div class="k2-player-games-status" data-k2-carry-scroll>
	<?php echo k2_h($listSummary); ?>
<?php if (amiga_tournament_index_filters_active($wcFilter, $typeFilter, $videosFilter, $countryFilter, $yearFilter, $perfectFilter)) { ?>
	<a class="k2-player-games-reset" href="<?php echo k2_h(amiga_tournament_index_reset_url()); ?>">Reset filters</a>
<?php } ?>
</div>

<?php amiga_tournament_index_render_table($rows); ?>

</div><!-- .k2-page-nav -->

</body>
</html>
