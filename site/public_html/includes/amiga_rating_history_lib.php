<?php
/**
 * Amiga historical rating ladder — snapshot catalogs and cutoff reads.
 *
 * Historical ladder at cutoff T = last `amiga_player_event_snapshots` row per player
 * on or before T (policy: amiga-event-snapshot-policy.md §6).
 *
 * @see docs/amiga-rating-history-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';

/** Amiga ladder start rating — debut Δ baseline when absent from prior wing snapshot. */
const AMIGA_RATING_HISTORY_START_RATING = 1600.0;

/** @var list<array<string, mixed>>|null */
$GLOBALS['_amiga_rating_history_tournaments'] = null;

/**
 * Finalized tournaments with event snapshots, in catalog chrono order.
 *
 * @return list<array{id: int, name: string, event_date: string, chrono: float}>
 */
function amiga_rating_history_tournaments(mysqli $con): array
{
    if ($GLOBALS['_amiga_rating_history_tournaments'] !== null) {
        return $GLOBALS['_amiga_rating_history_tournaments'];
    }

    $sql = 'SELECT DISTINCT t.id, t.name, t.event_date, t.chrono, t.country '
        . 'FROM tournaments t '
        . 'INNER JOIN amiga_player_event_snapshots s ON s.tournament_id = t.id '
        . 'WHERE t.rating_finalized = 1 '
        . 'ORDER BY t.event_date ASC, t.chrono ASC, t.id ASC';

    $res = mysqli_query($con, $sql);
    if ($res === false) {
        throw new RuntimeException('Failed to load rating history tournaments.');
    }

    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'event_date' => (string) $row['event_date'],
            'chrono' => (float) $row['chrono'],
            'country' => trim((string) ($row['country'] ?? '')),
        ];
    }
    mysqli_free_result($res);

    $GLOBALS['_amiga_rating_history_tournaments'] = $rows;

    return $rows;
}

/**
 * Last finalized World Cup in catalog chrono order, or null when none.
 *
 * @return array{id: int, name: string, event_date: string, chrono: float}|null
 */
function amiga_rating_history_last_world_cup_tournament(mysqli $con): ?array
{
    require_once __DIR__ . '/amiga_tournament_lib.php';

    $last = null;
    foreach (amiga_rating_history_tournaments($con) as $tournament) {
        if (amiga_tournament_is_world_cup($tournament)) {
            $last = $tournament;
        }
    }

    return $last;
}

/**
 * Tournament immediately before the given id in catalog chrono order.
 *
 * @return array{id: int, name: string, event_date: string, chrono: float}|null
 */
function amiga_rating_history_tournament_before(mysqli $con, int $tournamentId): ?array
{
    $prev = null;
    foreach (amiga_rating_history_tournaments($con) as $tournament) {
        if ($tournament['id'] === $tournamentId) {
            return $prev;
        }
        $prev = $tournament;
    }

    return null;
}

/**
 * Each player's Elo immediately before the given tournament's rating commits.
 *
 * @return array<int, float> player_id => rating
 */
function amiga_rating_history_baseline_rating_before_tournament(mysqli $con, array $tournament): array
{
    $prev = amiga_rating_history_tournament_before($con, (int) $tournament['id']);
    if ($prev === null) {
        return [];
    }

    $ladder = amiga_rating_history_ladder_at_cutoff(
        $con,
        $prev['event_date'],
        $prev['chrono'],
        $prev['id']
    );

    return amiga_rating_history_ladder_rating_map($ladder);
}

/**
 * @return array{min_date: string, max_date: string}|null
 */
function amiga_rating_history_date_bounds(mysqli $con): ?array
{
    $tournaments = amiga_rating_history_tournaments($con);
    if ($tournaments === []) {
        return null;
    }

    $first = $tournaments[0];
    $last = $tournaments[count($tournaments) - 1];

    return [
        'min_date' => $first['event_date'],
        'max_date' => $last['event_date'],
    ];
}

/**
 * @param array{id: int, name: string, event_date: string, chrono: float} $tournament
 */
