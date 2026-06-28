<?php

declare(strict_types=1);

/**
 * Player profile Videos wing — manifest rows + cross-tournament game index.
 *
 * @see docs/amiga-tournament-videos-policy.md
 * @see docs/k2-embedded-video-page-policy.md
 */

require_once __DIR__ . '/amiga_tournament_videos_lib.php';
require_once __DIR__ . '/k2_amiga_routes.php';
require_once __DIR__ . '/amiga_snapshot_url.php';
require_once __DIR__ . '/amiga_snapshot_context.php';

/** @var array<int, list<array<string, mixed>>>|null */
$GLOBALS['_amiga_player_videos_by_pid'] = null;

/** @return array<int, list<array<string, mixed>>> */
function amiga_player_videos_index_by_player(): array
{
    if ($GLOBALS['_amiga_player_videos_by_pid'] !== null) {
        return $GLOBALS['_amiga_player_videos_by_pid'];
    }

    $index = [];
    foreach (amiga_tournament_videos_manifest()['videos'] as $row) {
        if (!is_array($row) || ($row['kind'] ?? '') !== 'match') {
            continue;
        }
        $gameIds = isset($row['game_ids']) && is_array($row['game_ids']) ? $row['game_ids'] : [];
        if ($gameIds === []) {
            continue;
        }
        foreach (['player_a_id', 'player_b_id'] as $key) {
            $pid = (int) ($row[$key] ?? 0);
            if ($pid < 1) {
                continue;
            }
            $index[$pid][] = $row;
        }
    }

    $GLOBALS['_amiga_player_videos_by_pid'] = $index;

    return $index;
}

function amiga_player_has_videos(int $playerId): bool
{
    if ($playerId < 1) {
        return false;
    }

    return (amiga_player_videos_index_by_player()[$playerId] ?? []) !== [];
}

/** @return list<array<string, mixed>> */
function amiga_player_videos_manifest_rows(int $playerId): array
{
    if ($playerId < 1) {
        return [];
    }

    return amiga_player_videos_index_by_player()[$playerId] ?? [];
}

function amiga_player_videos_url(
    int $playerId,
    ?string $youtubeId = null,
    ?int $gameId = null,
    ?int $startSec = null,
    bool $withPlayerHash = false,
    ?int $opponentId = null,
): string {
    $params = ['id' => $playerId];
    if ($opponentId !== null && $opponentId > 0) {
        $params['opponent'] = $opponentId;
    }
    if ($youtubeId !== null && $youtubeId !== '') {
        $yt = amiga_tournament_videos_sanitize_youtube_id($youtubeId);
        if ($yt !== '') {
            $params['v'] = $yt;
        }
    }
    if ($gameId !== null && $gameId > 0) {
        $params['game'] = $gameId;
    }
    if ($startSec !== null && $startSec > 0) {
        $params['t'] = $startSec;
    }
    $path = k2_amiga_route('amiga-player-videos', $params);
    if ($withPlayerHash) {
        $path .= '#' . AMIGA_TOURNAMENT_VIDEOS_PLAYER_FRAGMENT;
    }

    return $path;
}

/**
 * @param list<int> $gameIds
 * @return array<int, array<string, mixed>>
 */
function amiga_player_videos_games_by_ids(
    mysqli $con,
    int $playerId,
    array $gameIds,
    ?AmigaSnapshotContext $ctx = null,
): array {
    if ($playerId < 1 || $gameIds === []) {
        return [];
    }

    require_once __DIR__ . '/amiga_db.php';

    $gameIds = array_values(array_unique(array_filter(array_map('intval', $gameIds), static fn (int $id): bool => $id > 0)));
    if ($gameIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($gameIds), '?'));
    $cutoffTypes = '';
    $cutoffParams = [];
    $cutoffSql = amiga_snapshot_rated_game_cutoff_and_sql($ctx, $cutoffTypes, $cutoffParams);
    $sql = 'SELECT r.id, r.`Date`, r.idA, r.NameA, r.idB, r.NameB, r.tournament_id, r.phase,
                   r.GoalsA, r.GoalsB, r.RatingA, r.RatingB, r.RatingDifference,
                   r.ExpectedScoreA, r.ExpectedScoreB, r.ActualScore, r.AdjustmentA, r.AdjustmentB,
                   r.NewRatingA, r.NewRatingB, r.SumOfGoals, r.GoalDifference,
                   r.country_a, r.country_b '
        . amiga_rated_games_from_sql()
        . ' WHERE r.id IN (' . $placeholders . ') AND (r.idA = ? OR r.idB = ?)' . $cutoffSql;

    $types = str_repeat('i', count($gameIds)) . 'ii' . $cutoffTypes;
    $params = array_merge($gameIds, [$playerId, $playerId], $cutoffParams);

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
 * @param list<int> $tournamentIds
 * @return array<int, array{name: string, chrono: int, country: string}>
 */
