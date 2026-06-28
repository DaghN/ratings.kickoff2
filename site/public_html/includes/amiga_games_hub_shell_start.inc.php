<?php
/**
 * Amiga Games hub chrome — open through <main> (sub-nav included).
 * Set $k2AmigaGamesHubView: recent | highlights | all.
 * Optional: $k2AmigaGamesPageTitle, $k2AmigaGamesHubTotal, $k2AmigaGamesRecentCount for chapter lede.
 */
declare(strict_types=1);

$k2AmigaGamesHubView = $k2AmigaGamesHubView ?? 'recent';
$k2AmigaGamesPageTitle = $k2AmigaGamesPageTitle ?? 'Games';
$k2AmigaGamesHubTotal = $k2AmigaGamesHubTotal ?? 0;
$k2AmigaGamesRecentCount = $k2AmigaGamesRecentCount ?? 0;
$k2AmigaGamesEnqueueTableJs = $k2AmigaGamesEnqueueTableJs ?? true;
if ($k2AmigaGamesEnqueueTableJs) {
    $k2RankedCloak = true;
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga 500 — <?php echo htmlspecialchars($k2AmigaGamesPageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="/stylesheets/amiga-tournament.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/amiga-tournament.css'); ?>" rel="stylesheet" type="text/css" />
<?php if ($k2AmigaGamesEnqueueTableJs) { include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; } ?>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2AmigaHubTabActive = 'games';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';
?>
<?php
$k2HubChapterTitle = 'Games';
$k2GamesPlayedLede = $k2AmigaGamesHubTotal > 0
    ? '<span class="blue">' . number_format($k2AmigaGamesHubTotal) . '</span> rated games'
    : 'rated games';
$k2HubChapterLede = 'The Kick Off Association has a long history with ' . $k2GamesPlayedLede . ' on real Amigas in official KOA tournaments.';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_games_hub_helpers.php';
$k2HubChapterList = amiga_games_hub_chapter_list_html($k2AmigaGamesHubTotal, $k2AmigaGamesRecentCount);
include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_hub_chapter.inc.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_games_hub_nav.php';
?>

<main class="k2-games-hub k2-amiga-games-hub" id="main">
