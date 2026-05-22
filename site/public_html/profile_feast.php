<?php
include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_feast_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_feast_blocks.php';
$calYear = (int) date('Y');
$playerId = (int) $pm['id'];
?>
<!DOCTYPE html>
<html lang="en" data-realm="online">
<head>
<meta charset="utf-8" />
<meta name="robots" content="noindex, nofollow" />
<title>Profile feast preview — <?php echo pm_h($pm['name']); ?></title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="stylesheets/player-feast.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/player-feast-sections.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/player-feast-glance.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/player-feast-personal-bests.css" rel="stylesheet" type="text/css" />
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_feast_scripts.php'; ?>
</head>
<body class="k2-site player-feast-body">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<div class="k2-page-nav">

<?php
player_feast_render_core($pm);

$k2PlayerTabActive = 'profile';
$id = $playerId;
include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_nav.php';

player_feast_render_presence_career_duo($pm);
player_feast_render_played_days($playerId, $calYear);
player_feast_render_peak_activity($pm);
player_feast_render_moments($pm);
player_feast_render_charts($playerId);
?>

</div>

<?php mysqli_close($con); ?>
</body>
</html>
