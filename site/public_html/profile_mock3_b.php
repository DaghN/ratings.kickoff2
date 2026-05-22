<?php
include $_SERVER['DOCUMENT_ROOT'] . '/includes/profile_mock_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/pm3_blocks.php';
?>
<!DOCTYPE html>
<html lang="en" data-realm="online">
<head>
<meta charset="utf-8" />
<meta name="robots" content="noindex, nofollow" />
<title>Pass 3 · Glass Ledger — <?php echo pm_h($pm['name']); ?></title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="stylesheets/profile-mock-v3.css" rel="stylesheet" type="text/css" />
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/pm3_scripts.php'; ?>
</head>
<body class="k2-site pm3-body pm3-body--b">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<div class="k2-page-nav">

<?php
pm3_render_nav($pm, 'ledger');
pm3_render_core_b($pm);
pm3_render_facts($pm);
pm3_render_rivalry_b($pm);
pm3_render_charts_prod((int) $pm['id']);
pm3_render_moments($pm, 'mosaic');
pm3_render_activity($pm);
pm3_render_busiest($pm, 'inline');
pm3_render_stats($pm);
?>

</div>

<?php mysqli_close($con); ?>
</body>
</html>
