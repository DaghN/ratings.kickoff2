<?php
/**
 * Small safety helpers for legacy public PHP pages.
 */
declare(strict_types=1);

/** Ensure PHP date functions interpret naive datetimes as UTC (visitor league countdown/medals). */
function k2_site_ensure_utc(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    if (date_default_timezone_get() !== 'UTC') {
        date_default_timezone_set('UTC');
    }
    $done = true;
}

function k2_h(mixed $value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function k2_public_error(string $message = 'Could not load ratings data.', int $statusCode = 500): void
{
	if (!headers_sent()) {
		http_response_code($statusCode);
	}
	echo '<p>' . k2_h($message) . '</p>';
	exit;
}

function k2_positive_int_param(string $name, string $message = 'Invalid request.'): int
{
	$value = $_GET[$name] ?? null;
	$id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
	if ($id === false) {
		k2_public_error($message, 400);
	}

	return (int) $id;
}

function k2_db_connect_or_public_error(
	string $dbhost,
	string $username,
	string $password,
	string $database,
	int|string $dbportnum
): mysqli {
	k2_site_ensure_utc();
	$con = new mysqli($dbhost, $username, $password, $database, (int) $dbportnum);
	if ($con->connect_errno) {
		error_log('DB connection failed: ' . $con->connect_error);
		k2_public_error('Could not connect to ratings database.');
	}
	if (!$con->set_charset('utf8mb4')) {
		error_log('DB charset setup failed: ' . $con->error);
		k2_public_error('Could not prepare ratings database connection.');
	}
	if (!$con->query("SET time_zone = '+00:00'")) {
		error_log('DB timezone setup failed: ' . $con->error);
		k2_public_error('Could not prepare ratings database connection.');
	}

	return $con;
}

function k2_query_or_public_error(mysqli $con, string $query, string $context = 'query'): mysqli_result
{
	$result = mysqli_query($con, $query);
	if ($result === false) {
		error_log('DB ' . $context . ' failed: ' . mysqli_error($con));
		k2_public_error('Could not load ratings data.');
	}

	return $result;
}

/** Hash target immediately above `.k2-player-hero` on all player-wing pages (inbound links only). */
const K2_PLAYER_PAGE_FRAGMENT = 'player';

function k2_player_profile_href(int $id, string $fragment = K2_PLAYER_PAGE_FRAGMENT): string
{
	require_once __DIR__ . '/k2_routes.php';
	$href = k2_route('player-profile', ['id' => $id]);
	if ($fragment !== '') {
		$href .= '#' . ltrim($fragment, '#');
	}

	return $href;
}

function k2_player_link(int|string $id, mixed $name, string $fragment = K2_PLAYER_PAGE_FRAGMENT): string
{
	return '<a class="k2-link-star" href="' . k2_h(k2_player_profile_href((int) $id, $fragment)) . '">' . k2_h($name) . '</a>';
}

/**
 * SQL NULL or empty string from mysqli (unset playertable career field after zero-derived).
 */
function k2_db_is_null(mixed $val): bool
{
	return $val === null || $val === '';
}

/** Default empty cell on leaderboard tables (matches ranked1 peak/nadir). */
function k2_fmt_dash(): string
{
	return '-';
}

/** Derived career game count from simul/post-game (`NumberGames` > 0). */
function k2_derived_games_started(mixed $games): bool
{
	return !k2_db_is_null($games) && (int) $games > 0;
}

/** Games column: always a number; NULL/0 before first processed game displays as 0. */
function k2_fmt_games_played(mixed $games): string
{
	if (k2_db_is_null($games)) {
		return '0';
	}

	return (string) (int) $games;
}

function k2_fmt_int(mixed $val, string $empty = '-'): string
{
	if (k2_db_is_null($val)) {
		return $empty;
	}

	return (string) (int) round((float) $val);
}

/**
 * Career count column. NULL means dash only before first derived game; after that NULL → 0.
 *
 * @param mixed $games Pass playertable `NumberGames` (or null to use legacy dash-on-NULL only).
 */
function k2_fmt_count(mixed $val, mixed $games = null, string $empty = '-'): string
{
	if (!k2_db_is_null($val)) {
		return (string) (int) $val;
	}
	if ($games !== null && k2_derived_games_started($games)) {
		return '0';
	}

	return $empty;
}

/**
 * Ratio stored as 0–1 fraction; $games gates dash vs 0% (see parity display policy in playertable-schema.md).
 */
function k2_fmt_pct_from_ratio(mixed $ratio, mixed $games = null, int $decimals = 1, string $empty = '-'): string
{
	if (!k2_derived_games_started($games)) {
		return $empty;
	}
	if (k2_db_is_null($ratio)) {
		return number_format(0, $decimals) . '%';
	}

	$pct = 100 * (float) $ratio;
	if ($pct == 0.0) {
		return '0%';
	}

	return number_format($pct, $decimals) . '%';
}

function k2_fmt_decimal(mixed $val, mixed $games = null, int $decimals = 2, string $empty = '-'): string
{
	if (!k2_db_is_null($val)) {
		return number_format((float) $val, $decimals);
	}
	if ($games !== null && k2_derived_games_started($games)) {
		return number_format(0, $decimals);
	}

	return $empty;
}

function k2_fmt_peak_rating(mixed $val, string $empty = '-'): string
{
	if (k2_db_is_null($val) || (float) $val == 0.0) {
		return $empty;
	}

	return (string) (int) round((float) $val);
}

function k2_fmt_peak_elo_rank(mixed $val, string $empty = '—'): string
{
	if (k2_db_is_null($val) || (int) $val < 1) {
		return $empty;
	}

	return '#' . (int) $val;
}

function k2_fmt_nadir_rating(mixed $val, float $sentinel = 5000.0, string $empty = '-'): string
{
	if (k2_db_is_null($val) || (float) $val >= $sentinel) {
		return $empty;
	}

	return (string) (int) round((float) $val);
}

/**
 * Leaderboard career stat: games-started NULL → 0; optional $dashWhenEqual (e.g. nadir 5000).
 */
function k2_fmt_lb_stat(mixed $val, mixed $games, ?float $dashWhenEqual = null, string $empty = '-'): string
{
	if ($dashWhenEqual !== null && !k2_db_is_null($val) && (float) $val == $dashWhenEqual) {
		return $empty;
	}

	return k2_fmt_count($val, $games, $empty);
}

/**
 * ratedresults row has been through post-game (same marker as ops replay skip check).
 *
 * @param array<string, mixed> $row raw mysqli assoc row
 */
function k2_rated_game_is_processed(array $row): bool
{
	if (!k2_db_is_null($row['NewRatingA'] ?? null)) {
		return true;
	}
	// Amiga finalize v1: per-game ratings row without new_rating_*.
	return !k2_db_is_null($row['AdjustmentA'] ?? null);
}

/** Non-leaderboard: unset or zero displays as dash (no NumberGames context). */
function k2_fmt_optional_int(mixed $val, float $zeroMeansEmpty = 0.0, string $empty = '-'): string
{
	if (k2_db_is_null($val) || (float) $val == $zeroMeansEmpty) {
		return $empty;
	}

	return (string) (int) round((float) $val);
}
