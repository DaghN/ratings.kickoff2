<?php
/**
 * World Cups hub chrome — open through <main> (sub-nav included).
 *
 * Set $k2AmigaWorldCupsHubView: events | stats | players
 * Optional: $k2AmigaWorldCupsPageTitle, $k2AmigaWorldCupsChapterLede
 * When $k2AmigaWorldCupsHubView === 'players', set $k2AmigaWorldCupsPlayersView: honours | results | goals | dds | opponents
 * When $k2AmigaWorldCupsHubView === 'stats', set $k2AmigaWorldCupsStatsView: goals | dds | participation | geography | podium
 */
declare(strict_types=1);

$k2AmigaWorldCupsHubView = $k2AmigaWorldCupsHubView ?? 'events';
$k2AmigaWorldCupsPageTitle = $k2AmigaWorldCupsPageTitle ?? 'World Cups';
$k2AmigaWorldCupsChapterLede = $k2AmigaWorldCupsChapterLede ?? '';
$k2AmigaWorldCupsEnqueueTableJs = $k2AmigaWorldCupsEnqueueTableJs ?? false;
$k2AmigaWorldCupsEnqueueScrollMirror = $k2AmigaWorldCupsEnqueueScrollMirror ?? false;
if ($k2AmigaWorldCupsEnqueueTableJs && $k2AmigaWorldCupsHubView === 'players') {
    $k2AmigaWorldCupsEnqueueScrollMirror = true;
}
if ($k2AmigaWorldCupsEnqueueTableJs) {
    $k2RankedCloak = true;
}

$k2AmigaWorldCupsChapterTitles = [
    'events' => 'World Cups',
    'stats' => 'World Cups — Tournament stats',
    'players' => 'World Cups — Player stats',
];
$k2HubChapterTitle = $k2AmigaWorldCupsChapterTitles[$k2AmigaWorldCupsHubView] ?? 'World Cups';
$k2HubChapterLede = $k2AmigaWorldCupsChapterLede;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga 500 — <?php echo htmlspecialchars($k2AmigaWorldCupsPageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<?php
if ($k2AmigaWorldCupsEnqueueTableJs) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_table_helpers.php';
    k2_table_js_enqueue();
    if ($k2AmigaWorldCupsEnqueueScrollMirror) {
        $mirrorV = (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table-scroll-mirror.js');
        echo '<script type="text/javascript" src="/js/k2-table-scroll-mirror.js?v=' . $mirrorV . '" defer="defer"></script>';
    }
}
?>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2AmigaHubTabActive = 'world-cups';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_hub_chapter.inc.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_world_cups_hub_nav.php';
if ($k2AmigaWorldCupsHubView === 'players') {
    include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_world_cups_players_nav.php';
}
if ($k2AmigaWorldCupsHubView === 'stats') {
    include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_world_cups_stats_nav.php';
}
?>

<main class="k2-amiga-world-cups-hub" id="main">