function amiga_rating_history_format_event_date_picker_label(array $tournament): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $tournament['event_date']);

    return $date instanceof DateTimeImmutable ? $date->format('M Y') : $tournament['event_date'];
}

/**
 * @param array{id: int, name: string, event_date: string, chrono: float} $tournament
 */
function amiga_rating_history_format_event_date_label(array $tournament): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $tournament['event_date']);

    return $date instanceof DateTimeImmutable ? $date->format('M j, Y') : $tournament['event_date'];
}

/**
 * @param array{id: int, name: string, event_date: string, chrono: float} $tournament
 */
function amiga_rating_history_format_event_label(array $tournament): string
{
    return $tournament['name'] . ' · ' . amiga_rating_history_format_event_date_label($tournament);
}

function amiga_rating_history_format_month_label(string $yearMonth): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $yearMonth . '-01');
    if (!$date instanceof DateTimeImmutable) {
        return $yearMonth;
    }

    return $date->format('F Y');
}

/**
 * @return list<array{
 *   key: string,
 *   label: string,
 *   tournament_name: string,
 *   event_date_label: string,
 *   event_date_picker_label: string,
 *   cutoff_tournament_id: int|null,
 *   cutoff_event_date: string|null,
 *   cutoff_chrono: float|null,
 *   has_finalize_in_period: bool
 * }>
 */
function amiga_rating_history_catalog_event(mysqli $con): array
{
    $catalog = [];
    foreach (amiga_rating_history_tournaments($con) as $tournament) {
        $catalog[] = [
            'key' => (string) $tournament['id'],
            'label' => amiga_rating_history_format_event_label($tournament),
            'tournament_name' => $tournament['name'],
            'host_country' => $tournament['country'] ?? '',
            'event_date_label' => amiga_rating_history_format_event_date_label($tournament),
            'event_date_picker_label' => amiga_rating_history_format_event_date_picker_label($tournament),
            'cutoff_tournament_id' => $tournament['id'],
            'cutoff_event_date' => $tournament['event_date'],
            'cutoff_chrono' => $tournament['chrono'],
            'has_finalize_in_period' => true,
        ];
    }

    return $catalog;
}

/**
 * Last tournament in chrono-ASC {@see amiga_rating_history_tournaments()} on or before $dateYmd.
 * Pass &$cursorIndex when scanning periods in order (months/years) — O(periods + tournaments).
 *
 * @param list<array{id: int, name: string, event_date: string, chrono: float, country: string}> $tournaments
 * @return array{id: int, name: string, event_date: string, chrono: float, country: string}|null
 */
function amiga_rating_history_last_tournament_on_or_before_from_list(
    array $tournaments,
    string $dateYmd,
    ?int &$cursorIndex = null
): ?array {
    if ($tournaments === []) {
        return null;
    }

    if ($cursorIndex === null) {
        $idx = -1;
        foreach ($tournaments as $i => $tournament) {
            if ($tournament['event_date'] <= $dateYmd) {
                $idx = $i;
            } else {
                break;
            }
        }

        return $idx >= 0 ? $tournaments[$idx] : null;
    }

    while ($cursorIndex + 1 < count($tournaments)
        && $tournaments[$cursorIndex + 1]['event_date'] <= $dateYmd) {
        $cursorIndex++;
    }

    return $cursorIndex >= 0 ? $tournaments[$cursorIndex] : null;
}

/**
 * @param array{id: int, event_date: string, chrono: float}|null $tournament
 * @return array{cutoff_tournament_id: int|null, cutoff_event_date: string|null, cutoff_chrono: float|null}
 */
function amiga_rating_history_catalog_cutoff_fields(?array $tournament): array
{
    if ($tournament === null) {
        return [
            'cutoff_tournament_id' => null,
            'cutoff_event_date' => null,
            'cutoff_chrono' => null,
        ];
    }

    return [
        'cutoff_tournament_id' => (int) $tournament['id'],
        'cutoff_event_date' => (string) $tournament['event_date'],
        'cutoff_chrono' => (float) $tournament['chrono'],
    ];
}

