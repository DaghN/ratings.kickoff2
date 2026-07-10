<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav_lib.php';
amiga_snapshot_redirect_present_only_page();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga — Live tournament</title>
<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="/stylesheets/amiga-tournament.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/amiga-tournament.css'); ?>" rel="stylesheet" type="text/css" />
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; ?>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_running_tournament_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_rated_game_row.php';
include __DIR__ . '/../../config/ko2amiga_config.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    http_response_code(404);
    exit('Live tournament not found.');
}

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");

$tournament = amiga_live_tournament_load($con, $id);
if ($tournament === null) {
    mysqli_close($con);
    http_response_code(404);
    exit('Live tournament not found.');
}

$participants = amiga_live_tournament_participants($con, $id);
$leagueTable = amiga_live_tournament_league_table_rows($con, $id);
$knockoutScopes = amiga_tournament_list_scopes($con, $id, 'knockout');
$bracketData = [
    'main' => [],
    'placement_final' => [],
    'placement_bracket' => [],
];
if ($knockoutScopes !== []) {
    $bracketData = amiga_tournament_knockout_bracket_data($con, $id, $knockoutScopes);
}
$fixtureGroups = amiga_live_tournament_fixture_groups($con, $id);
$liveGameCount = amiga_tournament_game_count($con, $id);
mysqli_close($con);

$k2AmigaHubTabActive = 'live-tournaments';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';

$tName = (string) $tournament['name'];

$k2TournamentHeroSummary = [
    'id' => $id,
    'name' => $tName,
    'country' => trim((string) ($tournament['country'] ?? '')),
    'event_date' => $tournament['event_date'] ?? null,
    'player_count' => (int) ($tournament['player_count'] ?? 0),
    'game_count' => $liveGameCount,
];
$k2TournamentHeroWinner = null;
$k2TournamentHeroBadges = [
    (string) ($tournament['lifecycle_status'] ?? 'live'),
    'Live view',
];
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_hero.php';
?>

<p class="k2-amiga-tournament-back">
  <a class="k2-link-star" href="/amiga/live-tournaments.php">← Live tournaments</a>
</p>

<div class="k2-amiga-live-view">

<?php if ($participants !== []) { ?>
<section class="k2-amiga-live-view__section" aria-labelledby="k2-amiga-live-players-heading">
  <h2 id="k2-amiga-live-players-heading" class="k2-panel-heading">Players</h2>
  <div class="k2-table-wrap">
    <table class="k2-table k2-table--numeric-default k2-table--calm-stats">
      <thead>
        <tr>
          <th data-k2-sort="number">Seed</th>
          <th class="k2-table-cell--left">Player</th>
          <th data-k2-sort="text">Country</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($participants as $row) { ?>
        <tr>
          <td><?php echo $row['seed_no'] !== null ? (int) $row['seed_no'] : '—'; ?></td>
          <td class="k2-table-cell--left"><?php
              echo k2_amiga_player_link((int) $row['player_id'], (string) $row['player_name']);
          ?></td>
          <td><?php echo !empty($row['country']) ? k2_h((string) $row['country']) : '—'; ?></td>
        </tr>
      <?php } ?>
      </tbody>
    </table>
  </div>
</section>
<?php } ?>

<?php if ($leagueTable !== null && $leagueTable['rows'] !== []) { ?>
<section class="k2-amiga-live-view__section k2-amiga-live-view__section--standings" aria-labelledby="k2-amiga-live-standings-heading">
  <h2 id="k2-amiga-live-standings-heading" class="k2-panel-heading">League table</h2>
  <?php if ($leagueTable['preview_note'] !== null) { ?>
    <p class="k2-amiga-tournament-empty"><?php echo k2_h((string) $leagueTable['preview_note']); ?></p>
  <?php } ?>
  <?php amiga_tournament_render_standings_table($leagueTable['rows'], false); ?>
</section>
<?php } ?>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_bracket.php';
amiga_tournament_render_bracket($bracketData);
?>

<?php foreach ($fixtureGroups as $group) {
    $stage = $group['stage'];
    $fixtures = $group['fixtures'];
    $stageHeading = (string) $stage['name'];
    if ((string) $stage['stage_key'] !== '' && (string) $stage['stage_key'] !== $stageHeading) {
        $stageHeading .= ' (' . (string) $stage['stage_key'] . ')';
    }
    ?>
<section class="k2-amiga-live-view__section" aria-labelledby="k2-amiga-live-stage-<?php echo (int) $stage['id']; ?>">
  <h2 id="k2-amiga-live-stage-<?php echo (int) $stage['id']; ?>" class="k2-panel-heading">
    <?php echo k2_h($stageHeading); ?>
    <span class="k2-amiga-live-view__stage-type"><?php echo k2_h((string) $stage['stage_type']); ?></span>
  </h2>
  <div class="k2-table-wrap">
    <table class="k2-table k2-table--numeric-default k2-table--calm-stats">
      <thead>
        <tr>
          <th class="k2-table-cell--left">Fixture</th>
          <th class="k2-table-cell--left">Phase</th>
          <th class="k2-table-cell--left">Home</th>
          <th>Score</th>
          <th class="k2-table-cell--left">Away</th>
          <th data-k2-sort="text">Status</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($fixtures as $fixture) {
          $status = (string) $fixture['status'];
          $rowClass = $status === 'void' ? ' k2-amiga-live-view__row--void' : '';
          $playerAId = $fixture['player_a_id'] !== null ? (int) $fixture['player_a_id'] : null;
          $playerBId = $fixture['player_b_id'] !== null ? (int) $fixture['player_b_id'] : null;
          ?>
        <tr class="<?php echo trim($rowClass); ?>">
          <td class="k2-table-cell--left"><?php echo k2_h((string) $fixture['fixture_key']); ?></td>
          <td class="k2-table-cell--left"><?php
              $phase = (string) ($fixture['phase_label'] ?? '');
              echo $phase !== '' ? k2_h($phase) : '—';
          ?></td>
          <td class="k2-table-cell--left"><?php
              echo amiga_live_tournament_format_player_slot(
                  $playerAId,
                  $fixture['player_a_name'] !== null ? (string) $fixture['player_a_name'] : null
              );
          ?></td>
          <td><?php
              if ($status === 'played' && amiga_running_tournament_fixture_has_result($fixture)) {
                  echo k2_rated_game_scoreline_html((int) $fixture['goals_a'], (int) $fixture['goals_b']);
                  echo amiga_tournament_format_game_extra(
                      isset($fixture['extra']) ? (string) $fixture['extra'] : null
                  );
              } elseif ($status === 'void') {
                  echo '<span class="k2-amiga-live-view__void-label">void</span>';
              } else {
                  echo '—';
              }
          ?></td>
          <td class="k2-table-cell--left"><?php
              echo amiga_live_tournament_format_player_slot(
                  $playerBId,
                  $fixture['player_b_name'] !== null ? (string) $fixture['player_b_name'] : null
              );
          ?></td>
          <td><span class="k2-amiga-tournament-badge<?php echo $status === 'void' ? ' k2-amiga-live-view__badge--void' : ''; ?>"><?php
              echo k2_h($status);
          ?></span></td>
        </tr>
      <?php } ?>
      </tbody>
    </table>
  </div>
</section>
<?php } ?>

<?php if ($fixtureGroups === []) { ?>
<p class="k2-amiga-tournament-empty">No fixtures scheduled for this event yet.</p>
<?php } ?>

</div><!-- .k2-amiga-live-view -->

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_site_end.inc.php'; ?>
</body>
</html>
