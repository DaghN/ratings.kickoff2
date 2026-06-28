<?php

declare(strict_types=1);

/**
 * WC Videos tab — games table + extras list (TV-3 spotlight slice).
 */

/** @param list<array{game_id: int, youtube_id: string, video: array<string, mixed>, game: array<string, mixed>, sort_bucket: int}> $entries */
function amiga_tournament_videos_render_wc_mode_nav(
    int $tournamentId,
    string $activeMode,
    bool $hasAtmosphereWing,
    bool $hasGamesWing = true,
): void {
    if (!$hasGamesWing && !$hasAtmosphereWing) {
        return;
    }
    $gamesHref = amiga_tournament_href(amiga_tournament_videos_url($tournamentId, 'games'));
    $atmosphereHref = amiga_tournament_href(amiga_tournament_videos_url($tournamentId, 'atmosphere'));
    ?>
<div class="k2-chrome-tabs k2-tournament-videos-wings">
  <nav class="k2-chrome-tabs__bar" data-k2-carry-scroll aria-label="Video sections">
    <?php if ($hasGamesWing) { ?>
    <a href="<?php echo k2_h($gamesHref); ?>" class="k2-chrome-tabs__tab<?php echo $activeMode === 'games' ? ' is-active' : ''; ?>"<?php
        echo $activeMode === 'games' ? ' aria-current="page"' : '';
    ?>>Games</a>
    <?php } ?>
    <?php if ($hasAtmosphereWing) { ?>
    <a href="<?php echo k2_h($atmosphereHref); ?>" class="k2-chrome-tabs__tab<?php echo $activeMode === 'atmosphere' ? ' is-active' : ''; ?>"<?php
        echo $activeMode === 'atmosphere' ? ' aria-current="page"' : '';
    ?>>Atmosphere</a>
    <?php } ?>
  </nav>
</div>
    <?php
}

/**
 * @param list<array{game_id: int, youtube_id: string, video: array<string, mixed>, game: array<string, mixed>, sort_bucket: int}> $entries
 * @param array{game_id: int, youtube_id: string, video: array<string, mixed>, game: array<string, mixed>, sort_bucket: int}|null $spotlightEntry
 */
