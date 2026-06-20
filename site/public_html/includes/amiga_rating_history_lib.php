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

    $sql = 'SELECT DISTINCT t.id, t.name, t.event_date, t.chrono '
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
        ];
    }
    mysqli_free_result($res);

    $GLOBALS['_amiga_rating_history_tournaments'] = $rows;

    return $rows;
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
function amiga_rating_history_format_event_label(array $tournament): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $tournament['event_date']);
    $datePart = $date instanceof DateTimeImmutable ? $date->format('M Y') : $tournament['event_date'];

    return $tournament['name'] . ' · ' . $datePart;
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
 * Monotonic ms key for tournament chrono (same-day safe).
 */
function amiga_rating_history_event_sort_ms(string $eventDate, float $chrono, int $tournamentId): int
{
    $base = strtotime($eventDate . ' UTC');
    if ($base === false) {
        return $tournamentId;
    }

    return $base * 1000 + (int) round($chrono * 1000) + $tournamentId;
}

/**
 * @return list<array{
 *   key: string,
 *   label: string,
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
            'cutoff_tournament_id' => $tournament['id'],
            'cutoff_event_date' => $tournament['event_date'],
            'cutoff_chrono' => $tournament['chrono'],
            'has_finalize_in_period' => true,
        ];
    }

    return $catalog;
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
    for ($cursor = $start; $cursor <= $end; $cursor = $cursor->modify('+1 month')) {
        $ym = $cursor->format('Y-m');
        $cutoff = amiga_rating_history_cutoff_tournament_for_month_end($con, $ym);
        $catalog[] = [
            'key' => $ym,
            'label' => amiga_rating_history_format_month_label($ym),
            'cutoff_tournament_id' => $cutoff['id'] ?? null,
            'cutoff_event_date' => $cutoff['event_date'] ?? null,
            'cutoff_chrono' => isset($cutoff['chrono']) ? (float) $cutoff['chrono'] : null,
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
    for ($year = $startYear; $year <= $endYear; $year++) {
        $key = (string) $year;
        $cutoff = amiga_rating_history_cutoff_tournament_for_year_end($con, $year);
        $catalog[] = [
            'key' => $key,
            'label' => $key,
            'cutoff_tournament_id' => $cutoff['id'] ?? null,
            'cutoff_event_date' => $cutoff['event_date'] ?? null,
            'cutoff_chrono' => isset($cutoff['chrono']) ? (float) $cutoff['chrono'] : null,
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
    $stmt = $con->prepare(
        'SELECT t.id, t.event_date, t.chrono '
        . 'FROM tournaments t '
        . 'INNER JOIN amiga_player_event_snapshots s ON s.tournament_id = t.id '
        . 'WHERE t.rating_finalized = 1 AND t.event_date <= ? '
        . 'ORDER BY t.event_date DESC, t.chrono DESC, t.id DESC '
        . 'LIMIT 1'
    );
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare month/year cutoff query.');
    }
    $stmt->bind_param('s', $dateYmd);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if ($row === null) {
        return null;
    }

    return [
        'id' => (int) $row['id'],
        'event_date' => (string) $row['event_date'],
        'chrono' => (float) $row['chrono'],
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

function amiga_rating_history_page_url(string $wing, string $atKey): string
{
    return '/amiga/history.php?' . http_build_query([
        'wing' => amiga_rating_history_normalize_wing($wing),
        'at' => $atKey,
    ]);
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

/**
 * Top-N Elo line-race payload for News animation (event-stepped timeline).
 *
 * @return array{
 *   meta: array{topN: int, frameCount: int, timelineStart: string|null, timelineEnd: string|null},
 *   frames: list<array{i: int, date: string, label: string, tournamentId: int, top10: list<array{playerId: int, name: string, rating: float, rank: int}>}>,
 *   players: list<array{id: int, name: string, country: string, series: list<array{i: int, date: string, sortMs: int, rating: float}>}>
 * }
 */