/**
 * Every calendar month from first ladder month through last (inclusive).
 *
 * @return list<array<string, mixed>>
 */
function amiga_rating_history_catalog_month(mysqli $con): array
{
    $bounds = amiga_rating_history_date_bounds($con);
    if ($bounds === null) {
        return [];
    }

    $tournaments = amiga_rating_history_tournaments($con);
    $finalizeMonths = [];
    foreach ($tournaments as $tournament) {
        $ym = substr($tournament['event_date'], 0, 7);
        $finalizeMonths[$ym] = true;
    }

    $start = DateTimeImmutable::createFromFormat('!Y-m-d', substr($bounds['min_date'], 0, 7) . '-01');
    $end = DateTimeImmutable::createFromFormat('!Y-m-d', substr($bounds['max_date'], 0, 7) . '-01');
    if (!$start instanceof DateTimeImmutable || !$end instanceof DateTimeImmutable) {
        return [];
    }

    $catalog = [];
    $cutoffCursor = -1;
    for ($cursor = $start; $cursor <= $end; $cursor = $cursor->modify('+1 month')) {
        $ym = $cursor->format('Y-m');
        $monthEnd = $cursor->modify('last day of this month')->format('Y-m-d');
        $cutoffTournament = amiga_rating_history_last_tournament_on_or_before_from_list(
            $tournaments,
            $monthEnd,
            $cutoffCursor
        );
        $cutoffFields = amiga_rating_history_catalog_cutoff_fields($cutoffTournament);
        $catalog[] = [
            'key' => $ym,
            'label' => amiga_rating_history_format_month_label($ym),
            'cutoff_tournament_id' => $cutoffFields['cutoff_tournament_id'],
            'cutoff_event_date' => $cutoffFields['cutoff_event_date'],
            'cutoff_chrono' => $cutoffFields['cutoff_chrono'],
            'has_finalize_in_period' => isset($finalizeMonths[$ym]),
        ];
    }

    return $catalog;
}

/**
 * Every calendar year from first ladder year through last (inclusive).
 *
 * @return list<array<string, mixed>>
 */
function amiga_rating_history_catalog_year(mysqli $con): array
{
    $bounds = amiga_rating_history_date_bounds($con);
    if ($bounds === null) {
        return [];
    }

    $tournaments = amiga_rating_history_tournaments($con);
    $finalizeYears = [];
    foreach ($tournaments as $tournament) {
        $y = substr($tournament['event_date'], 0, 4);
        $finalizeYears[$y] = true;
    }

    $startYear = (int) substr($bounds['min_date'], 0, 4);
    $endYear = (int) substr($bounds['max_date'], 0, 4);

    $catalog = [];
    $cutoffCursor = -1;
    for ($year = $startYear; $year <= $endYear; $year++) {
        $key = (string) $year;
        $yearEnd = sprintf('%04d-12-31', $year);
        $cutoffTournament = amiga_rating_history_last_tournament_on_or_before_from_list(
            $tournaments,
            $yearEnd,
            $cutoffCursor
        );
        $cutoffFields = amiga_rating_history_catalog_cutoff_fields($cutoffTournament);
        $catalog[] = [
            'key' => $key,
            'label' => $key,
            'cutoff_tournament_id' => $cutoffFields['cutoff_tournament_id'],
            'cutoff_event_date' => $cutoffFields['cutoff_event_date'],
            'cutoff_chrono' => $cutoffFields['cutoff_chrono'],
            'has_finalize_in_period' => isset($finalizeYears[$key]),
        ];
    }

    return $catalog;
}

/**
 * @return array{id: int, event_date: string, chrono: float}|null
 */
