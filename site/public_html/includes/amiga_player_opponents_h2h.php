<?php
/**
 * Amiga Opponents H2H — stored matchup reads, poster, pickers, pair detail.
 *
 * @see docs/amiga-opponents-wing-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_archive_listbox.php';
require_once __DIR__ . '/amiga_player_load.php';
require_once __DIR__ . '/amiga_player_opponents_lib.php';
require_once __DIR__ . '/amiga_player_opponents_load.php';
require_once __DIR__ . '/amiga_player_matchup_lib.php';
require_once __DIR__ . '/amiga_db.php';
require_once __DIR__ . '/amiga_player_games_lib.php';
require_once __DIR__ . '/amiga_player_h2h_pair_lib.php';
require_once __DIR__ . '/amiga_performance_rating.php';
require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/player_opponents_h2h.php';
require_once __DIR__ . '/player_opponents_h2h_charts.php';

function amiga_player_opponents_h2h_parse_opponent_id(mixed $raw, int $playerId): int
{
    return player_opponents_h2h_parse_opponent_id($raw, $playerId);
}

function amiga_player_opponents_h2h_parse_pick_source(mixed $raw): ?string
{
    return player_opponents_h2h_parse_pick_source($raw);
}

/**
 * @return list<array{opponent_id: int, opponent_name: string, games: int}>
 */
function amiga_player_opponents_h2h_played_opponents(
    mysqli $con,
    int $playerId,
    ?AmigaSnapshotContext $ctx = null
): array {
    $playerId = max(0, $playerId);
    if ($playerId <= 0) {
        return [];
    }

    $rows = amiga_player_opponents_matchup_rows($con, $playerId, $ctx);
    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'opponent_id' => (int) $row['opponent_id'],
            'opponent_name' => (string) $row['opponent_name'],
            'games' => (int) $row['games'],
        ];
    }

    return $out;
}

/**
 * @return array{opponent_id: int, opponent_name: string, games: int}|null
 */
function amiga_player_opponents_h2h_resolve_opponent(
    mysqli $con,
    int $playerId,
    int $opponentId,
    ?AmigaSnapshotContext $ctx = null
): ?array {
    if ($opponentId <= 0 || $opponentId === $playerId) {
        return null;
    }

    $identity = amiga_player_identity_row($con, $opponentId);
    if ($identity === null) {
        return null;
    }

    $directed = amiga_player_opponents_h2h_directed_row($con, $playerId, $opponentId, $ctx);
    $games = $directed !== null ? (int) $directed['games'] : 0;

    return [
        'opponent_id' => $opponentId,
        'opponent_name' => (string) $identity['name'],
        'games' => $games,
    ];
}

/**
 * @return array<string, mixed>|null
 */
function amiga_player_opponents_h2h_directed_row(
    mysqli $con,
    int $playerId,
    int $opponentId,
    ?AmigaSnapshotContext $ctx = null
): ?array {
    $raw = amiga_player_matchup_directed_opponent_row($con, $playerId, $opponentId, $ctx);
    if ($raw === null) {
        return null;
    }

    return amiga_player_opponents_normalize_matchup_row($raw);
}

/**
 * @return array{games: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int}|null
 */
function amiga_player_opponents_h2h_pair_record(
    mysqli $con,
    int $playerId,
    int $opponentId,
    ?AmigaSnapshotContext $ctx = null
): ?array {
    $row = amiga_player_opponents_h2h_directed_row($con, $playerId, $opponentId, $ctx);
    if ($row === null || (int) $row['games'] <= 0) {
        return null;
    }

    return [
        'games' => (int) $row['games'],
        'wins' => (int) $row['wins'],
        'draws' => (int) $row['draws'],
        'losses' => (int) $row['losses'],
        'goals_for' => (int) $row['goals_for'],
        'goals_against' => (int) $row['goals_against'],
    ];
}

/**
 * @return array{player_id: int, name: string, display: bool, rank: ?int, rating: mixed, profile_href: string}|null
 */
function amiga_player_opponents_h2h_load_player_card(
    mysqli $con,
    int $playerId,
    ?AmigaSnapshotContext $ctx = null
): ?array {
    $playerId = max(0, $playerId);
    if ($playerId <= 0) {
        return null;
    }

    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();
    $profileHref = k2_amiga_player_profile_href($playerId);

    try {
        $pm = amiga_player_load($con, $playerId, $ctx);
    } catch (RuntimeException) {
        $identity = amiga_player_identity_row($con, $playerId);
        if ($identity === null) {
            return null;
        }

        return [
            'player_id' => (int) $identity['id'],
            'name' => (string) $identity['name'],
            'display' => true,
            'rank' => null,
            'rating' => null,
            'profile_href' => $profileHref,
        ];
    }

    $display = !empty($pm['display']);
    $rank = null;
    if ($display && ($pm['at_cutoff'] ?? true) && (int) ($pm['rank'] ?? 0) > 0) {
        $rank = (int) $pm['rank'];
    }

    return [
        'player_id' => (int) $pm['id'],
        'name' => (string) $pm['name'],
        'display' => $display,
        'rank' => $rank,
        'rating' => ($pm['at_cutoff'] ?? true) ? ($pm['rating'] ?? null) : null,
        'profile_href' => $profileHref,
    ];
}

