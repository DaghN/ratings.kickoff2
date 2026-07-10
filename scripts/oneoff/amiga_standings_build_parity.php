<?php
declare(strict_types=1);

/**
 * CLI probe: compute tournament standings in PHP for Python parity oracle (SC-5).
 *
 * usage: php amiga_standings_build_parity.php TOURNAMENT_ID
 */

$tournamentId = (int) ($argv[1] ?? 0);
if ($tournamentId <= 0) {
    fwrite(STDERR, "usage: php amiga_standings_build_parity.php TOURNAMENT_ID\n");
    exit(2);
}

require __DIR__ . '/../../site/public_html/amiga/ops/includes/amiga_post_game_standings.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_error) {
    fwrite(STDERR, 'connect: ' . $con->connect_error . PHP_EOL);
    exit(1);
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

$games = amiga_ops_standings_load_tournament_games($con, $tournamentId);
$scoringContext = amiga_scoring_load_context_for_tournament($con, $tournamentId);
$rows = amiga_ops_compute_tournament_standings($games, $scoringContext);

echo json_encode(
    ['tournament_id' => $tournamentId, 'rows' => $rows],
    JSON_THROW_ON_ERROR,
);
$con->close();