<?php
/**
 * Load and label a historical period league (points or activity) for league.php.
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/status_queries.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/period_activity_leaderboard_query.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/league_standings.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_league_table_render.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_routes.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_ratedresults_games_filters.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_rated_game_row.php';

const K2_LEAGUE_PERIOD_GAMES_PAGE_SIZE = 250;
const K2_LEAGUE_PERIOD_ANCHOR_ID = 'k2-league-period';

function k2_league_period_anchor_id(): string
{
    return K2_LEAGUE_PERIOD_ANCHOR_ID;
}

function k2_league_period_anchor_fragment(): string
{
    return '#' . K2_LEAGUE_PERIOD_ANCHOR_ID;
}

/**
 * league.php deep link with scroll target on the period header.
 *
 * @param array<string, scalar|null> $params
 */
function k2_league_period_landing_href(array $params): string
{
    return k2_route('league', $params) . k2_league_period_anchor_fragment();
}

/**
 * Canonical period anchor for league URLs (day/week Y-m-d, month Y-m, year Y).
 */
function k2_league_period_normalize_start_param(string $period, string $start): ?string
{
    $start = trim($start);
    if ($start === '') {
        return null;
    }

    return match ($period) {
        'day', 'week' => k2_period_activity_normalize_key($period, $start),
        'month' => k2_period_activity_normalize_key(
            'month',
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) ? substr($start, 0, 7) : $start
        ),
        'year' => k2_period_activity_normalize_key(
            'year',
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) ? substr($start, 0, 4)
                : (preg_match('/^\d{4}-\d{2}$/', $start) ? substr($start, 0, 4) : $start)
        ),
        default => null,
    };
}

/**
 * @return array{cup: string, period: string, start: string}|null
 */
function k2_league_period_parse_request(): ?array
{
    $cup = isset($_GET['cup']) ? strtolower(trim((string) $_GET['cup'])) : '';
    $period = isset($_GET['period']) ? strtolower(trim((string) $_GET['period'])) : '';
    $start = isset($_GET['start']) ? trim((string) $_GET['start']) : '';

    if (!in_array($cup, ['points', 'activity'], true)) {
        return null;
    }
    if (!in_array($period, ['day', 'week', 'month', 'year'], true)) {
        return null;
    }
    $normalizedStart = k2_league_period_normalize_start_param($period, $start);
    if ($normalizedStart === null) {
        return null;
    }

    return ['cup' => $cup, 'period' => $period, 'start' => $normalizedStart];
}

function k2_league_period_href(string $cup, string $period, string $periodStart): string
{
    $start = k2_league_period_normalize_start_param($period, $periodStart) ?? $periodStart;

    return k2_league_period_landing_href([
        'cup' => $cup,
        'period' => $period,
        'start' => $start,
    ]);
}

/** In-page league navigation (period steps, games pager) — no landing anchor; use with carry-scroll. */
function k2_league_period_peer_href(string $cup, string $period, string $periodStart, int $offset = 0): string
{
    $start = k2_league_period_normalize_start_param($period, $periodStart) ?? $periodStart;
    $params = [
        'cup' => $cup,
        'period' => $period,
        'start' => $start,
    ];
    if ($offset > 0) {
        $params['offset'] = $offset;
    }

    return k2_route('league', $params);
}

function k2_league_period_cup_label(string $cup): string
{
    return $cup === 'activity' ? 'Activity league' : 'Points league';
}

/**
 * Short link label for milestone garden cards.
 */
function k2_league_period_short_label(string $cup, string $period, string $periodStart): string
{
    $key = k2_league_key_from_period_start($period, $periodStart);
    if ($key === null) {
        return k2_league_period_cup_label($cup);
    }
    $bounds = k2_status_bounds_from_period_key($period, $key);
    $grain = k2_status_period_segment_label($period);

    return trim($grain . ' ' . ($cup === 'activity' ? 'activity' : 'points') . ' league, ' . ($bounds['label'] ?? $periodStart));
}

/**
 * League meta bundle for Status-style prose (label, end, total_games, period).
 *
 * @param array{label: string, start: string, end: string} $bounds
 * @return array{label: string, start: string, end: string, total_games: int, period: string}
 */
