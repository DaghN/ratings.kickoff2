<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga tournaments</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="/stylesheets/amiga-tournament.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/amiga-tournament.css'); ?>" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="/js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2AmigaHubTabActive = 'tournaments';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_lib.php';
include __DIR__ . '/../../config/ko2amiga_config.php';

$typeFilter = isset($_GET['type']) ? (string) $_GET['type'] : '';
if (!in_array($typeFilter, ['', 'world-cup', 'league', 'cup'], true)) {
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

<header class="k2-hub-page-intro-head" style="padding:0 1.25rem">
  <h1 class="k2-hub-intro" style="margin:0 0 0.5rem">Tournaments</h1>
  <p class="k2-hub-intro" style="margin:0 0 1rem;color:var(--k2-text-secondary)">
    Offline events with derived standings. Cups include group tables and knockout brackets.
  </p>
  <nav class="k2-player-nav k2-nav-pills k2-amiga-tournament-nav" aria-label="Filter by format" style="margin-bottom:1rem">
    <div class="k2-player-nav__links">
      <a href="?" class="k2-player-nav__btn<?php echo $typeFilter === '' ? ' is-active' : ''; ?>">All</a>
      <a href="?type=world-cup" class="k2-player-nav__btn<?php echo $typeFilter === 'world-cup' ? ' is-active' : ''; ?>">World Cups</a>
      <a href="?type=league" class="k2-player-nav__btn<?php echo $typeFilter === 'league' ? ' is-active' : ''; ?>">Leagues</a>
      <a href="?type=cup" class="k2-player-nav__btn<?php echo $typeFilter === 'cup' ? ' is-active' : ''; ?>">Cups</a>
    </div>
  </nav>
</header>

<div class="k2-table-wrap">
<table class="k2-table k2-table--numeric-default k2-table--calm-stats" data-k2-table="sortable" data-k2-autorank="false">
<thead>
    <tr>
        <th class="k2-table-cell--left" data-k2-sort="text">Tournament</th>
        <th data-k2-sort="text">Date</th>
        <th data-k2-sort="text">Country</th>
        <th data-k2-sort="number">Games</th>
        <th data-k2-sort="number">Players</th>
        <th data-k2-sort="text">Format</th>
    </tr>
</thead>
<tbody class="black">
<?php if ($rows === []) { ?>
    <tr>
        <td colspan="6" class="k2-table-cell--left" style="color:var(--k2-text-secondary)">No tournaments match this filter.</td>
    </tr>
<?php } ?>
<?php foreach ($rows as $row) {
    $games = (int) $row['game_count'];
    $players = (int) $row['standing_players'];
    $hasStandings = (int) ($row['standing_rows'] ?? 0) > 0;
    $kind = amiga_tournament_index_format_kind($row);
    $linkFragment = $kind === 'cup' && (int) ($row['knockout_ties'] ?? 0) > 0 ? '#bracket' : '';
    ?>
    <tr>
        <td class="k2-table-cell--left"><?php
            if ($hasStandings) {
                echo amiga_tournament_link((int) $row['id'], (string) $row['name'], $linkFragment);
            } else {
                echo k2_h((string) $row['name']);
            }
        ?></td>
        <td><?php echo $row['event_date'] ? k2_h((string) $row['event_date']) : '—'; ?></td>
        <td><?php echo !empty($row['country']) ? k2_h((string) $row['country']) : '—'; ?></td>
        <td><?php echo $games; ?></td>
        <td><?php echo $hasStandings ? (string) $players : '—'; ?></td>
        <td>
            <span class="k2-amiga-tournament-type">
                <span class="k2-amiga-tournament-badge k2-amiga-tournament-badge--<?php echo k2_h($kind); ?>"><?php

                    echo $kind === 'cup' ? 'Cup' : 'League';

                ?></span>
            </span>
        </td>
    </tr>
<?php } ?>
</tbody>
</table>
</div>

<p style="padding:0 1.25rem 1rem;color:var(--k2-text-secondary)">
    <?php echo count($rows); ?> tournament<?php echo count($rows) === 1 ? '' : 's'; ?><?php

        echo $typeFilter !== '' ? ' (filtered)' : '';

    ?>.
</p>

</div><!-- .k2-page-nav -->

</body>
</html>
