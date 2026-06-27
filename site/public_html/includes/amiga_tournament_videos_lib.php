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

/** @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>} */
function amiga_tournament_videos_partition(array $rows): array
{
    $match = [];
    $extras = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        if ((string) ($row['kind'] ?? '') === 'match') {
            $match[] = $row;
        } else {
            $extras[] = $row;
        }
    }

    return [$match, $extras];
}

/** WC Games tab — explicit catalog slot when set; else stage/title heuristics. */
function amiga_tournament_videos_wc_sort_bucket(array $video): int
{
    $slot = strtolower(trim((string) ($video['wc_video_slot'] ?? '')));
    if ($slot === 'third_place') {
        return 20;
    }

    $stage = strtolower((string) ($video['stage'] ?? ''));
    $title = strtolower((string) ($video['title'] ?? ''));

    if ($stage === 'semi' || str_contains($title, 'semi final') || str_contains($title, 'semi-final')) {
        return 30;
    }
    if ($stage === 'quarter' || str_contains($title, 'quarter final') || str_contains($title, 'quarter-final')) {
        return 40;
    }
    if ($stage === 'silver' || str_contains($title, 'silver final') || str_contains($title, 'silver cup final')) {
        return 50;
    }
    if (
        $stage === 'final'
        && !str_contains($title, 'silver')
        && !str_contains($title, 'bronze')
        && !str_contains($title, 'third')
        && !str_contains($title, 'koa cup')
        && !str_contains($title, '3rd')
    ) {
        return 10;
    }

    return 60;
}

function amiga_tournament_videos_wing_from_request(bool $hasExtrasWing): string
{
    $wing = isset($_GET['wing']) ? trim((string) $_GET['wing']) : 'games';
    if ($wing === 'extras' && $hasExtrasWing) {
        return 'extras';
    }

    return 'games';
}

/**
 * @param list<int> $gameIds
 * @return array<int, array<string, mixed>>
 */
function amiga_tournament_videos_games_by_ids(mysqli $con, int $tournamentId, array $gameIds): array
{
    if ($tournamentId < 1 || $gameIds === []) {
        return [];
    }
    $gameIds = array_values(array_unique(array_filter(array_map('intval', $gameIds), static fn (int $id): bool => $id > 0)));
    if ($gameIds === []) {
        return [];
    }

    require_once __DIR__ . '/amiga_db.php';

    $placeholders = implode(',', array_fill(0, count($gameIds), '?'));
    $sql = 'SELECT r.id, r.`Date`, r.idA, r.NameA, r.idB, r.NameB, r.phase,
                   r.GoalsA, r.GoalsB, r.RatingA, r.RatingB, r.RatingDifference,
                   r.ExpectedScoreA, r.ExpectedScoreB, r.ActualScore, r.AdjustmentA, r.AdjustmentB,
                   r.NewRatingA, r.NewRatingB, r.SumOfGoals, r.GoalDifference,
                   r.country_a, r.country_b '
        . amiga_rated_games_from_sql()
        . ' WHERE r.tournament_id = ? AND r.id IN (' . $placeholders . ')';

    $types = 'i' . str_repeat('i', count($gameIds));
    $params = array_merge([$tournamentId], $gameIds);

    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $byId = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $byId[(int) $row['id']] = $row;
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);

    return $byId;
}

/**
 * One index row per linked game (dual-leg videos → two rows, same youtube_id for now).
 *
 * @param list<array<string, mixed>> $matchVideos
 * @return list<array{game_id: int, youtube_id: string, video: array<string, mixed>, game: array<string, mixed>, sort_bucket: int}>
 */