function amiga_player_videos_tournament_meta(mysqli $con, array $tournamentIds): array
{
    $tournamentIds = array_values(array_unique(array_filter(array_map('intval', $tournamentIds), static fn (int $id): bool => $id > 0)));
    if ($tournamentIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($tournamentIds), '?'));
    $types = str_repeat('i', count($tournamentIds));
    $sql = 'SELECT id, name, chrono, country FROM tournaments WHERE id IN (' . $placeholders . ')';
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, $types, ...$tournamentIds);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $out = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $out[(int) $row['id']] = [
                'name' => (string) ($row['name'] ?? ''),
                'chrono' => (int) ($row['chrono'] ?? 0),
                'country' => trim((string) ($row['country'] ?? '')),
            ];
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);

    return $out;
}

function amiga_player_videos_sort_timestamp(array $game, array $tournamentMeta): int
{
    $date = trim((string) ($game['Date'] ?? ''));
    if ($date !== '') {
        $ts = strtotime($date);
        if ($ts !== false) {
            return (int) $ts;
        }
    }

    $tid = (int) ($game['tournament_id'] ?? 0);
    if ($tid > 0 && isset($tournamentMeta[$tid])) {
        return (int) ($tournamentMeta[$tid]['chrono'] ?? 0);
    }

    return 0;
}

/**
 * Cross-tournament game index for one player — reverse chronological (latest first).
 *
 * @return list<array{
 *   game_id: int,
 *   youtube_id: string,
 *   video: array<string, mixed>,
 *   game: array<string, mixed>,
 *   tournament_id: int,
 *   tournament_name: string,
 *   sort_ts: int,
 * }>
 */
function amiga_player_videos_game_index(mysqli $con, int $playerId, ?AmigaSnapshotContext $ctx = null): array
{
    if ($playerId < 1) {
        return [];
    }

    $ctx ??= amiga_snapshot_context_peek();

    $pending = [];
    $gameIds = [];
    foreach (amiga_player_videos_manifest_rows($playerId) as $video) {
        $yt = (string) ($video['youtube_id'] ?? '');
        if ($yt === '') {
            continue;
        }
        $tid = (int) ($video['tournament_id'] ?? 0);
        $ids = isset($video['game_ids']) && is_array($video['game_ids']) ? $video['game_ids'] : [];
        foreach ($ids as $gid) {
            $gid = (int) $gid;
            if ($gid < 1) {
                continue;
            }
            $pending[] = [
                'game_id' => $gid,
                'youtube_id' => $yt,
                'video' => $video,
                'tournament_id' => $tid,
            ];
            $gameIds[] = $gid;
        }
    }

    if ($pending === []) {
        return [];
    }

    $gamesById = amiga_player_videos_games_by_ids($con, $playerId, $gameIds, $ctx);
    if ($gamesById === []) {
        return [];
    }

    $tournamentMeta = amiga_player_videos_tournament_meta(
        $con,
        array_merge(
            array_column($pending, 'tournament_id'),
            array_map(static fn (array $g): int => (int) ($g['tournament_id'] ?? 0), $gamesById),
        ),
    );

    $entries = [];
    foreach ($pending as $item) {
        $gid = $item['game_id'];
        if (!isset($gamesById[$gid])) {
            continue;
        }
        $game = $gamesById[$gid];
        $tid = (int) ($game['tournament_id'] ?? $item['tournament_id'] ?? 0);
        $entries[] = [
            'game_id' => $gid,
            'youtube_id' => $item['youtube_id'],
            'video' => $item['video'],
            'game' => $game,
            'tournament_id' => $tid,
            'tournament_name' => (string) ($tournamentMeta[$tid]['name'] ?? ''),
            'tournament_country' => (string) ($tournamentMeta[$tid]['country'] ?? ''),
            'sort_ts' => amiga_player_videos_sort_timestamp($game, $tournamentMeta),
        ];
    }

    usort(
        $entries,
        static function (array $a, array $b): int {
            if ($a['sort_ts'] !== $b['sort_ts']) {
                return $b['sort_ts'] <=> $a['sort_ts'];
            }

            return $b['game_id'] <=> $a['game_id'];
        },
    );

    return $entries;
}

function amiga_player_videos_opponent_from_request(): int
{
    return max(0, (int) ($_GET['opponent'] ?? 0));
}

/**
 * @param array{game: array<string, mixed>} $entry
 */
function amiga_player_videos_entry_opponent_id(array $entry, int $playerId): int
{
    $game = $entry['game'];
    $idA = (int) ($game['idA'] ?? 0);

    return $idA === $playerId ? (int) ($game['idB'] ?? 0) : (int) ($game['idA'] ?? 0);
}

/**
 * @param array{game: array<string, mixed>} $entry
 */
function amiga_player_videos_entry_opponent_name(array $entry, int $playerId): string
{
    $game = $entry['game'];
    $idA = (int) ($game['idA'] ?? 0);

    return $idA === $playerId ? (string) ($game['NameB'] ?? '') : (string) ($game['NameA'] ?? '');
}