function amiga_rating_history_top10_race_payload(mysqli $con, int $topN = 10): array
{
    $topN = max(1, min(20, $topN));
    $tournaments = amiga_rating_history_tournaments($con);
    if ($tournaments === []) {
        return [
            'meta' => [
                'topN' => $topN,
                'frameCount' => 0,
                'timelineStart' => null,
                'timelineEnd' => null,
                'timelineStartMs' => null,
                'timelineEndMs' => null,
            ],
            'frames' => [],
            'players' => [],
        ];
    }

    $sql = 'SELECT s.player_id, s.rating_after, s.tournament_id, p.name, p.country '
        . 'FROM amiga_player_event_snapshots s '
        . 'INNER JOIN tournaments t ON t.id = s.tournament_id '
        . 'INNER JOIN amiga_players p ON p.id = s.player_id '
        . 'WHERE s.rating_after IS NOT NULL '
        . 'ORDER BY t.event_date ASC, t.chrono ASC, t.id ASC, s.player_id ASC';

    $res = mysqli_query($con, $sql);
    if ($res === false) {
        throw new RuntimeException('Failed to load rating events for race payload.');
    }

    /** @var array<int, list<array{player_id: int, rating_after: float, name: string, country: string}>> */
    $eventsByTournament = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $tid = (int) $row['tournament_id'];
        $eventsByTournament[$tid][] = [
            'player_id' => (int) $row['player_id'],
            'rating_after' => (float) $row['rating_after'],
            'name' => (string) $row['name'],
            'country' => (string) ($row['country'] ?? ''),
        ];
    }
    mysqli_free_result($res);

    /** @var array<int, float> */
    $ratings = [];
    /** @var array<int, string> */
    $names = [];
    /** @var array<int, string> */
    $countries = [];
    /** @var array<int, true> */
    $everTop = [];
    /** @var array<int, list<array{i: int, date: string, sortMs: int, rating: float}>> */
    $playerSeries = [];

    $frames = [];
    $frameIndex = 0;
    $timelineStartMs = null;
    $timelineEndMs = null;

    foreach ($tournaments as $tournament) {
        $tid = (int) $tournament['id'];
        $sortMs = amiga_rating_history_event_sort_ms(
            $tournament['event_date'],
            (float) $tournament['chrono'],
            $tid
        );
        if ($timelineStartMs === null || $sortMs < $timelineStartMs) {
            $timelineStartMs = $sortMs;
        }
        if ($timelineEndMs === null || $sortMs > $timelineEndMs) {
            $timelineEndMs = $sortMs;
        }
        foreach ($eventsByTournament[$tid] ?? [] as $event) {
            $pid = $event['player_id'];
            $ratings[$pid] = $event['rating_after'];
            $names[$pid] = $event['name'];
            $countries[$pid] = $event['country'];
        }

        /** @var array<int, true> */
        $participantsThisEvent = [];
        foreach ($eventsByTournament[$tid] ?? [] as $event) {
            $participantsThisEvent[(int) $event['player_id']] = true;
        }

        $ladder = [];
        foreach ($ratings as $pid => $rating) {
            $ladder[] = ['player_id' => $pid, 'rating' => $rating];
        }
        usort($ladder, static function (array $a, array $b): int {
            $cmp = $b['rating'] <=> $a['rating'];
            if ($cmp !== 0) {
                return $cmp;
            }

            return $a['player_id'] <=> $b['player_id'];
        });

        $top = array_slice($ladder, 0, $topN);
        $topRows = [];
        $rank = 0;
        foreach ($top as $row) {
            $rank++;
            $pid = (int) $row['player_id'];
            $everTop[$pid] = true;
            $topRows[] = [
                'playerId' => $pid,
                'name' => $names[$pid],
                'rating' => round((float) $row['rating'], 6),
                'rank' => $rank,
            ];
        }

        foreach (array_keys($everTop) as $pid) {
            if (!isset($ratings[$pid]) || !isset($participantsThisEvent[$pid])) {
                continue;
            }
            if (!isset($playerSeries[$pid])) {
                $playerSeries[$pid] = [];
            }
            $playerSeries[$pid][] = [
                'i' => $frameIndex,
                'date' => $tournament['event_date'],
                'sortMs' => $sortMs,
                'rating' => round((float) $ratings[$pid], 6),
            ];
        }

        $frames[] = [
            'i' => $frameIndex,
            'date' => $tournament['event_date'],
            'sortMs' => $sortMs,
            'label' => amiga_rating_history_format_event_label($tournament),
            'tournamentId' => $tid,
            'top10' => $topRows,
        ];
        $frameIndex++;
    }

    $players = [];
    foreach (array_keys($everTop) as $pid) {
        $players[] = [
            'id' => $pid,
            'name' => $names[$pid] ?? '',
            'country' => $countries[$pid] ?? '',
            'series' => $playerSeries[$pid] ?? [],
        ];
    }
    usort($players, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

    $first = $tournaments[0];
    $last = $tournaments[count($tournaments) - 1];

    return [
        'meta' => [
            'topN' => $topN,
            'frameCount' => count($frames),
            'timelineStart' => $first['event_date'],
            'timelineEnd' => $last['event_date'],
            'timelineStartMs' => $timelineStartMs,
            'timelineEndMs' => $timelineEndMs,
        ],
        'frames' => $frames,
        'players' => $players,
    ];
}
