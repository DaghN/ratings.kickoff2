<?php
/**
 * Tournament entity chevrons — stepping catalog + filter bag.
 *
 * @see docs/with-player-stepper-policy.md §5.5–§5.7
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/amiga_tournament_lib.php';
require_once __DIR__ . '/amiga_participation_step_lib.php';
require_once __DIR__ . '/amiga_id_with_url.php';
require_once __DIR__ . '/amiga_id_country_url.php';
require_once __DIR__ . '/amiga_id_wc_url.php';

/**
 * @return array{player_id: int|null, country: string|null, wc_only: bool}
 */
function amiga_tournament_step_filter_bag_from_request(mysqli $con): array
{
    $playerId = amiga_id_with_active_player_id($con);
    $country = amiga_id_country_active($con);

    return [
        'player_id' => $playerId > 0 ? $playerId : null,
        'country' => $country !== '' ? $country : null,
        'wc_only' => amiga_id_wc_active($con),
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
function amiga_tournament_step_row_by_id(
    mysqli $con,
    ?AmigaSnapshotContext $ctx = null,
): array {
    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();
    $rows = amiga_tournament_index_rows($con, 0, 0, $ctx);
    /** @var array<int, array<string, mixed>> $byId */
    $byId = [];
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id > 0) {
            $byId[$id] = $row;
        }
    }

    return $byId;
}

/**
 * Host countries present in the stepping catalog (TT cutoff when active).
 * Faceted: respects active with-player filter, not host-country filter.
 *
 * @return list<array{value: string, label: string, meta: string}>
 */
function amiga_tournament_step_country_choices(
    mysqli $con,
    ?AmigaSnapshotContext $ctx = null,
): array {
    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();
    $catalog = amiga_tournament_step_catalog($con, $ctx);
    $filterBag = amiga_tournament_step_filter_bag_from_request($con);
    $counts = amiga_tournament_step_country_facet_counts($con, $catalog, $filterBag, $ctx);
    $counts = amiga_tournament_index_inject_selected_country($counts, amiga_id_country_from_request());
    $choices = [['value' => '', 'label' => 'All countries']];
    foreach ($counts as $country => $count) {
        $choices[] = [
            'value' => $country,
            'label' => $country,
            'meta' => (string) (int) $count,
        ];
    }

    return $choices;
}

/**
 * Players with rated tournament participation; facet count = stepping-catalog tournaments
 * matching other active filters (e.g. host country).
 *
 * @return list<array{value: string, label: string, meta: string}>
 */
function amiga_tournament_step_player_choices(
    mysqli $con,
    ?AmigaSnapshotContext $ctx = null,
): array {
    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();
    $catalog = amiga_tournament_step_catalog($con, $ctx);
    $filterBag = amiga_tournament_step_filter_bag_from_request($con);
    $counts = amiga_tournament_step_player_facet_counts($con, $catalog, $filterBag);
    $selectedId = amiga_id_with_from_request();
    if ($selectedId > 0 && !isset($counts[$selectedId])) {
        $counts[$selectedId] = 0;
    }

    $choices = [['value' => '', 'label' => 'All players']];
    foreach (amiga_participation_eligible_players($con) as $player) {
        $playerId = (int) $player['id'];
        $count = $counts[$playerId] ?? 0;
        if ($count < 1 && $playerId !== $selectedId) {
            continue;
        }
        $choices[] = [
            'value' => (string) $playerId,
            'label' => (string) $player['name'],
            'meta' => (string) $count,
        ];
    }

    return $choices;
}

/**
 * Facet counts: stepping-catalog tournaments per player; other filters apply, not player.
 *
 * @param list<array{key: string}> $catalog
 * @param array{player_id: int|null, country: string|null, wc_only?: bool} $filterBag
 * @return array<int, int>
 */
function amiga_tournament_step_player_facet_counts(mysqli $con, array $catalog, array $filterBag): array
{
    $facetBag = [
        'player_id' => null,
        'country' => $filterBag['country'] ?? null,
        'wc_only' => (bool) ($filterBag['wc_only'] ?? false),
    ];
    $eligible = amiga_tournament_step_eligible_key_set($con, $catalog, $facetBag);
    /** @var array<int, int> $counts */
    $counts = [];
    foreach (amiga_participation_eligible_players($con) as $player) {
        $playerId = (int) $player['id'];
        $participated = amiga_player_participated_event_key_set($con, $playerId);
        $count = 0;
        foreach ($eligible as $key => $_) {
            if (isset($participated[$key])) {
                $count++;
            }
        }
        if ($count > 0) {
            $counts[$playerId] = $count;
        }
    }

    return $counts;
}

