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
include __DIR__ . '/../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");

$fixtureOpsUrl = '/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot&pwd=coffee';

/** @var list<array<string, mixed>> */
$generatedRows = [];
$res = $con->query(
    "SELECT t.id, t.name, t.event_date, t.lifecycle_status,
            COUNT(DISTINCT s.id) AS stage_count,
            COUNT(DISTINCT f.id) AS fixture_count,
            COUNT(DISTINCT g.id) AS game_count
     FROM tournaments t
     INNER JOIN tournament_stages s ON s.tournament_id = t.id
     LEFT JOIN tournament_fixtures f ON f.stage_id = s.id
     LEFT JOIN amiga_games g ON g.fixture_id = f.id
     WHERE t.source_id IS NULL
       AND (
         COALESCE(t.format_overrides, '') LIKE '%scripts.amiga.tournament_builder%'
         OR COALESCE(t.format_overrides, '') LIKE '%site.public_html.amiga.ops.fixtures%'
       )
     GROUP BY t.id, t.name, t.event_date, t.lifecycle_status
     ORDER BY t.id DESC
     LIMIT 50"
);
while ($res && ($row = $res->fetch_assoc())) {
    $generatedRows[] = $row;
}
if ($res) {
    $res->free();
}
mysqli_close($con);
?>

<header class="k2-hub-page-intro-head" style="padding:0 1.25rem">
  <h1 class="k2-hub-intro" style="margin:0 0 0.5rem">Live tournaments</h1>
  <p class="k2-hub-intro" style="margin:0 0 1rem;color:var(--k2-text-secondary)">
    Fixture-backed events created in this database — create, lifecycle, fixtures, and result entry.
    Historical completed events remain under the <a href="/amiga/tournaments.php">Tournaments</a> tab.
  </p>
  <nav class="k2-player-nav k2-nav-pills k2-amiga-tournament-nav" aria-label="Live tournament tools" style="margin-bottom:1rem">
    <div class="k2-player-nav__links">
      <a href="<?php echo k2_h($fixtureOpsUrl); ?>" class="k2-player-nav__btn is-active">Fixture manager</a>
    </div>
  </nav>
</header>

<div class="k2-table-wrap" style="padding:0 1.25rem 1rem">
<table class="k2-table k2-table--numeric-default k2-table--calm-stats" data-k2-table="sortable" data-k2-autorank="false">
<thead>
  <tr>
    <th class="k2-table-cell--left" data-k2-sort="number">ID</th>
    <th class="k2-table-cell--left" data-k2-sort="text">Tournament</th>
    <th data-k2-sort="text">Date</th>
    <th data-k2-sort="text">Lifecycle</th>
    <th data-k2-sort="number">Fixtures</th>
    <th data-k2-sort="number">Games</th>
    <th class="k2-table-cell--left">Manage</th>
  </tr>
</thead>
<tbody>
<?php if ($generatedRows === []) { ?>
  <tr>
    <td colspan="7" class="k2-table-cell--left" style="color:var(--k2-text-secondary)">No generated fixture-backed tournaments yet. Open the fixture manager to create one.</td>
  </tr>
<?php } ?>
<?php foreach ($generatedRows as $row) {
    $manageUrl = $fixtureOpsUrl . '&tournament_id=' . (int) $row['id'];
    ?>
  <tr>
    <td class="k2-table-cell--left"><?php echo (int) $row['id']; ?></td>
    <td class="k2-table-cell--left"><?php echo k2_h((string) $row['name']); ?></td>
    <td><?php echo $row['event_date'] !== null ? k2_h((string) $row['event_date']) : '—'; ?></td>
    <td><span class="k2-amiga-tournament-badge"><?php echo k2_h((string) $row['lifecycle_status']); ?></span></td>
    <td><?php echo (int) $row['fixture_count']; ?></td>
    <td><?php echo (int) $row['game_count']; ?></td>
    <td class="k2-table-cell--left"><a href="<?php echo k2_h($manageUrl); ?>">fixtures</a></td>
  </tr>
<?php } ?>
</tbody>
</table>
</div>

</div><!-- .k2-page-nav -->

</body>
</html>
