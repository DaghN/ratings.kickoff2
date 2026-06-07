<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga — Live tournaments</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="/stylesheets/amiga-tournament.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/amiga-tournament.css'); ?>" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="/js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2AmigaHubTabActive = 'live-tournaments';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_lib.php';
include __DIR__ . '/../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");

$liveRows = amiga_live_tournament_index_rows($con);
$allowlistConfigured = amiga_live_tournament_allowlist_ids() !== [];
mysqli_close($con);
?>

<header class="k2-hub-page-intro-head" style="padding:0 1.25rem">
  <h1 class="k2-hub-intro" style="margin:0 0 0.5rem">Live tournaments</h1>
  <p class="k2-hub-intro" style="margin:0 0 1rem;color:var(--k2-text-secondary)">
    Read-only view of running fixture-backed events selected for public display.
    Completed and archived events remain under the <a href="/amiga/tournaments.php">Tournaments</a> tab.
  </p>
</header>

<div class="k2-table-wrap" style="padding:0 1.25rem 1rem">
<table class="k2-table k2-table--numeric-default k2-table--calm-stats" data-k2-table="sortable" data-k2-autorank="false">
<thead>
  <tr>
    <th class="k2-table-cell--left" data-k2-sort="text">Tournament</th>
    <th data-k2-sort="text">Date</th>
    <th data-k2-sort="text">Country</th>
    <th data-k2-sort="text">Status</th>
    <th data-k2-sort="number">Played</th>
    <th data-k2-sort="number">Scheduled</th>
    <th data-k2-sort="number">Fixtures</th>
  </tr>
</thead>
<tbody>
<?php if ($liveRows === []) { ?>
  <tr>
    <td colspan="7" class="k2-table-cell--left" style="color:var(--k2-text-secondary)"><?php
        if (!$allowlistConfigured) {
            echo 'No live events are published for public viewing yet.';
        } else {
            echo 'No running live events match the current public allowlist.';
        }
    ?></td>
  </tr>
<?php } ?>
<?php foreach ($liveRows as $row) { ?>
  <tr>
    <td class="k2-table-cell--left"><?php echo amiga_live_tournament_link((int) $row['id'], (string) $row['name']); ?></td>
    <td><?php echo $row['event_date'] !== null ? k2_h((string) $row['event_date']) : '—'; ?></td>
    <td><?php echo !empty($row['country']) ? k2_h((string) $row['country']) : '—'; ?></td>
    <td><span class="k2-amiga-tournament-badge"><?php echo k2_h((string) $row['lifecycle_status']); ?></span></td>
    <td><?php echo (int) ($row['played_count'] ?? 0); ?></td>
    <td><?php echo (int) ($row['scheduled_count'] ?? 0); ?></td>
    <td><?php echo (int) ($row['fixture_count'] ?? 0); ?></td>
  </tr>
<?php } ?>
</tbody>
</table>
</div>

<p class="k2-amiga-live-view__ops-note" style="padding:0 1.25rem 1rem;color:var(--k2-text-secondary)">
  Operators: fixture management and result entry use the internal
  <a href="/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot">fixture manager</a> (password required).
</p>

</div><!-- .k2-page-nav -->

</body>
</html>
