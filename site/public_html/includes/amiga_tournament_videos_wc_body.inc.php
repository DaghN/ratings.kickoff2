<?php

declare(strict_types=1);

/** @var int $id */
/** @var string $tournamentVideosMode */
/** @var list<array{game_id: int, youtube_id: string, video: array<string, mixed>, game: array<string, mixed>, sort_bucket: int}> $tournamentVideosGameEntries */
/** @var list<array<string, mixed>> $tournamentVideosExtrasRows */
/** @var array<string, mixed>|array{game_id: int, youtube_id: string, video: array<string, mixed>, game: array<string, mixed>, sort_bucket: int}|null $tournamentVideosSpotlight */
/** @var string $tournamentVideosSpotlightLabel */
/** @var string $tournamentVideosSpotlightYoutube */
/** @var int $tournamentVideosSpotlightStartSec */
/** @var bool $tournamentVideosHighlightRow */
/** @var string $tournamentVideosIndexUrl */

/** @var bool $tournamentVideosHasGamesWing */
/** @var bool $tournamentVideosHasExtrasWing */

require_once __DIR__ . '/amiga_tournament_videos_wc_render.inc.php';

$hasAtmosphereWing = $tournamentVideosHasExtrasWing ?? ($tournamentVideosExtrasRows !== []);
$hasGamesWing = $tournamentVideosHasGamesWing ?? ($tournamentVideosGameEntries !== []);
?>
<section
  class="k2-tournament-videos k2-tournament-videos--wc"
  aria-label="Videos"
  data-k2-tv-tournament-id="<?php echo (int) $id; ?>"
  data-k2-tv-mode="<?php echo k2_h($tournamentVideosMode); ?>"
  data-k2-tv-index-url="<?php echo k2_h($tournamentVideosIndexUrl); ?>"
  data-k2-tv-table="<?php echo k2_h($tournamentVideosMode === 'atmosphere' ? '.k2-table--tournament-videos-extras' : '.k2-table--tournament-videos-games'); ?>"
>
  <?php amiga_tournament_videos_render_wc_mode_nav($id, $tournamentVideosMode, $hasAtmosphereWing, $hasGamesWing); ?>

  <?php if ($tournamentVideosMode === 'atmosphere') { ?>
  <?php amiga_tournament_videos_render_wc_extras_table(
      $id,
      $tournamentVideosMode,
      $tournamentVideosExtrasRows,
      $tournamentVideosSpotlightYoutube,
      $tournamentVideosHighlightRow,
  ); ?>
  <?php } else { ?>
  <?php amiga_tournament_videos_render_wc_games_table(
      $id,
      $tournamentVideosMode,
      $tournamentVideosGameEntries,
      $tournamentVideosSpotlight,
      $tournamentVideosSpotlightYoutube,
      $tournamentVideosHighlightRow,
  ); ?>
  <?php } ?>

  <?php
  $tournamentVideosSpotlightLabelHtml = ($tournamentVideosMode === 'games'
      && is_array($tournamentVideosSpotlight)
      && (int) ($tournamentVideosSpotlight['game_id'] ?? 0) > 0)
      ? amiga_tournament_videos_wc_game_caption_html($tournamentVideosSpotlight)
      : '';
  amiga_tournament_videos_render_spotlight(
      $tournamentVideosSpotlightYoutube,
      $tournamentVideosSpotlightLabel,
      $tournamentVideosSpotlightStartSec,
      $tournamentVideosIndexUrl,
      $tournamentVideosSpotlightLabelHtml,
  ); ?>
</section>