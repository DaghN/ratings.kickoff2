<?php
/**
 * Amiga historical rating ladder — snapshot catalogs and compute-on-read ladder.
 *
 * @see docs/amiga-rating-history-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/amiga_tournament_lib.php';

/** @var list<array<string, mixed>>|null */
$GLOBALS['_amiga_rating_history_tournaments'] = null;

/**
 * Finalized tournaments that have rating events, in catalog chrono order.
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
        . 'INNER JOIN amiga_rating_events e ON e.tournament_id = t.id '
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
 * One snapshot per finalized World Cup tournament, in catalog chrono order.
 *
 * @return list<array<string, mixed>>
 */
function amiga_rating_history_catalog_world_cup(mysqli $con): array
{
    $catalog = [];
    foreach (amiga_rating_history_tournaments($con) as $tournament) {
        if (!amiga_tournament_is_world_cup_by_name((string) $tournament['name'])) {
            continue;
        }
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
        . 'INNER JOIN amiga_rating_events e ON e.tournament_id = t.id '
        . 'WHERE t.event_date <= ? '
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
        . 'SELECT e.player_id, p.name, p.country, e.rating_after, '
        . 'ROW_NUMBER() OVER ('
        . 'PARTITION BY e.player_id '
        . 'ORDER BY t.event_date DESC, t.chrono DESC, t.id DESC'
        . ') AS rn '
        . 'FROM amiga_rating_events e '
        . 'INNER JOIN tournaments t ON t.id = e.tournament_id '
        . 'INNER JOIN amiga_players p ON p.id = e.player_id '
        . 'WHERE (t.event_date, t.chrono, t.id) <= (?, ?, ?)'
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
 * @return list<array<string, mixed>>
 */
function amiga_rating_history_catalog_for_wing(mysqli $con, string $wing): array
{
    return match ($wing) {
        'month' => amiga_rating_history_catalog_month($con),
        'year' => amiga_rating_history_catalog_year($con),
        'world-cup' => amiga_rating_history_catalog_world_cup($con),
        default => amiga_rating_history_catalog_event($con),
    };
}

function amiga_rating_history_normalize_wing(string $wing): string
{
    $wing = strtolower(trim($wing));

    return match ($wing) {
        'month', 'year' => $wing,
        'world-cup', 'worldcup', 'world_cup' => 'world-cup',
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
 *   ladder: list<array{player_id: int, name: string, country: string, rating_after: float, rank: int}>
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
        $ladder = amiga_rating_history_ladder_at_cutoff(
            $con,
            $entry['cutoff_event_date'] !== null ? (string) $entry['cutoff_event_date'] : null,
            $entry['cutoff_chrono'] !== null ? (float) $entry['cutoff_chrono'] : null,
            $entry['cutoff_tournament_id'] !== null ? (int) $entry['cutoff_tournament_id'] : null
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
