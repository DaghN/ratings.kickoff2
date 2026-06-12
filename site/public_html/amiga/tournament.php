<?php

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_lib.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';

$tournamentPageId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$tournamentScopeType = isset($_GET['scope']) ? (string) $_GET['scope'] : 'league';

$tournamentScopeKey = isset($_GET['scope_key']) ? (string) $_GET['scope_key'] : '';

$tournamentPageView = match ((string) ($_GET['view'] ?? '')) {
    'event-stats' => 'event-stats',
    'games' => 'games',
    'stages' => 'stages',
    'standings' => 'standings',
    default => 'standings',
};

$tournamentCanonicalScope = amiga_tournament_canonicalize_scope_request($tournamentScopeType, $tournamentScopeKey);

$tournamentBootstrap = null;

$tournamentDb = null;

if ($tournamentPageId >= 1) {

    include __DIR__ . '/../../config/ko2amiga_config.php';

    $tournamentDb = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);

    $tournamentDb->query("SET time_zone = '+00:00'");

    $tournamentBootstrap = amiga_tournament_load($tournamentDb, $tournamentPageId);

    amiga_tournament_apply_entry_redirects(
        $tournamentPageId,
        $tournamentBootstrap,
        $tournamentCanonicalScope,
        $tournamentPageView,
        $_GET,
    );

}

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">

<head>

<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<title>Amiga tournament standings</title>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>

<link href="/stylesheets/amiga-tournament.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/amiga-tournament.css'); ?>" rel="stylesheet" type="text/css" />

<script type="text/javascript" src="/js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>

<script type="text/javascript" src="/js/k2-archive-listbox.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-archive-listbox.js'); ?>" defer="defer"></script>

<script type="text/javascript" src="/js/individual3-filters.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/individual3-filters.js'); ?>" defer="defer"></script>

</head>

<body class="k2-site">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>



<?php

$id = $tournamentPageId;

$scopeType = $tournamentCanonicalScope['scope_type'];

$scopeKey = $tournamentCanonicalScope['scope_key'];

$pageView = $tournamentPageView;

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_load.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_bracket.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_tournament_lib.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_profile_blocks.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_archive_listbox.php';

if ($id < 1) {

    http_response_code(404);

    exit('Tournament not found.');

}

$con = $tournamentDb ?? null;

if ($con === null) {

    include __DIR__ . '/../../config/ko2amiga_config.php';

    $con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);

    $con->query("SET time_zone = '+00:00'");

}

$tournament = $tournamentBootstrap ?? amiga_tournament_load($con, $id);

if ($tournament === null) {

    mysqli_close($con);

    http_response_code(404);

    exit('Tournament not found.');

}



$leagueLabeledScopes = amiga_tournament_list_league_labeled_scopes($con, $id);

$knockoutScopes = amiga_tournament_list_scopes($con, $id, 'knockout');

$implicitLeagueRows = amiga_tournament_standings_rows($con, $id, 'league', '');

$rows = amiga_tournament_standings_rows($con, $id, $scopeType, $scopeKey);



if ($scopeType === 'league' && $scopeKey === '' && $rows === [] && $leagueLabeledScopes !== []) {

    $scopeKey = $leagueLabeledScopes[0];

    $rows = amiga_tournament_standings_rows($con, $id, 'league', $scopeKey);

} elseif ($scopeType === 'league' && $scopeKey === '' && $rows === [] && $knockoutScopes !== []) {

    $scopeType = 'knockout';

    $scopeKey = $knockoutScopes[0];

    $rows = amiga_tournament_standings_rows($con, $id, $scopeType, $scopeKey);

}

$knockoutScopeLabels = [];
foreach ($knockoutScopes as $kk) {
    $knockoutScopeLabels[$kk] = amiga_tournament_scope_label($con, $kk);
}

$tName = (string) $tournament['name'];
$scopeLabel = $scopeKey !== '' && $scopeType === 'league'
    ? amiga_tournament_scope_label($con, $scopeKey)
    : '';
