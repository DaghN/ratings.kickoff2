<?php
/**
 * World Cup player slice read helpers (present + time travel).
 *
 * @see docs/amiga-world-cups-leaderboard-policy.md
 */
declare(strict_types=1);

function amiga_slice_key_world_cup(): string
{
    return 'world_cup';
}

/** SQL column aliases mapping slice → legacy wc_* LB names. */
function amiga_slice_wc_lb_select_sql(string $alias = 'wcs'): string
{
    return 'COALESCE(' . $alias . '.tournaments_played, 0) AS wc_played, '
        . 'COALESCE(' . $alias . '.gold, 0) AS wc_gold, '
        . 'COALESCE(' . $alias . '.silver, 0) AS wc_silver, '
        . 'COALESCE(' . $alias . '.bronze, 0) AS wc_bronze, '
        . 'COALESCE(' . $alias . '.podiums, 0) AS wc_podiums';
}

function amiga_slice_present_join_sql(string $playerIdExpr): string
{
    return 'LEFT JOIN amiga_player_slice_totals wcs ON wcs.player_id = ' . $playerIdExpr
        . " AND wcs.slice_key = '" . amiga_slice_key_world_cup() . "'";
}

/**
 * Latest world_cup slice row per player on or before cutoff (chrono tuple).
 *
 * @return array{sql: string, types: string}
 */
function amiga_slice_at_cutoff_join_sql(): array
{
    $sliceKey = amiga_slice_key_world_cup();
    $sql = 'LEFT JOIN ('
        . '  SELECT x.player_id, x.tournaments_played, x.gold, x.silver, x.bronze, x.podiums '
        . '  FROM ('
        . '    SELECT s.player_id, s.tournaments_played, s.gold, s.silver, s.bronze, s.podiums, '
        . '           ROW_NUMBER() OVER ('
        . '             PARTITION BY s.player_id '
        . '             ORDER BY s.event_date DESC, s.event_chrono DESC, s.as_of_tournament_id DESC'
        . '           ) AS rn '
        . '    FROM amiga_player_slice_at_event s '
        . '    WHERE s.slice_key = ? '
        . '      AND (s.event_date, s.event_chrono, s.as_of_tournament_id) <= (?, ?, ?)'
        . '  ) x '
        . '  WHERE x.rn = 1'
        . ') wcs ON wcs.player_id = t.player_id';

    return ['sql' => $sql, 'types' => 'ssdi'];
}

/**
 * World Cup podium medal counts for one player (present or snapshot at cutoff).
 *
 * @return array{wc_gold: int, wc_silver: int, wc_bronze: int}
 */
function amiga_player_wc_medal_counts(mysqli $con, int $playerId, ?AmigaSnapshotContext $ctx = null): array
{
    $zero = ['wc_gold' => 0, 'wc_silver' => 0, 'wc_bronze' => 0];
    if ($playerId < 1) {
        return $zero;
    }

    require_once __DIR__ . '/amiga_snapshot_context.php';
    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();

    if (!$ctx->isActive()) {
        $sliceKey = amiga_slice_key_world_cup();
        $sql = 'SELECT COALESCE(gold, 0) AS wc_gold, COALESCE(silver, 0) AS wc_silver, COALESCE(bronze, 0) AS wc_bronze
                FROM amiga_player_slice_totals
                WHERE player_id = ? AND slice_key = ?
                LIMIT 1';
        $stmt = $con->prepare($sql);
        if ($stmt === false) {
            return $zero;
        }
        $stmt->bind_param('is', $playerId, $sliceKey);
        if (!$stmt->execute()) {
            $stmt->close();

            return $zero;
        }
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : false;
        if ($res) {
            $res->free();
        }
        $stmt->close();

        if ($row === false) {
            return $zero;
        }

        return [
            'wc_gold' => (int) ($row['wc_gold'] ?? 0),
            'wc_silver' => (int) ($row['wc_silver'] ?? 0),
            'wc_bronze' => (int) ($row['wc_bronze'] ?? 0),
        ];
    }

    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return $zero;
    }

    $sliceKey = amiga_slice_key_world_cup();
    $sql = 'SELECT COALESCE(x.gold, 0) AS wc_gold, COALESCE(x.silver, 0) AS wc_silver, COALESCE(x.bronze, 0) AS wc_bronze
            FROM (
                SELECT s.gold, s.silver, s.bronze,
                       ROW_NUMBER() OVER (
                           ORDER BY s.event_date DESC, s.event_chrono DESC, s.as_of_tournament_id DESC
                       ) AS rn
                FROM amiga_player_slice_at_event s
                WHERE s.slice_key = ?
                  AND s.player_id = ?
                  AND (s.event_date, s.event_chrono, s.as_of_tournament_id) <= (?, ?, ?)
            ) x
            WHERE x.rn = 1
            LIMIT 1';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        return $zero;
    }

    $eventDate = (string) $cutoff['event_date'];
    $chrono = (float) $cutoff['chrono'];
    $tournamentId = (int) $cutoff['tournament_id'];
    $stmt->bind_param('sisdi', $sliceKey, $playerId, $eventDate, $chrono, $tournamentId);
    if (!$stmt->execute()) {
        $stmt->close();

        return $zero;
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : false;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    if ($row === false) {
        return $zero;
    }

    return [
        'wc_gold' => (int) ($row['wc_gold'] ?? 0),
        'wc_silver' => (int) ($row['wc_silver'] ?? 0),
        'wc_bronze' => (int) ($row['wc_bronze'] ?? 0),
    ];
}
