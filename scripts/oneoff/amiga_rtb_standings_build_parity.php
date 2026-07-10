<?php
declare(strict_types=1);

/**
 * CLI probe: RTB fixture broadcast standings in PHP for SC-8 parity oracle.
 *
 * usage: php amiga_rtb_standings_build_parity.php TOURNAMENT_ID
 */

$tournamentId = (int) ($argv[1] ?? 0);
if ($tournamentId <= 0) {
    fwrite(STDERR, "usage: php amiga_rtb_standings_build_parity.php TOURNAMENT_ID\n");
    exit(2);
}

require __DIR__ . '/../../site/public_html/includes/amiga_running_tournament_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_error) {
    fwrite(STDERR, 'connect: ' . $con->connect_error . PHP_EOL);
    exit(1);
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

$rows = amiga_running_tournament_compute_standings($con, $tournamentId);

echo json_encode(
    ['tournament_id' => $tournamentId, 'rows' => $rows],
    JSON_THROW_ON_ERROR,
);
$con->close();