function amiga_tournament_videos_render_wc_games_table(
    int $tournamentId,
    string $mode,
    array $entries,
    ?array $spotlightEntry,
    string $spotlightYoutube,
    bool $highlightRow,
): void {
    require_once __DIR__ . '/k2_table_helpers.php';
    require_once __DIR__ . '/k2_rated_game_row.php';
    require_once __DIR__ . '/k2_player_game_row.php';
    require_once __DIR__ . '/amiga_rated_game_row.php';
    require_once __DIR__ . '/k2_amiga_country_flag.php';

    $showPhase = false;
    $showFlags = false;
    foreach ($entries as $entry) {
        if (trim((string) ($entry['game']['phase'] ?? '')) !== '') {
            $showPhase = true;
        }
        if (trim((string) ($entry['game']['country_a'] ?? '')) !== '' || trim((string) ($entry['game']['country_b'] ?? '')) !== '') {
            $showFlags = true;
        }
    }

    $spotlightGameId = (int) ($spotlightEntry['game_id'] ?? 0);
    $spotlightYoutube = $spotlightYoutube !== '' ? $spotlightYoutube : (string) ($spotlightEntry['youtube_id'] ?? '');
    ?>
<?php k2_table_wrap_open(true); ?>
<table class="k2-table k2-table--tournament-games k2-table--tournament-videos-games">
  <thead>
    <tr>
      <th class="k2-table-cell--left">ID</th>
      <?php if ($showPhase) { ?><th class="k2-table-cell--left">Phase</th><?php } ?>
      <th class="k2-table-cell--right">Player A</th>
      <th>A</th>
      <th class="k2-table-cell--left">B</th>
      <th class="k2-table-cell--left">Player B</th>
      <th class="k2-table-cell--pad-left-md" data-k2-help="Player A's Elo rating before this game.">Rating A</th>
      <th data-k2-help="Player B's Elo rating before this game.">Rating B</th>
      <th class="k2-table-cell--center"><span class="visually-hidden">Play video</span></th>
    </tr>
  </thead>
  <tbody class="black">
  <?php if ($entries === []) { ?>
    <tr><td colspan="<?php echo $showPhase ? 9 : 8; ?>" class="k2-table-cell--left" style="color:var(--k2-text-secondary)">No game videos linked for this event yet.</td></tr>
  <?php } ?>
  <?php foreach ($entries as $entry) {
      $gameRow = $entry['game'];
      $game = k2_player_game_normalize_row($gameRow);
      $processed = k2_rated_game_is_processed($gameRow);
      $phase = trim((string) ($gameRow['phase'] ?? ''));
      $countryA = trim((string) ($gameRow['country_a'] ?? ''));
      $countryB = trim((string) ($gameRow['country_b'] ?? ''));
      $dash = k2_fmt_dash();
      $goalsA = (int) $game['GoalsA'];
      $goalsB = (int) $game['GoalsB'];
      if ($processed) {
          $aWin = k2_rated_game_is_a_win($game);
          $bWin = k2_rated_game_is_b_win($game);
      } else {
          $aWin = $goalsA > $goalsB;
          $bWin = $goalsB > $goalsA;
      }
      if ($processed) {
          $ratingACell = (string) (int) round((float) $game['RatingA']);
          $ratingBCell = (string) (int) round((float) $game['RatingB']);
      } else {
          $ratingACell = $dash;
          $ratingBCell = $dash;
      }
      $flagA = $showFlags && $countryA !== '' ? k2_amiga_country_flag_link($countryA, ['class' => 'k2-amiga-tgame-flag']) : '';
      $flagB = $showFlags && $countryB !== '' ? k2_amiga_country_flag_link($countryB, ['class' => 'k2-amiga-tgame-flag']) : '';
      $teamACell = '<span class="k2-amiga-tgame-side k2-amiga-tgame-side--a">' . $flagA
          . k2_amiga_player_link((int) $game['idA'], (string) $game['NameA']) . '</span>';
      $teamBCell = '<span class="k2-amiga-tgame-side k2-amiga-tgame-side--b">'
          . k2_amiga_player_link((int) $game['idB'], (string) $game['NameB']) . $flagB . '</span>';
      $goalsAClass = $aWin ? 'k2-amiga-tgame-goal--win' : '';
      $goalsBClass = 'k2-table-cell--left' . ($bWin ? ' k2-amiga-tgame-goal--win' : '');
      $goalsACell = $aWin ? '<span class="blue">' . $goalsA . '</span>' : (string) $goalsA;
      $goalsBCell = $bWin ? '<span class="blue">' . $goalsB . '</span>' : (string) $goalsB;
      $spotlightLabel = amiga_tournament_videos_wc_game_spotlight_label($entry);
      $spotlightHtml = amiga_tournament_videos_wc_game_caption_html($entry);
      $isActive = $highlightRow
          && $spotlightGameId === (int) $entry['game_id']
          && $spotlightYoutube === (string) $entry['youtube_id'];
      $rowClass = $isActive ? ' class="is-active"' : '';
      ?>
    <tr<?php echo $rowClass; ?>>
      <td class="k2-table-cell--left"><?php echo amiga_rated_game_id_html((int) $game['id']); ?></td>
      <?php if ($showPhase) { ?>
      <td class="k2-table-cell--left"><?php echo $phase !== '' ? k2_h($phase) : $dash; ?></td>
      <?php } ?>
      <td class="k2-table-cell--right k2-amiga-tgame-team k2-amiga-tgame-team--a"><?php echo $teamACell; ?></td>
      <td class="<?php echo k2_h($goalsAClass); ?>"><?php echo $goalsACell; ?></td>
      <td class="<?php echo k2_h($goalsBClass); ?>"><?php echo $goalsBCell; ?></td>
      <td class="k2-table-cell--left k2-amiga-tgame-team k2-amiga-tgame-team--b"><?php echo $teamBCell; ?></td>
      <td class="k2-table-cell--pad-left-md"><?php echo $ratingACell; ?></td>
      <td><?php echo $ratingBCell; ?></td>
      <td class="k2-table-cell--center"><?php
          echo amiga_tournament_videos_play_button_html(
              $tournamentId,
              $mode,
              (string) $entry['youtube_id'],
              $spotlightLabel,
              $isActive,
              (int) $entry['game_id'],
              0,
              $spotlightHtml,
          );
      ?></td>
    </tr>
  <?php } ?>
  </tbody>
</table>
<?php k2_table_wrap_close(); ?>
    <?php
}