/**
 * @param list<array{game_id: int, youtube_id: string, video: array<string, mixed>, game: array<string, mixed>, tournament_id: int, tournament_name: string, sort_ts: int}> $entries
 * @return list<array{opponent_id: int, opponent_name: string, games: int}>
 */
function amiga_player_videos_opponent_facets(array $entries, int $playerId): array
{
    /** @var array<int, array{opponent_id: int, opponent_name: string, games: int}> $counts */
    $counts = [];
    foreach ($entries as $entry) {
        $opponentId = amiga_player_videos_entry_opponent_id($entry, $playerId);
        if ($opponentId < 1) {
            continue;
        }
        if (!isset($counts[$opponentId])) {
            $counts[$opponentId] = [
                'opponent_id' => $opponentId,
                'opponent_name' => amiga_player_videos_entry_opponent_name($entry, $playerId),
                'games' => 0,
            ];
        }
        $counts[$opponentId]['games']++;
    }

    $rows = array_values($counts);
    usort(
        $rows,
        static function (array $a, array $b): int {
            $nameCmp = strcasecmp($a['opponent_name'], $b['opponent_name']);
            if ($nameCmp !== 0) {
                return $nameCmp;
            }

            return $a['opponent_id'] <=> $b['opponent_id'];
        },
    );

    return $rows;
}

/**
 * @param list<array{opponent_id: int, opponent_name: string, games: int}> $facets
 */
function amiga_player_videos_validate_opponent_filter(int $opponentId, array $facets): int
{
    if ($opponentId < 1) {
        return 0;
    }

    foreach ($facets as $row) {
        if ((int) $row['opponent_id'] === $opponentId) {
            return $opponentId;
        }
    }

    return 0;
}

/**
 * @param list<array{game_id: int, youtube_id: string, video: array<string, mixed>, game: array<string, mixed>, tournament_id: int, tournament_name: string, sort_ts: int}> $entries
 * @return list<array{game_id: int, youtube_id: string, video: array<string, mixed>, game: array<string, mixed>, tournament_id: int, tournament_name: string, sort_ts: int}>
 */
function amiga_player_videos_filter_by_opponent(array $entries, int $playerId, int $opponentId): array
{
    if ($opponentId < 1) {
        return $entries;
    }

    return array_values(array_filter(
        $entries,
        static fn (array $entry): bool => amiga_player_videos_entry_opponent_id($entry, $playerId) === $opponentId,
    ));
}

/**
 * @param list<array{opponent_id: int, opponent_name: string, games: int}> $facets
 * @return list<array{value: string, label: string, meta: string}>
 */
function amiga_player_videos_opponent_listbox_choices(array $facets): array
{
    $choices = [['value' => '0', 'label' => '', 'meta' => '']];
    foreach ($facets as $row) {
        $choices[] = [
            'value' => (string) (int) $row['opponent_id'],
            'label' => (string) $row['opponent_name'],
            'meta' => (string) (int) $row['games'],
        ];
    }

    return $choices;
}

/**
 * @param list<array{game_id: int, youtube_id: string, video: array<string, mixed>, game: array<string, mixed>, tournament_id: int, tournament_name: string, sort_ts: int}> $entries
 * @return array{
 *   entry: array{game_id: int, youtube_id: string, video: array<string, mixed>, game: array<string, mixed>, tournament_id: int, tournament_name: string, sort_ts: int}|null,
 *   youtube_id: string,
 *   label: string,
 *   start_sec: int,
 *   highlight_row: bool,
 * }
 */
function amiga_player_videos_spotlight_state(array $entries): array
{
    $params = amiga_tournament_videos_wc_request_params();
    $state = amiga_tournament_videos_wc_games_spotlight_state(
        $entries,
        $params['v'] !== '' ? $params['v'] : null,
        $params['game'] > 0 ? $params['game'] : null,
        $params['start_sec'],
    );

    return $state;
}

function amiga_player_videos_play_button_html(
    int $playerId,
    string $youtubeId,
    string $spotlightLabel,
    bool $isActive,
    ?int $gameId = null,
    int $startSec = 0,
    string $spotlightHtml = '',
    int $opponentFilter = 0,
): string {
    $classes = 'k2-tournament-videos__play-btn' . ($isActive ? ' is-active' : '');
    $href = amiga_player_videos_url(
        $playerId,
        $youtubeId,
        $gameId,
        $startSec > 0 ? $startSec : null,
        true,
        $opponentFilter > 0 ? $opponentFilter : null,
    );
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
        . '<span class="k2-tournament-videos__play-glyph" aria-hidden="true">'
        . '<svg width="14" height="14" viewBox="0 0 24 24" focusable="false">'
        . '<path fill="currentColor" d="M8 5v14l11-7z"/>'
        . '</svg></span></a>';
}