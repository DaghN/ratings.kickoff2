<?php
include $_SERVER['DOCUMENT_ROOT'] . '/includes/profile_mock_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/pm3d_blocks.php';
$calYear = (int) date('Y');
$playerId = (int) $pm['id'];
?>
<!DOCTYPE html>
<html lang="en" data-realm="online">
<head>
<meta charset="utf-8" />
<meta name="robots" content="noindex, nofollow" />
<title>Pass 3 · Composite 3H — <?php echo pm_h($pm['name']); ?></title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="stylesheets/profile-mock-v3.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/profile-mock-v3d.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/profile-mock-v3efg.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/profile-mock-v3hij.css" rel="stylesheet" type="text/css" />
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/pm3_scripts.php'; ?>
</head>
<body class="k2-site pm3d-body pm3efg-body pm3hij-body pm3hij-body--h">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<div class="k2-page-nav">

<?php
pm3d_render_core($pm);

$k2PlayerTabActive = 'profile';
$id = $playerId;
include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_nav.php';

pm3d_render_presence_career_duo($pm);
pm3d_render_played_days($playerId, $calYear);
pm3d_render_peak_activity($pm, 'h');
pm3d_render_moments($pm);
pm3d_render_best_friend($pm, 'h');
pm3d_render_charts($playerId);
?>

</div>

<?php mysqli_close($con); ?>
</body>
</html>