function amiga_tournament_videos_wc_game_index(mysqli $con, int $tournamentId, array $matchVideos): array
{
    $pending = [];
    foreach ($matchVideos as $video) {
        $yt = (string) ($video['youtube_id'] ?? '');
        if ($yt === '') {
            continue;
        }
        $gameIds = isset($video['game_ids']) && is_array($video['game_ids']) ? $video['game_ids'] : [];
        if ($gameIds === []) {
            continue;
        }
        $bucket = amiga_tournament_videos_wc_sort_bucket($video);
        foreach ($gameIds as $gid) {
            $gid = (int) $gid;
            if ($gid < 1) {
                continue;
            }
            $pending[] = [
                'game_id' => $gid,
                'youtube_id' => $yt,
                'video' => $video,
                'sort_bucket' => $bucket,
            ];
        }
    }

    $gamesById = amiga_tournament_videos_games_by_ids(
        $con,
        $tournamentId,
        array_column($pending, 'game_id'),
    );

    $entries = [];
    foreach ($pending as $item) {
        $gid = $item['game_id'];
        if (!isset($gamesById[$gid])) {
            continue;
        }
        $entries[] = [
            'game_id' => $gid,
            'youtube_id' => $item['youtube_id'],
            'video' => $item['video'],
            'game' => $gamesById[$gid],
            'sort_bucket' => $item['sort_bucket'],
        ];
    }

    usort(
        $entries,
        static function (array $a, array $b): int {
            if ($a['sort_bucket'] !== $b['sort_bucket']) {
                return $a['sort_bucket'] <=> $b['sort_bucket'];
            }

            return $a['game_id'] <=> $b['game_id'];
        },
    );

    return $entries;
}

/**
 * @param list<array{game_id: int, youtube_id: string, video: array<string, mixed>, game: array<string, mixed>, sort_bucket: int}> $entries
 * @return array{game_id: int, youtube_id: string, video: array<string, mixed>, game: array<string, mixed>, sort_bucket: int}|null
 */
function amiga_tournament_videos_wc_default_game_spotlight(array $entries): ?array
{
    foreach ($entries as $entry) {
        if ($entry['sort_bucket'] === 10) {
            return $entry;
        }
    }

    return $entries[0] ?? null;
}

/** @param list<array<string, mixed>> $rows */
function amiga_tournament_videos_sort_extras(array $rows): array
{
    $kindOrder = [
        'ceremony' => 1,
        'atmosphere' => 2,
        'compilation' => 3,
        'stream' => 4,
        'coverage' => 5,
    ];
    usort(
        $rows,
        static function (array $a, array $b) use ($kindOrder): int {
            $ka = $kindOrder[(string) ($a['kind'] ?? '')] ?? 9;
            $kb = $kindOrder[(string) ($b['kind'] ?? '')] ?? 9;
            if ($ka !== $kb) {
                return $ka <=> $kb;
            }

            return strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        },
    );

    return $rows;
}

/** @param list<array<string, mixed>> $rows */
function amiga_tournament_videos_default_extra_spotlight(array $rows): ?array
{
    $rows = amiga_tournament_videos_sort_extras($rows);

    return $rows[0] ?? null;
}

function amiga_tournament_videos_wc_game_spotlight_label(array $entry): string
{
    require_once __DIR__ . '/k2_player_game_row.php';
    $game = k2_player_game_normalize_row($entry['game']);
    $phase = trim((string) ($entry['game']['phase'] ?? ''));
    $players = (string) $game['NameA'] . ' vs ' . (string) $game['NameB'];
    if ($phase !== '') {
        return $phase . ' · ' . $players;
    }

    return $players;
}

function amiga_tournament_videos_extra_spotlight_label(array $video): string
{
    $title = trim((string) ($video['title'] ?? 'Video'));
    $duration = amiga_tournament_video_format_duration(
        isset($video['duration_sec']) ? (int) $video['duration_sec'] : null,
    );
    if ($duration === '') {
        return $title;
    }

    return $title . ' · ' . $duration;
}

function amiga_tournament_videos_play_button_html(
    string $youtubeId,
    string $spotlightLabel,
    bool $isActive,
    ?int $gameId = null,
): string {
    $classes = 'k2-tournament-videos__play-btn' . ($isActive ? ' is-active' : '');
    $attrs = ' type="button" class="' . k2_h($classes) . '"'
        . ' data-youtube-id="' . k2_h($youtubeId) . '"'
        . ' data-spotlight-label="' . k2_h($spotlightLabel) . '"'
        . ' aria-label="' . k2_h('Play video: ' . $spotlightLabel) . '"';
    if ($gameId !== null && $gameId > 0) {
        $attrs .= ' data-game-id="' . (int) $gameId . '"';
    }

    return '<button' . $attrs . '>'
        .         '<span class="k2-tournament-videos__play-glyph" aria-hidden="true">'
        . '<svg width="14" height="14" viewBox="0 0 24 24" focusable="false">'
        . '<path fill="currentColor" d="M8 5v14l11-7z"/>'
        . '</svg></span></button>';
}