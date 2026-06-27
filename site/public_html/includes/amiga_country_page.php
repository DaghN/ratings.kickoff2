<?php
/**
 * Amiga country entity page shell — Roster (default) · Rivals segment.
 *
 * Entity page (docs/navigation-model.md NM2/NM3): the realm hub bar is present
 * with NO active pill; the Roster·Rivals segment below is the wayfinding.
 * Thin entries set $k2AmigaCountryView ('roster'|'rivals') then require this file.
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_countries_lib.php';

$k2AmigaCountryView = ($k2AmigaCountryView ?? 'roster') === 'rivals' ? 'rivals' : 'roster';
$countryToken = amiga_countries_normalize_country_param((string) ($_GET['country'] ?? ''));
$pageTitleSuffix = $k2AmigaCountryView === 'rivals' ? ' rivals' : ' roster';
$pageTitle = $countryToken !== ''
    ? 'Amiga ladder — ' . $countryToken . $pageTitleSuffix
    : 'Amiga ladder — Country';
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo k2_h($pageTitle); ?></title>
<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; ?>
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

if ($countryToken === '') {
    header('Location: ' . k2_amiga_route('amiga-countries'), true, 302);
    exit;
}

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");
$ctx = amiga_lb_context($con);
$playerRows = amiga_countries_player_rows($con, $ctx);
$indexRows = amiga_countries_index_rows($playerRows);
$summaryRow = amiga_countries_index_row_for_token($indexRows, $countryToken);
$rosterRows = amiga_countries_roster_rows($playerRows, $countryToken);
mysqli_close($con);

if ($summaryRow === null) {
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
    ?>
<section class="k2-country-rivals" aria-label="Rivals">
    <p style="padding:1.5rem 1.25rem 2.5rem;">Country vs country comparisons for <strong><?php echo k2_h($countryToken); ?></strong> are coming soon.</p>
</section>
    <?php
} else {
    amiga_countries_render_roster_table($rosterRows, $countryToken);
}
?>

</div><!-- .k2-page-nav -->

</body>
</html>