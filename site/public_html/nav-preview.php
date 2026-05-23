<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings — Hub nav preview</title>

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/k2_head.php"; ?>

</head>

<body class="k2-site">

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>

<div class="k2-page-nav" style="max-width: var(--k2-max-width); margin: 0 auto;">
	<h1 style="margin: 16px 0 8px; font-size: 1.25em; font-weight: 600;">Hub navigation preview</h1>
	<p class="k2-hub-panel__hint" style="margin: 0 0 20px;">Production uses <strong>segment</strong> by default. Compare <strong>solid</strong>, <strong>segment</strong>, and <strong>soft</strong> via <code>?k2_hub_nav=</code> or the links below (sticks in <code>sessionStorage</code> for the session).</p>

<?php
$pages = [
	'Status' => 'status.php',
	'Leaderboards' => 'ranked7.php',
	'Trends' => 'server1.php',
];
$variants = [
	'segment' => 'Segment track + outline (production default)',
	'solid' => 'Solid fill',
	'soft' => 'Soft tint fill + accent text',
];
foreach ($variants as $key => $label) {
	echo '<section style="margin-bottom: 24px;">';
	echo '<h2 style="margin: 0 0 8px; font-size: 1em; font-weight: 600;">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</h2>';
	echo '<ul style="margin: 0; padding-left: 1.25em; line-height: 1.6;">';
	foreach ($pages as $name => $href) {
		$url = $href . '?k2_hub_nav=' . rawurlencode($key);
		echo '<li><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</a></li>';
	}
	echo '</ul></section>';
}
?>

	<p class="k2-hub-panel__hint">Clear session: open any hub link without <code>?k2_hub_nav=</code> and set storage in devtools, or use <code>sessionStorage.removeItem('k2-hub-nav-tune')</code>.</p>
</div>

</body>
</html>
