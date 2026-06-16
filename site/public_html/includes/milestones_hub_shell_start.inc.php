<?php
/**
 * Milestones hub chrome — open through <main> (sub-nav included).
 * Set $k2MsHubView before include: recent | catalog.
 */
$k2MsHubView = $k2MsHubView ?? 'recent';
$k2HubTabActive = 'milestones';
$k2MilestonesHubTitle = $k2MilestonesHubTitle ?? 'Milestones';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings — <?php echo htmlspecialchars($k2MilestonesHubTitle, ENT_QUOTES, 'UTF-8'); ?></title>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="/stylesheets/player-milestones.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-milestones.css'); ?>" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="/js/player-search.js" defer="defer"></script>

</head>

<body class="k2-site">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/hub_nav.php'; ?>
<?php
$k2HubChapterTitle = 'Milestones';
$k2HubChapterLede = 'Who just unlocked what on the ladder — a first double digit, a twentieth rated game, a league medal, a ten-thousand-match career? Milestones are those marks across a rated career, from first steps to legendary grinds. <strong>Recent</strong> follows new unlocks; <strong>Catalog</strong> walks the full set, tier by tier.';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_hub_chapter.inc.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/milestones_hub_nav.php';
?>

<main class="k2-ms-hub" id="main">
