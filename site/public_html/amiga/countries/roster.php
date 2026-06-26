<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_countries_lib.php';
$countryToken = amiga_countries_normalize_country_param((string) ($_GET['country'] ?? ''));
$pageTitle = $countryToken !== '' ? 'Amiga ladder — ' . $countryToken . ' roster' : 'Amiga ladder — Country roster';
?>
<title><?php echo k2_h($pageTitle); ?></title>
<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; ?>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2AmigaHubTabActive = 'countries';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_countries_roster_table.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_country_hero.php';
include __DIR__ . '/../../../config/ko2amiga_config.php';

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

$k2HubChapterTitle = $countryToken . ' roster';
$k2HubChapterLede = 'Players from ' . htmlspecialchars($countryToken, ENT_QUOTES, 'UTF-8') . ' on the Amiga ladder. '
    . '<a href="' . htmlspecialchars(k2_amiga_route('amiga-countries'), ENT_QUOTES, 'UTF-8') . '">All countries</a>'
    . ' · <a href="' . htmlspecialchars(amiga_countries_wc_stats_href_for_token($countryToken), ENT_QUOTES, 'UTF-8') . '">World Cup country stats</a>';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_hub_chapter.inc.php';

echo k2_amiga_country_roster_anchor_markup();

$k2CountryHeroToken = $countryToken;
$k2CountryHeroSummary = $summaryRow;
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_country_hero.php';

amiga_countries_render_roster_table($rosterRows, $countryToken);
?>

</div><!-- .k2-page-nav -->

</body>
</html>