function k2_league_period_meta_bundle(string $period, array $bounds, int $totalGames): array
{
    return [
        'label' => (string) ($bounds['label'] ?? ''),
        'start' => (string) ($bounds['start'] ?? ''),
        'end' => (string) ($bounds['end'] ?? ''),
        'total_games' => $totalGames,
        'period' => $period,
    ];
}

function k2_league_period_title(string $cup, array $bounds): string
{
    $periodLabel = trim((string) ($bounds['label'] ?? ''));
    $cupLabel = k2_league_period_cup_label($cup);

    return $periodLabel !== '' ? $cupLabel . ' · ' . $periodLabel : $cupLabel;
}

function k2_league_period_render_title_html(string $cup, array $bounds): void
{
    $cupLabel = k2_league_period_cup_label($cup);
    $periodLabel = trim((string) ($bounds['label'] ?? ''));
    ?>
		<h1 id="<?php echo k2_h(k2_league_period_anchor_id()); ?>" class="k2-league-period__title">
			<span class="k2-league-period__title-cup"><?php echo k2_h($cupLabel); ?></span><?php if ($periodLabel !== '') { ?><span class="k2-league-period__title-sep" aria-hidden="true"> · </span><span class="k2-league-period__title-period"><?php echo k2_h($periodLabel); ?></span><?php } ?>
		</h1>
<?php
}

/** @return DateTimeImmutable|null */
function k2_league_period_parse_utc_instant(string $isoDateTime): ?DateTimeImmutable
{
    if (function_exists('k2_site_ensure_utc')) {
        k2_site_ensure_utc();
    }
    $utc = new DateTimeZone('UTC');
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $isoDateTime, $utc);
    if (!$dt instanceof DateTimeImmutable) {
        $ts = strtotime($isoDateTime);
        if ($ts === false) {
            return null;
        }
        $dt = (new DateTimeImmutable('@' . $ts))->setTimezone($utc);
    }

    return $dt;
}

/** Readable UTC instant — calendar date in link-star, e.g. Thursday, January 1, 2026, 00:00 UTC */
function k2_league_period_format_utc_instant_html(string $isoDateTime): string
{
    $dt = k2_league_period_parse_utc_instant($isoDateTime);
    if ($dt === null) {
        return k2_h($isoDateTime);
    }

    return k2_h($dt->format('l')) . ', <span class="k2-link-star">' . k2_h($dt->format('F j, Y')) . '</span>, '
        . k2_h($dt->format('H:i')) . ' UTC';
}

function k2_league_period_link_star_num(int $value): string
{
    return '<span class="blue">' . number_format($value) . '</span>';
}

/** Players with ≥1 rated game in the period (from standings rows already loaded). */
function k2_league_period_player_count(string $cup, ?array $pointsLeague, array $activityEntries): int
{
    if ($cup === 'points' && $pointsLeague !== null) {
        return count($pointsLeague['rows'] ?? []);
    }

    return count($activityEntries);
}

function k2_league_period_sibling_cup(string $cup): string
{
    return $cup === 'activity' ? 'points' : 'activity';
}

function k2_league_period_sibling_link_html(string $cup, string $period, string $periodStart): string
{
    $siblingCup = k2_league_period_sibling_cup($cup);
    $href = k2_league_period_peer_href($siblingCup, $period, $periodStart);
    $label = k2_league_period_cup_label($siblingCup);

    return '<span class="k2-league-period__sibling" data-k2-carry-scroll>(<a class="k2-link-star k2-league-period__sibling-link" href="'
        . k2_h($href) . '">' . k2_h($label) . ' &rarr;</a>)</span>';
}

function k2_league_period_cup_scope_label(string $cup): string
{
    return $cup === 'activity' ? 'activity league' : 'points league';
}

function k2_league_period_time_left_html(string $timeLeft, bool $isLive): string
{
    if (!$isLive) {
        return '';
    }
    $remaining = trim($timeLeft);
    if ($remaining === '' || $remaining === 'ended') {
        return '';
    }

    return 'Time left: <span class="blue">' . k2_h($remaining) . '</span>';
}

