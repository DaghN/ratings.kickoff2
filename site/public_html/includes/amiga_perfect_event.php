<?php
/**
 * Perfect event (undefeated tournament run).
 *
 * @see docs/amiga-perfect-event-policy.md
 */
declare(strict_types=1);

const AMIGA_PERFECT_EVENT_MIN_GAMES = 2;

function amiga_is_perfect_event_from_rollup(int $games, int $wins, int $draws, int $losses): bool
{
    if ($games < AMIGA_PERFECT_EVENT_MIN_GAMES) {
        return false;
    }

    return $losses === 0 && $draws === 0 && $wins === $games;
}

/**
 * WC perfect-event counts per player (snapshot read path; no slice DDL).
 *
 * @return array<int, int> player_id => count
 */
function amiga_wc_perfect_events_by_player(mysqli $con, ?AmigaSnapshotContext $ctx = null): array
{
    require_once __DIR__ . '/amiga_snapshot_context.php';

    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();

    $types = '';
    $params = [];
    $cutoffSql = '';
    if ($ctx->isActive()) {
        $cutoff = $ctx->cutoff();
        if ($cutoff !== null) {
            $cutoffSql = amiga_snapshot_event_tuple_cutoff_and_sql(
                $cutoff,
                $types,
                $params,
                's.event_date',
                's.event_chrono',
                's.tournament_id',
            );
        }
    }

    $sql = 'SELECT s.player_id, COUNT(*) AS perfect_events
            FROM amiga_player_event_snapshots s
            INNER JOIN tournaments t ON t.id = s.tournament_id
            WHERE s.is_perfect_event = 1
              AND t.name REGEXP \'^World Cup[[:space:]]+[^[:space:]]\'
              ' . $cutoffSql . '
            GROUP BY s.player_id';
    if ($types === '') {
        $res = mysqli_query($con, $sql);
        if ($res === false) {
            return [];
        }
    } else {
        $stmt = mysqli_prepare($con, $sql);
        if ($stmt === false) {
            return [];
        }
        $refs = [];
        foreach ($params as $key => $value) {
            $refs[$key] = &$params[$key];
        }
        mysqli_stmt_bind_param($stmt, $types, ...$refs);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
    }

    $out = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $out[(int) $row['player_id']] = (int) ($row['perfect_events'] ?? 0);
        }
        mysqli_free_result($res);
    }
    if (isset($stmt)) {
        mysqli_stmt_close($stmt);
    }

    return $out;
}