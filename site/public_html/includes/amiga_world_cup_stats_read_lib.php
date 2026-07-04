<?php
/**
 * Read path for amiga_world_cup_stats (World Cups hub wing 2).
 *
 * @see docs/amiga-world-cup-stats-table-plan.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_db.php';
require_once __DIR__ . '/amiga_community_stats_lib.php';
require_once __DIR__ . '/amiga_snapshot_context.php';

/** Load time-travel context for World Cup stats pages. */
function amiga_world_cup_stats_context(mysqli $con): AmigaSnapshotContext
{
    return amiga_snapshot_context_from_request($con);
}

/**
 * Year % = this WC's games ÷ realm games in that calendar year at the read cutoff.
 *
 * @see docs/amiga-world-cup-stats-table-plan.md §3.10
 *
 * @param list<array<string, mixed>> $rows
 */
function amiga_world_cup_stats_apply_share_of_year_games(mysqli $con, array &$rows, ?int $cutoffTournamentId): void
{
    if ($rows === [] || $cutoffTournamentId === null || $cutoffTournamentId <= 0) {
        return;
    }

    $years = [];
    foreach ($rows as $row) {
        $year = (int) ($row['calendar_year'] ?? 0);
        if ($year > 0) {
            $years[$year] = true;
        }
    }
    if ($years === []) {
        return;
    }

    $realmGamesByYear = amiga_community_year_realm_games_at_cutoff(
        $con,
        $cutoffTournamentId,
        array_keys($years),
    );

    foreach ($rows as &$row) {
        $year = (int) ($row['calendar_year'] ?? 0);
        $ratedGames = (int) ($row['rated_games'] ?? 0);
        $denominator = $realmGamesByYear[$year] ?? null;
        $row['share_of_year_games'] = amiga_community_share_ratio($ratedGames, (int) ($denominator ?? 0));
    }
    unset($row);
}

/**
 * Rows from amiga_world_cup_stats, filtered at snapshot cutoff when active.
 *
 * Request-scoped cache keyed by cutoff tuple: hub shell (chapter counts) and page
 * body both need these rows and use separate connections — compute once per request.
 *
 * @return list<array<string, mixed>>
 */
function amiga_world_cup_stats_rows(mysqli $con, ?AmigaSnapshotContext $ctx = null): array
{
    static $cache = [];

    $ctx ??= amiga_snapshot_context_peek();
    $cacheKey = 'present';
    if ($ctx instanceof AmigaSnapshotContext && $ctx->isActive()) {
        $cutoffKey = $ctx->cutoff();
        if ($cutoffKey !== null) {
            $cacheKey = $cutoffKey['event_date'] . '|' . $cutoffKey['chrono'] . '|' . $cutoffKey['tournament_id'];
        }
    }
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    return $cache[$cacheKey] = amiga_world_cup_stats_rows_uncached($con, $ctx);
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_world_cup_stats_rows_uncached(mysqli $con, ?AmigaSnapshotContext $ctx): array
{
    $types = '';
    $params = [];
    $cutoffSql = '';
    if ($ctx instanceof AmigaSnapshotContext && $ctx->isActive()) {
        $cutoff = $ctx->cutoff();
        if ($cutoff !== null) {
            $cutoffSql = amiga_snapshot_event_tuple_cutoff_and_sql(
                $cutoff,
                $types,
                $params,
                'w.event_date',
                'w.event_chrono',
                'w.tournament_id',
            );
        }
    }

    $sql = 'SELECT w.* FROM amiga_world_cup_stats w WHERE 1=1' . $cutoffSql
        . ' ORDER BY w.event_date DESC, w.event_chrono DESC, w.tournament_id DESC';

    if ($types === '') {
        $res = $con->query($sql);
        if ($res === false) {
            throw new RuntimeException('world cup stats query: ' . $con->error);
        }
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->free();
        amiga_world_cup_stats_apply_share_of_year_games(
            $con,
            $rows,
            amiga_community_cutoff_tournament_id_for_read($con, $ctx),
        );

        return $rows;
    }

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare world cup stats: ' . $con->error);
    }
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('execute world cup stats: ' . $err);
    }
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $res->free();
    $stmt->close();
    amiga_world_cup_stats_apply_share_of_year_games(
        $con,
        $rows,
        amiga_community_cutoff_tournament_id_for_read($con, $ctx),
    );

    return $rows;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<int>
 */
function amiga_world_cup_stats_collect_player_ids(array $rows): array
{
    $ids = [];
    foreach ($rows as $row) {
        foreach (['gold_player_id', 'silver_player_id', 'bronze_player_id'] as $key) {
            $id = (int) ($row[$key] ?? 0);
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
    }

    return array_keys($ids);
}