/**
 * Facet counts: stepping-catalog tournaments per host country; other filters apply, not country.
 *
 * @param list<array{key: string}> $catalog
 * @param array{player_id: int|null, country: string|null, wc_only?: bool} $filterBag
 * @return array<string, int>
 */
function amiga_tournament_step_country_facet_counts(
    mysqli $con,
    array $catalog,
    array $filterBag,
    ?AmigaSnapshotContext $ctx = null,
): array {
    $facetBag = [
        'player_id' => $filterBag['player_id'] ?? null,
        'country' => null,
        'wc_only' => (bool) ($filterBag['wc_only'] ?? false),
    ];
    $eligible = amiga_tournament_step_eligible_key_set($con, $catalog, $facetBag);
    $rowsById = amiga_tournament_step_row_by_id($con, $ctx);
    /** @var array<string, int> $counts */
    $counts = [];
    foreach ($eligible as $key => $_) {
        $row = $rowsById[(int) $key] ?? null;
        if ($row === null) {
            continue;
        }
        $country = trim((string) ($row['country'] ?? ''));
        if ($country === '') {
            continue;
        }
        $counts[$country] = ($counts[$country] ?? 0) + 1;
    }
    ksort($counts, SORT_STRING);

    return $counts;
}

/**
 * Base public tournament catalog for stepping (chrono asc, TT cutoff when active).
 *
 * @return list<array{key: string}>
 */
function amiga_tournament_step_catalog(
    mysqli $con,
    ?AmigaSnapshotContext $ctx = null,
): array {
    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();
    $rows = amiga_tournament_index_rows($con, 0, 0, $ctx);
    /** @var list<array{key: string}> $catalog */
    $catalog = [];
    for ($i = count($rows) - 1; $i >= 0; $i--) {
        $tid = (int) ($rows[$i]['id'] ?? 0);
        if ($tid > 0) {
            $catalog[] = ['key' => (string) $tid];
        }
    }

    return $catalog;
}

/**
 * @param list<array{key: string}> $catalog
 * @param array{player_id: int|null, country: string|null, wc_only?: bool} $filterBag
 * @return array<string, true>
 */
function amiga_tournament_step_eligible_key_set(mysqli $con, array $catalog, array $filterBag): array
{
    /** @var array<string, true> $eligible */
    $eligible = [];
    foreach ($catalog as $entry) {
        $eligible[(string) $entry['key']] = true;
    }

    if ((bool) ($filterBag['wc_only'] ?? false)) {
        $rowsById = amiga_tournament_step_row_by_id($con);
        /** @var array<string, true> $filtered */
        $filtered = [];
        foreach ($eligible as $key => $_) {
            $row = $rowsById[(int) $key] ?? null;
            if ($row !== null && amiga_tournament_index_matches_wc_filter($row, 'world-cup')) {
                $filtered[$key] = true;
            }
        }
        $eligible = $filtered;
    }

    $countryFilter = trim((string) ($filterBag['country'] ?? ''));
    if ($countryFilter !== '') {
        $rowsById = amiga_tournament_step_row_by_id($con);
        /** @var array<string, true> $filtered */
        $filtered = [];
        foreach ($eligible as $key => $_) {
            $row = $rowsById[(int) $key] ?? null;
            if ($row !== null && amiga_tournament_index_matches_country_filter($row, $countryFilter)) {
                $filtered[$key] = true;
            }
        }
        $eligible = $filtered;
    }

    $playerId = (int) ($filterBag['player_id'] ?? 0);
    if ($playerId < 1) {
        return $eligible;
    }

    $participated = amiga_player_participated_event_key_set($con, $playerId);
    /** @var array<string, true> $filtered */
    $filtered = [];
    foreach ($eligible as $key => $_) {
        if (isset($participated[$key])) {
            $filtered[$key] = true;
        }
    }

    return $filtered;
}

/**
 * @param list<array{key: string}> $catalog base chrono catalog (never pre-filtered)
 * @param array{player_id: int|null, country: string|null, wc_only?: bool} $filterBag
 * @return array{prev_key: string|null, next_key: string|null, in_base_catalog: bool}
 */
