<?php
/**
 * Online league period — with-player stepper (`start_with=`).
 *
 * @see docs/with-player-stepper-policy.md §3.3
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_archive_listbox.php';
require_once __DIR__ . '/k2_table_helpers.php';
require_once __DIR__ . '/k2_routes.php';
require_once __DIR__ . '/k2_start_with_url.php';
require_once __DIR__ . '/amiga_participation_step_lib.php';
require_once __DIR__ . '/league_standings.php';

/**
 * @return list<array{id: int, name: string}>
 */
function k2_league_period_participation_eligible_players(mysqli $con): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $sql = 'SELECT ID AS id, Name AS name FROM playertable '
        . 'WHERE Display = 1 AND NumberGames > 0 AND Name IS NOT NULL AND TRIM(Name) <> \'\' '
        . 'ORDER BY Name ASC, ID ASC';
    $res = $con->query($sql);
    if (!$res) {
        $cache = [];

        return $cache;
    }

    $players = [];
    while ($row = $res->fetch_assoc()) {
        $players[] = [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
        ];
    }
    $res->free();
    $cache = $players;

    return $players;
}

function k2_league_period_online_player_has_rated_games(mysqli $con, int $playerId): bool
{
    static $cache = [];

    if ($playerId < 1) {
        return false;
    }
    if (array_key_exists($playerId, $cache)) {
        return $cache[$playerId];
    }

    $stmt = $con->prepare(
        'SELECT 1 FROM playertable WHERE ID = ? AND Display = 1 AND NumberGames > 0 LIMIT 1'
    );
    if (!$stmt) {
        $cache[$playerId] = false;

        return false;
    }
    $stmt->bind_param('i', $playerId);
    $ok = $stmt->execute();
    $res = $ok ? $stmt->get_result() : false;
    $has = $res && $res->num_rows > 0;
    if ($res) {
        $res->free();
    }
    $stmt->close();
    $cache[$playerId] = $has;

    return $has;
}

/** Unknown id or player with no rated games → filter off (silent). */
function k2_start_with_active_player_id(mysqli $con): int
{
    $playerId = k2_start_with_from_request();
    if ($playerId < 1) {
        return 0;
    }
    if (!k2_league_period_online_player_has_rated_games($con, $playerId)) {
        return 0;
    }

    return $playerId;
}

/**
 * @return list<string> normalized `start=` params (chrono asc)
 */
function k2_league_period_player_participated_starts(mysqli $con, int $playerId, string $period): array
{
    static $cache = [];

    if ($playerId < 1 || !in_array($period, K2_LEAGUE_PERIOD_TYPES, true)) {
        return [];
    }

    $cacheKey = $playerId . ':' . $period;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $sql = 'SELECT period_start FROM player_period_games '
        . 'WHERE player_id = ? AND period_type = ? AND games > 0 ORDER BY period_start ASC';
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('is', $playerId, $period);
    if (!$stmt->execute()) {
        $stmt->close();

        return [];
    }
    $res = $stmt->get_result();
    $starts = [];
    while ($row = $res->fetch_assoc()) {
        $normalized = k2_league_period_normalize_start_param($period, (string) ($row['period_start'] ?? ''));
        if ($normalized !== null) {
            $starts[] = $normalized;
        }
    }
    if ($res) {
        $res->free();
    }
    $stmt->close();
    $cache[$cacheKey] = $starts;

    return $starts;
}

/**
 * @return array<string, true>
 */
function k2_league_period_step_eligible_start_set(mysqli $con, int $playerId, string $period): array
{
    $set = [];
    foreach (k2_league_period_player_participated_starts($con, $playerId, $period) as $start) {
        $set[$start] = true;
    }

    return $set;
}

/**
 * Full chrono period catalog within Status nav bounds (never pre-filtered).
 *
 * @return list<array{key: string}>
 */
function k2_league_period_step_catalog(mysqli $con, string $period, DateTimeImmutable $serverNow): array
{
    static $cache = [];

    if (isset($cache[$period])) {
        return $cache[$period];
    }

    if (!in_array($period, K2_LEAGUE_PERIOD_TYPES, true)) {
        $cache[$period] = [];

        return [];
    }

    $bounds = k2_league_period_nav_bounds($con, $period, $serverNow);
    $minKey = k2_period_activity_normalize_key($period, $bounds['min']);
    $maxKey = k2_period_activity_normalize_key($period, $bounds['max']);
    if ($minKey === null || $maxKey === null || $minKey === '' || $maxKey === '') {
        $cache[$period] = [];

        return [];
    }

    $catalog = [];
    $key = $minKey;
    $guard = 0;
    while ($guard++ < 10000 && k2_league_period_compare_keys($period, $key, $maxKey) <= 0) {
        $start = k2_league_period_normalize_start_param($period, $key);
        if ($start !== null) {
            $catalog[] = ['key' => $start];
        }
        if (k2_league_period_compare_keys($period, $key, $maxKey) >= 0) {
            break;
        }
        $nextKey = k2_league_period_step_key($period, $key, 1);
        if ($nextKey === null || $nextKey === $key) {
            break;
        }
        $key = $nextKey;
    }

    $cache[$period] = $catalog;

    return $catalog;
}

