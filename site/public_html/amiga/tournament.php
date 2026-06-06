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

if (!in_array($scopeType, ['overall', 'group', 'placement', 'knockout'], true)) {

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

$knockoutScopes = amiga_tournament_list_scopes($con, $id, 'knockout');

$rows = amiga_tournament_standings_rows($con, $id, $scopeType, $scopeKey);



if ($scopeType === 'overall' && $scopeKey === '' && $rows === [] && $groupScopes !== []) {

    $scopeType = 'group';

    $scopeKey = $groupScopes[0];

    $rows = amiga_tournament_standings_rows($con, $id, $scopeType, $scopeKey);

} elseif ($scopeType === 'overall' && $scopeKey === '' && $rows === [] && $knockoutScopes !== []) {

    $scopeType = 'knockout';

    $scopeKey = $knockoutScopes[0];

    $rows = amiga_tournament_standings_rows($con, $id, $scopeType, $scopeKey);

}

$knockoutScopeLabels = [];
foreach ($knockoutScopes as $kk) {
    $knockoutScopeLabels[$kk] = amiga_tournament_scope_label($con, $kk);
}

$tName = (string) $tournament['name'];
$pageTitle = $tName;
if ($scopeKey !== '' && $scopeType !== 'overall') {
    $pageTitle .= ' — ' . amiga_tournament_scope_label($con, $scopeKey);
}
$isKnockoutView = $scopeType === 'knockout';

$knockoutFixture = [];
$knockoutWinner = null;
if ($isKnockoutView && $scopeKey !== '') {
    $knockoutFixture = amiga_tournament_knockout_fixture_games($con, $id, $scopeKey);
    $knockoutWinner = amiga_tournament_knockout_resolve_winner($knockoutFixture, $rows);
}

mysqli_close($con);

?>



<div class="k2-page-nav" style="padding:1rem 1.25rem 0">

  <p style="margin:0 0 1rem">

    <a class="k2-link-star" href="/amiga/rating.php">← Amiga ladder</a>

    · <a class="k2-link-star" href="/amiga/tournaments.php">All tournaments</a>

  </p>

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

  <p style="margin:0 0 0.5rem">

    <strong>Groups:</strong>

    <?php if ($scopeType === 'overall' && $scopeKey === '') { ?>

    <a href="?id=<?php echo $id; ?>&amp;scope=overall" aria-current="page">Overall</a>

    <?php } else { ?>

    <a href="?id=<?php echo $id; ?>&amp;scope=overall">Overall</a>

    <?php } ?>

    <?php foreach ($groupScopes as $gk) {

        $active = $scopeType === 'group' && $scopeKey === $gk;

        ?>

    · <a href="?id=<?php echo $id; ?>&amp;scope=group&amp;scope_key=<?php echo urlencode($gk); ?>"<?php

        echo $active ? ' aria-current="page"' : '';

    ?>><?php echo k2_h($gk); ?></a>

    <?php } ?>

  </p>

  <?php } ?>



  <?php if ($knockoutScopes !== []) { ?>

  <details style="margin:0 0 1rem"<?php echo $isKnockoutView ? ' open' : ''; ?>>

    <summary><strong>Elimination ties</strong> (<?php echo count($knockoutScopes); ?>)</summary>

    <p style="margin:0.5rem 0 0;line-height:1.6">

    <?php foreach ($knockoutScopes as $kk) {

        $active = $scopeType === 'knockout' && $scopeKey === $kk;

        $label = $knockoutScopeLabels[$kk] ?? $kk;

        ?>

    <a href="?id=<?php echo $id; ?>&amp;scope=knockout&amp;scope_key=<?php echo urlencode($kk); ?>"<?php

        echo $active ? ' aria-current="page"' : '';

    ?> style="display:inline-block;margin:0 0.75rem 0.75rem 0"><?php echo k2_h($label); ?></a>

    <?php } ?>

    </p>

  </details>

  <?php } ?>

</div>



