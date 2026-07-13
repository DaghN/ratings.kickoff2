<?php

declare(strict_types=1);

require_once __DIR__ . '/amiga_tournament_videos_lib.php';
require_once __DIR__ . '/amiga_tournament_videos_wc_render.inc.php';

/**
 * @param array<string, mixed> $gameRow
 * @param list<array{game_id: int, youtube_id: string, video: array<string, mixed>, sort: int, start_sec: int}> $videos
 */
function amiga_game_videos_render_section(
    array $gameRow,
    array $videos,
    int $activeIndex,
    int $startSec = 0,
): void {
    if ($videos === []) {
        return;
    }

    $activeIndex = max(0, min($activeIndex, count($videos) - 1));
    $active = $videos[$activeIndex];
    $gameId = (int) ($active['game_id'] ?? 0);
    $youtubeId = (string) ($active['youtube_id'] ?? '');
    $activeStartSec = $startSec > 0 ? $startSec : (int) ($active['start_sec'] ?? 0);
    $entry = [
        'game_id' => $gameId,
        'youtube_id' => $youtubeId,
        'video' => $active['video'] ?? [],
        'game' => $gameRow,
        'start_sec' => $activeStartSec,
    ];
    $spotlightLabel = amiga_tournament_videos_wc_game_spotlight_label($entry);
    $spotlightLabelHtml = amiga_tournament_videos_wc_game_caption_html($entry);
    $showMenu = count($videos) > 1;
    $videoCount = count($videos);
    ?>
<section class="k2-amiga-game-videos k2-tournament-videos--wc" aria-label="Game video" data-k2-game-video-start="<?php echo (int) $activeStartSec; ?>">
  <?php if ($showMenu) { ?>
  <div id="<?php echo k2_h(AMIGA_GAME_VIDEOS_MENU_FRAGMENT); ?>" class="k2-amiga-tournament-page-anchor" tabindex="-1"></div>
  <nav class="k2-amiga-game-videos__menu" aria-label="Videos for this game">
    <?php foreach ($videos as $index => $video) {
        $yt = (string) ($video['youtube_id'] ?? '');
        if ($yt === '') {
            continue;
        }
        $isActive = $index === $activeIndex;
        $videoStartSec = (int) ($video['start_sec'] ?? 0);
        $href = amiga_game_videos_url($gameId, $yt, $videoCount, $videoStartSec > 0 ? $videoStartSec : null);
        $linkClass = 'k2-amiga-game-videos__menu-link';
        if ($isActive) {
            $linkClass .= ' k2-link-star';
        } else {
            $linkClass .= ' k2-amiga-game-videos__menu-link--muted';
        }
        ?>
    <a
      href="<?php echo k2_h($href); ?>"
      class="<?php echo k2_h($linkClass); ?>"
      data-k2-game-video="<?php echo k2_h($yt); ?>"
      <?php if ($videoStartSec > 0) { ?> data-start-sec="<?php echo (int) $videoStartSec; ?>"<?php } ?>
      <?php echo $isActive ? ' aria-current="true"' : ''; ?>
    >Video <?php echo (int) ($index + 1); ?></a>
    <?php } ?>
  </nav>
  <?php } else { ?>
  <div id="<?php echo k2_h(AMIGA_GAME_VIDEOS_CAPTION_FRAGMENT); ?>" class="k2-amiga-tournament-page-anchor" tabindex="-1"></div>
  <?php } ?>

  <?php amiga_tournament_videos_render_spotlight(
      $youtubeId,
      $spotlightLabel,
      $activeStartSec,
      '',
      $spotlightLabelHtml,
      false,
  ); ?>
</section>
    <?php
}