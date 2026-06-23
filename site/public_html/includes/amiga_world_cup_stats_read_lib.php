<?php
/**
 * Read path for amiga_world_cup_stats (World Cups hub wing 2).
 *
 * @see docs/amiga-world-cup-stats-table-plan.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_db.php';
require_once __DIR__ . '/amiga_snapshot_context.php';

/** Load time-travel context for World Cup stats pages. */
function amiga_world_cup_stats_context(mysqli $con): AmigaSnapshotContext
{
    return amiga_snapshot_context_from_request($con);
}

/**
 * Rows from amiga_world_cup_stats, filtered at snapshot cutoff when active.
 *
 * @return list<array<string, mixed>>
 */
function amiga_world_cup_stats_rows(mysqli $con, ?AmigaSnapshotContext $ctx = null): array
{
    $ctx ??= amiga_snapshot_context_peek();
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
