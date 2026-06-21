<?php
/**
 * Career cumulative scalar last-rise tracking (mirrors scripts/amiga/career_rise.py).
 *
 * @see docs/amiga-hof-record-date-policy.md § SCH-030
 */
declare(strict_types=1);

/** @var list<array{0: string, 1: string, 2: string}> */
const AMIGA_CAREER_RISE_SPECS = [
    ['NumberGames', 'number_games', 'MostGamesPlayed'],
    ['NumberWins', 'number_wins', 'MostWins'],
    ['GoalsFor', 'goals_for', 'MostGoalsScored'],
    ['DoubleDigits', 'double_digits', 'MostDoubleDigits'],
    ['CleanSheets', 'clean_sheets', 'MostCleanSheets'],
    ['DifferentOpponents', 'different_opponents', 'MostDifferentOpponents'],
    ['DifferentVictims', 'different_victims', 'MostDifferentVictims'],
    ['DoubleDigitsVictims', 'double_digits_victims', 'MostDoubleDigitsVictims'],
    ['CleanSheetsVictims', 'clean_sheets_victims', 'MostCleanSheetsVictims'],
    ['BiggestRatingAscent', 'biggest_rating_ascent', 'BiggestRatingAscent'],
];

/**
 * @return list<string>
 */
function amiga_career_rise_player_columns(): array
{
    $cols = [];
    foreach (AMIGA_CAREER_RISE_SPECS as [$careerCol, $prefix, $_hof]) {
        $cols[] = "{$prefix}_last_rise_tournament_id";
        $cols[] = "{$prefix}_last_rise_event_date";
    }

    return $cols;
}

/**
 * @return array<string, mixed>
 */
function amiga_career_empty_rise_fields(): array
{
    $out = [];
    foreach (amiga_career_rise_player_columns() as $col) {
        $out[$col] = null;
    }

    return $out;
}

/**
 * @param array<string, mixed> $row
 * @return array<string, int|float>
 */
function amiga_career_prior_values_from_row(array $row): array
{
    $out = [];
    foreach (AMIGA_CAREER_RISE_SPECS as [$careerCol, $_prefix, $_hof]) {
        if ($careerCol === 'BiggestRatingAscent') {
            $out[$careerCol] = (float) ($row[$careerCol] ?? 0.0);
        } else {
            $out[$careerCol] = (int) ($row[$careerCol] ?? 0);
        }
    }

    return $out;
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function amiga_career_rise_from_row(array $row): array
{
    $out = amiga_career_empty_rise_fields();
    foreach (array_keys($out) as $col) {
        if (array_key_exists($col, $row)) {
            $out[$col] = $row[$col];
        }
    }

    return $out;
}

/**
 * @param array<string, mixed> $riseState
 * @param array<string, int|float> $priorCareer
 * @param array<string, mixed> $newCareer
 * @return array<string, mixed>
 */
function amiga_career_apply_rise_fields(
    array $riseState,
    array $priorCareer,
    array $newCareer,
    int $tournamentId,
    mixed $eventDate,
): array {
    $out = $riseState;
    foreach (AMIGA_CAREER_RISE_SPECS as [$careerCol, $prefix, $_hof]) {
        if ($careerCol === 'BiggestRatingAscent') {
            $old = (float) ($priorCareer[$careerCol] ?? 0.0);
            $new = (float) ($newCareer[$careerCol] ?? 0.0);
            $rose = $new > $old;
        } else {
            $old = (int) ($priorCareer[$careerCol] ?? 0);
            $new = (int) ($newCareer[$careerCol] ?? 0);
            $rose = $new > $old;
        }
        if ($rose) {
            $out["{$prefix}_last_rise_tournament_id"] = $tournamentId;
            $out["{$prefix}_last_rise_event_date"] = $eventDate;
        }
    }

    return $out;
}
