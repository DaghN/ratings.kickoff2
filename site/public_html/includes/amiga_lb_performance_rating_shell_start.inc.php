<?php
/**
 * Leaderboards → Performance rating page chrome (open through inner sub-nav).
 *
 * Set $k2AmigaLbPerfRatingView, $k2AmigaLbPerfRatingPageTitle, $k2AmigaLbPerfRatingLede before include.
 */
declare(strict_types=1);

$k2AmigaLbPerfRatingView = $k2AmigaLbPerfRatingView ?? 'best';
$k2AmigaLbPerfRatingPageTitle = $k2AmigaLbPerfRatingPageTitle ?? 'Amiga ladder — Performance rating';
$k2AmigaLbPerfRatingLede = $k2AmigaLbPerfRatingLede ?? '';
$k2AmigaLbPerfRatingLedeHtml = $k2AmigaLbPerfRatingLedeHtml ?? '';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo htmlspecialchars($k2AmigaLbPerfRatingPageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; ?>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2AmigaHubTabActive = 'leaderboards';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';

$k2AmigaLbWingActive = 'performance-rating';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_nav.php';

include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_performance_rating_nav.php';
?>

<?php if ($k2AmigaLbPerfRatingLede !== '' || $k2AmigaLbPerfRatingLedeHtml !== '') { ?>
<header class="k2-hub-page-intro-head" style="padding:0 1.25rem"<?php echo $k2AmigaLbPerfRatingLedeHtml !== '' ? ' data-k2-carry-scroll' : ''; ?>>
	<p class="k2-hub-page-intro" style="margin:0 0 1rem"><?php
if ($k2AmigaLbPerfRatingLedeHtml !== '') {
	echo $k2AmigaLbPerfRatingLedeHtml;
} else {
	echo htmlspecialchars($k2AmigaLbPerfRatingLede, ENT_QUOTES, 'UTF-8');
}
?></p>
</header>
<?php } ?>

<div class="k2-lb-performance-rating">