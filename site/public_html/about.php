<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 — About</title>
<?php
$k2OgTitle = 'About Kick Off 2 ratings';
$k2MetaDescription = 'Community Kick Off 2 ladder and Amiga 500 statistics — by fans.';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php';
?>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>
<?php
$k2HubTabActive = '';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/hub_nav.php';
?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/about_page_section.php'; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_site_end.inc.php'; ?>
</body>
</html>
