<?php
/**
 * Amiga World Cups leaderboard read path (honours · results · goals).
 *
 * @see docs/amiga-world-cups-leaderboard-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_slice_snapshot_lib.php';
require_once __DIR__ . '/amiga_lb_snapshot_lib.php';
require_once __DIR__ . '/amiga_player_current_lib.php';

/**
 * @param list<array<string, mixed>> $sliceRows
 * @return list<array<string, mixed>>
 */
function amiga_wc_lb_attach_player_meta_present(mysqli $con, array $sliceRows): array
{
    if ($sliceRows === []) {
        return [];
    }

    $ids = [];
    foreach ($sliceRows as $row) {
        $ids[] = (int) $row['player_id'];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $sql = 'SELECT p.id AS player_id,
                   p.name AS player_name,
                   p.country,
                   COALESCE(c.Rating, 0) AS rating
            FROM amiga_players p
            LEFT JOIN amiga_player_current c ON c.player_id = p.id
            WHERE p.id IN (' . $placeholders . ')';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        return [];
    }
    $stmt->bind_param($types, ...$ids);
    if (!$stmt->execute()) {
        $stmt->close();

        return [];
    }
    $result = $stmt->get_result();
    $metaByPlayer = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $metaByPlayer[(int) $row['player_id']] = $row;
        }
        $result->free();
    }
    $stmt->close();

    $rows = [];
    foreach ($sliceRows as $sliceRow) {
        $pid = (int) $sliceRow['player_id'];
        if (!isset($metaByPlayer[$pid])) {
            continue;
        }
        $rows[] = array_merge($sliceRow, $metaByPlayer[$pid]);
    }

    return $rows;
}

/**
 * @param list<array<string, mixed>> $sliceRows
 * @return list<array<string, mixed>>
 */
function amiga_wc_lb_attach_player_meta_at_cutoff(
    mysqli $con,
    AmigaSnapshotContext $ctx,
    array $sliceRows
): array {
    if ($sliceRows === []) {
        return [];
    }

    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return [];
    }

    $ids = [];
    foreach ($sliceRows as $row) {
        $ids[] = (int) $row['player_id'];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    $sql = 'SELECT snap.player_id,
                   p.name AS player_name,
                   p.country,
                   COALESCE(snap.Rating, 0) AS rating
            FROM (
                SELECT x.player_id, x.Rating
                FROM (
                    SELECT s.player_id, s.Rating,
                           ROW_NUMBER() OVER (
                               PARTITION BY s.player_id
                               ORDER BY s.event_date DESC, s.event_chrono DESC, s.tournament_id DESC
                           ) AS rn
                    FROM amiga_player_event_snapshots s
                    WHERE (s.event_date, s.event_chrono, s.tournament_id) <= (?, ?, ?)
                      AND s.player_id IN (' . $placeholders . ')
                ) x
                WHERE x.rn = 1
            ) snap
            INNER JOIN amiga_players p ON p.id = snap.player_id';

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        return [];
    }
    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tournamentId = $cutoff['tournament_id'];
    $bindTypes = 'sdi' . $types;
    $bindParams = array_merge([$eventDate, $chrono, $tournamentId], $ids);
    $stmt->bind_param($bindTypes, ...$bindParams);
    if (!$stmt->execute()) {
        $stmt->close();

        return [];
    }
    $result = $stmt->get_result();
    $metaByPlayer = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $metaByPlayer[(int) $row['player_id']] = $row;
        }
        $result->free();
    }
    $stmt->close();

    $rows = [];
    foreach ($sliceRows as $sliceRow) {
        $pid = (int) $sliceRow['player_id'];
        if (!isset($metaByPlayer[$pid])) {
            continue;
        }
        $rows[] = array_merge($sliceRow, $metaByPlayer[$pid]);
    }

    return $rows;
}

/**
 * World Cups honours sub-wing rows (WCs + podium medals).
 *
 * @return list<array<string, mixed>>
 */
function amiga_wc_lb_rows_for_view(mysqli $con, AmigaSnapshotContext $ctx, string $view): array
{
    $allowed = ['honours', 'results', 'goals', 'dds', 'opponents'];
    if (!in_array($view, $allowed, true)) {
        $view = 'honours';
    }

    if ($ctx->isActive()) {
        $sliceRows = amiga_lb_wc_slice_rows_at_cutoff($con, $ctx, $view);

        $rows = amiga_wc_lb_attach_player_meta_at_cutoff($con, $ctx, $sliceRows);
    } else {
        $sliceRows = amiga_lb_wc_slice_rows_present($con, $view);

        $rows = amiga_wc_lb_attach_player_meta_present($con, $sliceRows);
    }

    if ($view === 'honours' && $rows !== []) {
        require_once __DIR__ . '/amiga_perfect_event.php';
        $perfectByPlayer = amiga_wc_perfect_events_by_player($con, $ctx);
        foreach ($rows as $index => $row) {
            $pid = (int) $row['player_id'];
            $rows[$index]['wc_perfect_events'] = $perfectByPlayer[$pid] ?? 0;
        }
    }

    return $rows;
}

function amiga_wc_honours_leaderboard_rows(mysqli $con, AmigaSnapshotContext $ctx): array
{
    return amiga_wc_lb_rows_for_view($con, $ctx, 'honours');
}

function amiga_wc_honours_player_count(mysqli $con, AmigaSnapshotContext $ctx): int
{
    return amiga_lb_wc_slice_player_count($con, $ctx);
}

/**
 * Shared WC slice rows with player meta (all sub-wings).
 *
 * @return list<array<string, mixed>>
 */
/** @deprecated Use amiga_wc_lb_rows_for_view() with the active sub-wing id. */
function amiga_wc_lb_base_rows(mysqli $con, AmigaSnapshotContext $ctx): array
{
    return amiga_wc_honours_leaderboard_rows($con, $ctx);
}

/** WC match points per game (3/1/0). */
function amiga_wc_lb_points_per_game(int $points, int $games): ?float
{
    if ($games <= 0) {
        return null;
    }

    return $points / $games;
}

function amiga_wc_lb_goals_per_game(int $goals, int $games): ?float
{
    if ($games <= 0) {
        return null;
    }

    return $goals / $games;
}

/** Draws as half a win: (wins + 0.5 × draws) ÷ games. */
function amiga_wc_lb_win_rate(int $wins, int $draws, int $games): ?float
{
    if ($games <= 0) {
        return null;
    }

    return ($wins + 0.5 * $draws) / $games;
}