function k2_league_period_activity_prose_html(
    string $cup,
    int $playerCount,
    int $totalGames,
    bool $isLive
): string {
    $scope = k2_league_period_cup_scope_label($cup);

    if ($totalGames === 0 && $playerCount === 0) {
        return $isLive ? 'No rated games yet.' : 'No rated games were played.';
    }

    $playersWord = $playerCount === 1 ? 'player' : 'players';
    $gamesWord = $totalGames === 1 ? 'rated game' : 'rated games';
    $players = k2_league_period_link_star_num($playerCount);
    $games = k2_league_period_link_star_num($totalGames);

    if ($isLive) {
        $have = $playerCount === 1 ? 'has' : 'have';

        return 'So far, ' . $players . ' ' . $playersWord . ' ' . $have . ' played ' . $games . ' ' . $gamesWord
            . ' in this ' . k2_h($scope) . '.';
    }

    return $players . ' ' . $playersWord . ' played ' . $games . ' ' . $gamesWord
        . ' in this ' . k2_h($scope) . '.';
}

function k2_league_period_compare_keys(string $period, string $a, string $b): int
{
    if ($a === $b) {
        return 0;
    }
    if ($period === 'year') {
        return ((int) $a) <=> ((int) $b);
    }

    return $a < $b ? -1 : 1;
}

function k2_league_period_step_key(string $period, string $key, int $direction): ?string
{
    if ($direction === 0) {
        return $key;
    }
    $normalized = k2_period_activity_normalize_key($period, $key);
    if ($normalized === null) {
        return null;
    }
    $utc = new DateTimeZone('UTC');
    $sign = $direction > 0 ? '+' : '-';
    $steps = abs($direction);

    return match ($period) {
        'day' => (new DateTimeImmutable($normalized . ' 00:00:00', $utc))
            ->modify($sign . $steps . ' day')->format('Y-m-d'),
        'week' => (new DateTimeImmutable($normalized . ' 00:00:00', $utc))
            ->modify($sign . ($steps * 7) . ' day')->format('Y-m-d'),
        'month' => (new DateTimeImmutable($normalized . '-01 00:00:00', $utc))
            ->modify($sign . $steps . ' month')->format('Y-m'),
        'year' => (string) (((int) $normalized) + $direction),
        default => null,
    };
}

/**
 * @return array{min: string, max: string}
 */
function k2_league_period_nav_bounds(mysqli $con, string $period, DateTimeImmutable $serverNow): array
{
    $choicesErr = null;
    if ($period === 'day') {
        $dayBounds = k2_period_activity_day_bounds($con, $choicesErr);
        $today = $serverNow->format('Y-m-d');
        $firstRatedDay = $today;
        $rFirst = mysqli_query($con, 'SELECT MIN(DATE(`Date`)) AS d FROM ratedresults');
        if ($rFirst !== false) {
            $rowFirst = mysqli_fetch_assoc($rFirst);
            mysqli_free_result($rFirst);
            if (!empty($rowFirst['d'])) {
                $firstRatedDay = (string) $rowFirst['d'];
            }
        }
        $dayMin = $dayBounds['min'] ?? $today;
        $dayMax = $dayBounds['max'] ?? $today;
        if ($dayMin === $dayMax || ($dayBounds['min'] ?? null) === null) {
            $dayMin = $firstRatedDay;
        }
        if ($dayMax < $today) {
            $dayMax = $today;
        }
        if ($dayMin < $firstRatedDay) {
            $dayMin = $firstRatedDay;
        }

        return ['min' => $dayMin, 'max' => $dayMax];
    }

    $choices = k2_period_activity_available_keys($con, $period, $choicesErr);
    $leagueBounds = k2_status_league_period_bounds($period, 0, $serverNow);
    $maxKey = '';
    if ($leagueBounds !== null) {
        $maxKey = k2_status_period_activity_key_from_bounds($period, $leagueBounds) ?? '';
    }
    $minKey = $choices !== [] ? (string) $choices[array_key_last($choices)] : '';

    return ['min' => $minKey, 'max' => $maxKey];
}

/**
 * @return array{prev: ?string, next: ?string}
 */