function amiga_tournament_step_keys(
    mysqli $con,
    array $catalog,
    int $currentId,
    array $filterBag,
): array {
    if ($catalog === [] || $currentId < 1) {
        return ['prev_key' => null, 'next_key' => null, 'in_base_catalog' => false];
    }

    $currentKey = (string) $currentId;
    $inBase = false;
    foreach ($catalog as $entry) {
        if ((string) $entry['key'] === $currentKey) {
            $inBase = true;
            break;
        }
    }
    if (!$inBase) {
        return ['prev_key' => null, 'next_key' => null, 'in_base_catalog' => false];
    }

    $eligible = amiga_tournament_step_eligible_key_set($con, $catalog, $filterBag);
    if ($eligible === []) {
        return ['prev_key' => null, 'next_key' => null, 'in_base_catalog' => true];
    }

    $steps = k2_participation_step_keys($catalog, $currentKey, $eligible);

    return [
        'prev_key' => $steps['prev_key'],
        'next_key' => $steps['next_key'],
        'in_base_catalog' => true,
    ];
}

/** Resolve active WC-only filter for tournament chevrons (`id_wc=world-cup`). */
function amiga_id_wc_active(mysqli $con, ?AmigaSnapshotContext $ctx = null): bool
{
    if (amiga_id_wc_from_request() !== 'world-cup') {
        return false;
    }

    $rowsById = amiga_tournament_step_row_by_id($con, $ctx);
    foreach ($rowsById as $row) {
        if (amiga_tournament_index_matches_wc_filter($row, 'world-cup')) {
            return true;
        }
    }

    return false;
}

/** Resolve active with-player filter for tournament chevrons (`id_with=`). */
function amiga_id_with_active_player_id(mysqli $con): int
{
    $playerId = amiga_id_with_from_request();
    if ($playerId < 1) {
        return 0;
    }

    if (amiga_player_participated_event_keys($con, $playerId) === []) {
        return 0;
    }

    return $playerId;
}

/** Resolve active host-country filter for tournament chevrons (`id_country=`). */
function amiga_id_country_active(mysqli $con, ?AmigaSnapshotContext $ctx = null): string
{
    $country = amiga_id_country_from_request();
    if ($country === '') {
        return '';
    }

    $rowsById = amiga_tournament_step_row_by_id($con, $ctx);
    foreach ($rowsById as $row) {
        if (amiga_tournament_index_matches_country_filter($row, $country)) {
            return $country;
        }
    }

    return '';
}

/**
 * @param array{player_id: int|null, country: string|null, wc_only?: bool} $filterBag
 */
function amiga_tournament_step_filter_active(array $filterBag): bool
{
    return ($filterBag['player_id'] ?? null) !== null
        || trim((string) ($filterBag['country'] ?? '')) !== ''
        || (bool) ($filterBag['wc_only'] ?? false);
}

/**
 * @param list<array{key: string}> $catalog
 * @param array{player_id: int|null, country: string|null, wc_only?: bool} $filterBag
 */
function amiga_tournament_step_current_is_eligible(
    mysqli $con,
    array $catalog,
    int $currentId,
    array $filterBag,
): bool {
    if (!amiga_tournament_step_filter_active($filterBag)) {
        return true;
    }
    $eligible = amiga_tournament_step_eligible_key_set($con, $catalog, $filterBag);

    return isset($eligible[(string) $currentId]);
}

/**
 * When filters are active and current tournament is off-filter, nearest snap target:
 * prefer previous eligible (back in chrono), else next.
 *
 * @param list<array{key: string}> $catalog
 * @param array{player_id: int|null, country: string|null, wc_only?: bool} $filterBag
 */
function amiga_tournament_step_snap_target_key(
    mysqli $con,
    array $catalog,
    int $currentId,
    array $filterBag,
): ?string {
    if (!amiga_tournament_step_filter_active($filterBag)) {
        return null;
    }
    if (amiga_tournament_step_current_is_eligible($con, $catalog, $currentId, $filterBag)) {
        return null;
    }

    $steps = amiga_tournament_step_keys($con, $catalog, $currentId, $filterBag);
    if (!$steps['in_base_catalog']) {
        return null;
    }

    $prev = $steps['prev_key'];
    if ($prev !== null && $prev !== '') {
        return $prev;
    }
    $next = $steps['next_key'];
    if ($next !== null && $next !== '') {
        return $next;
    }

    return null;
}