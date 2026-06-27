<?php

declare(strict_types=1);

/** @var int $id */
/** @var string $tournamentVideosWing */
/** @var list<array{game_id: int, youtube_id: string, video: array<string, mixed>, game: array<string, mixed>, sort_bucket: int}> $tournamentVideosGameEntries */
/** @var list<array<string, mixed>> $tournamentVideosExtrasRows */
/** @var array<string, mixed>|array{game_id: int, youtube_id: string, video: array<string, mixed>, game: array<string, mixed>, sort_bucket: int}|null $tournamentVideosSpotlight */
/** @var string $tournamentVideosSpotlightLabel */
/** @var string $tournamentVideosSpotlightYoutube */

require_once __DIR__ . '/amiga_tournament_videos_wc_render.inc.php';

$hasExtrasWing = $tournamentVideosExtrasRows !== [];
?>
<section class="k2-tournament-videos k2-tournament-videos--wc" aria-label="Videos">
  <?php amiga_tournament_videos_render_wc_wing_nav($id, $tournamentVideosWing, $hasExtrasWing); ?>

  <?php if ($tournamentVideosWing === 'extras') { ?>
  <?php amiga_tournament_videos_render_wc_extras_table($tournamentVideosExtrasRows, $tournamentVideosSpotlight); ?>
  <?php } else { ?>
  <?php amiga_tournament_videos_render_wc_games_table($tournamentVideosGameEntries, $tournamentVideosSpotlight); ?>
  <?php } ?>

  <?php if ($tournamentVideosSpotlightYoutube !== '') { ?>
  <?php amiga_tournament_videos_render_spotlight($tournamentVideosSpotlightYoutube, $tournamentVideosSpotlightLabel); ?>
  <?php } ?>
</section>