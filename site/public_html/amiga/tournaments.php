<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga tournaments</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<script type="text/javascript" src="/js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_lib.php';
include __DIR__ . '/../../config/ko2amiga_config.php';

$limit = 200;
$offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");

$total = amiga_tournament_index_count($con);
$rows = amiga_tournament_index_rows($con, $limit, $offset);
mysqli_close($con);

$firstShown = $total > 0 ? $offset + 1 : 0;
$lastShown = $offset + count($rows);
?>

<div class="k2-page-nav" style="padding:1rem 1.25rem 0">
  <p style="margin:0 0 1rem"><a class="k2-link-star" href="/amiga/rating.php">← Amiga ladder</a></p>
  <h1 class="k2-hub-intro" style="margin:0 0 0.5rem">Tournaments</h1>
  <p class="k2-hub-intro" style="margin:0 0 1rem;color:var(--k2-text-secondary)">
    Offline events with derived standings. Click a name for the points table.
  </p>
</div>

<div class="k2-table-wrap">
<table class="k2-table k2-table--numeric-default k2-table--calm-stats" data-k2-table="sortable" data-k2-autorank="false">
<thead>
    <tr>
        <th class="k2-table-cell--left" data-k2-sort="text">Tournament</th>
        <th data-k2-sort="text">Date</th>
        <th data-k2-sort="text">Country</th>
        <th data-k2-sort="number">Games</th>
        <th data-k2-sort="number">Players</th>
        <th data-k2-sort="text">Type</th>
    </tr>
</thead>
<tbody class="black">
<?php foreach ($rows as $row) {
    $games = (int) $row['game_count'];
    $players = (int) $row['standing_players'];
    $hasStandings = (int) ($row['standing_rows'] ?? 0) > 0;
    ?>
    <tr>
        <td class="k2-table-cell--left"><?php
            if ($hasStandings) {
                echo amiga_tournament_link((int) $row['id'], (string) $row['name']);
            } else {
                echo k2_h((string) $row['name']);
            }
        ?></td>
        <td><?php echo $row['event_date'] ? k2_h((string) $row['event_date']) : '—'; ?></td>
        <td><?php echo !empty($row['country']) ? k2_h((string) $row['country']) : '—'; ?></td>
        <td><?php echo $games; ?></td>
        <td><?php echo $hasStandings ? (string) $players : '—'; ?></td>
        <td><?php echo (int) $row['is_cup'] === 1 ? 'Cup' : 'League'; ?></td>
    </tr>
<?php } ?>
</tbody>
</table>
</div>

<p style="padding:0 1.25rem 1rem;color:var(--k2-text-secondary)">
    Showing <?php echo $firstShown; ?>–<?php echo $lastShown; ?> of <?php echo $total; ?> tournaments.
    <?php if ($offset > 0) { ?>
    <a href="?offset=<?php echo max(0, $offset - $limit); ?>">Previous <?php echo $limit; ?></a>
    <?php } ?>
    <?php if ($offset + $limit < $total) { ?>
    <a href="?offset=<?php echo $offset + $limit; ?>">Next <?php echo $limit; ?></a>
    <?php } ?>
</p>

</body>
</html>
