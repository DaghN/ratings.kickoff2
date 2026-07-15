<?php
/**
 * Shared Amiga player chronologies page shell.
 *
 * Set $k2AmigaPlayerChronologyKind and $k2AmigaPlayerChronologySegment before require.
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_chronologies_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_chronologies_render.php';

$k2AmigaPlayerChronologyKind = amiga_player_chronology_parse_kind($k2AmigaPlayerChronologyKind ?? null);
$k2AmigaPlayerChronologySegment = amiga_player_chronology_parse_segment($k2AmigaPlayerChronologySegment ?? null);
$kind = $k2AmigaPlayerChronologyKind;
$segment = $k2AmigaPlayerChronologySegment;
$kindLabel = amiga_player_chronology_kind_label($kind);
$isMadeIt = $segment === 'made-it';
$k2ScrollTargetId = AMIGA_PLAYER_CHRONOLOGY_SPOTLIGHT_FRAGMENT;

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga player — <?php echo k2_h($kindLabel); ?> chronology</title>
<?php
if ($isMadeIt) {
    $k2RankedCloak = true;
}
include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php';
?>
<link href="/stylesheets/player-feast.css" rel="stylesheet" type="text/css" />
<link href="/stylesheets/player-feast-sections.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-feast-sections.css'); ?>" rel="stylesheet" type="text/css" />
<link href="/stylesheets/player-milestones.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-milestones.css'); ?>" rel="stylesheet" type="text/css" />
<link href="/stylesheets/amiga-tournament.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/amiga-tournament.css'); ?>" rel="stylesheet" type="text/css" />
<?php if ($isMadeIt) { ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; ?>
<?php } else {
    $chartsJs = match ($kind) {
        AMIGA_PLAYER_CHRONOLOGY_KIND_VICTIMS => 'amiga-chronology-victims-charts.js',
        default => 'amiga-chronology-opponents-charts.js',
    };
?>
<script src="/js/chart.umd.min.js"></script>
<script src="/js/chartjs-adapter-date-fns.bundle.min.js"></script>
<script src="/js/chart-theme.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/chart-theme.js'); ?>"></script>
<script src="/js/chart-date-range.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/chart-date-range.js'); ?>"></script>
<script type="text/javascript" src="/js/<?php echo k2_h($chartsJs); ?>?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/' . $chartsJs); ?>" defer="defer"></script>
<?php } ?>
</head>
<body class="k2-site k2-player-wing player-feast-body k2-amiga-chronology-page">

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_snapshot_context.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_videos_lib.php';

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
    $pm = amiga_player_load($con, $id, $ctx);
} catch (RuntimeException $e) {
    mysqli_close($con);
    http_response_code(404);
    exit('Player not found.');
}

amiga_player_publish_hero_context($pm, $con);

$chronologyRows = [];
$chartPayload = [];
if ($kind === AMIGA_PLAYER_CHRONOLOGY_KIND_OPPONENTS) {
    $chronologyRows = amiga_player_chronology_opponents_load($con, $id, $ctx);
    $chartPayload = amiga_player_chronology_opponents_chart_payload($con, $id, $chronologyRows, $Name);
} elseif ($kind === AMIGA_PLAYER_CHRONOLOGY_KIND_VICTIMS) {
    $chronologyRows = amiga_player_chronology_victims_load($con, $id, $ctx);
    $chartPayload = amiga_player_chronology_victims_chart_payload($con, $id, $chronologyRows, $Name);
}

$k2AmigaPlayerHasVideos = amiga_player_has_videos($id, $con, $ctx);
mysqli_close($con);

$k2AmigaHubTabActive = '';
$k2AmigaPlayerTabActive = '';
$k2AmigaPlayerTabWiredAtCutoff = true;
?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_wing_hub_nav.inc.php'; ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_hero.php'; ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_nav.php'; ?>

<main class="k2-amiga-chronology-main" id="main">
<?php
amiga_player_chronology_render_spotlight($id, $Name, $kind);
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_player_hero_glow_session.php';
k2_ms_detail_spotlight_glow_session_mark();
amiga_player_chronology_render_segment_nav($id, $kind, $segment);
?>
<div class="k2-amiga-chronology-panels">
<?php if ($kind === AMIGA_PLAYER_CHRONOLOGY_KIND_OPPONENTS && $segment === 'made-it') {
    amiga_player_chronology_render_opponents_made_it($id, $chronologyRows);
} elseif ($kind === AMIGA_PLAYER_CHRONOLOGY_KIND_OPPONENTS && $segment === 'graphs') {
    amiga_player_chronology_render_opponents_graphs($chartPayload);
} elseif ($kind === AMIGA_PLAYER_CHRONOLOGY_KIND_VICTIMS && $segment === 'made-it') {
    amiga_player_chronology_render_victims_made_it($id, $chronologyRows);
} elseif ($kind === AMIGA_PLAYER_CHRONOLOGY_KIND_VICTIMS && $segment === 'graphs') {
    amiga_player_chronology_render_victims_graphs($chartPayload);
} ?>
</div>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_site_end.inc.php'; ?>
</body>
</html>