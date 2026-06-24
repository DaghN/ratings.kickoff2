<?php
/**
 * Leaderboards → World Cups player stats page chrome (open through inner sub-nav).
 *
 * Set $k2AmigaWcPlayersView and $k2AmigaWcPlayersPageTitle before include.
 */
declare(strict_types=1);

$k2AmigaWcPlayersView = $k2AmigaWcPlayersView ?? $k2AmigaWcLbView ?? 'honours';
$k2AmigaWcPlayersPageTitle = $k2AmigaWcPlayersPageTitle ?? 'Amiga ladder — World Cups';
$k2AmigaWcLbView = $k2AmigaWcPlayersView;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo htmlspecialchars($k2AmigaWcPlayersPageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; ?>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2AmigaHubTabActive = 'leaderboards';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';

$k2AmigaLbWingActive = 'world-cups';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_nav.php';

include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_wc_lb_nav.php';
?>