function amiga_rating_history_cutoff_tournament_for_month_end(mysqli $con, string $yearMonth): ?array
{
    if (!preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
        return null;
    }

    $monthEnd = DateTimeImmutable::createFromFormat('!Y-m-d', $yearMonth . '-01');
    if (!$monthEnd instanceof DateTimeImmutable) {
        return null;
    }
    $monthEnd = $monthEnd->modify('last day of this month')->format('Y-m-d');

    return amiga_rating_history_cutoff_tournament_on_or_before_date($con, $monthEnd);
}

/**
 * @return array{id: int, event_date: string, chrono: float}|null
 */
function amiga_rating_history_cutoff_tournament_for_year_end(mysqli $con, int $year): ?array
{
    if ($year < 1000 || $year > 9999) {
        return null;
    }

    return amiga_rating_history_cutoff_tournament_on_or_before_date($con, sprintf('%04d-12-31', $year));
}

/**
 * @return array{id: int, event_date: string, chrono: float}|null
 */
function amiga_rating_history_cutoff_tournament_on_or_before_date(mysqli $con, string $dateYmd): ?array
{
    $tournament = amiga_rating_history_last_tournament_on_or_before_from_list(
        amiga_rating_history_tournaments($con),
        $dateYmd
    );
    if ($tournament === null) {
        return null;
    }

    return [
        'id' => (int) $tournament['id'],
        'event_date' => (string) $tournament['event_date'],
        'chrono' => (float) $tournament['chrono'],
    ];
}

/**
 * @return array{id: int, event_date: string, chrono: float}|null
 */
function amiga_rating_history_cutoff_tournament_by_id(mysqli $con, int $tournamentId): ?array
{
    foreach (amiga_rating_history_tournaments($con) as $tournament) {
        if ($tournament['id'] === $tournamentId) {
            return $tournament;
        }
    }

    return null;
}

/**
 * @param list<array<string, mixed>> $catalog
 * @return array{entry: array<string, mixed>|null, prev_key: string|null, next_key: string|null}
 */
function amiga_rating_history_catalog_position(array $catalog, ?string $key): array
{
    if ($catalog === []) {
        return ['entry' => null, 'prev_key' => null, 'next_key' => null];
    }

    $index = null;
    if ($key !== null && $key !== '') {
        foreach ($catalog as $i => $entry) {
            if ((string) $entry['key'] === $key) {
                $index = $i;
                break;
            }
        }
    }
    if ($index === null) {
        $index = count($catalog) - 1;
    }

    return [
        'entry' => $catalog[$index],
        'prev_key' => $index > 0 ? (string) $catalog[$index - 1]['key'] : null,
        'next_key' => $index < count($catalog) - 1 ? (string) $catalog[$index + 1]['key'] : null,
    ];
}

/**
 * @return list<array{player_id: int, name: string, country: string, rating_after: float, rank: int}>
 */
function amiga_rating_history_ladder_at_cutoff(
    mysqli $con,
    ?string $cutoffEventDate,
    ?float $cutoffChrono,
    ?int $cutoffTournamentId
): array {
    if ($cutoffEventDate === null || $cutoffChrono === null || $cutoffTournamentId === null) {
        return [];
    }

    $sql = 'SELECT player_id, name, country, rating_after FROM ('
        . 'SELECT s.player_id, p.name, p.country, s.rating_after, '
        . 'ROW_NUMBER() OVER ('
        . 'PARTITION BY s.player_id '
        . 'ORDER BY s.event_date DESC, s.event_chrono DESC, s.tournament_id DESC'
        . ') AS rn '
        . 'FROM amiga_player_event_snapshots s '
        . 'INNER JOIN amiga_players p ON p.id = s.player_id '
        . 'WHERE s.rating_after IS NOT NULL '
        . 'AND (s.event_date, s.event_chrono, s.tournament_id) <= (?, ?, ?)'
        . ') ranked WHERE rn = 1 '
        . 'ORDER BY rating_after DESC, player_id ASC';

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare historical ladder query.');
    }
    $stmt->bind_param('sdi', $cutoffEventDate, $cutoffChrono, $cutoffTournamentId);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    $rank = 0;
    while ($row = $res->fetch_assoc()) {
        $rank++;
        $rows[] = [
            'player_id' => (int) $row['player_id'],
            'name' => (string) $row['name'],
            'country' => (string) ($row['country'] ?? ''),
            'rating_after' => (float) $row['rating_after'],
            'rank' => $rank,
        ];
    }
    $stmt->close();

    return $rows;
}

