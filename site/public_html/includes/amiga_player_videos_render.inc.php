<?php

declare(strict_types=1);

require_once __DIR__ . '/amiga_player_videos_lib.php';
require_once __DIR__ . '/amiga_tournament_videos_wc_render.inc.php';
require_once __DIR__ . '/k2_archive_listbox.php';
require_once __DIR__ . '/amiga_tournament_lib.php';
require_once __DIR__ . '/k2_table_helpers.php';
require_once __DIR__ . '/k2_rated_game_row.php';
require_once __DIR__ . '/k2_player_game_row.php';
require_once __DIR__ . '/amiga_rated_game_row.php';
require_once __DIR__ . '/k2_amiga_country_flag.php';

/**
 * @param list<array{game_id: int, youtube_id: string, video: array<string, mixed>, game: array<string, mixed>, tournament_id: int, tournament_name: string, sort_ts: int}> $entries
 * @param array{game_id: int, youtube_id: string, video: array<string, mixed>, game: array<string, mixed>, tournament_id: int, tournament_name: string, sort_ts: int}|null $spotlightEntry
 */
function amiga_player_videos_render_games_table(
    int $playerId,
    array $entries,
    ?array $spotlightEntry,
    string $spotlightYoutube,
    bool $highlightRow,
    int $opponentFilter = 0,
): void {
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
    $colspan = 11 + ($showPhase ? 1 : 0);
    ?>
<?php k2_table_wrap_open(true); ?>
<table class="k2-table k2-table--tournament-games k2-table--tournament-videos-games k2-table--player-videos">
  <thead>
    <tr>
      <th class="k2-table-cell--left">ID</th>
      <th class="k2-table-cell--left">Date</th>
      <th class="k2-table-cell--left">Tournament</th>
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
    <tr><td colspan="<?php echo $colspan; ?>" class="k2-table-cell--left" style="color:var(--k2-text-secondary)">No game videos linked for this player yet.</td></tr>
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
      $flagA = $showFlags && $countryA !== '' ? k2_amiga_country_flag_link($countryA) : '';
      $flagB = $showFlags && $countryB !== '' ? k2_amiga_country_flag_link($countryB) : '';
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
      $tid = (int) ($entry['tournament_id'] ?? 0);
      $tName = trim((string) ($entry['tournament_name'] ?? ''));
      $tHost = trim((string) ($entry['tournament_country'] ?? ''));
      $tournamentCell = $tid > 0 && $tName !== ''
          ? k2_amiga_lb_tournament_cell($tid, $tName, $tHost)
          : $dash;
      ?>
    <tr<?php echo $rowClass; ?>>
      <td class="k2-table-cell--left"><?php echo amiga_rated_game_id_html((int) $game['id']); ?></td>
      <td class="k2-table-cell--left k2-table-cell--pad-left-xs k2-amiga-player-games-date"><?php echo amiga_player_game_date_html((string) ($gameRow['Date'] ?? '')); ?></td>
      <td class="k2-table-cell--left"><?php echo $tournamentCell; ?></td>
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
          echo amiga_player_videos_play_button_html(
              $playerId,
              (string) $entry['youtube_id'],
              $spotlightLabel,
              $isActive,
              (int) $entry['game_id'],
              0,
              $spotlightHtml,
              $opponentFilter,
          );
      ?></td>
    </tr>
  <?php } ?>
  </tbody>
</table>
<?php k2_table_wrap_close(); ?>
    <?php
}

/**
 * @param list<array{value: string, label: string, meta: string}> $opponentChoices
 */
function amiga_player_videos_render_opponent_filter(
    int $playerId,
    int $opponentFilter,
    array $opponentChoices,
): void {
    if (count($opponentChoices) <= 1) {
        return;
    }
    ?>
<form class="k2-player-games-controls" method="get" action="<?php echo k2_h(k2_amiga_route('amiga-player-videos')); ?>" data-k2-carry-scroll>
  <div class="k2-player-games-controls__meta">
    <input type="hidden" name="id" value="<?php echo (int) $playerId; ?>" />
  </div>
  <div class="k2-player-games-controls__fields k2-amiga-player-games-filter-row">
    <div class="k2-player-games-controls__field">
      <span class="server-period-activity-leaderboard__picker-label">Opponent</span>
      <?php k2_archive_listbox_render('opponent', 'k2-player-videos-opponent', (string) $opponentFilter, $opponentChoices, 'Filter by opponent', '', '', false, '0'); ?>
    </div>
  </div>
</form>
    <?php
}

/**
 * @param list<array{game_id: int, youtube_id: string, video: array<string, mixed>, game: array<string, mixed>, tournament_id: int, tournament_name: string, sort_ts: int}> $entries
 * @param array{game_id: int, youtube_id: string, video: array<string, mixed>, game: array<string, mixed>, tournament_id: int, tournament_name: string, sort_ts: int}|null $spotlightEntry
 */
function amiga_player_videos_render_body(
    int $playerId,
    array $entries,
    ?array $spotlightEntry,
    string $spotlightLabel,
    string $spotlightYoutube,
    int $spotlightStartSec,
    bool $highlightRow,
    string $indexUrl,
    int $opponentFilter = 0,
    array $opponentChoices = [],
): void {
    $spotlightLabelHtml = ($spotlightEntry !== null && (int) ($spotlightEntry['game_id'] ?? 0) > 0)
        ? amiga_tournament_videos_wc_game_caption_html($spotlightEntry)
        : '';
    ?>
<section
  class="k2-tournament-videos k2-tournament-videos--wc k2-player-videos"
  aria-label="Videos"
  data-k2-tv-player-id="<?php echo (int) $playerId; ?>"
  data-k2-tv-index-url="<?php echo k2_h($indexUrl); ?>"
  data-k2-tv-table=".k2-table--player-videos"
>
  <?php amiga_player_videos_render_opponent_filter($playerId, $opponentFilter, $opponentChoices); ?>

  <?php amiga_player_videos_render_games_table(
      $playerId,
      $entries,
      $spotlightEntry,
      $spotlightYoutube,
      $highlightRow,
      $opponentFilter,
  ); ?>

  <?php amiga_tournament_videos_render_spotlight(
      $spotlightYoutube,
      $spotlightLabel,
      $spotlightStartSec,
      $indexUrl,
      $spotlightLabelHtml,
  ); ?>
</section>
    <?php
}