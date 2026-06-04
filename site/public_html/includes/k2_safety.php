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

function k2_player_link(int|string $id, mixed $name): string
{
	return '<a class="k2-link-star" href="individual1.php?id=' . (int) $id . '">' . k2_h($name) . '</a>';
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

function k2_fmt_int(mixed $val, string $empty = '-'): string
{
	if (k2_db_is_null($val)) {
		return $empty;
	}

	return (string) (int) round((float) $val);
}

function k2_fmt_count(mixed $val, string $empty = '-'): string
{
	if (k2_db_is_null($val)) {
		return $empty;
	}

	return (string) (int) $val;
}

/**
 * Ratio stored as 0–1 fraction; optional $games avoids showing 0% when career stats are unset.
 */
function k2_fmt_pct_from_ratio(mixed $ratio, mixed $games = null, int $decimals = 1, string $empty = '-'): string
{
	if ($games !== null && k2_db_is_null($games)) {
		return $empty;
	}
	if (k2_db_is_null($ratio)) {
		return $empty;
	}

	return number_format(100 * (float) $ratio, $decimals) . '%';
}

function k2_fmt_decimal(mixed $val, int $decimals = 2, string $empty = '-'): string
{
	if (k2_db_is_null($val)) {
		return $empty;
	}

	return number_format((float) $val, $decimals);
}

function k2_fmt_peak_rating(mixed $val, string $empty = '-'): string
{
	if (k2_db_is_null($val) || (float) $val == 0.0) {
		return $empty;
	}

	return (string) (int) round((float) $val);
}

function k2_fmt_nadir_rating(mixed $val, float $sentinel = 5000.0, string $empty = '-'): string
{
	if (k2_db_is_null($val) || (float) $val >= $sentinel) {
		return $empty;
	}

	return (string) (int) round((float) $val);
}

/** Victim/culprit/streak-style: unset or zero displays as dash. */
function k2_fmt_optional_int(mixed $val, float $zeroMeansEmpty = 0.0, string $empty = '-'): string
{
	if (k2_db_is_null($val) || (float) $val == $zeroMeansEmpty) {
		return $empty;
	}

	return (string) (int) round((float) $val);
}