/**
 * @param list<array{player_id: int, rating_after: float}> $ladder
 * @return array<int, float>
 */
function amiga_rating_history_ladder_rating_map(array $ladder): array
{
    $map = [];
    foreach ($ladder as $row) {
        $map[(int) $row['player_id']] = (float) $row['rating_after'];
    }

    return $map;
}

/**
 * @param list<array<string, mixed>> $catalog
 */
function amiga_rating_history_catalog_entry_by_key(array $catalog, ?string $key): ?array
{
    if ($key === null || $key === '') {
        return null;
    }
    foreach ($catalog as $entry) {
        if ((string) $entry['key'] === $key) {
            return $entry;
        }
    }

    return null;
}

/**
 * @return array<int, true>
 */
function amiga_rating_history_event_participant_ids(mysqli $con, int $tournamentId): array
{
    if ($tournamentId < 1) {
        return [];
    }

    $stmt = $con->prepare(
        'SELECT DISTINCT player_id FROM amiga_player_event_snapshots WHERE tournament_id = ?'
    );
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare event participant lookup.');
    }
    $stmt->bind_param('i', $tournamentId);
    $stmt->execute();
    $res = $stmt->get_result();

    $ids = [];
    while ($row = $res->fetch_assoc()) {
        $ids[(int) $row['player_id']] = true;
    }
    $stmt->close();

    return $ids;
}

/**
 * Wing-step rating change vs previous snapshot in the same wing.
 *
 * Event wing: non-participants in the snapshot tournament → 0.
 * Player debut on ladder (absent from prior wing snapshot, or no prior wing snapshot) → vs 1600.
 */
function amiga_rating_history_compute_rating_delta(
    int $playerId,
    float $ratingAfter,
    bool $hasPrevWingSnapshot,
    array $prevRatingByPlayer,
    ?array $eventParticipantIds
): float {
    if ($hasPrevWingSnapshot && $eventParticipantIds !== null && !isset($eventParticipantIds[$playerId])) {
        return 0.0;
    }

    $baseline = ($hasPrevWingSnapshot && isset($prevRatingByPlayer[$playerId]))
        ? (float) $prevRatingByPlayer[$playerId]
        : AMIGA_RATING_HISTORY_START_RATING;

    return $ratingAfter - $baseline;
}

function amiga_rating_history_format_rating_delta_html(float $delta): string
{
    $rounded = (int) round($delta);
    if ($rounded === 0) {
        return '0';
    }
    if ($rounded > 0) {
        return '<span class="blue">+' . $rounded . '</span>';
    }

    return '<span class="red">' . $rounded . '</span>';
}

/**
 * @param list<array{player_id: int, name: string, country: string, rating_after: float, rank: int}> $ladder
 * @return list<array{player_id: int, name: string, country: string, rating_after: float, rank: int, rating_delta: float}>
 */
