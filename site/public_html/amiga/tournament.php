<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga tournament standings</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<script type="text/javascript" src="/js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_lib.php';
include __DIR__ . '/../../config/ko2amiga_config.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$scopeType = isset($_GET['scope']) ? (string) $_GET['scope'] : 'overall';
$scopeKey = isset($_GET['scope_key']) ? (string) $_GET['scope_key'] : '';
if ($id < 1) {
    http_response_code(404);
    exit('Tournament not found.');
}
if (!in_array($scopeType, ['overall', 'group', 'placement'], true)) {
    $scopeType = 'overall';
}

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");

$tournament = amiga_tournament_load($con, $id);
if ($tournament === null) {
    mysqli_close($con);
    http_response_code(404);
    exit('Tournament not found.');
}

$groupScopes = amiga_tournament_list_scopes($con, $id, 'group');
$rows = amiga_tournament_standings_rows($con, $id, $scopeType, $scopeKey);
mysqli_close($con);

$tName = (string) $tournament['name'];
$pageTitle = $tName;
if ($scopeType === 'group' && $scopeKey !== '') {
    $pageTitle .= ' — ' . $scopeKey;
}
?>

<div class="k2-page-nav" style="padding:1rem 1.25rem 0">
  <p style="margin:0 0 1rem"><a class="k2-link-star" href="/amiga/rating.php">← Amiga ladder</a></p>
  <h1 class="k2-hub-intro" style="margin:0 0 0.5rem"><?php echo k2_h($pageTitle); ?></h1>
  <?php if (!empty($tournament['event_date'])) { ?>
  <p class="k2-hub-intro" style="margin:0 0 1rem;color:var(--k2-text-secondary)"><?php
      echo k2_h((string) $tournament['event_date']);
      if (!empty($tournament['country'])) {
          echo ' · ' . k2_h((string) $tournament['country']);
      }
  ?></p>
  <?php } ?>

  <?php if ($groupScopes !== []) { ?>
  <p style="margin:0 0 1rem">
    <strong>Table:</strong>
    <a href="?id=<?php echo $id; ?>&amp;scope=overall"<?php echo $scopeType === 'overall' ? ' aria-current="page"' : ''; ?>>Overall</a>
    <?php foreach ($groupScopes as $gk) {
        $active = $scopeType === 'group' && $scopeKey === $gk;
        $label = $gk !== '' ? $gk : 'Group';
        ?>
    · <a href="?id=<?php echo $id; ?>&amp;scope=group&amp;scope_key=<?php echo urlencode($gk); ?>"<?php
        echo $active ? ' aria-current="page"' : '';
    ?>><?php echo k2_h($label); ?></a>
    <?php } ?>
  </p>
  <?php } ?>
</div>

<div class="k2-table-wrap">
<table class="k2-table k2-table--numeric-default k2-table--calm-stats" data-k2-table="sortable" data-k2-autorank="false">
<thead>
    <tr>
        <th data-k2-sort="number">Pos</th>
        <th class="k2-table-cell--left" data-k2-sort="text">Player</th>
        <th data-k2-sort="number">Pts</th>
        <th data-k2-sort="number">G</th>
        <th data-k2-sort="number">W</th>
        <th data-k2-sort="number">D</th>
        <th data-k2-sort="number">L</th>
        <th data-k2-sort="number">GF</th>
        <th data-k2-sort="number">GA</th>
        <th data-k2-sort="number">GD</th>
    </tr>
</thead>
<tbody class="black">
<?php foreach ($rows as $row) {
    $gd = (int) $row['goals_for'] - (int) $row['goals_against'];
    ?>
    <tr>
        <td><?php echo (int) $row['position']; ?></td>
        <td class="k2-table-cell--left"><?php
            echo k2_amiga_player_link((int) $row['player_id'], (string) $row['player_name']);
        ?></td>
        <td><?php echo (int) $row['points']; ?></td>
        <td><?php echo (int) $row['games']; ?></td>
        <td><?php echo (int) $row['wins']; ?></td>
        <td><?php echo (int) $row['draws']; ?></td>
        <td><?php echo (int) $row['losses']; ?></td>
        <td><?php echo (int) $row['goals_for']; ?></td>
        <td><?php echo (int) $row['goals_against']; ?></td>
        <td><?php echo $gd > 0 ? '+' . $gd : (string) $gd; ?></td>
    </tr>
<?php } ?>
</tbody>
</table>
</div>

<p style="padding:0 1.25rem 2rem;color:var(--k2-text-secondary)">
  Standings derived from match results (3 pts win, 1 draw). Rebuilt by <code>python -m scripts.amiga replay</code>.
</p>

</body>
</html>