/**
 * @return array{subject: ?int, opponent: ?int}
 */
function amiga_player_h2h_pair_performance_ratings(
    mysqli $con,
    int $playerId,
    int $opponentId,
    ?AmigaSnapshotContext $ctx = null
): array {
    $playerId = max(0, $playerId);
    $opponentId = max(0, $opponentId);
    if ($playerId < 1 || $opponentId < 1 || $playerId === $opponentId) {
        return ['subject' => null, 'opponent' => null];
    }

    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();
    $types = '';
    $params = [];
    $whereSql = amiga_games_where_clause(
        $playerId,
        '',
        $opponentId,
        0,
        '',
        '',
        '',
        '',
        0,
        0,
        0,
        -1,
        -1,
        -1,
        null,
        $types,
        $params,
        $ctx
    );

    $sql = 'SELECT r.idA, r.RatingA, r.RatingB, r.ActualScore '
        . amiga_rated_games_from_sql()
        . ' WHERE ' . $whereSql
        . ' ORDER BY r.`Date` ASC, r.id ASC';

    $rows = amiga_games_query_all($con, $sql, $types, $params);
    if ($rows === []) {
        return ['subject' => null, 'opponent' => null];
    }

    $subjectPairs = player_h2h_performance_rating_pairs_for_player($rows, $playerId);
    $opponentPairs = player_h2h_performance_rating_pairs_for_player($rows, $opponentId);
    $subjectPerf = amiga_performance_rating_from_pairs($subjectPairs);
    $opponentPerf = amiga_performance_rating_from_pairs($opponentPairs);

    return [
        'subject' => $subjectPerf !== null ? (int) round($subjectPerf) : null,
        'opponent' => $opponentPerf !== null ? (int) round($opponentPerf) : null,
    ];
}

/**
 * @return array<string, mixed>|null
 */
function amiga_player_opponents_h2h_pair_detail_load(
    mysqli $con,
    int $playerId,
    int $opponentId,
    ?AmigaSnapshotContext $ctx = null
): ?array {
    $row = amiga_player_opponents_h2h_directed_row($con, $playerId, $opponentId, $ctx);
    if ($row === null || (int) $row['games'] <= 0) {
        return null;
    }

    $detail = player_opponents_h2h_pair_detail_map_row($row, true);
    if ((int) $detail['games'] < PERFORMANCE_RATING_MIN_GAMES) {
        $detail['perf_rating_subject'] = null;
        $detail['perf_rating_opponent'] = null;

        return $detail;
    }

    $perf = amiga_player_h2h_pair_performance_ratings($con, $playerId, $opponentId, $ctx);
    $detail['perf_rating_subject'] = $perf['subject'];
    $detail['perf_rating_opponent'] = $perf['opponent'];

    return $detail;
}