function amiga_rating_history_ladder_with_deltas(
    mysqli $con,
    string $wing,
    array $ladder,
    array $catalog,
    ?string $prevKey,
    ?array $entry
): array {
    if ($ladder === []) {
        return [];
    }

    $hasPrevWingSnapshot = $prevKey !== null && $prevKey !== '';
    $prevRatingByPlayer = [];
    if ($hasPrevWingSnapshot) {
        $prevEntry = amiga_rating_history_catalog_entry_by_key($catalog, $prevKey);
        if ($prevEntry !== null) {
            $prevLadder = amiga_rating_history_ladder_at_cutoff(
                $con,
                $prevEntry['cutoff_event_date'] !== null ? (string) $prevEntry['cutoff_event_date'] : null,
                $prevEntry['cutoff_chrono'] !== null ? (float) $prevEntry['cutoff_chrono'] : null,
                $prevEntry['cutoff_tournament_id'] !== null ? (int) $prevEntry['cutoff_tournament_id'] : null
            );
            $prevRatingByPlayer = amiga_rating_history_ladder_rating_map($prevLadder);
        }
    }

    $eventParticipantIds = null;
    if ($wing === 'event' && $entry !== null && $entry['cutoff_tournament_id'] !== null) {
        $eventParticipantIds = amiga_rating_history_event_participant_ids($con, (int) $entry['cutoff_tournament_id']);
    }

    $rows = [];
    foreach ($ladder as $row) {
        $rows[] = array_merge($row, [
            'rating_delta' => amiga_rating_history_compute_rating_delta(
                (int) $row['player_id'],
                (float) $row['rating_after'],
                $hasPrevWingSnapshot,
                $prevRatingByPlayer,
                $eventParticipantIds
            ),
        ]);
    }

    return $rows;
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_rating_history_catalog_for_wing(mysqli $con, string $wing): array
{
    return match ($wing) {
        'month' => amiga_rating_history_catalog_month($con),
        'year' => amiga_rating_history_catalog_year($con),
        default => amiga_rating_history_catalog_event($con),
    };
}

function amiga_rating_history_normalize_wing(string $wing): string
{
    $wing = strtolower(trim($wing));

    return match ($wing) {
        'month', 'year' => $wing,
        default => 'event',
    };
}

// --- Time travel shared catalog / cutoff resolution (amiga-time-travel-policy.md) ---

/**
 * Parse canonical `as` param: `year:2003`, `month:2003-11`, `event:589`.
 *
 * @return array{wing: string, key: string}|null
 */
function amiga_snapshot_parse_as_param(string $as): ?array
{
    $as = trim($as);
    if ($as === '' || !preg_match('/^(year|month|event):(.+)$/i', $as, $matches)) {
        return null;
    }

    $wing = amiga_rating_history_normalize_wing($matches[1]);
    $key = trim($matches[2]);
    if ($key === '') {
        return null;
    }

    if ($wing === 'month' && !preg_match('/^\d{4}-\d{2}$/', $key)) {
        return null;
    }
    if ($wing === 'year' && !preg_match('/^\d{4}$/', $key)) {
        return null;
    }
    if ($wing === 'event' && !ctype_digit($key)) {
        return null;
    }

    return ['wing' => $wing, 'key' => $key];
}

function amiga_snapshot_format_as_param(string $wing, string $key): string
{
    return amiga_rating_history_normalize_wing($wing) . ':' . $key;
}

/**
 * Resolve wing catalog position without loading a ladder (time travel context).
 *
 * @return array{
 *   wing: string,
 *   key: string,
 *   catalog: list<array<string, mixed>>,
 *   entry: array<string, mixed>|null,
 *   prev_key: string|null,
 *   next_key: string|null
 * }|null
 */
function amiga_snapshot_resolve_catalog_view(mysqli $con, string $wing, ?string $atKey): ?array
{
    $wing = amiga_rating_history_normalize_wing($wing);
    $catalog = amiga_rating_history_catalog_for_wing($con, $wing);
    if ($catalog === []) {
        return null;
    }

    $position = amiga_rating_history_catalog_position($catalog, $atKey);
    $entry = $position['entry'];
    if ($entry === null || $entry['cutoff_tournament_id'] === null) {
        return null;
    }

    $resolvedKey = (string) $entry['key'];

    return [
        'wing' => $wing,
        'key' => $resolvedKey,
        'catalog' => $catalog,
        'entry' => $entry,
        'prev_key' => $position['prev_key'],
        'next_key' => $position['next_key'],
    ];
}

/**
 * @param array<string, mixed>|null $entry
 * @return array{
 *   wing: string,
 *   key: string,
 *   tournament_id: int,
 *   event_date: string,
 *   chrono: float,
 *   label: string
 * }|null
 */
function amiga_snapshot_cutoff_from_catalog_entry(?array $entry, string $wing, string $key): ?array
{
    if ($entry === null || $entry['cutoff_tournament_id'] === null) {
        return null;
    }

    return [
        'wing' => amiga_rating_history_normalize_wing($wing),
        'key' => $key,
        'tournament_id' => (int) $entry['cutoff_tournament_id'],
        'event_date' => (string) $entry['cutoff_event_date'],
        'chrono' => (float) $entry['cutoff_chrono'],
        'label' => (string) ($entry['label'] ?? $key),
    ];
}

/**
 * Resolve full catalog view from canonical `as` param.
 *
 * @return array{
 *   wing: string,
 *   key: string,
 *   catalog: list<array<string, mixed>>,
 *   entry: array<string, mixed>|null,
 *   prev_key: string|null,
 *   next_key: string|null
 * }|null
 */
function amiga_snapshot_resolve_as(mysqli $con, string $asParam): ?array
{
    $parsed = amiga_snapshot_parse_as_param($asParam);
    if ($parsed === null) {
        return null;
    }

    return amiga_snapshot_resolve_catalog_view($con, $parsed['wing'], $parsed['key']);
}

function amiga_rating_history_page_url(string $wing, string $atKey): string
{
    return '/amiga/leaderboards/rating.php?as=' . rawurlencode(
        amiga_snapshot_format_as_param(amiga_rating_history_normalize_wing($wing), $atKey)
    );
}

/** Latest finalized event — default event-wing `as=` param (`event:{id}`). */
function amiga_snapshot_default_event_as_param(mysqli $con): ?string
{
    $catalog = amiga_rating_history_catalog_event($con);
    if ($catalog === []) {
        return null;
    }

    $last = $catalog[count($catalog) - 1];

    return amiga_snapshot_format_as_param('event', (string) $last['key']);
}

/**
 * @return array{
 *   wing: string,
 *   catalog: list<array<string, mixed>>,
 *   entry: array<string, mixed>|null,
 *   prev_key: string|null,
 *   next_key: string|null,
 *   ladder: list<array{player_id: int, name: string, country: string, rating_after: float, rank: int, rating_delta: float}>
 * }
 */
function amiga_rating_history_resolve_from_context(mysqli $con, AmigaSnapshotContext $ctx): array
{
    if (!$ctx->isActive()) {
        return amiga_rating_history_resolve_view($con, 'event', null);
    }

    return amiga_rating_history_resolve_view($con, $ctx->wing(), $ctx->key());
}

/**
 * @return array{
 *   wing: string,
 *   catalog: list<array<string, mixed>>,
 *   entry: array<string, mixed>|null,
 *   prev_key: string|null,
 *   next_key: string|null,
 *   ladder: list<array{player_id: int, name: string, country: string, rating_after: float, rank: int, rating_delta: float}>
 * }
 */
function amiga_rating_history_resolve_view(mysqli $con, string $wing, ?string $atKey): array
{
    $wing = amiga_rating_history_normalize_wing($wing);
    $catalog = amiga_rating_history_catalog_for_wing($con, $wing);
    $position = amiga_rating_history_catalog_position($catalog, $atKey);
    $entry = $position['entry'];

    $ladder = [];
    if ($entry !== null) {
        $ladderRows = amiga_rating_history_ladder_at_cutoff(
            $con,
            $entry['cutoff_event_date'] !== null ? (string) $entry['cutoff_event_date'] : null,
            $entry['cutoff_chrono'] !== null ? (float) $entry['cutoff_chrono'] : null,
            $entry['cutoff_tournament_id'] !== null ? (int) $entry['cutoff_tournament_id'] : null
        );
        $ladder = amiga_rating_history_ladder_with_deltas(
            $con,
            $wing,
            $ladderRows,
            $catalog,
            $position['prev_key'],
            $entry
        );
    }

    return [
        'wing' => $wing,
        'catalog' => $catalog,
        'entry' => $entry,
        'prev_key' => $position['prev_key'],
        'next_key' => $position['next_key'],
        'ladder' => $ladder,
    ];
}
