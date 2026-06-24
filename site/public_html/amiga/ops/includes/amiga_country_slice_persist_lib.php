<?php
/**
 * Persist World Cup country slice rows at tournament finalize.
 *
 * @see scripts/amiga/country_slice_persist.py
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_country_slice_totals_lib.php';
require_once __DIR__ . '/amiga_slice_persist_lib.php';

/**
 * @param array<string, array<string, mixed>> $sliceByCountry
 */
function amiga_ops_persist_country_slices(
    mysqli $con,
    int $tournamentId,
    mixed $eventDate,
    float $eventChrono,
    array $sliceByCountry,
): int {
    $active = [];
    foreach ($sliceByCountry as $token => $totals) {
        if ((int) ($totals['players'] ?? 0) >= 1) {
            $active[] = (string) $token;
        }
    }
    sort($active);
    if ($active === []) {
        return 0;
    }

    $sliceKey = amiga_slice_key_world_cup();
    $written = 0;
    foreach ($active as $token) {
        $totals = $sliceByCountry[$token];
        $atRow = [
            'country_token' => $token,
            'slice_key' => $sliceKey,
            'as_of_tournament_id' => $tournamentId,
            'event_date' => $eventDate,
            'event_chrono' => $eventChrono,
        ];
        $totalRow = [
            'country_token' => $token,
            'slice_key' => $sliceKey,
        ];
        foreach (AMIGA_COUNTRY_SLICE_STAT_COLUMNS as $col) {
            $atRow[$col] = $totals[$col] ?? null;
            $totalRow[$col] = $totals[$col] ?? null;
        }

        amiga_slice_upsert_row(
            $con,
            'amiga_country_slice_at_event',
            $atRow,
            ['country_token', 'slice_key', 'as_of_tournament_id']
        );
        amiga_slice_upsert_row(
            $con,
            'amiga_country_slice_totals',
            $totalRow,
            ['country_token', 'slice_key']
        );
        $written++;
    }

    return $written;
}