function k2_league_period_adjacent_starts(
    mysqli $con,
    string $period,
    string $periodStart,
    DateTimeImmutable $serverNow
): array {
    $key = k2_league_key_from_period_start($period, $periodStart);
    if ($key === null) {
        return ['prev' => null, 'next' => null];
    }

    $bounds = k2_league_period_nav_bounds($con, $period, $serverNow);
    $result = ['prev' => null, 'next' => null];

    $prevKey = k2_league_period_step_key($period, $key, -1);
    if ($prevKey !== null) {
        $min = $bounds['min'];
        if ($min === '' || k2_league_period_compare_keys($period, $prevKey, $min) >= 0) {
            $result['prev'] = k2_league_period_normalize_start_param($period, $prevKey);
        }
    }

    $nextKey = k2_league_period_step_key($period, $key, 1);
    if ($nextKey !== null) {
        $max = $bounds['max'];
        if ($max === '' || k2_league_period_compare_keys($period, $nextKey, $max) <= 0) {
            $result['next'] = k2_league_period_normalize_start_param($period, $nextKey);
        }
    }

    return $result;
}

function k2_league_period_step_help_attrs(string $label): string
{
    return ' aria-label="' . k2_h($label) . '"';
}

function k2_league_period_render_period_steps_html(?string $prevStart, ?string $nextStart, string $cup, string $period): void
{
    $prevHref = $prevStart !== null && $prevStart !== ''
        ? k2_league_period_peer_href($cup, $period, $prevStart)
        : null;
    $nextHref = $nextStart !== null && $nextStart !== ''
        ? k2_league_period_peer_href($cup, $period, $nextStart)
        : null;
    $prevLabel = 'Previous league period';
    $nextLabel = 'Next league period';
    ?>
			<nav class="k2-player-games-day-steps k2-league-period__period-steps" data-k2-carry-scroll aria-label="League period">
<?php if ($prevHref !== null) { ?>
				<a class="k2-player-games-day-step k2-player-games-day-step--prev" href="<?php echo k2_h($prevHref); ?>"<?php echo k2_league_period_step_help_attrs($prevLabel); ?>>
					<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span>
				</a>
<?php } else { ?>
				<span class="k2-player-games-day-step k2-player-games-day-step--prev is-disabled" aria-disabled="true"<?php echo k2_league_period_step_help_attrs($prevLabel); ?>>
					<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span>
				</span>
<?php } ?>
<?php if ($nextHref !== null) { ?>
				<a class="k2-player-games-day-step k2-player-games-day-step--next" href="<?php echo k2_h($nextHref); ?>"<?php echo k2_league_period_step_help_attrs($nextLabel); ?>>
					<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span>
				</a>
<?php } else { ?>
				<span class="k2-player-games-day-step k2-player-games-day-step--next is-disabled" aria-disabled="true"<?php echo k2_league_period_step_help_attrs($nextLabel); ?>>
					<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span>
				</span>
<?php } ?>
			</nav>
<?php
}

/**
 * @param array<string, mixed> $loaded
 */
function k2_league_period_render_intro(array $loaded): void
{
    $bounds = is_array($loaded['bounds'] ?? null) ? $loaded['bounds'] : [];
    $startLabelHtml = k2_league_period_format_utc_instant_html((string) ($bounds['start'] ?? ''));
    $endLabelHtml = k2_league_period_format_utc_instant_html((string) ($bounds['end'] ?? ''));
    $totalGames = (int) ($loaded['total_games'] ?? 0);
    $playerCount = (int) ($loaded['player_count'] ?? 0);
    $isLive = (bool) ($loaded['is_live'] ?? false);
    $periodNotStarted = false;
    $startRaw = (string) ($bounds['start'] ?? '');
    if ($startRaw !== '') {
        if (function_exists('k2_site_ensure_utc')) {
            k2_site_ensure_utc();
        }
        $startTs = strtotime($startRaw);
        $periodNotStarted = $startTs !== false && time() < $startTs;
    }
    $openVerb = $periodNotStarted ? 'Opens' : 'Opened';
    $closeVerb = $isLive ? 'closes' : 'closed';
    $timeLeftHtml = k2_league_period_time_left_html((string) ($loaded['time_left'] ?? ''), $isLive);
    $cup = (string) ($loaded['cup'] ?? 'points');
    ?>
	<header class="k2-league-period__intro k2-hub-chapter">
<?php k2_league_period_render_title_html($cup, $bounds); ?>
		<div class="k2-league-period__lede">
			<p><?php echo k2_h($openVerb); ?> <?php echo $startLabelHtml; ?> and <?php echo k2_h($closeVerb); ?> <?php echo $endLabelHtml; ?>.</p>
			<p><?php echo k2_league_period_activity_prose_html(
			    $cup,
			    $playerCount,
			    $totalGames,
			    $isLive
			); ?></p>
<?php if ($timeLeftHtml !== '') { ?>
			<p><?php echo $timeLeftHtml; ?></p>
<?php } ?>
		</div>
	</header>
<?php
}

