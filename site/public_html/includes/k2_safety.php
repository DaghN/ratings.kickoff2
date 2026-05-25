<?php
/**
 * Small safety helpers for legacy public PHP pages.
 */
declare(strict_types=1);

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
	$con = new mysqli($dbhost, $username, $password, $database, (int) $dbportnum);
	if ($con->connect_errno) {
		error_log('DB connection failed: ' . $con->connect_error);
		k2_public_error('Could not connect to ratings database.');
	}
	if (!$con->set_charset('utf8mb4')) {
		error_log('DB charset setup failed: ' . $con->error);
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
	return '<a href="individual1.php?id=' . (int) $id . '">' . k2_h($name) . '</a>';
}
