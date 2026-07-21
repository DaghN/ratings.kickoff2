<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 — Status</title>

<?php
$k2OgTitle = 'Kick Off 2 ratings';
$k2MetaDescription = 'Live Kick Off 2 ladder and Amiga 500 statistics.';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php';
?>
<link href="stylesheets/flatpickr.min.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/flatpickr.min.css'); ?>" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/flatpickr.min.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/flatpickr.min.js'); ?>" defer="defer"></script>
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_table_helpers.php';
k2_table_js_enqueue();
?>
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>
<script type="text/javascript" src="js/k2-archive-listbox.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-archive-listbox.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="js/status-period-competitions.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/status-period-competitions.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="js/k2-live-glow.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-live-glow.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="js/status-room-live.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/status-room-live.js'); ?>" defer="defer"></script>

</head>

<body class="k2-site">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2HubTabActive = 'status';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/hub_nav.php';
?>

<?php
include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/status_queries.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/status_room_live_sim.php';

$k2StatusRoom = null;
$k2StatusRoomError = null;

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
if (k2_status_room_sim_is_allowed()) {
    k2_status_room_sim_tick_if_due($con);
}
$k2StatusRoom = k2_status_load_room($con, $k2StatusRoomError);
mysqli_close($con);
unset($con);

include $_SERVER['DOCUMENT_ROOT'] . '/includes/status_room_section.php';
?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_site_end.inc.php'; ?>
</body>
</html>