/**
 * @param array<string, mixed> $loaded
 */
function k2_league_period_render_standings_header(array $loaded): void
{
    $cup = (string) ($loaded['cup'] ?? 'points');
    $period = (string) ($loaded['period'] ?? '');
    $periodStart = (string) ($loaded['start'] ?? '');
    ?>
	<div class="k2-league-period__standings-head">
		<h2 id="k2-league-period-standings-title" class="k2-panel-heading k2-league-period__standings-heading">Standings</h2>
		<div class="k2-league-period__standings-nav">
<?php k2_league_period_render_period_steps_html(
    isset($loaded['period_prev_start']) ? (string) $loaded['period_prev_start'] : null,
    isset($loaded['period_next_start']) ? (string) $loaded['period_next_start'] : null,
    $cup,
    $period
); ?>
			<?php echo k2_league_period_sibling_link_html($cup, $period, $periodStart); ?>
		</div>
	</div>
<?php
}

function k2_league_period_games_page_size(): int
{
    return K2_LEAGUE_PERIOD_GAMES_PAGE_SIZE;
}

function k2_league_period_games_href(string $cup, string $period, string $periodStart, int $offset = 0): string
{
    return k2_league_period_peer_href($cup, $period, $periodStart, $offset);
}

/**
 * @return array{0: string, 1: string}|null UTC [start, end) for ratedresults
 */
function k2_league_period_games_bounds(string $period, string $periodStart): ?array
{
    $key = k2_league_key_from_period_start($period, $periodStart);
    if ($key === null) {
        return null;
    }
    $bounds = k2_status_bounds_from_period_key($period, $key);
    if ($bounds === null) {
        return null;
    }

    return [(string) $bounds['start'], (string) $bounds['end']];
}

function k2_league_period_count_games(mysqli $con, string $period, string $periodStart, ?string &$error = null): int
{
    $error = null;
    $range = k2_league_period_games_bounds($period, $periodStart);
    if ($range === null) {
        $error = 'invalid_period';

        return 0;
    }
    [$start, $end] = $range;
    $stmt = mysqli_prepare($con, 'SELECT COUNT(*) AS c FROM ratedresults WHERE `Date` >= ? AND `Date` < ?');
    if ($stmt === false) {
        $error = mysqli_error($con);

        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'ss', $start, $end);
    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);

        return 0;
    }
    $r = mysqli_stmt_get_result($stmt);
    $row = $r ? mysqli_fetch_assoc($r) : false;
    if ($r) {
        mysqli_free_result($r);
    }
    mysqli_stmt_close($stmt);

    return $row ? (int) ($row['c'] ?? 0) : 0;
}

/**
 * @return list<array<string, mixed>>
 */
function k2_league_period_fetch_games(
    mysqli $con,
    string $period,
    string $periodStart,
    int $offset,
    int $limit,
    ?string &$error = null
): array {
    $error = null;
    $range = k2_league_period_games_bounds($period, $periodStart);
    if ($range === null) {
        $error = 'invalid_period';

        return [];
    }
    [$start, $end] = $range;
    $offset = max(0, $offset);
    $limit = max(1, min(500, $limit));
    $sql = 'SELECT * FROM ratedresults WHERE `Date` >= ? AND `Date` < ? ORDER BY `Date` DESC, `id` DESC LIMIT ? OFFSET ?';
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        $error = mysqli_error($con);

        return [];
    }
    mysqli_stmt_bind_param($stmt, 'ssii', $start, $end, $limit, $offset);
    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);

        return [];
    }
    $r = mysqli_stmt_get_result($stmt);
    $rows = [];
    if ($r !== false) {
        while ($row = mysqli_fetch_assoc($r)) {
            $rows[] = $row;
        }
        mysqli_free_result($r);
    }
    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * @return array{
 *   cup: string,
 *   period: string,
 *   start: string,
 *   title: string,
 *   bounds: array{label: string, start: string, end: string},
 *   is_live: bool,
 *   time_left: string,
 *   show_medals: bool,
 *   total_games: int,
 *   player_count: int,
 *   period_prev_start: ?string,
 *   period_next_start: ?string,
 *   points_league: ?array,
 *   activity_entries: ?array
 * }|null
 */
