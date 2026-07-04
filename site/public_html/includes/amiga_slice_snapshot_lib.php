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
 * ORDER BY clause for WC player LB sub-wings (must match default sort + skip-initial-sort).
 *
 * @see docs/amiga-world-cups-leaderboard-policy.md §6.1
 */
function amiga_lb_wc_slice_order_sql(string $view, string $alias = 'wcs'): string
{
    $a = $alias;
    $player = "{$a}.player_id ASC";

    switch ($view) {
        case 'results':
            return "{$a}.points DESC, {$a}.games DESC, {$a}.wins DESC, {$player}";
        case 'goals':
            return "{$a}.goals_for DESC, {$a}.games DESC, {$player}";
        case 'dds':
            return "{$a}.double_digits DESC, {$a}.games DESC, {$player}";
        case 'opponents':
            return "{$a}.different_opponents DESC, {$a}.games DESC, {$player}";
        case 'honours':
        default:
            return "{$a}.gold DESC, {$a}.silver DESC, {$a}.bronze DESC, {$a}.podiums DESC, {$a}.tournaments_played DESC, {$player}";
    }
}

/**
 * Present-day WC slice stats for LB wings (eligibility: tournaments_played > 0).
 *
 * Sub-wing tables use k2-table client sort (skip-initial-sort); SQL order must match each wing default.
 *
 * @return list<array<string, mixed>>
 */
function amiga_lb_wc_slice_rows_present(mysqli $con, string $view = 'honours'): array
{
    static $cache = [];
    if (isset($cache[$view])) {
        return $cache[$view];
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
            FROM amiga_player_slice_totals wcs
            WHERE wcs.slice_key = ?
              AND wcs.tournaments_played > 0
            ORDER BY ' . amiga_lb_wc_slice_order_sql($view, 'wcs') . '
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

    return $cache[$view] = $rows;
}

/**
 * WC slice stats at cutoff — latest at_event row per player with tournaments_played > 0.
 *
 * Request-scoped cache is keyed by cutoff + sub-wing (sort order is wing-specific).
 *
 * @return list<array<string, mixed>>
 */
function amiga_lb_wc_slice_rows_at_cutoff(mysqli $con, AmigaSnapshotContext $ctx, string $view = 'honours'): array
{
    if (!$ctx->isActive()) {
        return amiga_lb_wc_slice_rows_present($con, $view);
    }

    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return [];
    }

    static $cache = [];
    $cacheKey = $cutoff['event_date'] . '|' . $cutoff['chrono'] . '|' . $cutoff['tournament_id'] . '|' . $view;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
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
            ' . amiga_lb_wc_player_slice_from_sql('wcs') . '
            WHERE wcs.tournaments_played > 0
            ORDER BY ' . amiga_lb_wc_slice_order_sql($view, 'wcs') . '
';

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        return [];
    }
    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tournamentId = $cutoff['tournament_id'];
    $stmt->bind_param('ssdis', $sliceKey, $eventDate, $chrono, $tournamentId, $sliceKey);
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

    return $cache[$cacheKey] = $rows;
}

function amiga_lb_wc_slice_player_count(mysqli $con, AmigaSnapshotContext $ctx): int
{
    return count(amiga_lb_wc_slice_rows_at_cutoff($con, $ctx));
}
