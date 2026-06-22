<?php
declare(strict_types=1);

$tournamentId = (int) ($argv[1] ?? 0);
if ($tournamentId <= 0) {
    fwrite(STDERR, "usage: php amiga_community_build_parity.php TOURNAMENT_ID\n");
    exit(2);
}

require __DIR__ . '/../../site/public_html/amiga/ops/includes/amiga_community_stats_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_error) {
    fwrite(STDERR, 'connect: ' . $con->connect_error . PHP_EOL);
    exit(1);
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

$cutoff = amiga_realm_load_cutoff($con, $tournamentId);
$headline = amiga_realm_compute_server_aggregates($con, $cutoff);
$facts = amiga_community_build_facts_at_cutoff($con, $tournamentId);

echo json_encode(
    ['tournament_id' => $tournamentId, 'headline' => $headline, 'facts' => $facts],
    JSON_THROW_ON_ERROR,
);
$con->close();