$isKnockoutView = $scopeType === 'knockout';
$formatKind = amiga_tournament_format_kind($tournament, $leagueLabeledScopes, $knockoutScopes);
$hasBracket = $knockoutScopes !== [];
// Show when implicit league+'' rows exist — mixed events use this as aggregate tab; pure single-table events need it for active nav.
$showLeagueTableTab = $implicitLeagueRows !== [];
$hasLeagueStandingsNav = $implicitLeagueRows !== [] || $leagueLabeledScopes !== [];

$knockoutFixture = [];
$knockoutWinner = null;
if ($isKnockoutView && $scopeKey !== '') {
    $knockoutFixture = amiga_tournament_knockout_fixture_games($con, $id, $scopeKey);
    $knockoutWinner = amiga_tournament_knockout_resolve_winner($knockoutFixture, $rows);
}

$bracketData = $hasBracket
    ? amiga_tournament_knockout_bracket_data($con, $id, $knockoutScopes)
    : ['main' => [], 'placement_final' => [], 'placement_bracket' => []];

$eventStatsRows = amiga_tournament_participation_rows($con, $id);

$tournamentGameCount = amiga_tournament_game_count($con, $id);

$hasGamesTab = $tournamentGameCount > 0;

$tournamentGamesPlayerFilter = isset($_GET['player']) ? max(0, (int) $_GET['player']) : 0;

$tournamentGamePlayerChoices = [];

$tournamentGamesRows = [];

if ($pageView === 'games' && $hasGamesTab) {

    $tournamentGamePlayerChoices = amiga_tournament_game_player_choices($con, $id);

    $validPlayerIds = [];

    foreach ($tournamentGamePlayerChoices as $choice) {

        $validPlayerIds[(int) $choice['player_id']] = true;

    }

    if ($tournamentGamesPlayerFilter > 0 && !isset($validPlayerIds[$tournamentGamesPlayerFilter])) {

        $tournamentGamesPlayerFilter = 0;

    }

    $tournamentGamesRows = amiga_tournament_games_rows($con, $id, $tournamentGamesPlayerFilter);

}

$isWorldCupEvent = amiga_tournament_is_world_cup($tournament);

$hasStagesTab = $isWorldCupEvent && ($hasLeagueStandingsNav || $hasBracket);

$stagesEntryUrl = amiga_tournament_stages_entry_url($id, $showLeagueTableTab, $leagueLabeledScopes, $hasBracket);

$isStagesContentView = $pageView === 'stages' || ($pageView === 'standings' && !$isWorldCupEvent);

mysqli_close($con);

?>



<?php
$k2AmigaHubTabActive = 'tournaments';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';
?>

<header class="k2-amiga-tournament-hero">

  <h1 class="k2-amiga-tournament-hero__title k2-hub-intro"><?php echo k2_h($tName); ?></h1>

  <p class="k2-amiga-tournament-hero__meta"><?php

      $meta = [];
      if (!empty($tournament['event_date'])) {
          $meta[] = amiga_profile_format_event_date($tournament['event_date']);
      }
      if (!empty($tournament['country'])) {
          $meta[] = k2_h((string) $tournament['country']);
      }
      if ((int) ($tournament['player_count'] ?? 0) > 0) {
          $meta[] = k2_h((int) $tournament['player_count'] . ' players');
      }
      echo $meta !== [] ? implode(' · ', $meta) : '—';

  ?></p>

</header>



