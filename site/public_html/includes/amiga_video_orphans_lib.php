<?php
/**
 * Orphan / unassigned tournament videos — review index (Jun 2026).
 *
 * @see docs/amiga-tournament-videos-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_tournament_videos_lib.php';
require_once __DIR__ . '/k2_safety.php';

const AMIGA_VIDEO_ORPHANS_PAGE_PATH = '/amiga/videos/orphans.php';

function amiga_video_orphans_review_csv_path(): string
{
    static $resolved = null;
    if ($resolved !== null) {
        return $resolved;
    }
    $candidates = [
        __DIR__ . '/../data/amiga/tournament_videos/review.csv',
        __DIR__ . '/../../../data/amiga/tournament_videos/review.csv',
    ];
    foreach ($candidates as $path) {
        if (is_readable($path)) {
            $resolved = $path;
            return $resolved;
        }
    }
    $resolved = $candidates[0];

    return $resolved;
}

/** @return list<array<string, string>> */
function amiga_video_orphans_load_review_rows(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    if (!is_readable(amiga_video_orphans_review_csv_path())) {
        return $cache;
    }
    $fh = fopen(amiga_video_orphans_review_csv_path(), 'rb');
    if ($fh === false) {
        return $cache;
    }
    $header = fgetcsv($fh);
    if (!is_array($header)) {
        fclose($fh);
        return $cache;
    }
    while (($line = fgetcsv($fh)) !== false) {
        if (!is_array($line) || count($line) < count($header)) {
            continue;
        }
        $cache[] = array_combine($header, array_pad($line, count($header), ''));
    }
    fclose($fh);
    return $cache;
}

/** @return array<string, array<string, string>> */
function amiga_video_orphans_review_by_youtube_id(): array
{
    static $index = null;
    if ($index !== null) {
        return $index;
    }
    $index = [];
    foreach (amiga_video_orphans_load_review_rows() as $row) {
        $yt = amiga_tournament_videos_sanitize_youtube_id($row['youtube_id'] ?? '');
        if ($yt !== '') {
            $index[$yt] = $row;
        }
    }
    return $index;
}

/** @return list<array<string, string>> */
function amiga_video_orphans_unassigned_rows(): array
{
    $out = [];
    foreach (amiga_video_orphans_load_review_rows() as $row) {
        if (($row['kind'] ?? '') === 'excluded') {
            continue;
        }
        if (trim((string) ($row['guessed_tournament_id'] ?? '')) !== '') {
            continue;
        }
        $out[] = $row;
    }
    return $out;
}

/** @return list<array<string, string>> */
function amiga_video_orphans_excluded_rows(): array
{
    $out = [];
    foreach (amiga_video_orphans_load_review_rows() as $row) {
        if (($row['kind'] ?? '') !== 'excluded') {
            continue;
        }
        $out[] = $row;
    }
    usort($out, static function (array $a, array $b): int {
        return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
    });
    return $out;
}

/** @return list<array{id: string, title: string, lede: string, youtube_ids: list<string>}> */
function amiga_video_orphans_curated_groups(): array
{
    /** @var list<array{id: string, title: string, lede: string, youtube_ids: list<string>}> $groups */
    $groups = require __DIR__ . '/amiga_video_orphans_catalog.php';
    return $groups;
}

/**
 * @return array{groups: list<array{id: string, title: string, lede: string, rows: list<array<string, string>>}>, stray: list<array<string, string>>}
 */
