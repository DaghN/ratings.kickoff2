<?php
/**
 * Amiga Games hub — shared counts, recent tournament buckets, chapter copy.
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_db.php';
require_once __DIR__ . '/amiga_lb_snapshot_lib.php';
require_once __DIR__ . '/amiga_profile_blocks.php';
require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/amiga_tournament_lib.php';

const AMIGA_GAMES_HUB_RECENT_TOURNAMENT_LIMIT = 5;

function amiga_games_hub_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * @return array{total: int, recent: int}
 * @param array<int, list<array<string, mixed>>>|null $gamesByTournament
 */
function amiga_games_hub_status_counts(
    mysqli $con,
    AmigaSnapshotContext $ctx,
    ?int $totalOverride = null,
    ?array $gamesByTournament = null,
): array {
    $total = $totalOverride ?? amiga_lb_games_count($con, $ctx);
    $recentTournaments = amiga_games_hub_recent_tournaments($con, $ctx);
    if ($gamesByTournament !== null) {
        $recent = 0;
        foreach ($recentTournaments as $row) {
            $tournamentId = (int) ($row['id'] ?? 0);
            if ($tournamentId > 0) {
                $recent += count($gamesByTournament[$tournamentId] ?? []);
            }
        }
    } else {
        $recent = amiga_games_hub_recent_game_count($con, $recentTournaments, $ctx);
    }

    return ['total' => $total, 'recent' => $recent];
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_games_hub_recent_tournaments(
    mysqli $con,
    AmigaSnapshotContext $ctx,
    int $limit = AMIGA_GAMES_HUB_RECENT_TOURNAMENT_LIMIT,
): array {
    static $cache = [];
    $limit = max(1, min(20, $limit));
    $cutoffForKey = $ctx->isActive() ? $ctx->cutoff() : null;
    $cacheKey = ($cutoffForKey === null ? 'present' : $cutoffForKey['event_date'] . '|' . $cutoffForKey['chrono'] . '|' . $cutoffForKey['tournament_id'])
        . '|' . $limit;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $types = '';
    $params = [];
    $cutoffSql = amiga_snapshot_tournament_cutoff_and_sql($ctx, $types, $params);

    $sql = 'SELECT t.id, t.name, t.event_date, t.chrono, t.country,
                   COALESCE(c.game_count, 0) AS game_count
            FROM tournaments t
            LEFT JOIN amiga_tournament_catalog_stats c ON c.tournament_id = t.id
            WHERE ' . amiga_tournament_public_visibility_where('t') . $cutoffSql . '
            ORDER BY COALESCE(t.chrono, 999999) DESC, COALESCE(t.event_date, \'1970-01-01\') DESC, t.name ASC
            LIMIT ' . (int) $limit;

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        return [];
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->free();
    }
    $stmt->close();

    return $cache[$cacheKey] = $rows;
}

/**
 * @param list<array<string, mixed>> $recentTournaments
 */
function amiga_games_hub_recent_game_count(
    mysqli $con,
    array $recentTournaments,
    AmigaSnapshotContext $ctx,
): int {
    if ($recentTournaments === []) {
        return 0;
    }

    if (!$ctx->isActive()) {
        $total = 0;
        foreach ($recentTournaments as $row) {
            $total += (int) ($row['game_count'] ?? 0);
        }

        return $total;
    }

    $ids = array_values(array_filter(
        array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $recentTournaments),
        static fn (int $id): bool => $id > 0,
    ));
    if ($ids === []) {
        return 0;
    }

    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $params = $ids;
    $cutoffTypes = '';
    $cutoffParams = [];
    $cutoffSql = amiga_snapshot_tournament_cutoff_and_sql(
        $ctx,
        $cutoffTypes,
        $cutoffParams,
        't.event_date',
        't.chrono',
        't.id',
    );

    $sql = 'SELECT COUNT(*) AS n FROM amiga_games g '
        . 'INNER JOIN tournaments t ON t.id = g.tournament_id '
        . 'WHERE g.tournament_id IN (' . $placeholders . ')' . $cutoffSql;
    $types .= $cutoffTypes;
    $params = array_merge($params, $cutoffParams);

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        return 0;
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : false;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    return $row !== false ? (int) ($row['n'] ?? 0) : 0;
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_games_hub_recent_games_for_tournament(
    mysqli $con,
    int $tournamentId,
    AmigaSnapshotContext $ctx,
): array {
    if ($tournamentId < 1) {
        return [];
    }

    require_once __DIR__ . '/amiga_realm_games_hub_lib.php';

    $byTournament = amiga_realm_games_hub_fetch_games_by_tournaments($con, [$tournamentId], $ctx);

    return $byTournament[$tournamentId] ?? [];
}

/**
 * @param list<array<string, mixed>> $recentTournaments
 * @return array<int, list<array<string, mixed>>>
 */
function amiga_games_hub_recent_games_by_tournament(
    mysqli $con,
    array $recentTournaments,
    AmigaSnapshotContext $ctx,
): array {
    $ids = array_values(array_filter(
        array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $recentTournaments),
        static fn (int $id): bool => $id > 0,
    ));
    if ($ids === []) {
        return [];
    }

    require_once __DIR__ . '/amiga_realm_games_hub_lib.php';

    return amiga_realm_games_hub_fetch_games_by_tournaments($con, $ids, $ctx);
}

function amiga_games_hub_tournament_section_heading(array $tournamentRow): string
{
    $name = amiga_games_hub_h((string) ($tournamentRow['name'] ?? ''));
    $date = amiga_profile_format_event_date($tournamentRow['event_date'] ?? null);

    return $name . ' &middot; ' . $date;
}

function amiga_games_hub_chapter_list_html(int $totalGames, int $recentGameCount): string
{
    return '<ul class="k2-hub-chapter__list">'
        . '<li><strong>Recent</strong> lists <span class="blue">' . number_format($recentGameCount) . '</span> games from the last 5 tournaments, tournament by tournament.</li>'
        . '<li><strong>Highlights</strong> surfaces all-time spectacles — goal feasts, huge draws, big wins.</li>'
        . '<li><strong>All games</strong> searches the full history with filters and sorting.</li>'
        . '</ul>';
}
