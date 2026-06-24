<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga 500 — Activity</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2AmigaHubTabActive = 'activity';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_snapshot_url.php';
if (!amiga_snapshot_time_travel_active_from_request()) {
?>
<header class="k2-hub-chapter">
  <h1 class="k2-hub-chapter__title">Activity</h1>
</header>
<?php
}
?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_activity_summary.php'; ?>

</body>
</html>