function amiga_player_opponents_render_h2h_panel(
    mysqli $con,
    int $playerId,
    string $playerName,
    int $selectedOpponentId = 0,
    bool $defaultToTopOpponent = false,
    ?string $pickSource = null,
    ?AmigaSnapshotContext $ctx = null
): void {
    $playerId = max(0, $playerId);
    $playerName = trim($playerName);
    if ($playerName === '') {
        $playerName = '#' . $playerId;
    }

    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();
    $played = amiga_player_opponents_h2h_played_opponents($con, $playerId, $ctx);
    if ($defaultToTopOpponent && $selectedOpponentId <= 0 && $played !== []) {
        $selectedOpponentId = (int) $played[0]['opponent_id'];
    }

    $byAlpha = $played;
    usort(
        $byAlpha,
        static function (array $a, array $b): int {
            return strcasecmp($a['opponent_name'], $b['opponent_name']);
        }
    );

    $pair = $selectedOpponentId > 0
        ? amiga_player_opponents_h2h_resolve_opponent($con, $playerId, $selectedOpponentId, $ctx)
        : null;

    $gamesShowName = $pickSource === 'games' || $pickSource === 'search';
    $alphaShowName = $pickSource === 'alpha';
    $searchUid = 'k2-amiga-h2h-search-' . $playerId;
    $h2hBase = amiga_player_opponents_href($playerId, 'h2h');
    ?>
<div
    class="k2-player-opponents-h2h"
    data-k2-carry-scroll
    data-realm="amiga"
    data-player-id="<?php echo $playerId; ?>"
    data-h2h-base="<?php echo k2_h($h2hBase); ?>"
    <?php if ($pair !== null) { ?>
    data-chart-opponent-id="<?php echo (int) $pair['opponent_id']; ?>"
    data-chart-opponent-name="<?php echo k2_h((string) $pair['opponent_name']); ?>"
    <?php } ?>
>
    <div class="k2-player-opponents-h2h__pickers">
        <div class="k2-player-opponents-h2h__search player-search" role="search">
            <label class="player-search-label" for="<?php echo k2_h($searchUid); ?>">Search</label>
            <input
                id="<?php echo k2_h($searchUid); ?>"
                class="player-search-input k2-header-search__input k2-player-opponents-h2h__search-input"
                type="search"
                maxlength="32"
                autocomplete="off"
                spellcheck="false"
                placeholder="Player name…"
                aria-expanded="false"
                aria-controls="<?php echo k2_h($searchUid); ?>-results"
            />
            <ul
                id="<?php echo k2_h($searchUid); ?>-results"
                class="player-search-results k2-player-opponents-h2h__search-results"
                role="listbox"
                hidden
            ></ul>
        </div>
        <div class="k2-player-opponents-h2h__listbox-wrap">
            <label class="k2-player-opponents-h2h__select-label" for="k2-h2h-games-<?php echo $playerId; ?>-trigger">By games played</label>
            <?php k2_h2h_opponent_listbox_render(
                'k2-h2h-games-' . $playerId,
                (string) $selectedOpponentId,
                $played,
                'Choose opponent by games played',
                'Choose opponent…',
                'No opponents yet',
                $gamesShowName
            ); ?>
        </div>
        <div class="k2-player-opponents-h2h__listbox-wrap">
            <label class="k2-player-opponents-h2h__select-label" for="k2-h2h-alpha-<?php echo $playerId; ?>-trigger">A–Z</label>
            <?php k2_h2h_opponent_listbox_render(
                'k2-h2h-alpha-' . $playerId,
                (string) $selectedOpponentId,
                $byAlpha,
                'Choose opponent A to Z',
                'Choose opponent…',
                'No opponents yet',
                $alphaShowName
            ); ?>
        </div>
    </div>

    <div class="k2-player-opponents-h2h__stage">
        <?php if ($pair === null) { ?>
        <p class="k2-player-opponents-h2h__prompt k2-hub-page-intro">Choose an opponent above to compare head-to-head.</p>
        <?php } else {
            $subjectCard = amiga_player_opponents_h2h_load_player_card($con, $playerId, $ctx);
            $opponentCard = amiga_player_opponents_h2h_load_player_card($con, $pair['opponent_id'], $ctx);
            if ($subjectCard !== null && $opponentCard !== null) {
                $record = $pair['games'] > 0
                    ? amiga_player_opponents_h2h_pair_record($con, $playerId, $pair['opponent_id'], $ctx)
                    : null;
                $games = (int) $pair['games'];
                player_opponents_render_h2h_poster($subjectCard, $opponentCard, $record, $games);
                if ($games > 0) {
                    $detail = amiga_player_opponents_h2h_pair_detail_load($con, $playerId, $pair['opponent_id'], $ctx);
                    if ($detail !== null) {
                        player_opponents_render_h2h_pair_detail($subjectCard, $opponentCard, $detail);
                    }
                    $subjectCard['games_href'] = amiga_player_opponents_games_filtered_href($playerId, $pair['opponent_id']);
                    player_opponents_render_h2h_all_games_link($subjectCard, $opponentCard, $games);
                    $momentGames = amiga_player_h2h_pair_games_rows($con, $playerId, $pair['opponent_id'], $ctx);
                    $momentSlots = player_opponents_h2h_moments_slots(
                        $momentGames,
                        (string) ($subjectCard['name'] ?? ''),
                        (string) ($opponentCard['name'] ?? '')
                    );
                    player_opponents_render_h2h_moments_grid($momentSlots);
                }
            } else { ?>
        <p class="k2-player-opponents-h2h__empty">Could not load player data for this pairing.</p>
        <?php }
        } ?>
    </div>
    <?php if ($played !== []) {
        player_opponents_render_h2h_matchup_charts(
            $playerId,
            false,
            $pair !== null ? (string) $pair['opponent_name'] : null,
            $pair !== null ? (int) $pair['opponent_id'] : null,
            $playerName,
            'amiga'
        );
    } ?>
</div>
    <?php
}