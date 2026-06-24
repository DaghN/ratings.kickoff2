<?php
/**
 * Load WC country slice rows and render the active sub-wing table.
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_wc_countries_table.php';

$k2AmigaWcCountriesView = $k2AmigaWcCountriesView
    ?? $k2AmigaWorldCupsCountriesView
    ?? 'honours';

include __DIR__ . '/../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");
$ctx = amiga_lb_context($con);

$rows = amiga_wc_country_rows_for_view($con, $ctx, $k2AmigaWcCountriesView);
$countryCount = amiga_wc_country_count($con, $ctx);

mysqli_close($con);

amiga_wc_countries_render_view($k2AmigaWcCountriesView, $rows, $countryCount);