<?php if ($hasLeagueStandingsNav || $hasBracket || $hasGamesTab || $eventStatsRows !== []) { ?>

<nav class="k2-amiga-tournament-nav k2-player-nav-bar" aria-label="Tournament sections">

  <div class="k2-player-nav k2-nav-pills">

    <div class="k2-player-nav__links">

      <?php
      $eventStatsActive = $pageView === 'event-stats';
      $eventStatsNav = static function () use ($id, $eventStatsActive, $eventStatsRows): void {
          if ($eventStatsRows === []) {
              return;
          }
          ?>
      <a href="<?php echo k2_h(amiga_tournament_event_stats_url($id)); ?>" class="k2-player-nav__btn<?php echo $eventStatsActive ? ' is-active' : ''; ?>"<?php

          echo $eventStatsActive ? ' aria-current="page"' : '';

      ?>>Event stats</a>
      <?php
      };
      $stagesSubNav = static function () use (
          $id,
          $pageView,
          $showLeagueTableTab,
          $leagueLabeledScopes,
          $hasBracket,
          $knockoutScopes,
          $scopeType,
          $scopeKey,
          $isKnockoutView,
          $isWorldCupEvent,
      ): void {
          if ($showLeagueTableTab) {
              $leagueTableActive = $scopeType === 'league' && $scopeKey === '' && $pageView === 'stages';
              ?>
      <a href="<?php echo k2_h(amiga_tournament_standings_nav_url($id, 'league', '', $isWorldCupEvent)); ?>" class="k2-player-nav__btn<?php echo $leagueTableActive ? ' is-active' : ''; ?>"<?php
              echo $leagueTableActive ? ' aria-current="page"' : '';
              ?>>League table</a>
              <?php
          }
          foreach ($leagueLabeledScopes as $lk) {
              $active = $scopeType === 'league' && $scopeKey === $lk && $pageView === 'stages';
              ?>
      <a href="<?php echo k2_h(amiga_tournament_standings_nav_url($id, 'league', $lk, $isWorldCupEvent)); ?>" class="k2-player-nav__btn<?php echo $active ? ' is-active' : ''; ?>"<?php
              echo $active ? ' aria-current="page"' : '';
              ?>><?php echo k2_h($lk); ?></a>
              <?php
          }
          if ($hasBracket) {
              $bracketScopeKey = $knockoutScopes[0] ?? '';
              ?>
      <a href="<?php echo k2_h(amiga_tournament_standings_nav_url($id, 'knockout', $bracketScopeKey, $isWorldCupEvent)); ?>" class="k2-player-nav__btn<?php echo $isKnockoutView && $pageView === 'stages' ? ' is-active' : ''; ?>">Bracket</a>
              <?php
          }
      };

      if ($isWorldCupEvent) {
          $eventStatsNav();
          if ($hasStagesTab) {
              $stagesActive = $pageView === 'stages';
              ?>
      <a href="<?php echo k2_h($stagesEntryUrl); ?>" class="k2-player-nav__btn<?php echo $stagesActive ? ' is-active' : ''; ?>"<?php
              echo $stagesActive ? ' aria-current="page"' : '';
              ?>>Stages</a>
              <?php
          }
          if ($hasGamesTab) {
              $gamesActive = $pageView === 'games';
              ?>
      <a href="<?php echo k2_h(amiga_tournament_games_url($id)); ?>" class="k2-player-nav__btn<?php echo $gamesActive ? ' is-active' : ''; ?>"<?php
              echo $gamesActive ? ' aria-current="page"' : '';
              ?>>Games</a>
              <?php
          }
      } else {
          if ($showLeagueTableTab) {
              $leagueTableActive = $scopeType === 'league' && $scopeKey === '' && $pageView === 'standings';
              ?>
      <a href="<?php echo k2_h(amiga_tournament_standings_nav_url($id, 'league', '', false)); ?>" class="k2-player-nav__btn<?php echo $leagueTableActive ? ' is-active' : ''; ?>"<?php
              echo $leagueTableActive ? ' aria-current="page"' : '';
              ?>>League table</a>
              <?php
          }
          foreach ($leagueLabeledScopes as $lk) {
              $active = $scopeType === 'league' && $scopeKey === $lk && $pageView === 'standings';
              ?>
      <a href="<?php echo k2_h(amiga_tournament_standings_nav_url($id, 'league', $lk, false)); ?>" class="k2-player-nav__btn<?php echo $active ? ' is-active' : ''; ?>"<?php
              echo $active ? ' aria-current="page"' : '';
              ?>><?php echo k2_h($lk); ?></a>
              <?php
          }
          if ($hasBracket) {
              $bracketScopeKey = $knockoutScopes[0] ?? '';
              ?>
      <a href="<?php echo k2_h(amiga_tournament_standings_nav_url($id, 'knockout', $bracketScopeKey, false)); ?>" class="k2-player-nav__btn<?php echo $isKnockoutView && $pageView === 'standings' ? ' is-active' : ''; ?>">Bracket</a>
              <?php
          }
          if ($hasGamesTab) {
              $gamesActive = $pageView === 'games';
              ?>
      <a href="<?php echo k2_h(amiga_tournament_games_url($id)); ?>" class="k2-player-nav__btn<?php echo $gamesActive ? ' is-active' : ''; ?>"<?php
              echo $gamesActive ? ' aria-current="page"' : '';
              ?>>Games</a>
              <?php
          }
          $eventStatsNav();
      }
      ?>

    </div>

  </div>

</nav>

<?php if ($isWorldCupEvent && $pageView === 'stages' && ($hasLeagueStandingsNav || $hasBracket)) { ?>

<nav class="k2-amiga-tournament-nav k2-amiga-tournament-stages-nav k2-player-nav-bar" aria-label="Tournament stages">

  <div class="k2-player-nav k2-nav-pills">

    <div class="k2-player-nav__links">

      <?php $stagesSubNav(); ?>

    </div>

  </div>

</nav>

<?php } ?>

<?php } ?>



