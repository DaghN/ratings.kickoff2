<?php

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_lib.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';

$tournamentPageId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$tournamentScopeType = isset($_GET['scope']) ? (string) $_GET['scope'] : 'league';

$tournamentScopeKey = isset($_GET['scope_key']) ? (string) $_GET['scope_key'] : '';

$k2AmigaTournamentView = $k2AmigaTournamentView ?? 'event-stats';
$tournamentPageView = $k2AmigaTournamentView;

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

    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_step_href.php';
    $stepSnapIntent = amiga_tournament_step_nav_intent_from_request(
        $tournamentCanonicalScope['scope_type'],
        $tournamentCanonicalScope['scope_key'],
        $tournamentPageView,
        isset($k2AmigaTournamentVideosMode) && is_string($k2AmigaTournamentVideosMode)
            ? $k2AmigaTournamentVideosMode
            : null,
    );
    amiga_tournament_apply_step_filter_snap_redirect($tournamentDb, $tournamentPageId, $stepSnapIntent);

    if ($tournamentPageView === 'videos') {
        $requestedVideosMode = isset($k2AmigaTournamentVideosMode) && is_string($k2AmigaTournamentVideosMode)
            ? $k2AmigaTournamentVideosMode
            : amiga_tournament_videos_mode_from_request();
        amiga_tournament_videos_apply_mode_redirect_from_db(
            $tournamentDb,
            $tournamentPageId,
            $requestedVideosMode,
            $_GET,
        );
    }

}

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">

<head>

<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<title>Amiga tournament<?php

echo match ($tournamentPageView) {
    'event-stats' => ' — Event stats',
    'games' => ' — Games',
    'videos' => ' — Videos',
    'stages', 'standings' => ' — Stages',
    default => '',
};

?></title>

<?php
// Cold/full loads of a video deep link (?v=…) should land on the player. Declare
// the pre-paint scroll target for k2_carry_scroll_restore.php (handles hashless
// shared links and reloads); in-session picks scroll via amiga-tournament-videos.js.
$k2ScrollTargetId = ($tournamentPageView === 'videos' && isset($_GET['v']) && (string) $_GET['v'] !== '')
    ? 'k2-tournament-video-player'
    : '';
// A link to one knockout tie lands on its card in the bracket (the legs are in the card).
if ($k2ScrollTargetId === '' && $tournamentScopeType === 'knockout' && $tournamentScopeKey !== '') {
    $k2ScrollTargetId = amiga_tournament_tie_card_id($tournamentScopeKey);
}
?>
<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>

<link href="/stylesheets/amiga-tournament.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/amiga-tournament.css'); ?>" rel="stylesheet" type="text/css" />

<?php if ($tournamentPageView === 'videos') { ?>
<link href="/stylesheets/amiga-tournament-videos.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/amiga-tournament-videos.css'); ?>" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="/js/amiga-tournament-videos.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/amiga-tournament-videos.js'); ?>" defer="defer"></script>
<?php } ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; ?>

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

$needsStandingsRows = $pageView === 'stages';

$needsParticipationRows = $pageView === 'event-stats';

$hasImplicitLeagueTable = amiga_tournament_has_implicit_league_table($con, $id);

$hasEventStatsTab = $needsParticipationRows
    ? true
    : amiga_tournament_has_participation($con, $id);

$implicitLeagueRows = [];

$rows = [];

if ($needsStandingsRows) {

    $implicitLeagueRows = $hasImplicitLeagueTable
        ? amiga_tournament_standings_rows($con, $id, 'league', '')
        : [];

    $rows = amiga_tournament_standings_rows($con, $id, $scopeType, $scopeKey);

}


if ($needsStandingsRows) {

    if ($scopeType === 'league' && $scopeKey === '' && $rows === [] && $leagueLabeledScopes !== []) {

        $scopeKey = $leagueLabeledScopes[0];

        $rows = amiga_tournament_standings_rows($con, $id, 'league', $scopeKey);

    } elseif ($scopeType === 'league' && $scopeKey === '' && $rows === [] && $knockoutScopes !== []) {

        $scopeType = 'knockout';

        $scopeKey = amiga_tournament_knockout_landing_scope($knockoutScopes);

        $rows = amiga_tournament_standings_rows($con, $id, $scopeType, $scopeKey);

    }

}

