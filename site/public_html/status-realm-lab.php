<?php
$k2RealmLabVariant = isset($_GET['variant']) ? (string) $_GET['variant'] : 'identity';
if (!in_array($k2RealmLabVariant, ['identity', 'strip'], true)) {
	$k2RealmLabVariant = 'identity';
}

function k2_realm_lab_switch_html(): void
{
	?>
	<nav class="k2-realm-switch" aria-label="Realm">
		<button type="button" class="k2-realm-switch__btn" data-realm="online" aria-pressed="true">Online</button>
		<button type="button" class="k2-realm-switch__btn" data-realm="amiga" aria-pressed="false">Amiga</button>
	</nav>
	<?php
}

function k2_realm_lab_header(string $variant): void
{
	?>
<header class="k2-site-header k2-realm-lab-header k2-realm-lab-header--<?php echo htmlspecialchars($variant, ENT_QUOTES, 'UTF-8'); ?>">
	<div class="k2-realm-lab-brand">
		<h1 class="k2-wordmark">
			<a href="status.php" class="k2-wordmark__link">
				<span class="k2-wordmark__main">Kick Off 2</span>
			</a>
		</h1>
<?php if ($variant === 'identity') { ?>
		<div class="k2-realm-lab-inline-realm" aria-label="Site realm">
			<span class="k2-realm-lab-label">Realm</span>
<?php k2_realm_lab_switch_html(); ?>
		</div>
<?php } ?>
	</div>
	<div class="k2-site-header__links k2-realm-lab-search">
		<?php $playerSearchInHeader = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_search_bar.php'; ?>
	</div>
</header>
<script type="text/javascript" src="js/realm-switch.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/realm-switch.js'); ?>" defer="defer"></script>
<div class="k2-page-nav">
	<?php
}

function k2_realm_lab_strip(): void
{
	?>
<section class="k2-realm-lab-strip" aria-label="Site realm">
	<div class="k2-realm-lab-strip__copy">
		<span class="k2-realm-lab-label">Viewing realm</span>
		<span class="k2-realm-lab-strip__hint">Online ladder today / Amiga 500 real-world games later</span>
	</div>
<?php k2_realm_lab_switch_html(); ?>
</section>
	<?php
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 - Status realm lab</title>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>
<script type="text/javascript" src="js/status-league-toggle.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/status-league-toggle.js'); ?>" defer="defer"></script>

</head>

<body class="k2-site">

<?php k2_realm_lab_header($k2RealmLabVariant); ?>
<?php if ($k2RealmLabVariant === 'strip') { ?>
<?php k2_realm_lab_strip(); ?>
<?php } ?>

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
	$con->query("SET time_zone = '+00:00'");
	$k2StatusRoom = k2_status_load_room($con, $k2StatusRoomError);
	mysqli_close($con);
	unset($con);
}

include $_SERVER['DOCUMENT_ROOT'] . '/includes/status_room_section.php';
?>

</div><!-- .k2-page-nav -->

</body>
</html>