<div class="k2-amiga-tournament-body">

<?php if ($pageView === 'games') { ?>

<section class="k2-amiga-tournament-games" aria-labelledby="k2-amiga-tournament-games-heading">

  <h2 id="k2-amiga-tournament-games-heading" class="k2-panel-heading" style="margin:0 1.25rem 0.75rem">Games</h2>

  <?php if (!$hasGamesTab) { ?>

  <p class="k2-amiga-tournament-empty">No games recorded for this event yet.</p>

  <?php } else {

      $playerChoices = [['value' => '0', 'label' => 'All players']];

      foreach ($tournamentGamePlayerChoices as $choice) {

          $playerChoices[] = [

              'value' => (string) (int) $choice['player_id'],

              'label' => (string) $choice['player_name'] . ' (' . (int) $choice['games'] . ')',

          ];

      }

      ?>

  <form class="k2-player-games-controls" method="get" action="/amiga/tournament.php" style="margin:0 1.25rem 0.75rem">

    <input type="hidden" name="id" value="<?php echo (int) $id; ?>" />

    <input type="hidden" name="view" value="games" />

    <div class="k2-player-games-controls__field">

      <span class="server-period-activity-leaderboard__picker-label">Player</span>

      <?php k2_archive_listbox_render(

          'player',

          'k2-tournament-games-player',

          (string) $tournamentGamesPlayerFilter,

          $playerChoices,

          'Filter by player',

      ); ?>

    </div>

    <a class="k2-player-games-action" href="<?php echo k2_h(amiga_tournament_games_url($id)); ?>">Reset</a>

  </form>

  <p style="margin:0 1.25rem 0.75rem;color:var(--k2-text-secondary)"><?php

      echo (int) count($tournamentGamesRows) . ' game' . (count($tournamentGamesRows) === 1 ? '' : 's');

      if ($tournamentGamesPlayerFilter > 0) {

          echo ' for selected player';

      }

  ?>.</p>

  <?php

      amiga_tournament_render_games_table($tournamentGamesRows);

  } ?>

</section>

<?php } elseif ($pageView === 'event-stats') { ?>

<section class="k2-amiga-tournament-event-stats" aria-labelledby="k2-amiga-event-stats-heading">

  <h2 id="k2-amiga-event-stats-heading" class="k2-panel-heading" style="margin:0 1.25rem 0.75rem">Event stats</h2>

  <p style="margin:0 1.25rem 0.75rem;color:var(--k2-text-secondary)">Per-player totals across all phases in this event.</p>

  <?php if ($eventStatsRows === []) { ?>

  <p class="k2-amiga-tournament-empty">No participation rows for this event yet.</p>

  <?php } else {

      amiga_tournament_render_event_stats_table($eventStatsRows, $isWorldCupEvent);

  } ?>

</section>

<?php } elseif ($isStagesContentView) { ?>

<?php if ($hasBracket) {

    amiga_tournament_render_bracket($bracketData, $isKnockoutView ? $scopeKey : '');

} ?>



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

<section class="k2-amiga-tournament-fixture" aria-labelledby="k2-amiga-fixture-heading">

  <h2 id="k2-amiga-fixture-heading" class="k2-panel-heading" style="margin:0 1.25rem 0.75rem"><?php

      echo k2_h($knockoutScopeLabels[$scopeKey] ?? $scopeLabel);

  ?></h2>

  <div class="k2-table-wrap" style="margin:0 1.25rem">

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

</section>

<?php } ?>



