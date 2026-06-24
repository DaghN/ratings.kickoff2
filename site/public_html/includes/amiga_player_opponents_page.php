<?php

/**

 * Shared Amiga Opponents wing page shell.

 * Set $k2AmigaPlayerOpponentsView before require (h2h | wdl | goals | dds).

 */

declare(strict_types=1);



require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_opponents_lib.php';



$k2AmigaPlayerOpponentsView = amiga_player_opponents_parse_view($k2AmigaPlayerOpponentsView ?? null);

$view = $k2AmigaPlayerOpponentsView;

$viewLabel = amiga_player_opponents_view_label($view);



?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">

<head>

<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<title>Amiga player opponents</title>

<?php
$k2AmigaPlayerOpponentsLedgerTable = in_array($view, ['wdl', 'goals', 'dds'], true);
if ($k2AmigaPlayerOpponentsLedgerTable) {
    $k2RankedCloak = true;
}
include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php';
?>

<link href="/stylesheets/player-feast.css" rel="stylesheet" type="text/css" />

<?php if ($k2AmigaPlayerOpponentsLedgerTable) { ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; ?>
<?php } else { ?>
<script type="text/javascript" src="/js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>
<?php } ?>

</head>

<body class="k2-site k2-player-wing player-feast-body">



<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_load.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_snapshot_context.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_opponents_tables.php';



include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2amiga_config.php';



$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id < 1) {

    http_response_code(404);

    exit('Player not found.');

}



$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);

$con->query("SET time_zone = '+00:00'");



$ctx = amiga_snapshot_context_from_request($con);



try {

    $pm = amiga_player_load($con, $id);

} catch (RuntimeException $e) {

    mysqli_close($con);

    http_response_code(404);

    exit('Player not found.');

}



amiga_player_publish_hero_context($pm);



include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php';

include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_wing_hub_nav.inc.php';

include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_hero.php';



$k2AmigaPlayerTabActive = 'opponents';

include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_nav.php';

include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_opponents_nav.php';



if ($view === 'h2h') {

    ?>

<section class="k2-amiga-opponents-placeholder" style="padding:1rem 1.25rem 2rem">

	<p class="k2-hub-page-intro" style="margin:0 0 0.5rem">

		<strong>Head-to-head</strong> — rivalry depth for the Amiga realm.

	</p>

	<p class="k2-hub-page-intro" style="margin:0;opacity:0.85">

		Poster, picker, and charts are not wired yet.

	</p>

</section>

    <?php

} elseif ($view === 'goals') {

    amiga_player_opponents_render_goals_table($con, $id, $ctx);

} elseif ($view === 'dds') {

    amiga_player_opponents_render_dds_table($con, $id, $ctx);

} else {

    amiga_player_opponents_render_wdl_table($con, $id, $ctx);

}



mysqli_close($con);

?>



</div><!-- .k2-chrome-tabs.k2-player-opponents -->



</div><!-- .k2-page-nav -->



</body>

</html>

