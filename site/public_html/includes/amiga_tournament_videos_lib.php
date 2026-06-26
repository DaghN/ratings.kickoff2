<?php

declare(strict_types=1);

/**
 * Read-only tournament video manifest (TV-3).
 *
 * @see docs/amiga-tournament-videos-policy.md
 */

const AMIGA_TOURNAMENT_VIDEOS_MANIFEST_PATH = __DIR__ . '/../data/amiga/tournament_videos.json';

/** @var array<string, mixed>|null */
$GLOBALS['_amiga_tournament_videos_manifest'] = null;

/** @var array<int, list<array<string, mixed>>>|null */
$GLOBALS['_amiga_tournament_videos_by_tid'] = null;

/**
 * @return array{schema_version: int, updated_at: string, videos: list<array<string, mixed>>}
 */
function amiga_tournament_videos_manifest(): array
{
    if ($GLOBALS['_amiga_tournament_videos_manifest'] !== null) {
        return $GLOBALS['_amiga_tournament_videos_manifest'];
    }
    $path = AMIGA_TOURNAMENT_VIDEOS_MANIFEST_PATH;
    if (!is_readable($path)) {
        $GLOBALS['_amiga_tournament_videos_manifest'] = [
            'schema_version' => 1,
            'updated_at' => '',
            'videos' => [],
        ];
        return $GLOBALS['_amiga_tournament_videos_manifest'];
    }
    $raw = file_get_contents($path);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data) || !isset($data['videos']) || !is_array($data['videos'])) {
        $data = ['schema_version' => 1, 'updated_at' => '', 'videos' => []];
    }
    $GLOBALS['_amiga_tournament_videos_manifest'] = $data;

    return $data;
}

/** @return array<int, list<array<string, mixed>>> */
function amiga_tournament_videos_index_by_tournament(): array
{
    if ($GLOBALS['_amiga_tournament_videos_by_tid'] !== null) {
        return $GLOBALS['_amiga_tournament_videos_by_tid'];
    }
    $index = [];
    foreach (amiga_tournament_videos_manifest()['videos'] as $row) {
        if (!is_array($row)) {
            continue;
        }
        if (($row['kind'] ?? '') === 'excluded') {
            continue;
        }
        $tid = (int) ($row['tournament_id'] ?? 0);
        if ($tid < 1) {
            continue;
        }
        $index[$tid][] = $row;
    }
    $GLOBALS['_amiga_tournament_videos_by_tid'] = $index;

    return $index;
}

function amiga_tournament_has_videos(int $tournamentId): bool
{
    if ($tournamentId < 1) {
        return false;
    }
    $index = amiga_tournament_videos_index_by_tournament();

    return isset($index[$tournamentId]) && $index[$tournamentId] !== [];
}

/** @return list<array<string, mixed>> */
function amiga_tournament_videos_for_id(int $tournamentId): array
{
    if ($tournamentId < 1) {
        return [];
    }
    $rows = amiga_tournament_videos_index_by_tournament()[$tournamentId] ?? [];
    usort(
        $rows,
        static function (array $a, array $b): int {
            $sa = (int) ($a['sort'] ?? 999);
            $sb = (int) ($b['sort'] ?? 999);
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }

            return strcmp((string) ($a['youtube_id'] ?? ''), (string) ($b['youtube_id'] ?? ''));
        },
    );

    return $rows;
}

function amiga_tournament_videos_section_key(array $row): string
{
    $kind = (string) ($row['kind'] ?? 'match');
    $stage = strtolower((string) ($row['stage'] ?? ''));

    if ($kind === 'ceremony' || in_array($stage, ['presentations', 'medals', 'ceremony'], true)) {
        return 'ceremony';
    }
    if ($kind === 'stream' || $kind === 'compilation' || $kind === 'atmosphere') {
        return 'coverage';
    }
    if ($stage === 'shame' || $stage === 'exhibition' || $stage === 'league') {
        return 'side';
    }
    if (in_array($stage, ['final', 'bronze'], true)) {
        return 'final';
    }
    if (in_array($stage, ['semi', 'quarter', 'silver', 'gold'], true)) {
        return 'knockout';
    }
    if ($kind === 'match') {
        return 'knockout';
    }

    return 'coverage';
}

/** @return array<string, list<array<string, mixed>>> */
function amiga_tournament_videos_grouped(array $rows): array
{
    $order = ['final', 'knockout', 'side', 'ceremony', 'coverage'];
    $grouped = array_fill_keys($order, []);
    foreach ($rows as $row) {
        $key = amiga_tournament_videos_section_key($row);
        if (!isset($grouped[$key])) {
            $grouped[$key] = [];
        }
        $grouped[$key][] = $row;
    }
    foreach ($grouped as $key => $list) {
        if ($list === []) {
            unset($grouped[$key]);
        }
    }
    $sorted = [];
    foreach ($order as $key) {
        if (isset($grouped[$key])) {
            $sorted[$key] = $grouped[$key];
        }
    }

    return $sorted;
}

function amiga_tournament_video_embed_url(string $youtubeId): string
{
    $id = preg_replace('/[^A-Za-z0-9_-]/', '', $youtubeId) ?? '';

    return 'https://www.youtube-nocookie.com/embed/' . rawurlencode($id);
}

function amiga_tournament_video_watch_url(string $youtubeId): string
{
    $id = preg_replace('/[^A-Za-z0-9_-]/', '', $youtubeId) ?? '';

    return 'https://www.youtube.com/watch?v=' . rawurlencode($id);
}

function amiga_tournament_video_thumb_url(string $youtubeId): string
{
    $id = preg_replace('/[^A-Za-z0-9_-]/', '', $youtubeId) ?? '';

    return 'https://i.ytimg.com/vi/' . rawurlencode($id) . '/hqdefault.jpg';
}

function amiga_tournament_video_format_duration(?int $seconds): string
{
    if ($seconds === null || $seconds < 1) {
        return '';
    }
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    if ($h > 0) {
        return $h . 'h ' . $m . 'm';
    }
    if ($m > 0) {
        return $m . ' min';
    }

    return $seconds . ' sec';
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<int, string>
 */
function amiga_tournament_videos_player_names(mysqli $con, array $rows): array
{
    $ids = [];
    foreach ($rows as $row) {
        foreach (['player_a_id', 'player_b_id'] as $key) {
            $pid = (int) ($row[$key] ?? 0);
            if ($pid > 0) {
                $ids[$pid] = true;
            }
        }
    }
    if ($ids === []) {
        return [];
    }
    $idList = implode(',', array_map('intval', array_keys($ids)));
    $res = mysqli_query($con, 'SELECT id, name FROM amiga_players WHERE id IN (' . $idList . ')');
    if ($res === false) {
        return [];
    }
    $names = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $names[(int) $r['id']] = (string) $r['name'];
    }
    mysqli_free_result($res);

    return $names;
}

function amiga_tournament_videos_section_label(string $key): string
{
    return match ($key) {
        'final' => 'Final & podium',
        'knockout' => 'Knockout',
        'side' => 'Side events',
        'ceremony' => 'Ceremony',
        'coverage' => 'Coverage',
        default => 'Videos',
    };
}