<?php

declare(strict_types=1);

/**
 * Read-only tournament video manifest (TV-3).
 *
 * @see docs/amiga-tournament-videos-policy.md
 * @see docs/k2-embedded-video-page-policy.md
 */

const AMIGA_TOURNAMENT_VIDEOS_MANIFEST_PATH = __DIR__ . '/../data/amiga/tournament_videos.json';

const AMIGA_TOURNAMENT_VIDEOS_PLAYER_FRAGMENT = 'k2-tournament-video-player';

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

function amiga_tournament_video_embed_url(string $youtubeId, int $startSec = 0): string
{
    $id = amiga_tournament_videos_sanitize_youtube_id($youtubeId);
    if ($id === '') {
        return '';
    }
    $url = 'https://www.youtube-nocookie.com/embed/' . rawurlencode($id);
    if ($startSec > 0) {
        $url .= '?start=' . $startSec;
    }

    return $url;
}

function amiga_tournament_videos_sanitize_youtube_id(?string $raw): string
{
    return preg_replace('/[^A-Za-z0-9_-]/', '', (string) $raw) ?? '';
}

/**
 * @return array{v: string, game: int, start_sec: int}
 */
function amiga_tournament_videos_wc_request_params(): array
{
    $v = amiga_tournament_videos_sanitize_youtube_id(isset($_GET['v']) ? (string) $_GET['v'] : '');
    $game = isset($_GET['game']) ? (int) $_GET['game'] : 0;
    $startSec = isset($_GET['t']) ? max(0, (int) $_GET['t']) : 0;

    return [
        'v' => $v,
        'game' => $game > 0 ? $game : 0,
        'start_sec' => $startSec,
    ];
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

/**
 * WC Games wing spotlight from cold URL (see k2-embedded-video-page-policy.md §2.2).
 * Index shape = no player; deep link `v=` only.
 *
 * @param list<array{game_id: int, youtube_id: string, video: array<string, mixed>, game: array<string, mixed>, sort_bucket: int}> $entries
 * @return array{
 *   entry: array{game_id: int, youtube_id: string, video: array<string, mixed>, game: array<string, mixed>, sort_bucket: int}|null,
 *   youtube_id: string,
 *   label: string,
 *   start_sec: int,
 *   highlight_row: bool,
 * }
 */
function amiga_tournament_videos_wc_games_spotlight_state(
    array $entries,
    ?string $requestVideoId,
    ?int $requestGameId,
    int $requestStartSec = 0,
): array {
    $startSec = max(0, $requestStartSec);

    $fromEntry = static function (?array $entry) use ($startSec): array {
        if ($entry === null) {
            return [
                'entry' => null,
                'youtube_id' => '',
                'label' => '',
                'start_sec' => $startSec,
                'highlight_row' => false,
            ];
        }

        return [
            'entry' => $entry,
            'youtube_id' => (string) ($entry['youtube_id'] ?? ''),
            'label' => amiga_tournament_videos_wc_game_spotlight_label($entry),
            'start_sec' => $startSec,
            'highlight_row' => true,
        ];
    };

    $vid = amiga_tournament_videos_sanitize_youtube_id($requestVideoId ?? '');
    if ($vid === '') {
        return $fromEntry(null);
    }

    $matches = array_values(array_filter(
        $entries,
        static fn (array $entry): bool => (string) ($entry['youtube_id'] ?? '') === $vid,
    ));

    if ($requestGameId !== null && $requestGameId > 0) {
        foreach ($matches as $entry) {
            if ((int) $entry['game_id'] === $requestGameId) {
                return $fromEntry($entry);
            }
        }

        return $fromEntry(null);
    }

    if (count($matches) === 1) {
        return $fromEntry($matches[0]);
    }

    if (count($matches) > 1) {
        $video = $matches[0]['video'] ?? [];
        $title = trim((string) ($video['title'] ?? 'Video'));

        return [
            'entry' => null,
            'youtube_id' => $vid,
            'label' => $title !== '' ? $title : 'Video',
            'start_sec' => $startSec,
            'highlight_row' => false,
        ];
    }

    return $fromEntry(null);
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array{
 *   row: array<string, mixed>|null,
 *   youtube_id: string,
 *   label: string,
 *   start_sec: int,
 *   highlight_row: bool,
 * }
 */
function amiga_tournament_videos_wc_extras_spotlight_state(
    array $rows,
    ?string $requestVideoId,
    int $requestStartSec = 0,
): array {
    $startSec = max(0, $requestStartSec);

    $fromRow = static function (?array $row) use ($startSec): array {
        if ($row === null) {
            return [
                'row' => null,
                'youtube_id' => '',
                'label' => '',
                'start_sec' => $startSec,
                'highlight_row' => false,
            ];
        }

        return [
            'row' => $row,
            'youtube_id' => (string) ($row['youtube_id'] ?? ''),
            'label' => amiga_tournament_videos_extra_spotlight_label($row),
            'start_sec' => $startSec,
            'highlight_row' => true,
        ];
    };

    $vid = amiga_tournament_videos_sanitize_youtube_id($requestVideoId ?? '');
    if ($vid === '') {
        return $fromRow(null);
    }

    $matches = array_values(array_filter(
        $rows,
        static fn (array $row): bool => (string) ($row['youtube_id'] ?? '') === $vid,
    ));

    if (count($matches) === 1) {
        return $fromRow($matches[0]);
    }

    if (count($matches) > 1) {
        return [
            'row' => $matches[0],
            'youtube_id' => $vid,
            'label' => amiga_tournament_videos_extra_spotlight_label($matches[0]),
            'start_sec' => $startSec,
            'highlight_row' => false,
        ];
    }

    return $fromRow(null);
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

/** Game date for the spotlight caption — full month, day, year (`June 17, 2025`). */
function amiga_tournament_videos_wc_game_caption_date_html(string $date): string
{
    require_once __DIR__ . '/k2_table_helpers.php';

    $date = trim($date);
    if ($date === '') {
        return k2_fmt_dash();
    }

    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date, new DateTimeZone('UTC'));
    if ($dt === false) {
        $ts = strtotime($date);
        if ($ts === false) {
            return k2_h($date);
        }
        $dt = (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone('UTC'));
    }

    return k2_h($dt->format('F j, Y'));
}

/** Tooltip body for pre-game Elo links in the WC spotlight caption (above the player). */
function amiga_tournament_videos_spotlight_rating_help(string $side): string
{
    return $side === 'b'
        ? "Team B's Elo rating before this game. Opens the rating leaderboard."
        : "Team A's Elo rating before this game. Opens the rating leaderboard.";
}

/**
 * Headerless one-row scoreboard shown above the spotlight player (game videos only).
 * Mirrors the index Games row cell treatments (linked id, winner goal in blue,
 * per-side flags when country data exists) minus the rating columns and play button;
 * each player's pre-game Elo is appended in parentheses as a link to the top of the
 * Amiga 500 rating leaderboard table; game date (`F j, Y`) in the rightmost column.
 * Returns '' for entries without a game row.
 *
 * @param array{game_id?: int, game?: array<string, mixed>}|array<string, mixed> $entry
 */
function amiga_tournament_videos_wc_game_caption_html(array $entry): string
{
    require_once __DIR__ . '/k2_player_game_row.php';
    require_once __DIR__ . '/k2_rated_game_row.php';
    require_once __DIR__ . '/amiga_rated_game_row.php';
    require_once __DIR__ . '/k2_amiga_country_flag.php';
    require_once __DIR__ . '/amiga_snapshot_url.php';
    require_once __DIR__ . '/lb_player_filters.php';

    $gameRow = $entry['game'] ?? [];
    if (!is_array($gameRow) || $gameRow === []) {
        return '';
    }

    $game = k2_player_game_normalize_row($gameRow);
    $processed = k2_rated_game_is_processed($gameRow);
    $phase = trim((string) ($gameRow['phase'] ?? ''));
    $countryA = trim((string) ($gameRow['country_a'] ?? ''));
    $countryB = trim((string) ($gameRow['country_b'] ?? ''));
    $goalsA = (int) $game['GoalsA'];
    $goalsB = (int) $game['GoalsB'];
    if ($processed) {
        $aWin = k2_rated_game_is_a_win($game);
        $bWin = k2_rated_game_is_b_win($game);
    } else {
        $aWin = $goalsA > $goalsB;
        $bWin = $goalsB > $goalsA;
    }

    // All rating links land on the top of the rating leaderboard table.
    $ratingLbHref = amiga_url_with_context('/amiga/leaderboards/rating.php') . k2_lb_table_anchor_hash();
    $ratingHtml = static function (float $rating, string $side) use ($processed, $ratingLbHref): string {
        if (!$processed) {
            return '';
        }

        return ' <span class="k2-tournament-videos__rating">(<a class="k2-tournament-videos__rating-link" href="'
            . k2_h($ratingLbHref) . '" data-k2-help="' . k2_h(amiga_tournament_videos_spotlight_rating_help($side))
            . '" data-k2-tooltip-hide-title="1">' . k2_h((string) (int) round($rating)) . '</a>)</span>';
    };

    $flagA = $countryA !== '' ? k2_amiga_country_flag_link($countryA, ['class' => 'k2-amiga-tgame-flag']) : '';
    $flagB = $countryB !== '' ? k2_amiga_country_flag_link($countryB, ['class' => 'k2-amiga-tgame-flag']) : '';
    $teamACell = '<span class="k2-amiga-tgame-side k2-amiga-tgame-side--a">' . $flagA
        . k2_amiga_player_link((int) $game['idA'], (string) $game['NameA'])
        . $ratingHtml((float) $game['RatingA'], 'a') . '</span>';
    $teamBCell = '<span class="k2-amiga-tgame-side k2-amiga-tgame-side--b">'
        . k2_amiga_player_link((int) $game['idB'], (string) $game['NameB'])
        . $ratingHtml((float) $game['RatingB'], 'b') . $flagB . '</span>';

    $goalsAClass = $aWin ? 'k2-amiga-tgame-goal--win' : '';
    $goalsBClass = 'k2-table-cell--left' . ($bWin ? ' k2-amiga-tgame-goal--win' : '');
    $goalsACell = $aWin ? '<span class="blue">' . $goalsA . '</span>' : (string) $goalsA;
    $goalsBCell = $bWin ? '<span class="blue">' . $goalsB . '</span>' : (string) $goalsB;
    $phaseTd = $phase !== '' ? '<td class="k2-table-cell--left">' . k2_h($phase) . '</td>' : '';

    return '<table class="k2-table k2-table--tournament-games k2-tournament-videos__caption-table">'
        . '<tbody class="black"><tr>'
        . '<td class="k2-table-cell--left">' . amiga_rated_game_id_html((int) $game['id']) . '</td>'
        . $phaseTd
        . '<td class="k2-table-cell--right k2-amiga-tgame-team k2-amiga-tgame-team--a">' . $teamACell . '</td>'
        . '<td class="' . k2_h($goalsAClass) . '">' . $goalsACell . '</td>'
        . '<td class="' . k2_h($goalsBClass) . '">' . $goalsBCell . '</td>'
        . '<td class="k2-table-cell--left k2-amiga-tgame-team k2-amiga-tgame-team--b">' . $teamBCell . '</td>'
        . '<td class="k2-table-cell--left k2-tournament-videos__caption-date">'
        . amiga_tournament_videos_wc_game_caption_date_html((string) $game['Date']) . '</td>'
        . '</tr></tbody></table>';
}

function amiga_tournament_videos_play_button_html(
    int $tournamentId,
    string $wing,
    string $youtubeId,
    string $spotlightLabel,
    bool $isActive,
    ?int $gameId = null,
    int $startSec = 0,
    string $spotlightHtml = '',
): string {
    require_once __DIR__ . '/amiga_tournament_lib.php';

    $classes = 'k2-tournament-videos__play-btn' . ($isActive ? ' is-active' : '');
    $href = amiga_tournament_href(amiga_tournament_videos_url(
        $tournamentId,
        $wing,
        $youtubeId,
        $gameId,
        $startSec > 0 ? $startSec : null,
        true,
    ));
    $attrs = ' class="' . k2_h($classes) . '"'
        . ' href="' . k2_h($href) . '"'
        . ' data-k2-tv-inpage="1"'
        . ' data-youtube-id="' . k2_h($youtubeId) . '"'
        . ' data-spotlight-label="' . k2_h($spotlightLabel) . '"'
        . ' aria-label="' . k2_h('Play video: ' . $spotlightLabel) . '"';
    if ($spotlightHtml !== '') {
        $attrs .= ' data-spotlight-html="' . k2_h($spotlightHtml) . '"';
    }
    if ($gameId !== null && $gameId > 0) {
        $attrs .= ' data-game-id="' . (int) $gameId . '"';
    }
    if ($startSec > 0) {
        $attrs .= ' data-start-sec="' . (int) $startSec . '"';
    }

    return '<a' . $attrs . '>'
        .         '<span class="k2-tournament-videos__play-glyph" aria-hidden="true">'
        . '<svg width="14" height="14" viewBox="0 0 24 24" focusable="false">'
        . '<path fill="currentColor" d="M8 5v14l11-7z"/>'
        . '</svg></span></a>';
}