<?php if (!$isKnockoutView && $rows === [] && $formatKind === 'league') { ?>

<p class="k2-amiga-tournament-empty">No derived standings for this scope yet. Rebuild with <code>python -m scripts.amiga replay</code>.</p>

<?php } elseif (!$isKnockoutView && $rows === [] && !$hasLeagueStandingsNav && !$hasBracket) { ?>

<p class="k2-amiga-tournament-empty">Informal round-robin — league standings will appear here when available.</p>

<?php } ?>



<?php if (!$isKnockoutView || $rows !== []) { ?>

<div class="k2-table-wrap">

<table class="k2-table k2-table--numeric-default k2-table--calm-stats" data-k2-table="sortable" data-k2-autorank="false">

<thead>

    <tr>

        <th data-k2-sort="number"><?php echo $isKnockoutView ? ' ' : 'Pos'; ?></th>

        <th class="k2-table-cell--left" data-k2-sort="text">Player</th>

        <th data-k2-sort="number">G</th>

        <th data-k2-sort="number">W</th>

        <th data-k2-sort="number">D</th>

        <th data-k2-sort="number">L</th>

        <th data-k2-sort="number">GF</th>

        <th data-k2-sort="number">GA</th>

        <th data-k2-sort="number">GD</th>

        <?php if (!$isKnockoutView) { ?>

        <th data-k2-sort="number">Pts</th>

        <?php } ?>

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

        <td><?php echo (int) $row['games']; ?></td>

        <td><?php echo (int) $row['wins']; ?></td>

        <td><?php echo (int) $row['draws']; ?></td>

        <td><?php echo (int) $row['losses']; ?></td>

        <td><?php echo (int) $row['goals_for']; ?></td>

        <td><?php echo (int) $row['goals_against']; ?></td>

        <td><?php echo $gd > 0 ? '+' . $gd : (string) $gd; ?></td>

        <?php if (!$isKnockoutView) { ?>

        <td><?php echo (int) $row['points']; ?></td>

        <?php } ?>

    </tr>

<?php } ?>

</tbody>

</table>

</div>

<?php } ?>



<p style="padding:0 1.25rem 0;color:var(--k2-text-secondary)">

  <?php if ($isKnockoutView) { ?>

  Per-leg scores above; aggregate table below. Winner by total goal difference (penalties in <code>extra</code> when aggregate is tied).

  <?php } else { ?>

  Standings derived from match results (3 pts win, 1 draw). Rebuilt by <code>python -m scripts.amiga replay</code>.

  <?php } ?>

</p>

<?php } ?>

</div><!-- .k2-amiga-tournament-body -->

</div><!-- .k2-page-nav -->

</body>

</html>
