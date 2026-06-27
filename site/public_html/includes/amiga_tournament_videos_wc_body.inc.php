<?php

declare(strict_types=1);

/** @var int $id */
/** @var string $tournamentVideosWing */
/** @var list<array{game_id: int, youtube_id: string, video: array<string, mixed>, game: array<string, mixed>, sort_bucket: int}> $tournamentVideosGameEntries */
/** @var list<array<string, mixed>> $tournamentVideosExtrasRows */
/** @var array<string, mixed>|array{game_id: int, youtube_id: string, video: array<string, mixed>, game: array<string, mixed>, sort_bucket: int}|null $tournamentVideosSpotlight */
/** @var string $tournamentVideosSpotlightLabel */
/** @var string $tournamentVideosSpotlightYoutube */
/** @var int $tournamentVideosSpotlightStartSec */
/** @var bool $tournamentVideosHighlightRow */
/** @var string $tournamentVideosIndexUrl */

require_once __DIR__ . '/amiga_tournament_videos_wc_render.inc.php';

$hasExtrasWing = $tournamentVideosExtrasRows !== [];
?>
<section
  class="k2-tournament-videos k2-tournament-videos--wc"
  aria-label="Videos"
  data-k2-tv-tournament-id="<?php echo (int) $id; ?>"
  data-k2-tv-wing="<?php echo k2_h($tournamentVideosWing); ?>"
  data-k2-tv-index-url="<?php echo k2_h($tournamentVideosIndexUrl); ?>"
  data-k2-tv-table="<?php echo k2_h($tournamentVideosWing === 'extras' ? '.k2-table--tournament-videos-extras' : '.k2-table--tournament-videos-games'); ?>"
>
  <?php amiga_tournament_videos_render_wc_wing_nav($id, $tournamentVideosWing, $hasExtrasWing); ?>

  <?php if ($tournamentVideosWing === 'extras') { ?>
  <?php amiga_tournament_videos_render_wc_extras_table(
      $id,
      $tournamentVideosWing,
      $tournamentVideosExtrasRows,
      $tournamentVideosSpotlightYoutube,
      $tournamentVideosHighlightRow,
  ); ?>
  <?php } else { ?>
  <?php amiga_tournament_videos_render_wc_games_table(
      $id,
      $tournamentVideosWing,
      $tournamentVideosGameEntries,
      $tournamentVideosSpotlight,
      $tournamentVideosSpotlightYoutube,
      $tournamentVideosHighlightRow,
  ); ?>
  <?php } ?>

  <?php amiga_tournament_videos_render_spotlight(
      $tournamentVideosSpotlightYoutube,
      $tournamentVideosSpotlightLabel,
      $tournamentVideosSpotlightStartSec,
      $tournamentVideosIndexUrl,
  ); ?>
</section>