/**
 * @return array{prev: ?string, next: ?string}
 */
function k2_league_period_with_player_adjacent_starts(
    mysqli $con,
    string $period,
    string $periodStart,
    int $playerId,
    DateTimeImmutable $serverNow
): array {
    $normalizedStart = k2_league_period_normalize_start_param($period, $periodStart);
    if ($normalizedStart === null || $playerId < 1) {
        return ['prev' => null, 'next' => null];
    }

    $catalog = k2_league_period_step_catalog($con, $period, $serverNow);
    if ($catalog === []) {
        return ['prev' => null, 'next' => null];
    }

    $eligible = k2_league_period_step_eligible_start_set($con, $playerId, $period);
    if ($eligible === []) {
        return ['prev' => null, 'next' => null];
    }

    $steps = k2_participation_step_keys($catalog, $normalizedStart, $eligible);

    return [
        'prev' => $steps['prev_key'],
        'next' => $steps['next_key'],
    ];
}

/**
 * 302 to nearest eligible period when `start_with=` is active but current `start=` is off-filter.
 * Prefers previous eligible neighbor, else next.
 */
function k2_league_period_apply_start_with_snap_redirect(
    mysqli $con,
    string $cup,
    string $period,
    string $periodStart,
    DateTimeImmutable $serverNow,
): void {
    if (headers_sent()) {
        return;
    }

    $playerId = k2_start_with_active_player_id($con);
    if ($playerId < 1) {
        return;
    }

    $normalizedStart = k2_league_period_normalize_start_param($period, $periodStart);
    if ($normalizedStart === null) {
        return;
    }

    $catalog = k2_league_period_step_catalog($con, $period, $serverNow);
    if ($catalog === []) {
        return;
    }

    $eligible = k2_league_period_step_eligible_start_set($con, $playerId, $period);
    $targetStart = k2_participation_snap_target_key($catalog, $normalizedStart, $eligible);
    if ($targetStart === null || $targetStart === $normalizedStart) {
        return;
    }

    header('Location: ' . k2_league_period_peer_href($cup, $period, $targetStart), true, 302);
    exit;
}

/**
 * @return array<string, scalar>
 */
function k2_league_period_with_player_carry_query_params(string $cup, string $period, string $periodStart): array
{
    $start = k2_league_period_normalize_start_param($period, $periodStart) ?? $periodStart;
    /** @var array<string, scalar> $params */
    $params = [
        'cup' => $cup,
        'period' => $period,
        'start' => $start,
    ];
    $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
    if ($offset > 0) {
        $params['offset'] = $offset;
    }

    return $params;
}

/**
 * @param array<string, mixed> $loaded
 */
function k2_league_period_render_with_player_listbox(mysqli $con, array $loaded): void
{
    $cup = (string) ($loaded['cup'] ?? 'points');
    $period = (string) ($loaded['period'] ?? '');
    $periodStart = (string) ($loaded['start'] ?? '');
    if ($period === '' || $periodStart === '') {
        return;
    }

    $playerId = k2_start_with_from_request();
    $selected = $playerId > 0 ? (string) $playerId : '';
    $choices = [
        ['value' => '', 'label' => 'All players'],
    ];
    foreach (k2_league_period_participation_eligible_players($con) as $player) {
        $choices[] = [
            'value' => (string) $player['id'],
            'label' => $player['name'],
        ];
    }
    ?>
<form class="k2-player-games-controls k2-league-period__with-player" method="get" action="<?php echo k2_h(k2_route('league')); ?>" data-k2-carry-scroll>
<?php
    foreach (k2_league_period_with_player_carry_query_params($cup, $period, $periodStart) as $carryName => $carryValue) {
        if ($carryName === 'start_with') {
            continue;
        }
        echo '<input type="hidden" name="' . k2_h($carryName) . '" value="' . k2_h((string) $carryValue) . '" />';
    }
    foreach (k2_table_scoped_sort_query_params() as $sortName => $sortValue) {
        echo '<input type="hidden" name="' . k2_h($sortName) . '" value="' . k2_h((string) $sortValue) . '" />';
    }
    k2_archive_listbox_render(
        'start_with',
        'k2-league-period-start-with',
        $selected,
        $choices,
        'With player',
        '',
        'With player...',
        false,
        '',
    ); ?>
</form>
<?php
}