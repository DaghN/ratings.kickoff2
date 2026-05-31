<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_milestones_helpers.php';

$milestoneKey = k2_milestone_key_param('key');
if ($milestoneKey === null) {
	k2_public_error('Milestone not specified.', 400);
}

$achieverSort = k2_milestone_achiever_sort_param();

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
if (!k2_milestone_tables_ready($con)) {
	mysqli_close($con);
	k2_public_error('Milestone data is not available.', 503);
}

$definition = k2_milestone_definition_hub($con, $milestoneKey);
if ($definition === null) {
	mysqli_close($con);
	k2_public_error('Unknown milestone.', 404);
}

$achievers = k2_milestone_achievers($con, $milestoneKey, $achieverSort);
$catalogTotal = k2_milestone_catalog_total($con);
mysqli_close($con);

$k2MilestoneDefinition = $definition;
$k2MilestoneChartIds = k2_milestone_detail_chart_ids($milestoneKey);

$token = (string) $definition['chart_token'];
$tierLabel = k2_milestone_tier_label((string) $definition['tier_band']);
$holders = (int) $definition['holders'];
$k2HubTabActive = 'milestones';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings — <?php echo k2_h((string) $definition['display_name']); ?></title>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="stylesheets/player-milestones.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-milestones.css'); ?>" rel="stylesheet" type="text/css" />
<script src="js/chart.umd.min.js"></script>
<script src="js/chartjs-adapter-date-fns.bundle.min.js"></script>
<script src="js/chart-theme.js"></script>
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>
<script type="text/javascript" src="js/milestone-unlock-timeline-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-double-digit-merchants-year-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-cumulative-double-digit-merchants-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-double-digit-merchant-rating-distribution-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-established-players-year-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-cumulative-established-month-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-established-rating-distribution-chart.js" defer="defer"></script>

</head>

<body class="k2-site">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/hub_nav.php'; ?>

<main class="k2-ms-detail-page k2-ms-detail-page--<?php echo k2_h($token); ?>" id="main">
	<p class="k2-ms-detail-crumb">
		<a href="<?php echo k2_h(k2_milestones_recent_href()); ?>">Recent</a>
		<span aria-hidden="true"> · </span>
		<a href="<?php echo k2_h(k2_milestones_catalog_href()); ?>">Catalog</a>
	</p>

	<header class="k2-ms-detail-hero">
		<p class="k2-ms-detail-hero__tier">
			<span class="k2-lb-ms-tier--<?php echo k2_h($token); ?>"><?php echo k2_h($tierLabel); ?></span>
			· <?php echo $holders === 1 ? '1 player has this' : k2_h((string) $holders) . ' players have this'; ?>
		</p>
		<h1 class="k2-ms-detail-hero__title k2-lb-ms-tier--<?php echo k2_h($token); ?>"><?php echo k2_h((string) $definition['display_name']); ?></h1>
		<p class="k2-ms-detail-hero__rule"><?php echo k2_h((string) $definition['rule_short']); ?></p>
		<?php if (!empty($definition['description'])) { ?>
		<p class="k2-ms-detail-hero__desc"><?php echo k2_h((string) $definition['description']); ?></p>
		<?php } ?>
	</header>

	<?php k2_milestone_render_detail_achievers($definition, $achievers, $achieverSort); ?>

	<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/milestone_detail_charts.inc.php'; ?>

	<p class="k2-ms-meta-hint k2-ms-detail-footer">
		<?php echo (int) $catalogTotal; ?> milestones on this ladder —
		<a href="<?php echo k2_h(k2_milestones_catalog_href()); ?>">back to catalog</a>
	</p>
</main>

</div><!-- .k2-page-nav -->

</body>
</html>
