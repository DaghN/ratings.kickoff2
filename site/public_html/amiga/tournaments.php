<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga tournaments</title>
<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="/stylesheets/amiga-tournament.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/amiga-tournament.css'); ?>" rel="stylesheet" type="text/css" />
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; ?>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2AmigaHubTabActive = 'tournaments';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_profile_blocks.php';
include __DIR__ . '/../../config/ko2amiga_config.php';

$typeFilter = isset($_GET['type']) ? (string) $_GET['type'] : '';
if (!in_array($typeFilter, ['', 'world-cup', 'league', 'cup', 'league-cup'], true)) {
    $typeFilter = '';
}

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");

$allRows = amiga_tournament_index_rows($con);
mysqli_close($con);

$rows = $allRows;
if ($typeFilter !== '') {
    $rows = array_values(array_filter(
        $allRows,
        static fn (array $row): bool => amiga_tournament_index_matches_filter($row, $typeFilter)
    ));
}
?>

<header class="k2-hub-chapter">
  <h1 class="k2-hub-chapter__title">Tournaments</h1>
  <nav class="k2-player-nav k2-nav-pills k2-amiga-tournament-nav k2-hub-chapter__nav" data-k2-carry-scroll aria-label="Filter by format">
    <div class="k2-player-nav__links">
      <a href="<?php echo k2_h(amiga_tournament_index_filter_url()); ?>" class="k2-player-nav__btn<?php echo $typeFilter === '' ? ' is-active' : ''; ?>">All</a>
      <a href="<?php echo k2_h(amiga_tournament_index_filter_url('world-cup')); ?>" class="k2-player-nav__btn<?php echo $typeFilter === 'world-cup' ? ' is-active' : ''; ?>">World Cups</a>
      <a href="<?php echo k2_h(amiga_tournament_index_filter_url('league')); ?>" class="k2-player-nav__btn<?php echo $typeFilter === 'league' ? ' is-active' : ''; ?>">Leagues</a>
      <a href="<?php echo k2_h(amiga_tournament_index_filter_url('cup')); ?>" class="k2-player-nav__btn<?php echo $typeFilter === 'cup' ? ' is-active' : ''; ?>">Cups</a>
      <a href="<?php echo k2_h(amiga_tournament_index_filter_url('league-cup')); ?>" class="k2-player-nav__btn<?php echo $typeFilter === 'league-cup' ? ' is-active' : ''; ?>">League + cup</a>
    </div>
  </nav>
</header>

<?php amiga_tournament_index_render_table($rows); ?>

<p class="k2-amiga-tournament-footnote" style="padding-bottom:1rem">
    <?php echo count($rows); ?> tournament<?php echo count($rows) === 1 ? '' : 's'; ?><?php

        echo $typeFilter !== '' ? ' (filtered)' : '';

    ?>.
</p>

</div><!-- .k2-page-nav -->

</body>
</html>
