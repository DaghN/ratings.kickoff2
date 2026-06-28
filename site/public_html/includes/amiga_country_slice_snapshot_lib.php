<?php
/**
 * World Cup country slice leaderboard rows — present + time travel.
 *
 * @see docs/amiga-world-cups-country-slice-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/amiga_player_slice_lib.php';

/** @return list<string> */
function amiga_lb_wc_country_stat_column_names(): array
{
    return [
        'players',
        'wc_participations',
        'wc_participations_per_player',
        'games_per_player',
        'domestic_games',
        'domestic_game_share',
        'international_games',
        'international_game_share',
        'games_share',
        'goals_share',
        'realm_wc_tournament_count',
        'realm_wc_player_games',
        'realm_wc_goals_for',
        'tournaments_with_nation',
        'gold',
        'silver',
        'bronze',
        'podiums',
        'games',
        'wins',
        'draws',
        'losses',
        'points',
        'points_per_realm_wc',
        'win_rate',
        'average_opponent_rating',
        'performance_rating',
        'goals_for',
        'goals_against',
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

function amiga_lb_wc_country_select_sql(string $alias): string
{
    $parts = [];
    foreach (amiga_lb_wc_country_stat_column_names() as $col) {
        $parts[] = "{$alias}.{$col}";
    }

    return implode(",\n                   ", $parts);
}

function amiga_lb_wc_country_order_sql(string $view, string $alias = 'ccs'): string
{
    $a = $alias;
    $token = "{$a}.country_token ASC";

    switch ($view) {
        case 'results':
            return "{$a}.points DESC, {$a}.games DESC, {$a}.wins DESC, {$token}";
        case 'participation':
            return "{$a}.wc_participations DESC, {$a}.players DESC, {$a}.games DESC, {$token}";
        case 'goals':
            return "{$a}.goals_for DESC, {$a}.games DESC, {$token}";
        case 'dds':
            return "{$a}.double_digits DESC, {$a}.games DESC, {$token}";
        case 'opponents':
            return "{$a}.different_opponents DESC, {$a}.games DESC, {$token}";
        case 'honours':
        default:
            return "{$a}.gold DESC, {$a}.silver DESC, {$a}.bronze DESC, {$a}.podiums DESC, {$a}.tournaments_with_nation DESC, {$token}";
    }
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_lb_wc_country_rows_present(mysqli $con, string $view = 'honours'): array
{
    $sliceKey = amiga_slice_key_world_cup();
    $sql = 'SELECT ccs.country_token,
                   ' . amiga_lb_wc_country_select_sql('ccs') . '
            FROM amiga_country_slice_totals ccs
            WHERE ccs.slice_key = ?
              AND ccs.players > 0
            ORDER BY ' . amiga_lb_wc_country_order_sql($view) . '
';
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
 * @return list<array<string, mixed>>
 */
function amiga_lb_wc_country_rows_at_cutoff(mysqli $con, AmigaSnapshotContext $ctx, string $view = 'honours'): array
{
    if (!$ctx->isActive()) {
        return amiga_lb_wc_country_rows_present($con, $view);
    }

    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return [];
    }

    $sliceKey = amiga_slice_key_world_cup();
    $sql = 'SELECT ranked.country_token,
                   ' . str_replace('ccs.', 'ranked.', amiga_lb_wc_country_select_sql('ccs')) . '
            FROM (
                SELECT x.*,
                       ROW_NUMBER() OVER (
                           PARTITION BY x.country_token
                           ORDER BY x.event_date DESC, x.event_chrono DESC, x.as_of_tournament_id DESC
                       ) AS rn
                FROM amiga_country_slice_at_event x
                WHERE x.slice_key = ?
                  AND (x.event_date, x.event_chrono, x.as_of_tournament_id)
                      <= (?, ?, ?)
            ) ranked
            WHERE ranked.rn = 1
              AND ranked.players > 0
            ORDER BY ' . amiga_lb_wc_country_order_sql($view, 'ranked') . '
';
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

function amiga_lb_wc_country_count(mysqli $con, AmigaSnapshotContext $ctx): int
{
    if ($ctx->isActive()) {
        return count(amiga_lb_wc_country_rows_at_cutoff($con, $ctx));
    }

    $sliceKey = amiga_slice_key_world_cup();
    $sql = 'SELECT COUNT(*) AS n FROM amiga_country_slice_totals WHERE slice_key = ? AND players > 0';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        return 0;
    }
    $stmt->bind_param('s', $sliceKey);
    if (!$stmt->execute()) {
        $stmt->close();

        return 0;
    }
    $result = $stmt->get_result();
    $n = 0;
    if ($result && ($row = $result->fetch_assoc())) {
        $n = (int) ($row['n'] ?? 0);
        $result->free();
    }
    $stmt->close();

    return $n;
}