function k2_league_period_load(mysqli $con, string $cup, string $period, string $periodStart): ?array
{
    $key = k2_league_key_from_period_start($period, $periodStart);
    if ($key === null) {
        return null;
    }
    $bounds = k2_status_bounds_from_period_key($period, $key);
    if ($bounds === null) {
        return null;
    }

    $clock = k2_status_server_clock($con);
    $serverNow = $clock['now'];
    $title = k2_league_period_title($cup, $bounds);

    if ($cup === 'points') {
        $error = null;
        $league = k2_status_league_for_key($con, $period, $key, null, $error);
        $totalGames = $league !== null ? (int) ($league['total_games'] ?? 0) : 0;
        $metaBundle = $league !== null
            ? array_merge($league, ['period' => $period])
            : k2_league_period_meta_bundle($period, $bounds, $totalGames);
        $endTs = k2_status_league_end_epoch($metaBundle);
        $isLive = $endTs > 0 && $endTs > $serverNow->getTimestamp();
        $timeLeft = $isLive ? k2_status_format_league_time_left($endTs - $serverNow->getTimestamp()) : '';
        $playerCount = k2_league_period_player_count('points', $league, []);
        $adjacent = k2_league_period_adjacent_starts($con, $period, $periodStart, $serverNow);

        return [
            'cup' => $cup,
            'period' => $period,
            'start' => $periodStart,
            'title' => $title,
            'bounds' => $bounds,
            'is_live' => $isLive,
            'time_left' => $timeLeft,
            'show_medals' => $endTs > 0 && !$isLive,
            'total_games' => $totalGames,
            'player_count' => $playerCount,
            'period_prev_start' => $adjacent['prev'],
            'period_next_start' => $adjacent['next'],
            'points_league' => $league,
            'activity_entries' => null,
        ];
    }

    $error = null;
    $entries = k2_period_activity_leaderboard_entries($con, $period, $key, 0, $error);
    $totalGames = k2_period_activity_total_games($con, $period, $key, $error);
    $metaBundle = k2_league_period_meta_bundle($period, $bounds, $totalGames);
    $endTs = k2_status_league_end_epoch($metaBundle);
    $isLive = $endTs > 0 && $endTs > $serverNow->getTimestamp();
    $timeLeft = $isLive ? k2_status_format_league_time_left($endTs - $serverNow->getTimestamp()) : '';
    $playerCount = k2_league_period_player_count('activity', null, $entries);
    $adjacent = k2_league_period_adjacent_starts($con, $period, $periodStart, $serverNow);

    return [
        'cup' => $cup,
        'period' => $period,
        'start' => $periodStart,
        'title' => $title,
        'bounds' => $bounds,
        'is_live' => $isLive,
        'time_left' => $timeLeft,
        'show_medals' => $endTs > 0 && !$isLive,
        'total_games' => $totalGames,
        'player_count' => $playerCount,
        'period_prev_start' => $adjacent['prev'],
        'period_next_start' => $adjacent['next'],
        'points_league' => null,
        'activity_entries' => $entries,
    ];
}

/**
 * @param array<string, mixed> $loaded
 */
function k2_league_period_render_table(array $loaded): void
{
    $showMedals = (bool) ($loaded['show_medals'] ?? false);
    if ($loaded['cup'] === 'points') {
        $league = $loaded['points_league'] ?? null;
        if ($league === null || ($league['rows'] ?? []) === []) {
            echo '<p class="k2-ms-meta-hint">No standings stored for this period.</p>';

            return;
        }
        k2_status_render_league_table($league, $showMedals, true);

        return;
    }

    $entries = $loaded['activity_entries'] ?? [];
    if ($entries === []) {
        echo '<p class="k2-ms-meta-hint">No activity standings for this period.</p>';

        return;
    }
    k2_status_render_activity_competition_table($entries, $showMedals, true);
}

/**
 * @param list<array<string, mixed>> $games
 */
