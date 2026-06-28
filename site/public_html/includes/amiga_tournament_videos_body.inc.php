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

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_videos_lib.php';

if (($tournamentVideosRows ?? []) === []) {
    ?>
<section class="k2-tournament-videos" aria-label="Videos">
  <p class="k2-amiga-tournament-empty">No videos catalogued for this event yet.</p>
</section>
    <?php
    return;
}

include __DIR__ . '/amiga_tournament_videos_wc_body.inc.php';
