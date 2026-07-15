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
        'opponent_countries_beaten_by',
        'different_opponents',
        'different_victims',
        'double_digits_victims',
        'clean_sheets_victims',
        'different_culprits',
        'double_digits_culprits',
        'clean_sheets_culprits',
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
 * Sortable column index → SQL expression for WC player LB SSR order (wcs alias).
 *
 * Player name and Elo are attached after the slice query — not mapped here.
 *
 * @return array<int, string>
 */
function amiga_lb_wc_slice_order_column_map(string $view, string $alias = 'wcs'): array
{
    $a = $alias;
    $ratio = static fn (string $col): string => "(CASE WHEN {$a}.{$col} IS NULL OR {$a}.{$col} < 0 THEN NULL ELSE {$a}.{$col} END)";
    $perGame = static fn (string $num): string => "({$a}.{$num} / NULLIF({$a}.games, 0))";
    $winRate = "(({$a}.wins + 0.5 * {$a}.draws) / NULLIF({$a}.games, 0))";

    switch ($view) {
        case 'results':
            return [
                3 => "{$a}.tournaments_played",
                4 => "{$a}.games",
                5 => "{$a}.wins",
                6 => "{$a}.draws",
                7 => "{$a}.losses",
                8 => "{$a}.points",
                9 => $perGame('points'),
                10 => $winRate,
            ];
        case 'goals':
            return [
                3 => "{$a}.games",
                4 => "{$a}.goals_for",
                5 => "{$a}.goals_against",
                6 => "({$a}.goals_for - {$a}.goals_against)",
                7 => $perGame('goals_for'),
                8 => $perGame('goals_against'),
                9 => "(({$a}.goals_for - {$a}.goals_against) / NULLIF({$a}.games, 0))",
                10 => $ratio('goal_ratio'),
                11 => "{$a}.most_goals_scored",
                12 => "{$a}.most_goals_conceded",
                13 => "{$a}.biggest_win_difference",
                14 => "{$a}.biggest_loss_difference",
                15 => "{$a}.biggest_sum_of_goals",
                16 => "{$a}.biggest_draw_sum",
            ];
        case 'dds':
            return [
                3 => "{$a}.games",
                4 => "{$a}.double_digits",
                5 => "{$a}.clean_sheets",
                6 => $ratio('double_digits_ratio'),
                7 => $ratio('clean_sheets_ratio'),
                8 => "{$a}.double_digits_conceded",
                9 => "{$a}.clean_sheets_conceded",
                10 => $ratio('double_digits_conceded_ratio'),
                11 => $ratio('clean_sheets_conceded_ratio'),
            ];
        case 'opponents':
            return [
                3 => "{$a}.games",
                4 => "{$a}.different_opponents",
                5 => "{$a}.different_victims",
                6 => "{$a}.different_culprits",
                7 => "{$a}.double_digits_victims",
                8 => "{$a}.double_digits_culprits",
                9 => "{$a}.clean_sheets_victims",
                10 => "{$a}.clean_sheets_culprits",
                11 => "{$a}.opponent_countries_faced",
                12 => "{$a}.opponent_countries_beaten",
                13 => "{$a}.opponent_countries_beaten_by",
            ];
        case 'honours':
        default:
            return [
                3 => "{$a}.tournaments_played",
                4 => "{$a}.gold",
                5 => "{$a}.silver",
                6 => "{$a}.bronze",
                7 => "{$a}.podiums",
            ];
    }
}

/** Default sort column (0-based th index) per WC players sub-wing. */
function amiga_lb_wc_players_default_sort_col(string $view): int
{
    return match ($view) {
        'results' => 8,
        default => 4,
    };
}

/**
 * Present-day WC slice stats for LB wings (eligibility: tournaments_played > 0).
 *
 * Sub-wing tables use k2-table client sort (skip-initial-sort); SQL order must match each wing default.
 *
 * @return list<array<string, mixed>>
 */
function amiga_lb_wc_slice_rows_present(mysqli $con, string $view = 'honours', ?string $orderClause = null): array
{
    $orderClause ??= amiga_lb_wc_slice_order_sql($view, 'wcs');

    static $cache = [];
    $cacheKey = $view . '|' . $orderClause;
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
            FROM amiga_player_slice_totals wcs
            WHERE wcs.slice_key = ?
              AND wcs.tournaments_played > 0
            ORDER BY ' . $orderClause . '
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

    return $cache[$cacheKey] = $rows;
}

/**
 * WC slice stats at cutoff — latest at_event row per player with tournaments_played > 0.
 *
 * Request-scoped cache is keyed by cutoff + sub-wing + order (sort order is wing-specific).
 *
 * @return list<array<string, mixed>>
 */
function amiga_lb_wc_slice_rows_at_cutoff(mysqli $con, AmigaSnapshotContext $ctx, string $view = 'honours', ?string $orderClause = null): array
{
    if (!$ctx->isActive()) {
        return amiga_lb_wc_slice_rows_present($con, $view, $orderClause);
    }

    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return [];
    }

    $orderClause ??= amiga_lb_wc_slice_order_sql($view, 'wcs');

    static $cache = [];
    $cacheKey = $cutoff['event_date'] . '|' . $cutoff['chrono'] . '|' . $cutoff['tournament_id'] . '|' . $view . '|' . $orderClause;
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
            ORDER BY ' . $orderClause . '
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