/** @param list<array<string, mixed>> $rows */
function amiga_tournament_videos_render_wc_extras_table(
    int $tournamentId,
    string $mode,
    array $rows,
    string $spotlightYoutube,
    bool $highlightRow,
): void {
    require_once __DIR__ . '/k2_table_helpers.php';
    ?>
<?php k2_table_wrap_open(true); ?>
<table class="k2-table k2-table--tournament-videos-extras">
  <thead>
    <tr>
      <th class="k2-table-cell--left">Title</th>
      <th class="k2-table-cell--left">Duration</th>
      <th class="k2-table-cell--center"><span class="visually-hidden">Play video</span></th>
    </tr>
  </thead>
  <tbody class="black">
  <?php if ($rows === []) { ?>
    <tr><td colspan="3" class="k2-table-cell--left" style="color:var(--k2-text-secondary)">No atmosphere videos for this event.</td></tr>
  <?php } ?>
  <?php foreach ($rows as $row) {
      $yt = (string) ($row['youtube_id'] ?? '');
      if ($yt === '') {
          continue;
      }
      $kind = (string) ($row['kind'] ?? '');
      $isStream = $kind === 'stream' || ($kind === 'atmosphere' && (int) ($row['duration_sec'] ?? 0) > 600);
      $duration = amiga_tournament_video_format_duration(
          isset($row['duration_sec']) ? (int) $row['duration_sec'] : null,
      );
      if ($isStream && $duration !== '') {
          $duration = 'Long coverage · ' . $duration;
      }
      $spotlightLabel = amiga_tournament_videos_extra_spotlight_label($row);
      $isActive = $highlightRow && $spotlightYoutube === $yt;
      ?>
    <tr<?php echo $isActive ? ' class="is-active"' : ''; ?>>
      <td class="k2-table-cell--left"><?php echo k2_h((string) ($row['title'] ?? 'Video')); ?></td>
      <td class="k2-table-cell--left"><?php echo $duration !== '' ? k2_h($duration) : k2_fmt_dash(); ?></td>
      <td class="k2-table-cell--center"><?php
          echo amiga_tournament_videos_play_button_html(
              $tournamentId,
              $mode,
              $yt,
              $spotlightLabel,
              $isActive,
              null,
              0,
          );
      ?></td>
    </tr>
  <?php } ?>
  </tbody>
</table>
<?php k2_table_wrap_close(); ?>
    <?php
}

function amiga_tournament_videos_render_spotlight(string $youtubeId, string $label, int $startSec = 0, string $indexUrl = '', string $labelHtml = ''): void
{
    $hasVideo = $youtubeId !== '';
    $embedUrl = $hasVideo ? amiga_tournament_video_embed_url($youtubeId, $startSec) : '';
    $emptyClass = $hasVideo ? '' : ' k2-tournament-videos__spotlight--empty';
    $backHref = $indexUrl !== '' ? $indexUrl : '#';
    ?>
<div class="k2-tournament-videos__spotlight<?php echo $emptyClass; ?>" id="<?php echo k2_h(AMIGA_TOURNAMENT_VIDEOS_PLAYER_FRAGMENT); ?>"<?php
    echo $hasVideo ? '' : ' hidden';
?>>
  <div class="k2-tournament-videos__spotlight-head">
    <div class="k2-tournament-videos__spotlight-label"><?php echo $labelHtml !== '' ? $labelHtml : k2_h($label); ?></div>
  </div>
  <div class="k2-tournament-videos__player-row">
  <div class="k2-game-page__video-wrap">
    <div class="k2-game-page__video">
      <iframe
        class="k2-game-page__video-iframe k2-tournament-videos__spotlight-iframe"<?php
        if ($embedUrl !== '') {
            echo ' src="' . k2_h($embedUrl) . '"';
        }
        ?>
        title="<?php echo k2_h($label !== '' ? $label : 'Tournament video'); ?>"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
        referrerpolicy="strict-origin-when-cross-origin"
        allowfullscreen
      ></iframe>
    </div>
  </div>
  <a class="k2-tournament-videos__back" data-k2-tv-back="1" href="<?php echo k2_h($backHref); ?>">&#8593; All videos</a>
  </div>
</div>
    <?php
}