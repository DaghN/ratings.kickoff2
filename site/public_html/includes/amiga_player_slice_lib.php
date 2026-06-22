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

    return ['sql' => $sql, 'types' => 'sddi'];
}
