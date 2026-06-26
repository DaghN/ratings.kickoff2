<?php

declare(strict_types=1);

/** @var int $id */
/** @var list<array<string, mixed>> $tournamentVideosRows */
/** @var array<string, list<array<string, mixed>>> $tournamentVideosGrouped */
/** @var array<int, string> $tournamentVideoPlayerNames */

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_videos_lib.php';

$renderAlternateList = static function (array $alternates): void {
    if ($alternates === []) {
        return;
    }
    echo '<ul class="k2-tournament-videos__alternates">';
    foreach ($alternates as $alt) {
        $yt = (string) ($alt['youtube_id'] ?? '');
        if ($yt === '') {
            continue;
        }
        echo '<li><a href="' . k2_h(amiga_tournament_video_watch_url($yt)) . '" rel="noopener noreferrer" target="_blank">'
            . k2_h((string) ($alt['title'] ?? 'Alternate recording')) . '</a></li>';
    }
    echo '</ul>';
};

$renderVideoCard = static function (array $row, array $alternates) use ($renderAlternateList, $tournamentVideoPlayerNames): void {
    $yt = (string) ($row['youtube_id'] ?? '');
    if ($yt === '') {
        return;
    }
    $kind = (string) ($row['kind'] ?? 'match');
    $isStream = $kind === 'stream' || ($kind === 'atmosphere' && (int) ($row['duration_sec'] ?? 0) > 600);
    $duration = amiga_tournament_video_format_duration(
        isset($row['duration_sec']) ? (int) $row['duration_sec'] : null,
    );
    $pa = (int) ($row['player_a_id'] ?? 0);
    $pb = (int) ($row['player_b_id'] ?? 0);
    $score = trim((string) ($row['score'] ?? ''));
    $gameIds = isset($row['game_ids']) && is_array($row['game_ids']) ? $row['game_ids'] : [];
    ?>
<article class="k2-tournament-videos__card">
  <div class="k2-tournament-videos__meta">
    <h3 class="k2-tournament-videos__title"><?php echo k2_h((string) ($row['title'] ?? 'Video')); ?></h3>
    <?php if ($pa > 0 && $pb > 0) { ?>
    <p class="k2-tournament-videos__players">
      <?php
      echo k2_amiga_player_link($pa, $tournamentVideoPlayerNames[$pa] ?? ('Player ' . $pa));
      if ($score !== '') {
          echo ' <span class="k2-tournament-videos__score">' . k2_h($score) . '</span> ';
      } else {
          echo ' vs ';
      }
      echo k2_amiga_player_link($pb, $tournamentVideoPlayerNames[$pb] ?? ('Player ' . $pb));
      ?>
    </p>
    <?php } elseif ($score !== '') { ?>
    <p class="k2-tournament-videos__scoreline"><?php echo k2_h($score); ?></p>
    <?php } ?>
    <?php if ($duration !== '') { ?>
    <p class="k2-tournament-videos__duration"><?php
        echo k2_h($isStream ? 'Long coverage · ' . $duration : $duration);
    ?></p>
    <?php } ?>
    <?php if ($gameIds !== []) {
        echo '<p class="k2-tournament-videos__games">Game';
        if (count($gameIds) > 1) {
            echo 's';
        }
        echo ': ';
        $links = [];
        foreach ($gameIds as $gid) {
            $gid = (int) $gid;
            if ($gid > 0) {
                $links[] = '<a href="' . k2_h(k2_amiga_route('amiga-game', ['id' => $gid])) . '">' . $gid . '</a>';
            }
        }
        echo implode(', ', $links);
        echo '</p>';
    } ?>
  </div>
  <div class="k2-game-page__video-wrap">
    <div class="k2-game-page__video">
      <iframe
        class="k2-game-page__video-iframe"
        src="<?php echo k2_h(amiga_tournament_video_embed_url($yt)); ?>"
        title="<?php echo k2_h((string) ($row['title'] ?? 'YouTube video')); ?>"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
        referrerpolicy="strict-origin-when-cross-origin"
        allowfullscreen
      ></iframe>
    </div>
  </div>
  <?php if ($alternates !== []) { ?>
  <div class="k2-tournament-videos__also">
    <p class="k2-tournament-videos__also-label">Also available</p>
    <?php $renderAlternateList($alternates); ?>
  </div>
  <?php } ?>
</article>
    <?php
};

?>
<section class="k2-tournament-videos" aria-label="Videos">
  <?php if ($tournamentVideosRows === []) { ?>
  <p class="k2-amiga-tournament-empty">No videos catalogued for this event yet.</p>
  <?php } else {
      foreach ($tournamentVideosGrouped as $sectionKey => $sectionRows) {
          $groups = [];
          $singles = [];
          foreach ($sectionRows as $row) {
              $rg = (string) ($row['relation_group'] ?? '');
              if ($rg !== '') {
                  $groups[$rg][] = $row;
              } else {
                  $singles[] = $row;
              }
          }
          ?>
  <section class="k2-tournament-videos__section" aria-labelledby="k2-tournament-videos-<?php echo k2_h($sectionKey); ?>">
    <h2 id="k2-tournament-videos-<?php echo k2_h($sectionKey); ?>" class="k2-panel-heading"><?php
        echo k2_h(amiga_tournament_videos_section_label($sectionKey));
    ?></h2>
          <?php
          foreach ($groups as $members) {
              $canonical = $members[0];
              foreach ($members as $member) {
                  if (($member['relation'] ?? '') === 'canonical') {
                      $canonical = $member;
                      break;
                  }
              }
              $alternates = array_values(array_filter(
                  $members,
                  static fn (array $m): bool => (string) ($m['youtube_id'] ?? '') !== (string) ($canonical['youtube_id'] ?? ''),
              ));
              $renderVideoCard($canonical, $alternates);
          }
          foreach ($singles as $row) {
              $renderVideoCard($row, []);
          }
          ?>
  </section>
          <?php
      }
  } ?>
</section>