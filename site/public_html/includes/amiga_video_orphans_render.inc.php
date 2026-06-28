<?php
declare(strict_types=1);

require_once __DIR__ . '/amiga_video_orphans_lib.php';
require_once __DIR__ . '/amiga_tournament_videos_wc_render.inc.php';
require_once __DIR__ . '/k2_table_helpers.php';

/** @param list<array<string, string>> $rows */
function amiga_video_orphans_render_table(
    array $rows,
    string $spotlightYoutube,
    bool $highlightRow,
    bool $showExclusionHelp = false,
): void {
    ?>
<?php k2_table_wrap_open(true); ?>
<table class="k2-table k2-table--tournament-videos-extras k2-table--video-orphans">
  <thead>
    <tr>
      <th class="k2-table-cell--left">Title</th>
      <th class="k2-table-cell--left">Kind</th>
      <th class="k2-table-cell--left">Duration</th>
      <?php if ($showExclusionHelp) { ?>
      <th class="k2-table-cell--center"><span class="visually-hidden">Exclusion reason</span></th>
      <?php } ?>
      <th class="k2-table-cell--center"><span class="visually-hidden">Play video</span></th>
    </tr>
  </thead>
  <tbody class="black">
  <?php if ($rows === []) { ?>
    <tr><td colspan="<?php echo $showExclusionHelp ? 5 : 4; ?>" class="k2-table-cell--left" style="color:var(--k2-text-secondary)">No videos in this section.</td></tr>
  <?php } ?>
  <?php foreach ($rows as $row) {
      $yt = amiga_tournament_videos_sanitize_youtube_id($row['youtube_id'] ?? '');
      if ($yt === '') {
          continue;
      }
      $label = amiga_video_orphans_row_label($row);
      $kind = amiga_video_orphans_row_meta($row);
      $duration = amiga_tournament_video_format_duration(
          isset($row['duration_sec']) && (string) $row['duration_sec'] !== ''
              ? (int) $row['duration_sec']
              : null,
      );
      $isActive = $highlightRow && $spotlightYoutube === $yt;
      $tooltip = $showExclusionHelp ? amiga_video_orphans_exclusion_tooltip($row) : '';
      $tid = trim((string) ($row['guessed_tournament_id'] ?? ''));
      $tlabel = trim((string) ($row['tournament_guess_label'] ?? ''));
      ?>
    <tr<?php echo $isActive ? ' class="is-active"' : ''; ?>>
      <td class="k2-table-cell--left">
        <?php echo k2_h($label); ?>
        <?php if ($showExclusionHelp && $tid !== '' && $tlabel !== '') { ?>
        <div class="k2-video-orphans__sub" style="color:var(--k2-text-secondary);font-size:0.92em;margin-top:0.15rem"><?php echo k2_h($tlabel); ?></div>
        <?php } ?>
      </td>
      <td class="k2-table-cell--left"><?php echo $kind !== '' ? k2_h($kind) : k2_fmt_dash(); ?></td>
      <td class="k2-table-cell--left"><?php echo $duration !== '' ? k2_h($duration) : k2_fmt_dash(); ?></td>
      <?php if ($showExclusionHelp) { ?>
      <td class="k2-table-cell--center">
        <?php if ($tooltip !== '') { ?>
        <button type="button" class="k2-video-orphans__why k2-table-helped" tabindex="0"
          data-k2-tooltip-label="Why excluded"
          data-k2-help="<?php echo k2_h($tooltip); ?>"
          aria-label="Why this video is excluded">?</button>
        <?php } else {
            echo k2_fmt_dash();
        } ?>
      </td>
      <?php } ?>
      <td class="k2-table-cell--center"><?php
          echo amiga_video_orphans_play_button_html($yt, $label, $isActive);
      ?></td>
    </tr>
  <?php } ?>
  </tbody>
</table>
<?php k2_table_wrap_close(); ?>
    <?php
}

/** @param array{groups: list<array{id: string, title: string, lede: string, rows: list<array<string, string>>}>, stray: list<array<string, string>>} $grouped */
function amiga_video_orphans_render_body(array $grouped, array $excluded, array $spotlight): void
{
    $indexUrl = amiga_video_orphans_index_url();
    $spotlightYt = (string) ($spotlight['youtube_id'] ?? '');
    $highlight = (bool) ($spotlight['highlight_row'] ?? false);
    ?>
<section
  class="k2-tournament-videos k2-tournament-videos--wc k2-video-orphans"
  aria-label="Orphan videos"
  data-k2-tv-index-url="<?php echo k2_h($indexUrl); ?>"
  data-k2-tv-table=".k2-table--video-orphans"
>
  <p class="k2-video-orphans__intro" style="color:var(--k2-text-secondary);margin:0 0 1rem;max-width:52rem">
    Review index for harvested videos with <strong>no tournament assignment</strong>, grouped for audit.
    Likely tournament matches are at the top. Excluded manifest rows are listed separately at the bottom.
  </p>

  <?php foreach ($grouped['groups'] as $group) { ?>
  <div class="k2-video-orphans__group" id="<?php echo k2_h('orphan-' . $group['id']); ?>">
    <h2 class="k2-video-orphans__group-title" style="font-size:1.15rem;margin:1.5rem 0 0.35rem"><?php echo k2_h((string) $group['title']); ?></h2>
    <?php if (($group['lede'] ?? '') !== '') { ?>
    <p class="k2-video-orphans__group-lede" style="color:var(--k2-text-secondary);margin:0 0 0.65rem;max-width:52rem"><?php echo k2_h((string) $group['lede']); ?></p>
    <?php } ?>
    <?php amiga_video_orphans_render_table($group['rows'], $spotlightYt, $highlight, false); ?>
  </div>
  <?php } ?>

  <?php if ($grouped['stray'] !== []) { ?>
  <div class="k2-video-orphans__group" id="orphan-uncatalogued">
    <h2 class="k2-video-orphans__group-title" style="font-size:1.15rem;margin:1.5rem 0 0.35rem">Uncatalogued orphans</h2>
    <p class="k2-video-orphans__group-lede" style="color:var(--k2-text-secondary);margin:0 0 0.65rem">In review.csv without tournament id but not yet placed in a curated group above.</p>
    <?php amiga_video_orphans_render_table($grouped['stray'], $spotlightYt, $highlight, false); ?>
  </div>
  <?php } ?>

  <div class="k2-video-orphans__group" id="orphan-excluded">
    <h2 class="k2-video-orphans__group-title" style="font-size:1.15rem;margin:1.75rem 0 0.35rem">Excluded from manifest</h2>
    <p class="k2-video-orphans__group-lede" style="color:var(--k2-text-secondary);margin:0 0 0.65rem">Duplicates, wrong-event clips, and other rows dropped from tournament pages. Hover <strong>?</strong> for the reason.</p>
    <?php amiga_video_orphans_render_table($excluded, $spotlightYt, $highlight, true); ?>
  </div>

  <?php amiga_tournament_videos_render_spotlight(
      $spotlightYt,
      (string) ($spotlight['label'] ?? ''),
      (int) ($spotlight['start_sec'] ?? 0),
      $indexUrl,
  ); ?>
</section>
    <?php
}