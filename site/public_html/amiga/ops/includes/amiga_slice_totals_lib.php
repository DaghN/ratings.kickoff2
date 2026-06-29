<?php
/**
 * World Cup slice totals writer (mirrors scripts/amiga/slice_totals.py).
 *
 * @see docs/amiga-world-cups-leaderboard-policy.md
 */
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/amiga_player_slice_lib.php';
require_once __DIR__ . '/amiga_honours_totals_lib.php';

/** @var list<string> */
const AMIGA_SLICE_RISE_METRICS = [
    'tournaments_played',
];

/**
 * @return list<string>
 */
function amiga_slice_rise_player_columns(): array
{
    $cols = [];
    foreach (AMIGA_SLICE_RISE_METRICS as $metric) {
        $cols[] = "{$metric}_last_rise_tournament_id";
        $cols[] = "{$metric}_last_rise_event_date";
    }

    return $cols;
}

/**
 * @return array<string, mixed>
 */
function amiga_slice_empty_rise_fields(): array
{
    $out = [];
    foreach (AMIGA_SLICE_RISE_METRICS as $metric) {
        $out["{$metric}_last_rise_tournament_id"] = null;
        $out["{$metric}_last_rise_event_date"] = null;
    }

    return $out;
}

/**
 * @param array<string, mixed> $totals
 */
function amiga_slice_set_last_rise(
    array &$totals,
    string $metric,
    int $tournamentId,
    mixed $eventDate,
): void {
    $totals["{$metric}_last_rise_tournament_id"] = $tournamentId;
    $totals["{$metric}_last_rise_event_date"] = $eventDate;
}

/**
 * @return array<string, mixed>
 */
function amiga_slice_empty_world_cup(): array
{
    return [
        'slice_key' => amiga_slice_key_world_cup(),
        'tournaments_played' => 0,
        'gold' => 0,
        'silver' => 0,
        'bronze' => 0,
        'podiums' => 0,
        'games' => 0,
        'wins' => 0,
        'draws' => 0,
        'losses' => 0,
        'goals_for' => 0,
        'goals_against' => 0,
        'points' => 0,
        'goal_ratio' => null,
        'most_goals_scored' => 0,
        'most_goals_conceded' => 0,
        'biggest_win_difference' => 0,
        'biggest_loss_difference' => 0,
        'biggest_sum_of_goals' => 0,
        'biggest_draw_sum' => 0,
        'double_digits' => 0,
        'clean_sheets' => 0,
        'double_digits_ratio' => null,
        'clean_sheets_ratio' => null,
        'double_digits_conceded' => 0,
        'clean_sheets_conceded' => 0,
        'double_digits_conceded_ratio' => null,
        'clean_sheets_conceded_ratio' => null,
        'opponent_countries_faced' => 0,
        'opponent_countries_beaten' => 0,
        'different_opponents' => 0,
        'different_victims' => 0,
        'double_digits_victims' => 0,
        'clean_sheets_victims' => 0,
        // WC HoF (SCH-046): per-event award counters + single-WC peaks.
        'best_attack_awards' => 0,
        'best_defense_awards' => 0,
        'best_single_wc_gf_per_game' => null,
        'best_single_wc_gf_per_game_tournament_id' => null,
        'best_single_wc_ga_per_game' => null,
        'best_single_wc_ga_per_game_tournament_id' => null,
    ] + amiga_slice_empty_rise_fields();
}

/**
 * @param array<string, mixed> $totals
 */
function amiga_slice_recompute_points(array &$totals): void
{
    $totals['points'] = 3 * (int) ($totals['wins'] ?? 0) + (int) ($totals['draws'] ?? 0);
}

/**
 * @param array<string, mixed> $totals
 */
function amiga_slice_recompute_podiums(array &$totals): void
{
    $totals['podiums'] = (int) ($totals['gold'] ?? 0)
        + (int) ($totals['silver'] ?? 0)
        + (int) ($totals['bronze'] ?? 0);
}

/**
 * @param array<string, mixed> $totals
 * @param array<string, mixed> $participation
 */
function amiga_slice_increment_world_cup(array &$totals, array $participation): void
{
    $tournamentName = (string) ($participation['tournament_name'] ?? '');
    if (!amiga_honours_is_world_cup_tournament($tournamentName)) {
        return;
    }

    $priorTournamentsPlayed = (int) ($totals['tournaments_played'] ?? 0);
    $totals['tournaments_played'] = $priorTournamentsPlayed + 1;

    $pos = $participation['event_finish_position'] ?? null;
    if ($pos !== null) {
        $pos = (int) $pos;
    }
    if ($pos === 1) {
        $totals['gold'] = (int) ($totals['gold'] ?? 0) + 1;
    } elseif ($pos === 2) {
        $totals['silver'] = (int) ($totals['silver'] ?? 0) + 1;
    } elseif ($pos === 3) {
        $totals['bronze'] = (int) ($totals['bronze'] ?? 0) + 1;
    }
    amiga_slice_recompute_podiums($totals);

    $totals['games'] = (int) ($totals['games'] ?? 0) + (int) ($participation['games'] ?? 0);
    $totals['wins'] = (int) ($totals['wins'] ?? 0) + (int) ($participation['wins'] ?? 0);
    $totals['draws'] = (int) ($totals['draws'] ?? 0) + (int) ($participation['draws'] ?? 0);
    $totals['losses'] = (int) ($totals['losses'] ?? 0) + (int) ($participation['losses'] ?? 0);
    $totals['goals_for'] = (int) ($totals['goals_for'] ?? 0) + (int) ($participation['goals_for'] ?? 0);
    $totals['goals_against'] = (int) ($totals['goals_against'] ?? 0) + (int) ($participation['goals_against'] ?? 0);
    amiga_slice_recompute_points($totals);

    $tournamentId = (int) $participation['tournament_id'];
    $eventDate = $participation['event_date'] ?? null;
    if ((int) $totals['tournaments_played'] > $priorTournamentsPlayed) {
        amiga_slice_set_last_rise($totals, 'tournaments_played', $tournamentId, $eventDate);
    }
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function amiga_slice_from_totals_row(array $row): array
{
    $out = amiga_slice_empty_world_cup();
    foreach (array_keys($out) as $key) {
        if ($key === 'slice_key') {
            continue;
        }
        if (array_key_exists($key, $row)) {
            $out[$key] = $row[$key];
        }
    }

    return $out;
}