// A group's games, listed under its table (loaded here: the connection closes before the page is drawn).
$groupGames = [];
if ($needsStandingsRows && $scopeType === 'league' && $scopeKey !== '' && $rows !== []) {
    $groupGameIds = amiga_tournament_group_game_ids($con, $id, $scopeKey);
    $groupGames = array_values(array_filter(
        amiga_tournament_games_rows($con, $id),
        static fn (array $g): bool => isset($groupGameIds[(int) $g['id']])
    ));
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
$showLeagueTableTab = $needsStandingsRows ? $implicitLeagueRows !== [] : $hasImplicitLeagueTable;
$hasLeagueStandingsNav = $showLeagueTableTab || $leagueLabeledScopes !== [];



$eventStatsRows = $needsParticipationRows ? amiga_tournament_participation_rows($con, $id) : [];

$tournamentGameCount = amiga_tournament_game_count($con, $id);

$tournamentWinner = amiga_tournament_winner($con, $id);

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

$hasStagesTab = $hasLeagueStandingsNav || $hasBracket;

$stagesEntryUrl = amiga_tournament_href(amiga_tournament_stages_entry_url($id, $showLeagueTableTab, $leagueLabeledScopes, $hasBracket));

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_videos_lib.php';

$hasVideosTab = amiga_tournament_has_videos($id);

$tournamentVideosRows = [];

$tournamentVideosMode = 'games';

$tournamentVideosGameEntries = [];

$tournamentVideosExtrasRows = [];

$tournamentVideosHasGamesWing = false;

$tournamentVideosHasExtrasWing = false;

$tournamentVideosSpotlight = null;

$tournamentVideosSpotlightLabel = '';

$tournamentVideosSpotlightYoutube = '';

$tournamentVideosSpotlightStartSec = 0;

$tournamentVideosHighlightRow = false;

$tournamentVideosIndexUrl = '';

if ($hasVideosTab) {

    if ($pageView === 'videos') {
        $videoWings = amiga_tournament_videos_wings_for_id($con, $id);
        $tournamentVideosRows = amiga_tournament_videos_for_id($id);
        $tournamentVideosMatchRows = $videoWings['match_rows'];
        $tournamentVideosExtrasRows = $videoWings['extras_rows'];
        $tournamentVideosGameEntries = $videoWings['game_entries'];
        $tournamentVideosHasGamesWing = $videoWings['has_games_wing'];
        $tournamentVideosHasExtrasWing = $videoWings['has_atmosphere_wing'];
        $requestedVideosMode = isset($k2AmigaTournamentVideosMode) && is_string($k2AmigaTournamentVideosMode)
            ? $k2AmigaTournamentVideosMode
            : amiga_tournament_videos_mode_from_request();
        $tournamentVideosMode = amiga_tournament_videos_resolve_mode(
            $requestedVideosMode,
            $tournamentVideosHasExtrasWing,
            $tournamentVideosHasGamesWing,
        );
        $tournamentVideosExtrasRows = amiga_tournament_videos_sort_extras($tournamentVideosExtrasRows);
        $tournamentVideosIndexUrl = amiga_tournament_href(amiga_tournament_videos_url($id, $tournamentVideosMode));
        $videoRequest = amiga_tournament_videos_wc_request_params();
        if ($tournamentVideosMode === 'games') {
            $gamesSpotlight = amiga_tournament_videos_wc_games_spotlight_state(
                $tournamentVideosGameEntries,
                $videoRequest['v'] !== '' ? $videoRequest['v'] : null,
                $videoRequest['game'] > 0 ? $videoRequest['game'] : null,
                $videoRequest['start_sec'],
            );
            $tournamentVideosSpotlight = $gamesSpotlight['entry'];
            $tournamentVideosSpotlightLabel = $gamesSpotlight['label'];
            $tournamentVideosSpotlightYoutube = $gamesSpotlight['youtube_id'];
            $tournamentVideosSpotlightStartSec = $gamesSpotlight['start_sec'];
            $tournamentVideosHighlightRow = $gamesSpotlight['highlight_row'];
        } else {
            $extrasSpotlight = amiga_tournament_videos_wc_extras_spotlight_state(
                $tournamentVideosExtrasRows,
                $videoRequest['v'] !== '' ? $videoRequest['v'] : null,
                $videoRequest['start_sec'],
            );
            $tournamentVideosSpotlight = $extrasSpotlight['row'];
            $tournamentVideosSpotlightLabel = $extrasSpotlight['label'];
            $tournamentVideosSpotlightYoutube = $extrasSpotlight['youtube_id'];
            $tournamentVideosSpotlightStartSec = $extrasSpotlight['start_sec'];
            $tournamentVideosHighlightRow = $extrasSpotlight['highlight_row'];
        }
    } else {
        $tournamentVideosRows = amiga_tournament_videos_for_id($id);
    }

}

$isStagesContentView = $pageView === 'stages';

$bracketData = [
    'main' => [],
    'placement_final' => [],
    'placement_bracket' => [],
];
if ($hasBracket && $isKnockoutView) {
    $bracketData = amiga_tournament_knockout_bracket_data($con, $id, $knockoutScopes);
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_step_href.php';

$tournamentStepNavIntent = amiga_tournament_step_nav_intent_from_request(
    $scopeType,
    $scopeKey,
    $pageView,
    $pageView === 'videos' ? $tournamentVideosMode : null,
);

?>



<?php
// A single tournament is an entity page (docs/navigation-model.md NM2): the hub
// bar is present but no pill is active. The tournament's own section nav below
// is the wayfinding. The Tournaments hub pill is active only on tournaments.php.
$k2AmigaHubTabActive = '';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';
?>

<div id="<?php echo k2_h(AMIGA_TOURNAMENT_PAGE_FRAGMENT); ?>" class="k2-amiga-tournament-page-anchor" tabindex="-1"></div>

<?php
$k2TournamentHeroSummary = [
    'id' => $id,
    'name' => $tName,
    'country' => trim((string) ($tournament['country'] ?? '')),
    'event_date' => $tournament['event_date'] ?? null,
    'player_count' => amiga_tournament_participant_count($con, $id),
    'game_count' => $tournamentGameCount,
];
$k2TournamentHeroWinner = $tournamentWinner;
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_hero.php';
?>



<?php if ($hasLeagueStandingsNav || $hasBracket || $hasGamesTab || $hasVideosTab || $hasEventStatsTab) {

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_step_nav.php';

?>

<nav class="k2-amiga-tournament-nav k2-player-nav-bar k2-amiga-tournament-nav--with-steps" data-k2-carry-scroll aria-label="Tournament sections">

  <div class="k2-player-nav k2-nav-pills">

    <div class="k2-player-nav__links">

      <?php
      $eventStatsActive = $pageView === 'event-stats';
      $eventStatsNav = static function () use ($id, $eventStatsActive, $hasEventStatsTab): void {
          if (!$hasEventStatsTab) {
              return;
          }
          ?>
      <a href="<?php echo k2_h(amiga_tournament_href(amiga_tournament_event_stats_url($id))); ?>" class="k2-player-nav__btn<?php echo $eventStatsActive ? ' is-active' : ''; ?>"<?php

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
      ): void {
          if ($showLeagueTableTab) {
              $leagueTableActive = $scopeType === 'league' && $scopeKey === '' && $pageView === 'stages';
              ?>
      <a href="<?php echo k2_h(amiga_tournament_href(amiga_tournament_standings_nav_url($id, 'league', ''))); ?>" class="k2-player-nav__btn<?php echo $leagueTableActive ? ' is-active' : ''; ?>"<?php
              echo $leagueTableActive ? ' aria-current="page"' : '';
              ?>>League table</a>
              <?php
          }
          foreach ($leagueLabeledScopes as $lk) {
              $active = $scopeType === 'league' && $scopeKey === $lk && $pageView === 'stages';
              ?>
      <a href="<?php echo k2_h(amiga_tournament_href(amiga_tournament_standings_nav_url($id, 'league', $lk))); ?>" class="k2-player-nav__btn<?php echo $active ? ' is-active' : ''; ?>"<?php
              echo $active ? ' aria-current="page"' : '';
              ?>><?php echo k2_h($lk); ?></a>
              <?php
          }
          if ($hasBracket) {
              $bracketScopeKey = ''; // the whole bracket, from the top
              ?>
      <a href="<?php echo k2_h(amiga_tournament_href(amiga_tournament_standings_nav_url($id, 'knockout', $bracketScopeKey))); ?>" class="k2-player-nav__btn<?php echo $isKnockoutView && $pageView === 'stages' ? ' is-active' : ''; ?>">Knockouts</a>
              <?php
          }
      };

      $videosNav = static function () use ($id, $pageView, $hasVideosTab): void {
          if (!$hasVideosTab) {
              return;
          }
          $videosActive = $pageView === 'videos';
          ?>
      <a href="<?php echo k2_h(amiga_tournament_href(amiga_tournament_videos_url($id))); ?>" class="k2-player-nav__btn<?php echo $videosActive ? ' is-active' : ''; ?>"<?php
          echo $videosActive ? ' aria-current="page"' : '';
      ?>>Videos</a>
      <?php
      };

      $eventStatsNav();
      if ($hasGamesTab) {
          $gamesActive = $pageView === 'games';
          ?>
      <a href="<?php echo k2_h(amiga_tournament_href(amiga_tournament_games_url($id))); ?>" class="k2-player-nav__btn<?php echo $gamesActive ? ' is-active' : ''; ?>"<?php
          echo $gamesActive ? ' aria-current="page"' : '';
          ?>>Games</a>
          <?php
      }
      if ($hasStagesTab) {
          $stagesActive = $pageView === 'stages';
          ?>
      <a href="<?php echo k2_h($stagesEntryUrl); ?>" class="k2-player-nav__btn<?php echo $stagesActive ? ' is-active' : ''; ?>"<?php
          echo $stagesActive ? ' aria-current="page"' : '';
          ?>>Stages</a>
          <?php
      }
      $videosNav();
      ?>

    </div>

  </div>

  <?php amiga_tournament_step_nav_render($con, $id, $tournamentStepNavIntent); ?>

</nav>

<?php if ($pageView === 'stages' && ($hasLeagueStandingsNav || $hasBracket)) { ?>

<nav class="k2-amiga-tournament-nav k2-amiga-tournament-stages-nav k2-player-nav-bar" data-k2-carry-scroll aria-label="Tournament stages">

  <div class="k2-player-nav k2-nav-pills">

    <div class="k2-player-nav__links">

      <?php $stagesSubNav(); ?>

    </div>

  </div>

</nav>

<?php } ?>

<?php } ?>

<?php mysqli_close($con); ?>

<div class="k2-amiga-tournament-body">

<?php if ($pageView === 'videos') { ?>

<?php include __DIR__ . '/amiga_tournament_videos_body.inc.php'; ?>

<?php } elseif ($pageView === 'games') { ?>

<section class="k2-amiga-tournament-games" aria-label="Games">

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

  <form class="k2-player-games-controls" method="get" action="/amiga/tournament/games.php" data-k2-carry-scroll>

    <div class="k2-player-games-controls__meta">

      <input type="hidden" name="id" value="<?php echo (int) $id; ?>" />

      <?php
      require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_snapshot_url.php';
      echo amiga_form_context_inputs();
      ?>

    </div>

    <div class="k2-player-games-controls__fields">

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

      <a class="k2-player-games-action" href="<?php echo k2_h(amiga_tournament_href(amiga_tournament_games_url($id))); ?>">Reset</a>

    </div>

  </form>

  <p class="k2-amiga-tournament-lede"><?php

      echo (int) count($tournamentGamesRows) . ' game' . (count($tournamentGamesRows) === 1 ? '' : 's');

      if ($tournamentGamesPlayerFilter > 0) {

          echo ' for selected player';

      }

  ?>.</p>

  <?php

      amiga_tournament_render_games_table($tournamentGamesRows, $con);

  } ?>

</section>

<?php } elseif ($pageView === 'event-stats') { ?>

<section class="k2-amiga-tournament-event-stats" aria-label="Event stats">

  <p class="k2-amiga-tournament-lede">Per-player totals across all phases in this event.</p>

  <?php if ($eventStatsRows === []) { ?>

  <p class="k2-amiga-tournament-empty">No participation rows for this event yet.</p>

  <?php } else {

      amiga_tournament_render_event_stats_table($eventStatsRows, $isWorldCupEvent);

  } ?>

</section>

<?php } elseif ($isStagesContentView) { ?>

<?php if ($hasBracket && $isKnockoutView) {

    amiga_tournament_render_bracket($bracketData, $scopeKey);

} ?>







<?php if (!$isKnockoutView && $rows === [] && $formatKind === 'league') { ?>

<p class="k2-amiga-tournament-empty">No derived standings for this scope yet. Rebuild with <code>python -m scripts.amiga replay</code>.</p>

<?php } elseif (!$isKnockoutView && $rows === [] && !$hasLeagueStandingsNav && !$hasBracket) { ?>

<p class="k2-amiga-tournament-empty">Informal round-robin — league standings will appear here when available.</p>

<?php } ?>



<?php if (!$isKnockoutView) {
    amiga_tournament_render_standings_table($rows, false);
} ?>

<?php // A group's games under its table, oldest first, with video icons (Dagh, 2026-11-23).
if ($groupGames !== []) { ?>
<section class="k2-amiga-group-games" aria-labelledby="k2-amiga-group-games-heading">
  <h2 id="k2-amiga-group-games-heading" class="k2-panel-heading">Games (<?php echo count($groupGames); ?>)</h2>
  <?php amiga_tournament_render_games_table($groupGames, null, ['phase' => false, 'request_sort' => false, 'oldest_first' => true]); ?>
</section>
<?php } ?>





<?php } ?>

</div><!-- .k2-amiga-tournament-body -->

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_site_end.inc.php'; ?>
</body>

</html>
