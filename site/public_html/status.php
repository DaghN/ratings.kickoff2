<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 — Status</title>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>
<script type="text/javascript" src="js/status-league-toggle.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/status-league-toggle.js'); ?>" defer="defer"></script>

</head>

<body class="k2-site">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2HubTabActive = 'status';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/hub_nav.php';
?>

<?php
include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/status_queries.php';

$k2StatusRoom = null;
$k2StatusRoomError = null;

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if (mysqli_connect_errno()) {
    $k2StatusRoomError = mysqli_connect_error();
} else {
    $k2StatusRoom = k2_status_load_room($con, $k2StatusRoomError);
    mysqli_close($con);
    unset($con);
}

include $_SERVER['DOCUMENT_ROOT'] . '/includes/status_room_section.php';
?>

</div><!-- .k2-page-nav -->

</body>
</html>
