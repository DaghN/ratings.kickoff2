<?php
/**
 * Running career honours totals (mirrors scripts/amiga/honours_totals.py).
 *
 * @see docs/amiga-hof-record-date-policy.md
 */
declare(strict_types=1);

/** @var list<string> */
const AMIGA_HONOURS_RISE_METRICS = [
    'tournaments_played',
    'event_gold',
    'perfect_events',
];

/**
 * @return list<string>
 */
function amiga_honours_rise_player_columns(): array
{
    $cols = [];
    foreach (AMIGA_HONOURS_RISE_METRICS as $metric) {
        $cols[] = "{$metric}_last_rise_tournament_id";
        $cols[] = "{$metric}_last_rise_event_date";
    }

    return $cols;
}

/**
 * @return array<string, mixed>
 */
function amiga_honours_empty_rise_fields(): array
{
    $out = [];
    foreach (AMIGA_HONOURS_RISE_METRICS as $metric) {
        $out["{$metric}_last_rise_tournament_id"] = null;
        $out["{$metric}_last_rise_event_date"] = null;
    }

    return $out;
}

/**
 * @param array<string, mixed> $totals
 */
function amiga_honours_set_last_rise(
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
function amiga_honours_empty_totals(): array
{
    return [
        'tournaments_played' => 0,
        'tournaments_won' => 0,
        'event_gold' => 0,
        'event_silver' => 0,
        'event_bronze' => 0,
        'event_podiums' => 0,
        'perfect_events' => 0,
        'last_event_date' => null,
        'last_tournament_id' => null,
    ] + amiga_honours_empty_rise_fields();
}

function amiga_honours_participation_is_world_cup(array $participation): bool
{
    if (array_key_exists('is_world_cup', $participation)) {
        return (int) ($participation['is_world_cup'] ?? 0) === 1;
    }

    return amiga_honours_is_world_cup_tournament((string) ($participation['tournament_name'] ?? ''));
}

function amiga_honours_is_world_cup_tournament(string $name): bool
{
    return preg_match('/^World Cup\s+\S/i', trim($name)) === 1;
}

/**
 * @param array<string, mixed> $totals
 * @param array<string, mixed> $participation
 */
function amiga_honours_increment_totals(array &$totals, array $participation): void
{
    $priorTournamentsPlayed = (int) ($totals['tournaments_played'] ?? 0);
    $priorEventGold = (int) ($totals['event_gold'] ?? 0);
    $priorPerfectEvents = (int) ($totals['perfect_events'] ?? 0);

    $totals['tournaments_played'] = $priorTournamentsPlayed + 1;

    $pos = $participation['event_finish_position'] ?? null;
    if ($pos !== null) {
        $pos = (int) $pos;
    }

    if ((int) ($participation['is_winner'] ?? 0) === 1 || $pos === 1) {
        $totals['tournaments_won'] = (int) ($totals['tournaments_won'] ?? 0) + 1;
    }

    if ($pos === 1) {
        $totals['event_gold'] = (int) ($totals['event_gold'] ?? 0) + 1;
    } elseif ($pos === 2) {
        $totals['event_silver'] = (int) ($totals['event_silver'] ?? 0) + 1;
    } elseif ($pos === 3) {
        $totals['event_bronze'] = (int) ($totals['event_bronze'] ?? 0) + 1;
    }

    if ($pos !== null && $pos <= 3) {
        $totals['event_podiums'] = (int) ($totals['event_podiums'] ?? 0) + 1;
    }

    if ((int) ($participation['is_perfect_event'] ?? 0) === 1) {
        $totals['perfect_events'] = (int) ($totals['perfect_events'] ?? 0) + 1;
    }

    $tournamentId = (int) $participation['tournament_id'];
    $eventDate = $participation['event_date'] ?? null;
    $totals['last_event_date'] = $eventDate;
    $totals['last_tournament_id'] = $tournamentId;

    if ((int) $totals['tournaments_played'] > $priorTournamentsPlayed) {
        amiga_honours_set_last_rise($totals, 'tournaments_played', $tournamentId, $eventDate);
    }
    if ((int) $totals['event_gold'] > $priorEventGold) {
        amiga_honours_set_last_rise($totals, 'event_gold', $tournamentId, $eventDate);
    }
    if ((int) $totals['perfect_events'] > $priorPerfectEvents) {
        amiga_honours_set_last_rise($totals, 'perfect_events', $tournamentId, $eventDate);
    }
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function amiga_honours_totals_from_snapshot_row(array $row): array
{
    $mapped = [
        'tournaments_played' => (int) ($row['tournaments_played'] ?? 0),
        'tournaments_won' => (int) ($row['tournaments_won'] ?? 0),
        'event_gold' => (int) ($row['event_gold'] ?? 0),
        'event_silver' => (int) ($row['event_silver'] ?? 0),
        'event_bronze' => (int) ($row['event_bronze'] ?? 0),
        'event_podiums' => (int) ($row['event_podiums'] ?? 0),
        'perfect_events' => (int) ($row['perfect_events'] ?? 0),
        'last_event_date' => $row['honours_last_event_date'] ?? $row['last_event_date'] ?? null,
        'last_tournament_id' => $row['honours_last_tournament_id'] ?? $row['last_tournament_id'] ?? null,
    ];
    foreach (amiga_honours_rise_player_columns() as $col) {
        $mapped[$col] = $row[$col] ?? null;
    }

    return $mapped;
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function amiga_honours_totals_from_current_row(array $row): array
{
    $mapped = amiga_honours_totals_from_snapshot_row($row);
    $mapped['last_event_date'] = $row['last_event_date'] ?? null;
    $mapped['last_tournament_id'] = $row['last_tournament_id'] ?? null;

    return $mapped;
}
