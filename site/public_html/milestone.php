<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_milestones_helpers.php';

$milestoneKey = k2_milestone_key_param('key');
if ($milestoneKey === null) {
	k2_public_error('Milestone not specified.', 400);
}

if (isset($_GET['sort'])) {
	$params = ['key' => $milestoneKey];
	if (isset($_GET['panel']) && strtolower(trim((string) $_GET['panel'])) === 'graphs') {
		$params['panel'] = 'graphs';
	}
	header('Location: milestone.php?' . http_build_query($params), true, 302);
	exit;
}

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

$achievers = k2_milestone_achievers($con, $milestoneKey, (string) $definition['chart_token']);
mysqli_close($con);

$k2MilestoneDefinition = $definition;
$k2MilestoneChartIds = k2_milestone_detail_chart_ids($milestoneKey);

$token = (string) $definition['chart_token'];
$k2MsDetailPanel = k2_milestone_detail_panel_param();
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
<script src="js/chart-date-range.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/chart-date-range.js'); ?>"></script>
<script type="text/javascript" src="js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>
<script type="text/javascript" src="js/milestone-unlocks-year-chart.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/milestone-unlocks-year-chart.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="js/milestone-cumulative-unlocks-chart.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/milestone-cumulative-unlocks-chart.js'); ?>" defer="defer"></script>

</head>

<body class="k2-site">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/hub_nav.php'; ?>
<?php
$k2MsHubView = '';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/milestones_hub_nav.php';
?>

<main class="k2-ms-detail-page k2-ms-detail-page--<?php echo k2_h($token); ?>" id="main">
	<?php k2_milestone_render_detail_spotlight($definition); ?>

	<?php k2_milestone_render_detail_panel_nav($milestoneKey, $k2MsDetailPanel); ?>

	<div class="k2-ms-detail-panels">
		<div id="k2-ms-detail-panel-unlockers" class="k2-ms-detail-panel" role="tabpanel" aria-labelledby="k2-ms-detail-tab-unlockers"<?php echo $k2MsDetailPanel !== 'unlockers' ? ' hidden' : ''; ?>>
			<?php k2_milestone_render_detail_achievers($definition, $achievers); ?>
		</div>
		<div id="k2-ms-detail-panel-graphs" class="k2-ms-detail-panel" role="tabpanel" aria-labelledby="k2-ms-detail-tab-graphs"<?php echo $k2MsDetailPanel !== 'graphs' ? ' hidden' : ''; ?>>
			<?php
			$k2MsDetailChartsHeading = false;
			include $_SERVER['DOCUMENT_ROOT'] . '/includes/milestone_detail_charts.inc.php';
			?>
		</div>
	</div>
</main>

</div><!-- .k2-page-nav -->

</body>
</html>
