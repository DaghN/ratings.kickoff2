<?php
/**
 * World Cup slice leaderboard base rows — present + time travel.
 *
 * @see docs/amiga-world-cups-leaderboard-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/amiga_player_slice_lib.php';

/**
 * V2 slice stat columns (present on totals + at_event).
 *
 * @return list<string>
 */
function amiga_lb_wc_slice_v2_column_names(): array
{
    return [
        'goal_ratio',
        'most_goals_scored',
        'most_goals_conceded',
        'biggest_win_difference',
        'biggest_loss_difference',
        'biggest_sum_of_goals',
        'biggest_draw_sum',
        'double_digits',
        'clean_sheets',
        'double_digits_ratio',
        'clean_sheets_ratio',
        'double_digits_conceded',
        'clean_sheets_conceded',
        'double_digits_conceded_ratio',
        'clean_sheets_conceded_ratio',
        'opponent_countries_faced',
        'opponent_countries_beaten',
        'different_opponents',
        'different_victims',
        'double_digits_victims',
        'clean_sheets_victims',
    ];
}

function amiga_lb_wc_slice_v2_select_sql(string $alias): string
{
    $parts = [];
    foreach (amiga_lb_wc_slice_v2_column_names() as $col) {
        $parts[] = "{$alias}.{$col}";
    }

    return implode(",\n                   ", $parts);
}

/**
 * Present-day WC slice stats for LB wings (eligibility: tournaments_played > 0).
 *
 * @return list<array<string, mixed>>
 */
function amiga_lb_wc_slice_rows_present(mysqli $con): array
{
    $sliceKey = amiga_slice_key_world_cup();
    $sql = 'SELECT wcs.player_id,
                   wcs.tournaments_played AS wc_played,
                   wcs.gold AS wc_gold,
                   wcs.silver AS wc_silver,
                   wcs.bronze AS wc_bronze,
                   wcs.podiums AS wc_podiums,
                   wcs.games,
                   wcs.wins,
                   wcs.draws,
                   wcs.losses,
                   wcs.goals_for,
                   wcs.goals_against,
                   wcs.points,
                   ' . amiga_lb_wc_slice_v2_select_sql('wcs') . '
            FROM amiga_player_slice_totals wcs
            WHERE wcs.slice_key = ?
              AND wcs.tournaments_played > 0
            ORDER BY wcs.gold DESC,
                     wcs.silver DESC,
                     wcs.bronze DESC,
                     wcs.podiums DESC,
                     wcs.tournaments_played DESC,
                     wcs.player_id ASC';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        return [];
    }
    $stmt->bind_param('s', $sliceKey);
    if (!$stmt->execute()) {
        $stmt->close();

        return [];
    }
    $result = $stmt->get_result();
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }
    $stmt->close();

    return $rows;
}

/**
 * WC slice stats at cutoff — latest at_event row per player with tournaments_played > 0.
 *
 * @return list<array<string, mixed>>
 */
function amiga_lb_wc_slice_rows_at_cutoff(mysqli $con, AmigaSnapshotContext $ctx): array
{
    if (!$ctx->isActive()) {
        return amiga_lb_wc_slice_rows_present($con);
    }

    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return [];
    }

    $sliceKey = amiga_slice_key_world_cup();
    $sql = 'SELECT wcs.player_id,
                   wcs.tournaments_played AS wc_played,
                   wcs.gold AS wc_gold,
                   wcs.silver AS wc_silver,
                   wcs.bronze AS wc_bronze,
                   wcs.podiums AS wc_podiums,
                   wcs.games,
                   wcs.wins,
                   wcs.draws,
                   wcs.losses,
                   wcs.goals_for,
                   wcs.goals_against,
                   wcs.points,
                   ' . amiga_lb_wc_slice_v2_select_sql('wcs') . '
            FROM (
                SELECT x.player_id,
                       x.tournaments_played,
                       x.gold,
                       x.silver,
                       x.bronze,
                       x.podiums,
                       x.games,
                       x.wins,
                       x.draws,
                       x.losses,
                       x.goals_for,
                       x.goals_against,
                       x.points,
                       ' . str_replace('wcs.', 'x.', amiga_lb_wc_slice_v2_select_sql('x')) . '
                FROM (
                    SELECT s.*,
                           ROW_NUMBER() OVER (
                               PARTITION BY s.player_id
                               ORDER BY s.event_date DESC, s.event_chrono DESC, s.as_of_tournament_id DESC
                           ) AS rn
                    FROM amiga_player_slice_at_event s
                    WHERE s.slice_key = ?
                      AND (s.event_date, s.event_chrono, s.as_of_tournament_id) <= (?, ?, ?)
                ) x
                WHERE x.rn = 1 AND x.tournaments_played > 0
            ) wcs
            ORDER BY wcs.gold DESC,
                     wcs.silver DESC,
                     wcs.bronze DESC,
                     wcs.podiums DESC,
                     wcs.tournaments_played DESC,
                     wcs.player_id ASC';

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        return [];
    }
    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tournamentId = $cutoff['tournament_id'];
    $stmt->bind_param('ssdi', $sliceKey, $eventDate, $chrono, $tournamentId);
    if (!$stmt->execute()) {
        $stmt->close();

        return [];
    }
    $result = $stmt->get_result();
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }
    $stmt->close();

    return $rows;
}

function amiga_lb_wc_slice_player_count(mysqli $con, AmigaSnapshotContext $ctx): int
{
    if (!$ctx->isActive()) {
        $sliceKey = amiga_slice_key_world_cup();
        $stmt = $con->prepare(
            'SELECT COUNT(*) AS n FROM amiga_player_slice_totals WHERE slice_key = ? AND tournaments_played > 0'
        );
        if ($stmt === false) {
            return 0;
        }
        $stmt->bind_param('s', $sliceKey);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : false;
        if ($res) {
            $res->free();
        }
        $stmt->close();

        return $row !== false ? (int) ($row['n'] ?? 0) : 0;
    }

    return count(amiga_lb_wc_slice_rows_at_cutoff($con, $ctx));
}