function k2_league_period_render_games_section(
    array $loaded,
    array $games,
    int $totalGames,
    int $offset,
    int $limit
): void {
    $cup = (string) ($loaded['cup'] ?? '');
    $period = (string) ($loaded['period'] ?? '');
    $start = (string) ($loaded['start'] ?? '');
    $shown = count($games);
    $firstShown = $totalGames > 0 ? $offset + 1 : 0;
    $lastShown = $offset + $shown;
    $prevHref = $offset > 0
        ? k2_league_period_games_href($cup, $period, $start, max(0, $offset - $limit))
        : null;
    $nextHref = $offset + $limit < $totalGames
        ? k2_league_period_games_href($cup, $period, $start, $offset + $limit)
        : null;
    ?>
<section class="k2-league-period__games-section" id="league-games">
	<h2 class="k2-panel-heading k2-league-period__games-heading">Games</h2>
	<div class="k2-player-games-status k2-realm-games-all__status k2-league-period__games-status" data-k2-carry-scroll>
		<div class="k2-realm-games-all__status-range">
			<span class="k2-realm-games-all__status-text">
<?php if ($totalGames === 0) { ?>
				No rated games in this period.
<?php } else { ?>
				Showing <?php echo (int) $firstShown; ?>–<?php echo (int) $lastShown; ?> of <span class="k2-link-star"><?php echo number_format($totalGames); ?></span> rated games.
<?php } ?>
			</span>
<?php if ($totalGames > $limit) { ?>
			<nav class="k2-player-games-day-steps k2-realm-games-all__status-nav" aria-label="Page">
<?php if ($prevHref !== null) { ?>
				<a class="k2-player-games-day-step k2-player-games-day-step--prev" href="<?php echo k2_h($prevHref); ?>"<?php echo k2_league_period_step_help_attrs('Previous page'); ?>>
					<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span>
				</a>
<?php } else { ?>
				<span class="k2-player-games-day-step k2-player-games-day-step--prev is-disabled" aria-disabled="true"<?php echo k2_league_period_step_help_attrs('Previous page'); ?>>
					<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span>
				</span>
<?php } ?>
<?php if ($nextHref !== null) { ?>
				<a class="k2-player-games-day-step k2-player-games-day-step--next" href="<?php echo k2_h($nextHref); ?>"<?php echo k2_league_period_step_help_attrs('Next page'); ?>>
					<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span>
				</a>
<?php } else { ?>
				<span class="k2-player-games-day-step k2-player-games-day-step--next is-disabled" aria-disabled="true"<?php echo k2_league_period_step_help_attrs('Next page'); ?>>
					<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span>
				</span>
<?php } ?>
			</nav>
<?php } ?>
		</div>
	</div>
<?php if ($games !== []) { ?>
	<div class="k2-table-wrap" data-k2-scroll-mirror>
		<table class="k2-table k2-table--numeric-default k2-table--calm-stats k2-table--realm-games-all ranked-pages-table ranked-table-pending" data-k2-table="sortable" data-k2-default-sort="1" data-k2-default-direction="desc" data-k2-sort-scope="league-games">
			<thead>
				<tr>
					<th class="k2-table-cell--left" data-k2-sort="number">ID</th>
					<th class="k2-table-cell--left k2-table-cell--pad-left-xs" data-k2-sort="number">Date</th>
					<th class="k2-table-cell--left" data-k2-sort="text">Team A</th>
					<th data-k2-sort="number">A</th>
					<th class="k2-table-cell--left" data-k2-sort="number">B</th>
					<th class="k2-table-cell--left" data-k2-sort="text">Team B</th>
					<th class="k2-table-cell--pad-left-md" data-k2-sort="number">GD</th>
					<th data-k2-sort="number">Sum</th>
					<th data-k2-sort="number">TS</th>
					<th class="k2-table-cell--left k2-table-cell--pad-left-lg" data-k2-sort="text">Winner</th>
					<th data-k2-sort="number">Rating A</th>
					<th data-k2-sort="number">Rating B</th>
					<th data-k2-sort="number">Elo Diff</th>
					<th class="k2-table-cell--pad-right-xs" data-k2-sort="number">Fav ES</th>
					<th class="k2-table-cell--left" data-k2-sort="number">Adjustment</th>
					<th class="k2-table-cell--left" data-k2-sort="number"><span class="visually-hidden">Adjustment lost</span></th>
				</tr>
			</thead>
			<tbody class="black">
<?php foreach ($games as $row) { ?>
				<?php echo k2_rated_game_row_html($row, ['id_mode' => 'link']); ?>
<?php } ?>
			</tbody>
		</table>
	</div>
<?php } ?>
</section>
<?php
}