function amiga_video_orphans_grouped_unassigned(): array
{
    $byId = amiga_video_orphans_review_by_youtube_id();
    $claimed = [];
    $built = [];
    foreach (amiga_video_orphans_curated_groups() as $group) {
        $rows = [];
        foreach ($group['youtube_ids'] as $yt) {
            $yt = amiga_tournament_videos_sanitize_youtube_id($yt);
            if ($yt === '' || !isset($byId[$yt])) {
                continue;
            }
            $rows[] = $byId[$yt];
            $claimed[$yt] = true;
        }
        if ($rows === []) {
            continue;
        }
        $built[] = [
            'id' => (string) $group['id'],
            'title' => (string) $group['title'],
            'lede' => (string) ($group['lede'] ?? ''),
            'rows' => $rows,
        ];
    }
    $stray = [];
    foreach (amiga_video_orphans_unassigned_rows() as $row) {
        $yt = amiga_tournament_videos_sanitize_youtube_id($row['youtube_id'] ?? '');
        if ($yt !== '' && !isset($claimed[$yt])) {
            $stray[] = $row;
        }
    }
    usort($stray, static fn (array $a, array $b): int => strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? '')));

    return ['groups' => $built, 'stray' => $stray];
}

function amiga_video_orphans_exclusion_tooltip(array $row): string
{
    $notes = trim((string) ($row['notes'] ?? ''));
    if ($notes !== '') {
        return $notes;
    }
    $relation = trim((string) ($row['relation'] ?? ''));
    $rg = trim((string) ($row['relation_group'] ?? ''));
    if ($relation === 'alternate_recording' && $rg !== '') {
        return 'Duplicate recording; canonical upload kept in manifest.';
    }
    return 'Excluded from tournament video manifest.';
}

function amiga_video_orphans_index_url(): string
{
    return AMIGA_VIDEO_ORPHANS_PAGE_PATH;
}

function amiga_video_orphans_video_url(string $youtubeId): string
{
    $id = amiga_tournament_videos_sanitize_youtube_id($youtubeId);
    return AMIGA_VIDEO_ORPHANS_PAGE_PATH . '?v=' . rawurlencode($id);
}

function amiga_video_orphans_play_button_html(
    string $youtubeId,
    string $spotlightLabel,
    bool $isActive,
): string {
    $yt = amiga_tournament_videos_sanitize_youtube_id($youtubeId);
    if ($yt === '') {
        return '';
    }
    $classes = 'k2-tournament-videos__play-btn' . ($isActive ? ' is-active' : '');
    $href = amiga_video_orphans_video_url($yt);
    $attrs = ' class="' . k2_h($classes) . '"'
        . ' href="' . k2_h($href) . '"'
        . ' data-k2-tv-inpage="1"'
        . ' data-youtube-id="' . k2_h($yt) . '"'
        . ' data-spotlight-label="' . k2_h($spotlightLabel) . '"'
        . ' aria-label="' . k2_h('Play video: ' . $spotlightLabel) . '"';

    return '<a' . $attrs . '>'
        . '<span class="k2-tournament-videos__play-glyph" aria-hidden="true">'
        . '<svg width="14" height="14" viewBox="0 0 24 24" focusable="false">'
        . '<path fill="currentColor" d="M8 5v14l11-7z"/>'
        . '</svg></span></a>';
}

function amiga_video_orphans_row_label(array $row): string
{
    $title = trim((string) ($row['title'] ?? ''));
    return $title !== '' ? $title : 'Video';
}

function amiga_video_orphans_row_meta(array $row): string
{
    $parts = [];
    $kind = trim((string) ($row['kind'] ?? ''));
    if ($kind !== '') {
        $parts[] = $kind;
    }
    $channel = trim((string) ($row['source_channel'] ?? ''));
    if ($channel !== '') {
        $parts[] = $channel;
    }
    return implode(' · ', $parts);
}

function amiga_video_orphans_spotlight_state(): array
{
    $params = amiga_tournament_videos_wc_request_params();
    $byId = amiga_video_orphans_review_by_youtube_id();
    $youtube = $params['v'];
    $label = 'Video';
    if ($youtube !== '' && isset($byId[$youtube])) {
        $label = amiga_video_orphans_row_label($byId[$youtube]);
    }
    $highlight = $youtube !== '';

    return [
        'youtube_id' => $youtube,
        'label' => $label,
        'start_sec' => $params['start_sec'],
        'highlight_row' => $highlight,
    ];
}