<?php
/**
 * Games hub chrome — open through <main> (sub-nav included).
 * Set $k2GamesHubView before include: recent | highlights | all.
 * Optional: $k2GamesPageTitle, $k2GamesHubArc, $k2GamesRecent14Count for chapter lede.
 */
$k2GamesHubView = $k2GamesHubView ?? 'recent';
$k2GamesPageTitle = $k2GamesPageTitle ?? 'Games';
$k2GamesRecent14Count = $k2GamesRecent14Count ?? 0;
$k2GamesHubArc = $k2GamesHubArc ?? null;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings — <?php echo htmlspecialchars($k2GamesPageTitle, ENT_QUOTES, 'UTF-8'); ?></title>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; ?>
<?php if ($k2GamesHubView === 'all') { ?>
<script type="text/javascript" src="/js/k2-archive-listbox.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-archive-listbox.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/k2-realm-games-filters.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-realm-games-filters.js'); ?>" defer="defer"></script>
<?php } ?>

</head>

<body class="k2-site">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2HubTabActive = 'games';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/hub_nav.php';
?>
<?php
$k2HubChapterTitle = 'Games';
$k2GamesPlayedLede = ($k2GamesHubArc !== null)
	? '<span class="blue">' . number_format((int) $k2GamesHubArc['games']) . '</span> rated games'
	: 'rated games';
$k2HubChapterLede = 'The Kick Off 2 online server has a long history with ' . $k2GamesPlayedLede . '.';
$k2HubChapterList = k2_games_hub_chapter_list_html($k2GamesRecent14Count);
include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_hub_chapter.inc.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/games_hub_nav.php';
?>

<main class="k2-games-hub" id="main">
