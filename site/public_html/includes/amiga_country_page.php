<?php
/**
 * Amiga country entity page shell — Roster (default) · Rivals segment.
 *
 * Entity page (docs/navigation-model.md NM2/NM3): the realm hub bar is present
 * with NO active pill; the Roster·Rivals segment below is the wayfinding.
 * Thin entries set $k2AmigaCountryView ('roster'|'rivals') then require this file.
 * Rivals wings set $k2AmigaCountryRivalsView (h2h|wdl|goals|dds).
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_countries_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_country_rivals_lib.php';

$k2AmigaCountryView = ($k2AmigaCountryView ?? 'roster') === 'rivals' ? 'rivals' : 'roster';
$k2AmigaCountryRivalsView = amiga_country_rivals_parse_view($k2AmigaCountryRivalsView ?? null);
$countryToken = amiga_countries_normalize_country_param((string) ($_GET['country'] ?? ''));
$pageTitleSuffix = $k2AmigaCountryView === 'rivals' ? ' rivals' : ' roster';
$pageTitle = $countryToken !== ''
    ? 'Amiga ladder — ' . $countryToken . $pageTitleSuffix
    : 'Amiga ladder — Country';
$k2AmigaCountryRivalsH2h = $k2AmigaCountryView === 'rivals' && $k2AmigaCountryRivalsView === 'h2h';

if ($countryToken === '') {
    header('Location: ' . k2_amiga_route('amiga-countries'), true, 302);
    exit;
}

if ($k2AmigaCountryRivalsH2h) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_lib.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_country_rivals_h2h.php';
    include __DIR__ . '/../../config/ko2amiga_config.php';
    $con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
    $con->query("SET time_zone = '+00:00'");
    $ctx = amiga_lb_context($con);
    amiga_country_rivals_h2h_redirect_default_rival_if_needed($con, $countryToken, $ctx);
    mysqli_close($con);
}

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo k2_h($pageTitle); ?></title>
<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<?php if ($k2AmigaCountryRivalsH2h) { ?>
<link rel="stylesheet" href="/stylesheets/player-opponents-h2h-poster.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-opponents-h2h-poster.css'); ?>" />
<link rel="stylesheet" href="/stylesheets/player-opponents-h2h-moments.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-opponents-h2h-moments.css'); ?>" />
<link rel="stylesheet" href="/stylesheets/player-opponents-h2h-scoreline-heatmap.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-opponents-h2h-scoreline-heatmap.css'); ?>" />
<script src="/js/chart.umd.min.js"></script>
<script src="/js/chartjs-adapter-date-fns.bundle.min.js"></script>
<script src="/js/chart-theme.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/chart-theme.js'); ?>"></script>
<script src="/js/k2-coarse-tap.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-coarse-tap.js'); ?>"></script>
<script type="text/javascript" src="/js/player-opponents-h2h-chart-context.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-opponents-h2h-chart-context.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/k2-archive-listbox.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-archive-listbox.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-opponents-h2h.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-opponents-h2h.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-head-to-head-chart.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-head-to-head-chart.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-head-to-head-goals-chart.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-head-to-head-goals-chart.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-h2h-total-goals-histogram.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-h2h-total-goals-histogram.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-h2h-scoreline-heatmap.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-h2h-scoreline-heatmap.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-goals-scored-histogram.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-goals-scored-histogram.js'); ?>" defer="defer"></script>
<?php } else { ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; ?>
<?php } ?>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
// Entity page: hub bar present, no active pill (docs/navigation-model.md NM2).
$k2AmigaHubTabActive = '';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_countries_roster_table.php';
include __DIR__ . '/../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");
$ctx = amiga_lb_context($con);
if ($k2AmigaCountryView === 'roster') {
    $rosterRows = amiga_countries_query_roster_rows($con, $ctx, $countryToken);
    $summaryRow = amiga_countries_summary_row_from_player_rows($rosterRows, $countryToken);
} else {
    $summaryRow = amiga_countries_query_country_summary($con, $ctx, $countryToken);
    $rosterRows = [];
}

if ($summaryRow === null) {
    mysqli_close($con);
    http_response_code(404);
    $k2HubChapterTitle = 'Country not found';
    $k2HubChapterLede = 'No rated players from this country at the active cutoff.';
    include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_hub_chapter.inc.php';
    echo '<p style="padding:0 1.25rem 2rem;"><a href="' . k2_h(k2_amiga_route('amiga-countries')) . '">Back to Countries</a></p>';
    echo '</div><!-- .k2-page-nav --></body></html>';
    exit;
}

echo k2_amiga_country_roster_anchor_markup();

$k2CountryHeroToken = $countryToken;
$k2CountryHeroSummary = $summaryRow;
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_country_hero.php';

$k2AmigaCountryToken = $countryToken;
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_country_nav.php';

if ($k2AmigaCountryView === 'rivals') {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_country_rivals_nav.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_country_rivals_tables.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_country_rivals_h2h.php';
    ?>
<section class="k2-country-rivals" aria-label="Rivals">
    <?php
    if ($k2AmigaCountryRivalsView === 'h2h') {
        $selectedRival = amiga_country_rivals_normalize_token((string) ($_GET['rival'] ?? ''));
        $h2hDefaultRival = !array_key_exists('rival', $_GET);
        $pickSource = amiga_player_opponents_h2h_parse_pick_source($_GET['pick'] ?? null);
        if ($pickSource === null && ($selectedRival !== '' || $h2hDefaultRival)) {
            $pickSource = 'games';
        }
        amiga_country_rivals_render_h2h_panel($con, $countryToken, $selectedRival, $h2hDefaultRival, $pickSource, $ctx, $summaryRow);
    } elseif ($k2AmigaCountryRivalsView === 'wdl') {
        amiga_country_rivals_render_wdl_table($con, $countryToken, $ctx);
    } elseif ($k2AmigaCountryRivalsView === 'goals') {
        amiga_country_rivals_render_goals_table($con, $countryToken, $ctx);
    } else {
        amiga_country_rivals_render_dds_table($con, $countryToken, $ctx);
    }
    ?>
</section>
    <?php
    mysqli_close($con);
} else {
    amiga_countries_render_roster_table($rosterRows, $countryToken);
    mysqli_close($con);
}
?>

</div><!-- .k2-page-nav -->

</body>
</html>
