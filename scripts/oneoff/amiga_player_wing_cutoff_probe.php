<?php
/**
 * Probe hero games + tournaments tabs at snapshot cutoff.
 *
 * Run: php scripts/oneoff/amiga_player_wing_cutoff_probe.php [player_id] [as]
 */
declare(strict_types=1);

require __DIR__ . '/../../site/public_html/includes/amiga_snapshot_context.php';
require __DIR__ . '/../../site/public_html/includes/amiga_player_tournament_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_player_games_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

$playerId = isset($argv[1]) ? (int) $argv[1] : 134;
$as = isset($argv[2]) ? (string) $argv[2] : 'year:2003';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    fwrite(STDERR, "connect: {$con->connect_error}\n");
    exit(1);
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

$presentTypes = '';
$presentParams = [];
$wherePresent = amiga_games_where_clause(
    $playerId,
    'all',
    0,
    0,
    'all',
    '',
    '',
    0,
    0,
    $presentTypes,
    $presentParams,
    null
);
$presentGames = amiga_games_query_all(
    $con,
    'SELECT COUNT(*) AS c ' . amiga_rated_games_from_sql() . ' WHERE ' . $wherePresent,
    $presentTypes,
    $presentParams
);
$presentTournaments = amiga_player_tournament_participation_all($con, $playerId);

amiga_snapshot_context_reset();
$_GET = ['id' => (string) $playerId, 'as' => $as];
$ctx = amiga_snapshot_context_from_request($con);

$cutoffTypes = '';
$cutoffParams = [];
$whereCutoff = amiga_games_where_clause(
    $playerId,
    'all',
    0,
    0,
    'all',
    '',
    '',
    0,
    0,
    $cutoffTypes,
    $cutoffParams,
    $ctx
);
$cutoffGames = amiga_games_query_all(
    $con,
    'SELECT COUNT(*) AS c ' . amiga_rated_games_from_sql() . ' WHERE ' . $whereCutoff,
    $cutoffTypes,
    $cutoffParams
);
$cutoffTournaments = amiga_player_tournament_participation_all($con, $playerId);

$presentGameCount = (int) ($presentGames[0]['c'] ?? 0);
$cutoffGameCount = (int) ($cutoffGames[0]['c'] ?? 0);
$presentTournamentCount = count($presentTournaments);
$cutoffTournamentCount = count($cutoffTournaments);

echo "player={$playerId} as={$as} label=" . ($ctx->label() ?? '') . PHP_EOL;
echo "games present={$presentGameCount} cutoff={$cutoffGameCount}" . PHP_EOL;
echo "tournaments present={$presentTournamentCount} cutoff={$cutoffTournamentCount}" . PHP_EOL;

if ($cutoffGameCount > $presentGameCount) {
    fwrite(STDERR, "FAIL: cutoff games > present\n");
    exit(1);
}
if ($cutoffTournamentCount > $presentTournamentCount) {
    fwrite(STDERR, "FAIL: cutoff tournaments > present\n");
    exit(1);
}

echo "PASS\n";