<?php if ($isKnockoutView && $knockoutFixture !== []) {

    $winnerId = $knockoutWinner !== null ? ($knockoutWinner['winner_id'] ?? null) : null;
    $loserId = $knockoutWinner !== null ? ($knockoutWinner['loser_id'] ?? null) : null;
    $winnerUnresolved = $knockoutWinner !== null && !empty($knockoutWinner['unresolved']);
    $fixtureNames = [];
    foreach ($knockoutFixture as $leg) {
        $fixtureNames[(int) $leg['player_a_id']] = (string) $leg['player_a_name'];
        $fixtureNames[(int) $leg['player_b_id']] = (string) $leg['player_b_name'];
    }
    $winnerName = $winnerId !== null ? ($fixtureNames[$winnerId] ?? ('#' . $winnerId)) : '';
    $winnerAgg = ($winnerId !== null && $knockoutWinner !== null)
        ? ($knockoutWinner['aggregate'][$winnerId] ?? null)
        : null;
    $loserAgg = ($loserId !== null && $knockoutWinner !== null)
        ? ($knockoutWinner['aggregate'][$loserId] ?? null)
        : null;

?>

<div style="padding:0 1.25rem 1.25rem">

  <div class="k2-table-wrap" style="margin:0">

    <p style="margin:0 0 0.75rem;line-height:1.5">

      <?php if ($winnerUnresolved) { ?>

      <strong>Tie unresolved</strong>

      <?php } elseif ($winnerId !== null && $winnerAgg !== null && $loserAgg !== null) { ?>

      <strong>Winner:</strong> <?php echo k2_amiga_player_link($winnerId, $winnerName); ?>

      (aggregate <?php echo (int) $winnerAgg['goals_for']; ?>–<?php echo (int) $loserAgg['goals_for']; ?>)

      <?php } ?>

    </p>

    <table class="k2-table k2-table--numeric-default k2-table--calm-stats">

      <thead>

        <tr>

          <th class="k2-table-cell--left">Leg</th>

          <th class="k2-table-cell--left" colspan="3">Fixture</th>

        </tr>

      </thead>

      <tbody class="black">

      <?php foreach ($knockoutFixture as $legIdx => $leg) { ?>

        <tr>

          <td class="k2-table-cell--left"><?php echo (int) ($legIdx + 1); ?></td>

          <td class="k2-table-cell--left"><?php

              echo k2_amiga_player_link((int) $leg['player_a_id'], (string) $leg['player_a_name']);

          ?></td>

          <td><?php

              echo (int) $leg['goals_a'] . ' – ' . (int) $leg['goals_b'];

              echo amiga_tournament_format_game_extra(isset($leg['extra']) ? (string) $leg['extra'] : null);

          ?></td>

          <td class="k2-table-cell--left"><?php

              echo k2_amiga_player_link((int) $leg['player_b_id'], (string) $leg['player_b_name']);

          ?></td>

        </tr>

      <?php } ?>

      </tbody>

    </table>

  </div>

</div>

<?php } ?>



<div class="k2-table-wrap">

<table class="k2-table k2-table--numeric-default k2-table--calm-stats" data-k2-table="sortable" data-k2-autorank="false">

<thead>

    <tr>

        <th data-k2-sort="number"><?php echo $isKnockoutView ? ' ' : 'Pos'; ?></th>

        <th class="k2-table-cell--left" data-k2-sort="text">Player</th>

        <?php if (!$isKnockoutView) { ?>

        <th data-k2-sort="number">Pts</th>

        <?php } ?>

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

<?php if ($rows === []) { ?>

    <tr>

        <td colspan="<?php echo $isKnockoutView ? 9 : 10; ?>" class="k2-table-cell--left" style="color:var(--k2-text-secondary)">

            No standings rows for this scope.

        </td>

    </tr>

<?php } ?>

<?php foreach ($rows as $row) {

    $gd = (int) $row['goals_for'] - (int) $row['goals_against'];

    $posLabel = $isKnockoutView

        ? ((int) $row['position'] === 1 ? 'W' : 'L')

        : (string) (int) $row['position'];

    ?>

    <tr>

        <td><?php echo k2_h($posLabel); ?></td>

        <td class="k2-table-cell--left"><?php

            echo k2_amiga_player_link((int) $row['player_id'], (string) $row['player_name']);

        ?></td>

        <?php if (!$isKnockoutView) { ?>

        <td><?php echo (int) $row['points']; ?></td>

        <?php } ?>

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

  <?php if ($isKnockoutView) { ?>

  Per-leg scores above; aggregate table below. Winner by total goal difference (penalties in <code>extra</code> when aggregate is tied).

  <?php } else { ?>

  Standings derived from match results (3 pts win, 1 draw). Rebuilt by <code>python -m scripts.amiga replay</code>.

  <?php } ?>

</p>



</body>

</html>

